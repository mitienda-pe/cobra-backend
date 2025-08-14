<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestTransferStep2 extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:transfer-step2';
    protected $description = 'Test transfer step 2 (getAccountInquiryById) specifically';

    public function run(array $params)
    {
        CLI::write('=== Testing Transfer Step 2 ===', 'yellow');
        CLI::write('');

        $ligoModel = new \App\Models\LigoModel();
        
        // First, let's test step 1 to get a valid accountInquiryId
        CLI::write('Step 1: Running account inquiry to get ID...', 'cyan');
        
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        $organizationModel = new \App\Models\OrganizationModel();
        
        $config = $superadminLigoConfigModel->where('enabled', 1)
                                            ->where('is_active', 1)
                                            ->first();
        
        if (!$config) {
            CLI::write('❌ No config found', 'red');
            return;
        }
        
        // Get any organization for testing
        $organization = $organizationModel->where('status', 'active')
                                          ->where('cci !=', '')
                                          ->first();
        
        if (!$organization) {
            CLI::write('❌ No organization with CCI found', 'red');
            return;
        }
        
        CLI::write('Using organization: ' . $organization['name'] . ' (CCI: ' . $organization['cci'] . ')', 'white');
        
        // Test account inquiry first
        $step1Response = $ligoModel->performAccountInquiry($config, $organization, $organization['cci'], 'PEN');
        
        if (isset($step1Response['error'])) {
            CLI::write('❌ Step 1 failed: ' . $step1Response['error'], 'red');
            return;
        }
        
        $accountInquiryId = $step1Response['accountInquiryId'] ?? null;
        if (!$accountInquiryId) {
            CLI::write('❌ No account inquiry ID received from step 1', 'red');
            return;
        }
        
        CLI::write('✅ Step 1 successful, Account Inquiry ID: ' . $accountInquiryId, 'green');
        CLI::write('');
        
        // Now test step 2
        CLI::write('Step 2: Testing getAccountInquiryById...', 'cyan');
        CLI::write('URL will be: /v1/getAccountInquiryById/' . $accountInquiryId, 'white');
        
        // Test different possible endpoints
        $endpointsToTest = [
            '/v1/getAccountInquiryById/' . $accountInquiryId,
            '/v1/accountInquiry/' . $accountInquiryId,
            '/v1/getAccountInquiry/' . $accountInquiryId,
            '/v1/accountInquiryResult/' . $accountInquiryId
        ];
        
        foreach ($endpointsToTest as $endpoint) {
            CLI::write('Testing endpoint: ' . $endpoint, 'white');
            
            $response = $ligoModel->makeApiRequest($endpoint, 'GET');
            
            CLI::write('Response: ' . json_encode($response), 'white');
            
            if (!isset($response['error']) && isset($response['status']) && $response['status'] == 1) {
                CLI::write('✅ SUCCESS with endpoint: ' . $endpoint, 'green');
                CLI::write('Response data: ' . json_encode($response['data'], JSON_PRETTY_PRINT), 'green');
                return;
            } else {
                CLI::write('❌ Failed with endpoint: ' . $endpoint, 'red');
                if (isset($response['error'])) {
                    CLI::write('Error: ' . $response['error'], 'red');
                }
            }
            CLI::write('');
        }
        
        CLI::write('All endpoints failed', 'red');
    }
}
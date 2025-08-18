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
        
        // Test account inquiry directly via makeApiRequest to avoid session dependency
        CLI::write('Testing account inquiry directly...', 'white');
        
        // Build the request data manually
        $accountInquiryData = [
            'debtorParticipantCode' => '0123',
            'creditorParticipantCode' => substr($organization['cci'], 0, 3),
            'debtorName' => $config['debtor_name'] ?? 'CobraPepe SuperAdmin',
            'debtorId' => $config['debtor_id'] ?? '20123456789',
            'debtorIdCode' => $config['debtor_id_code'] ?? '6',
            'debtorPhoneNumber' => '',
            'debtorAddressLine' => $config['debtor_address_line'] ?? 'Av. Javier Prado Este 123',
            'debtorMobileNumber' => $config['debtor_mobile_number'] ?? '999999999',
            'transactionType' => '320',
            'channel' => '15',
            'creditorAddressLine' => 'JR LIMA',
            'creditorCCI' => $organization['cci'],
            'debtorTypeOfPerson' => 'N',
            'currency' => '604'
        ];
        
        CLI::write('Account inquiry data: ' . json_encode($accountInquiryData, JSON_PRETTY_PRINT), 'white');
        
        $step1Response = $ligoModel->makeApiRequest('/v1/accountInquiry', 'POST', $accountInquiryData);
        
        if (isset($step1Response['error'])) {
            CLI::write('❌ Step 1 failed: ' . $step1Response['error'], 'red');
            return;
        }
        
        $accountInquiryId = $step1Response['data']['id'] ?? null;
        if (!$accountInquiryId) {
            CLI::write('❌ No account inquiry ID received from step 1', 'red');
            CLI::write('Step 1 response: ' . json_encode($step1Response), 'red');
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
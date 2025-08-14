<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestTransferStep2Direct extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:transfer-step2-direct';
    protected $description = 'Test transfer step 2 with direct HTTP calls';

    public function run(array $params)
    {
        CLI::write('=== Testing Transfer Step 2 Direct ===', 'yellow');
        CLI::write('');

        // Get config directly
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM superadmin_ligo_config WHERE enabled=1 AND is_active=1 LIMIT 1");
        $config = $query->getRowArray();
        
        if (!$config) {
            CLI::write('❌ No config found', 'red');
            return;
        }
        
        // Decrypt password manually
        $password = $config['password'];
        if (strpos($password, 'ENC:') === 0) {
            $password = base64_decode(substr($password, 4));
        }
        
        CLI::write('Using config ID: ' . $config['id'], 'white');
        
        // Step 1: Account Inquiry
        CLI::write('Step 1: Testing account inquiry...', 'cyan');
        
        $accountInquiryData = [
            'debtorParticipantCode' => '0921',
            'creditorParticipantCode' => '0049', // Use a known bank code
            'debtorName' => $config['debtor_name'],
            'debtorId' => $config['debtor_id'],
            'debtorIdCode' => $config['debtor_id_code'],
            'debtorPhoneNumber' => '',
            'debtorAddressLine' => $config['debtor_address_line'],
            'debtorMobileNumber' => $config['debtor_mobile_number'],
            'transactionType' => '320',
            'channel' => '15',
            'creditorAddressLine' => 'JR LIMA',
            'creditorCCI' => '04900100601812001010', // Use a test CCI
            'debtorTypeOfPerson' => 'N',
            'currency' => '604'
        ];
        
        $step1Result = $this->makeDirectApiCall($config, $password, '/v1/accountInquiry', 'POST', $accountInquiryData);
        
        if (isset($step1Result['error'])) {
            CLI::write('❌ Step 1 failed: ' . $step1Result['error'], 'red');
            return;
        }
        
        $accountInquiryId = $step1Result['data']['id'] ?? null;
        if (!$accountInquiryId) {
            CLI::write('❌ No account inquiry ID received', 'red');
            CLI::write('Step 1 response: ' . json_encode($step1Result), 'red');
            return;
        }
        
        CLI::write('✅ Step 1 successful, Account Inquiry ID: ' . $accountInquiryId, 'green');
        CLI::write('');
        
        // Step 2: Test different endpoints
        CLI::write('Step 2: Testing different endpoints...', 'cyan');
        
        $endpointsToTest = [
            '/v1/getAccountInquiryById/' . $accountInquiryId,
            '/v1/accountInquiry/' . $accountInquiryId,
            '/v1/getAccountInquiry/' . $accountInquiryId
        ];
        
        foreach ($endpointsToTest as $endpoint) {
            CLI::write('Testing: ' . $endpoint, 'white');
            
            sleep(3); // Wait for processing
            
            $response = $this->makeDirectApiCall($config, $password, $endpoint, 'GET');
            
            if (!isset($response['error']) && isset($response['status']) && $response['status'] == 1) {
                CLI::write('✅ SUCCESS with: ' . $endpoint, 'green');
                CLI::write('Response: ' . json_encode($response, JSON_PRETTY_PRINT), 'green');
                return;
            } else {
                CLI::write('❌ Failed: ' . json_encode($response), 'red');
            }
            CLI::write('');
        }
    }
    
    private function makeDirectApiCall($config, $password, $endpoint, $method, $data = null)
    {
        try {
            // Generate JWT token
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($config['private_key']);
            $payload = ['companyId' => $config['company_id']];
            $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600
            ]);
            
            // Authenticate
            $authUrl = 'https://cce-auth-prod.ligocloud.tech/v1/auth/sign-in?companyId=' . $config['company_id'];
            $authData = ['username' => $config['username'], 'password' => $password];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $authUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($authData),
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $authorizationToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30
            ]);
            
            $authResponse = curl_exec($curl);
            $authHttpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($authHttpCode >= 400) {
                return ['error' => 'Auth failed: ' . $authResponse];
            }
            
            $authResult = json_decode($authResponse, true);
            $accessToken = $authResult['data']['access_token'] ?? null;
            
            if (!$accessToken) {
                return ['error' => 'No access token received'];
            }
            
            // Make API call
            $apiUrl = 'https://cce-api-gateway-prod.ligocloud.tech' . $endpoint;
            
            $curl = curl_init();
            $options = [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 30
            ];
            
            if ($method === 'POST' && $data) {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($data);
            } elseif ($method === 'GET') {
                $options[CURLOPT_HTTPGET] = true;
            }
            
            curl_setopt_array($curl, $options);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);
            
            if ($httpCode >= 400) {
                return ['error' => 'API call failed (' . $httpCode . '): ' . $response];
            }
            
            return json_decode($response, true) ?: ['error' => 'Invalid JSON response'];
            
        } catch (\Exception $e) {
            return ['error' => 'Exception: ' . $e->getMessage()];
        }
    }
}
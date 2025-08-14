<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestLigoAuthStep extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:ligo-auth-step';
    protected $description = 'Test Ligo authentication step by step';

    public function run(array $params)
    {
        CLI::write('=== Testing Ligo Authentication Step by Step ===', 'yellow');
        CLI::write('');

        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        $config = $superadminLigoConfigModel->where('enabled', 1)
                                            ->where('is_active', 1)
                                            ->first();

        if (!$config) {
            CLI::write('❌ No config found', 'red');
            return;
        }

        // Extract credentials
        $username = $config['username'];
        $password = $config['password'];
        $companyId = $config['company_id'];
        $privateKey = $config['private_key'];
        $environment = $config['environment'];

        CLI::write('Step 1: Prepare auth data...', 'cyan');
        $authData = [
            'username' => $username,
            'password' => $password // Already decrypted by the model
        ];
        CLI::write('✅ Username: ' . $username, 'green');
        CLI::write('✅ Password length: ' . strlen($password), 'green');

        CLI::write('Step 2: Generate JWT token...', 'cyan');
        try {
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($privateKey);
            CLI::write('✅ Private key formatted', 'green');

            $payload = ['companyId' => $companyId];
            $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600
            ]);
            CLI::write('✅ JWT token generated (length: ' . strlen($authorizationToken) . ')', 'green');
        } catch (\Exception $e) {
            CLI::write('❌ JWT generation failed: ' . $e->getMessage(), 'red');
            return;
        }

        CLI::write('Step 3: Prepare HTTP request...', 'cyan');
        $urls = $superadminLigoConfigModel->getApiUrls($environment);
        $authUrl = $urls['auth_url'] . '/v1/auth/sign-in?companyId=' . $companyId;
        CLI::write('Auth URL: ' . $authUrl, 'white');

        $requestBody = json_encode($authData);
        CLI::write('Request body: ' . $requestBody, 'white');

        $headers = [
            'Authorization: Bearer ' . $authorizationToken,
            'Content-Type: application/json',
            'Content-Length: ' . strlen($requestBody),
            'Accept: application/json'
        ];
        CLI::write('Headers prepared (4 headers)', 'green');

        CLI::write('Step 4: Execute HTTP request...', 'cyan');
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $authUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            CLI::write('❌ cURL error: ' . $err, 'red');
            return;
        }

        CLI::write('Step 5: Analyze response...', 'cyan');
        CLI::write('HTTP Code: ' . $httpCode, 'white');
        CLI::write('Response: ' . $response, 'white');

        if ($httpCode >= 400) {
            CLI::write('❌ Authentication failed', 'red');
            
            // Try to decode and show specific error
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['errors'])) {
                CLI::write('Error details: ' . $decoded['errors'], 'red');
            }
        } else {
            CLI::write('✅ Authentication successful', 'green');
            $decoded = json_decode($response, true);
            if ($decoded && isset($decoded['data']['access_token'])) {
                CLI::write('✅ Token received', 'green');
            }
        }

        CLI::write('');
        CLI::write('=== End Test ===', 'yellow');
    }
}
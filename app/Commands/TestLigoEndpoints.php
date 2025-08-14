<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestLigoEndpoints extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:ligo-endpoints';
    protected $description = 'Test actual Ligo API endpoints to find correct ones';

    public function run(array $params)
    {
        CLI::write('=== Testing Ligo API Endpoints ===', 'yellow');
        CLI::write('');

        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Get active config
        $config = $superadminLigoConfigModel->where('is_active', 1)->first();
        if (!$config) {
            CLI::error('No active configuration found');
            return;
        }

        CLI::write("Using config ID {$config['id']} ({$config['environment']})", 'green');

        // Get authentication token first
        $token = $this->getAuthToken($config);
        if (!$token) {
            CLI::error('Failed to get authentication token');
            return;
        }

        CLI::write('✓ Authentication token obtained', 'green');
        CLI::write('');

        // Test different endpoint variations
        $baseUrl = "https://cce-api-gateway-{$config['environment']}.ligocloud.tech";
        
        $endpointsToTest = [
            // Account balance endpoints
            '/v1/accountBalance',
            '/v1/account/balance',
            '/accountBalance',
            '/balance',
            '/v1/getAccountBalance',
            
            // Transaction endpoints  
            '/v1/transactionsReport',
            '/v1/transactions/report',
            '/transactionsReport',
            '/transactions',
            '/v1/getTransactions',
            
            // General endpoints
            '/v1/status',
            '/status',
            '/health',
            '/v1/health',
            '/ping',
            '/v1/ping',
            '/',
            '/v1/',
            
            // QR endpoints (we know these might work)
            '/v1/createQr',
            '/v1/getCreateQRById/test',
        ];

        CLI::write('Testing endpoints with authentication:', 'cyan');
        foreach ($endpointsToTest as $endpoint) {
            $this->testEndpoint($baseUrl . $endpoint, $token);
        }
    }

    private function getAuthToken($config)
    {
        try {
            $urls = (new \App\Models\SuperadminLigoConfigModel())->getApiUrls($config['environment']);
            $authUrl = $urls['auth_url'] . '/v1/auth/sign-in?companyId=' . $config['company_id'];
            
            // Generate JWT token
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($config['private_key']);
            $payload = ['companyId' => $config['company_id']];
            
            $authToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600
            ]);
            
            $authData = [
                'username' => $config['username'],
                'password' => $config['password']
            ];
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $authUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($authData),
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $authToken,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            curl_close($curl);

            if ($httpCode == 200) {
                $decoded = json_decode($response, true);
                return $decoded['data']['access_token'] ?? $decoded['data']['token'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            CLI::error('Error getting auth token: ' . $e->getMessage());
            return null;
        }
    }

    private function testEndpoint($url, $token)
    {
        // Test both GET and POST methods
        $methods = ['GET', 'POST'];
        
        foreach ($methods as $method) {
            $curl = curl_init();
            
            $options = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Content-Type: application/json',
                    'Accept: application/json'
                ]
            ];
            
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode([]);
            }
            
            curl_setopt_array($curl, $options);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            $endpoint = str_replace('https://cce-api-gateway-prod.ligocloud.tech', '', $url);
            $endpoint = str_replace('https://cce-api-gateway-dev.ligocloud.tech', '', $url);
            
            if ($error) {
                continue; // Skip connection errors
            }
            
            if ($httpCode == 200) {
                CLI::write("  ✓ {$method} {$endpoint} - Success (200)", 'green');
                // Show a snippet of successful response
                $responseSnippet = substr($response, 0, 100);
                CLI::write("    Response: {$responseSnippet}...", 'light_gray');
            } elseif ($httpCode == 400 || $httpCode == 422) {
                CLI::write("  ⚠ {$method} {$endpoint} - Bad Request ({$httpCode}) - Endpoint exists but needs correct data", 'yellow');
            } elseif ($httpCode == 401) {
                CLI::write("  ⚠ {$method} {$endpoint} - Unauthorized (401) - Endpoint exists but auth issue", 'yellow');
            } elseif ($httpCode == 404) {
                // Don't show 404s to reduce noise
                continue;
            } else {
                CLI::write("  ⚠ {$method} {$endpoint} - HTTP {$httpCode}", 'yellow');
            }
        }
    }
}
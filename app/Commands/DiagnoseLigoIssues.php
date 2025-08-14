<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagnoseLigoIssues extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'diagnose:ligo-issues';
    protected $description = 'Comprehensive diagnosis of Ligo configuration and connectivity issues';

    public function run(array $params)
    {
        CLI::write('=== Ligo Issues Comprehensive Diagnosis ===', 'yellow');
        CLI::write('');

        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        $ligoModel = new \App\Models\LigoModel();

        // Step 1: Check configuration status
        CLI::write('1. Configuration Status', 'cyan');
        $this->checkConfigurationStatus($superadminLigoConfigModel);
        CLI::write('');

        // Step 2: Check URLs being used
        CLI::write('2. URL Configuration', 'cyan');
        $this->checkUrlConfiguration($superadminLigoConfigModel);
        CLI::write('');

        // Step 3: Test basic connectivity
        CLI::write('3. Basic Connectivity Test', 'cyan');
        $this->testConnectivity();
        CLI::write('');

        // Step 4: Test authentication flow (if config is complete)
        $activeConfig = $superadminLigoConfigModel->where('is_active', 1)->first();
        if ($activeConfig && $superadminLigoConfigModel->isConfigurationComplete($activeConfig)) {
            CLI::write('4. Authentication Flow Test', 'cyan');
            $this->testAuthenticationFlow($activeConfig);
        } else {
            CLI::write('4. Authentication Flow Test', 'cyan');
            CLI::write('  ⚠ Skipped - No complete active configuration found', 'yellow');
        }
        CLI::write('');

        // Step 5: Recommendations
        CLI::write('5. Recommendations', 'cyan');
        $this->provideRecommendations($activeConfig, $superadminLigoConfigModel);
    }

    private function checkConfigurationStatus($model)
    {
        $configs = $model->findAll();
        $activeConfig = null;

        CLI::write('  Available configurations:');
        foreach ($configs as $config) {
            $status = [];
            if ($config['enabled']) $status[] = 'enabled';
            if ($config['is_active']) {
                $status[] = 'active';
                $activeConfig = $config;
            }
            $complete = $model->isConfigurationComplete($config);
            if ($complete) $status[] = 'complete';

            $statusStr = empty($status) ? 'inactive' : implode(', ', $status);
            $color = ($config['is_active'] && $complete) ? 'green' : 'yellow';
            
            CLI::write("    ID {$config['id']} ({$config['environment']}): {$statusStr}", $color);
        }

        if ($activeConfig) {
            CLI::write("  ✓ Active config: ID {$activeConfig['id']} ({$activeConfig['environment']})", 'green');
            
            // Check missing fields
            $requiredFields = ['username', 'password', 'company_id', 'private_key'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($activeConfig[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                CLI::write('  ⚠ Missing fields: ' . implode(', ', $missingFields), 'red');
            } else {
                CLI::write('  ✓ All required fields present', 'green');
            }
        } else {
            CLI::write('  ✗ No active configuration found', 'red');
        }
    }

    private function checkUrlConfiguration($model)
    {
        $environments = ['dev', 'prod'];
        
        foreach ($environments as $env) {
            CLI::write("  {$env} environment:");
            $urls = $model->getApiUrls($env);
            CLI::write("    Auth URL: {$urls['auth_url']}");
            CLI::write("    API URL:  {$urls['api_url']}");
        }
        
        // Check current environment detection
        $currentEnv = env('CI_ENVIRONMENT', 'development') === 'production' ? 'prod' : 'dev';
        CLI::write("  Current environment detected as: {$currentEnv}", 'green');
    }

    private function testConnectivity()
    {
        $urlsToTest = [
            'https://cce-auth-prod.ligocloud.tech',
            'https://cce-api-gateway-prod.ligocloud.tech',
            'https://cce-auth-dev.ligocloud.tech',
            'https://cce-api-gateway-dev.ligocloud.tech'
        ];

        foreach ($urlsToTest as $url) {
            CLI::write("  Testing: {$url}");
            
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_NOBODY => true, // HEAD request
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                CLI::write("    ✗ Connection failed: {$error}", 'red');
            } elseif ($httpCode >= 200 && $httpCode < 400) {
                CLI::write("    ✓ Reachable (HTTP {$httpCode})", 'green');
            } elseif ($httpCode == 404) {
                CLI::write("    ⚠ Server reachable but endpoint not found (HTTP 404)", 'yellow');
            } else {
                CLI::write("    ⚠ Server reachable but returned HTTP {$httpCode}", 'yellow');
            }
        }
    }

    private function testAuthenticationFlow($config)
    {
        CLI::write("  Testing authentication with config ID {$config['id']} ({$config['environment']}):");
        
        // Get URLs for this environment
        $superadminModel = new \App\Models\SuperadminLigoConfigModel();
        $urls = $superadminModel->getApiUrls($config['environment']);
        
        CLI::write("    Using auth URL: {$urls['auth_url']}");
        
        try {
            // Test JWT generation first
            CLI::write("    Step 1: JWT Token Generation");
            $privateKey = $config['private_key'];
            $companyId = $config['company_id'];
            
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($privateKey);
            $payload = ['companyId' => $companyId];
            
            $authToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600
            ]);
            
            CLI::write("      ✓ JWT token generated successfully", 'green');
            
            // Test authentication request
            CLI::write("    Step 2: Authentication Request");
            $authUrl = $urls['auth_url'] . '/v1/auth/sign-in?companyId=' . $companyId;
            
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
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                CLI::write("      ✗ Connection failed: {$error}", 'red');
            } elseif ($httpCode == 200) {
                $decoded = json_decode($response, true);
                if (isset($decoded['data']['access_token']) || isset($decoded['data']['token'])) {
                    CLI::write("      ✓ Authentication successful!", 'green');
                } else {
                    CLI::write("      ⚠ HTTP 200 but no token in response", 'yellow');
                    CLI::write("        Response: " . substr($response, 0, 200) . "...");
                }
            } else {
                CLI::write("      ✗ Authentication failed (HTTP {$httpCode})", 'red');
                CLI::write("        Response: " . substr($response, 0, 200) . "...");
            }

        } catch (\Exception $e) {
            CLI::write("    ✗ Exception during authentication test: " . $e->getMessage(), 'red');
        }
    }

    private function provideRecommendations($activeConfig, $model)
    {
        if (!$activeConfig) {
            CLI::write('  → Execute: php spark activate:ligo-config prod', 'yellow');
            CLI::write('  → Configure credentials via web interface or CLI', 'yellow');
            return;
        }

        if (!$model->isConfigurationComplete($activeConfig)) {
            CLI::write('  → Complete missing credentials in configuration', 'yellow');
            CLI::write('  → URL: /superadmin/ligo-config', 'yellow');
            return;
        }

        CLI::write('  → Configuration appears complete', 'green');
        CLI::write('  → If issues persist, check with Ligo support about API access', 'yellow');
        CLI::write('  → Monitor logs for specific error details', 'yellow');
    }
}
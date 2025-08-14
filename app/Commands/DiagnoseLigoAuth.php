<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagnoseLigoAuth extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'diagnose:ligo-auth';
    protected $description = 'Diagnose Ligo authentication issues';

    public function run(array $params)
    {
        CLI::write('=== Ligo Authentication Diagnostic ===', 'yellow');
        CLI::write('');

        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Test the exact query used by the code
        CLI::write('1. Testing configuration query...', 'cyan');
        $config = $superadminLigoConfigModel->where('enabled', 1)
                                            ->where('is_active', 1)
                                            ->first();
        
        if (!$config) {
            CLI::write('❌ No active config found', 'red');
            return;
        }
        
        CLI::write('✅ Found config ID: ' . $config['id'] . ' (env: ' . $config['environment'] . ')', 'green');
        
        // Test password decryption
        CLI::write('2. Testing password decryption...', 'cyan');
        $rawPassword = $config['password'];
        CLI::write('Raw password: ' . substr($rawPassword, 0, 15) . '...', 'white');
        
        if (strpos($rawPassword, 'ENC:') === 0) {
            $decrypted = base64_decode(substr($rawPassword, 4));
            CLI::write('✅ Password is encrypted format', 'green');
            CLI::write('Decrypted length: ' . strlen($decrypted), 'white');
            CLI::write('Decrypted preview: ' . substr($decrypted, 0, 5) . '...', 'white');
        } else {
            CLI::write('❌ Password is not encrypted', 'red');
        }
        
        // Test basic credentials
        CLI::write('3. Testing credential completeness...', 'cyan');
        $requiredFields = ['username', 'password', 'company_id', 'private_key'];
        foreach ($requiredFields as $field) {
            $hasValue = !empty($config[$field]);
            $status = $hasValue ? '✅' : '❌';
            $length = $hasValue ? ' (length: ' . strlen($config[$field]) . ')' : '';
            CLI::write("{$status} {$field}: " . ($hasValue ? 'Present' : 'Missing') . $length, $hasValue ? 'green' : 'red');
        }
        
        // Test configuration completeness
        CLI::write('4. Testing configuration completeness...', 'cyan');
        $isComplete = $superadminLigoConfigModel->isConfigurationComplete($config);
        CLI::write('Configuration complete: ' . ($isComplete ? '✅ YES' : '❌ NO'), $isComplete ? 'green' : 'red');
        
        // Test URL generation
        CLI::write('5. Testing URL generation...', 'cyan');
        $urls = $superadminLigoConfigModel->getApiUrls($config['environment']);
        CLI::write('Auth URL: ' . $urls['auth_url'], 'white');
        CLI::write('API URL: ' . $urls['api_url'], 'white');
        
        CLI::write('');
        CLI::write('=== End Diagnostic ===', 'yellow');
    }
}
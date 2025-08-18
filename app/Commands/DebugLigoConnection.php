<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DebugLigoConnection extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'debug:ligo-connection';
    protected $description = 'Debug Ligo configuration loading and connection step by step';

    public function run(array $params)
    {
        CLI::write('=== Debugging Ligo Configuration and Connection ===', 'yellow');
        CLI::write('');

        // Test 1: Check CI_ENVIRONMENT
        CLI::write('1. Environment Check:', 'cyan');
        $ciEnv = env('CI_ENVIRONMENT', 'development');
        $mappedEnv = $ciEnv === 'production' ? 'prod' : 'dev';
        CLI::write("CI_ENVIRONMENT: {$ciEnv}", 'white');
        CLI::write("Mapped environment: {$mappedEnv}", 'white');
        CLI::write('');

        // Test 2: Direct database query with full config
        CLI::write('2. Direct Database Query (full config):', 'cyan');
        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM superadmin_ligo_config WHERE enabled=1 AND is_active=1 LIMIT 1");
        $directResult = $query->getRowArray();
        
        if ($directResult) {
            CLI::write('✅ Found config ID: ' . $directResult['id'], 'green');
            CLI::write('Environment: ' . $directResult['environment'], 'white');
            CLI::write('Username: ' . ($directResult['username'] ?: 'NULL'), 'white');
            CLI::write('Company ID: ' . ($directResult['company_id'] ?: 'NULL'), 'white');
            CLI::write('Has password: ' . (!empty($directResult['password']) ? 'YES' : 'NO'), 'white');
            CLI::write('Has private_key: ' . (!empty($directResult['private_key']) ? 'YES' : 'NO'), 'white');
            CLI::write('Participant code: ' . ($directResult['debtor_participant_code'] ?: 'NULL'), 'white');
        } else {
            CLI::write('❌ No config found', 'red');
            return;
        }
        CLI::write('');

        // Test 3: Test SuperadminLigoConfigModel methods
        CLI::write('3. SuperadminLigoConfigModel Methods:', 'cyan');
        $superadminModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Test where query
        $whereResult = $superadminModel->where('enabled', 1)->where('is_active', 1)->first();
        CLI::write('where() method result: ' . ($whereResult ? 'Found ID ' . $whereResult['id'] : 'NULL'), 'white');
        
        // Test getActiveConfig method
        $activeResult = $superadminModel->getActiveConfig($mappedEnv);
        CLI::write('getActiveConfig() result: ' . ($activeResult ? 'Found ID ' . $activeResult['id'] : 'NULL'), 'white');
        CLI::write('');

        // Test 4: LigoModel getSuperadminLigoConfig 
        CLI::write('4. LigoModel getSuperadminLigoConfig Method:', 'cyan');
        $ligoModel = new \App\Models\LigoModel();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($ligoModel);
        $method = $reflection->getMethod('getSuperadminLigoConfig');
        $method->setAccessible(true);
        
        try {
            $ligoResult = $method->invoke($ligoModel);
            if ($ligoResult) {
                CLI::write('✅ LigoModel config result: Found ID ' . $ligoResult['id'], 'green');
                CLI::write('Has password: ' . (!empty($ligoResult['password']) ? 'YES' : 'NO'), 'white');
                CLI::write('Has private_key: ' . (!empty($ligoResult['private_key']) ? 'YES' : 'NO'), 'white');
            } else {
                CLI::write('❌ LigoModel config result: NULL', 'red');
            }
        } catch (\Exception $e) {
            CLI::write('❌ Exception in LigoModel: ' . $e->getMessage(), 'red');
        }
        CLI::write('');

        // Test 5: If we have config, test if it's complete
        if ($directResult) {
            CLI::write('5. Configuration Completeness Check:', 'cyan');
            $isComplete = $superadminModel->isConfigurationComplete($directResult);
            CLI::write('Configuration is complete: ' . ($isComplete ? 'YES' : 'NO'), $isComplete ? 'green' : 'red');
            
            if (!$isComplete) {
                $required = ['username', 'password', 'company_id', 'private_key'];
                CLI::write('Missing required fields:', 'red');
                foreach ($required as $field) {
                    if (empty($directResult[$field])) {
                        CLI::write("  - {$field}", 'red');
                    }
                }
            }
        }

        CLI::write('');
        CLI::write('=== End Debug ===', 'yellow');
    }
}
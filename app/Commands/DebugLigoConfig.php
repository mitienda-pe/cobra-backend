<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DebugLigoConfig extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'debug:ligo-config';
    protected $description = 'Debug what Ligo config is actually being loaded';

    public function run(array $params)
    {
        CLI::write('=== Debugging Ligo Configuration Loading ===', 'yellow');
        CLI::write('');

        // Test 1: Direct database query
        CLI::write('1. Direct database query:', 'cyan');
        $db = \Config\Database::connect();
        $query = $db->query("SELECT id, environment, enabled, is_active, debtor_participant_code FROM superadmin_ligo_config WHERE enabled=1 AND is_active=1");
        $directResult = $query->getRowArray();
        
        if ($directResult) {
            CLI::write('✅ Direct DB result: ' . json_encode($directResult), 'green');
        } else {
            CLI::write('❌ No direct DB result found', 'red');
        }

        // Test 2: Using SuperadminLigoConfigModel
        CLI::write('2. Using SuperadminLigoConfigModel:', 'cyan');
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        $modelResult = $superadminLigoConfigModel->where('enabled', 1)
                                                 ->where('is_active', 1)
                                                 ->first();
        
        if ($modelResult) {
            CLI::write('✅ Model result: ' . json_encode([
                'id' => $modelResult['id'],
                'environment' => $modelResult['environment'],
                'debtor_participant_code' => $modelResult['debtor_participant_code'] ?? 'NOT_SET'
            ]), 'green');
        } else {
            CLI::write('❌ No model result found', 'red');
        }

        // Test 3: Using LigoModel getSuperadminLigoConfig method
        CLI::write('3. Using LigoModel getSuperadminLigoConfig:', 'cyan');
        $ligoModel = new \App\Models\LigoModel();
        
        // Use reflection to access protected method
        $reflection = new \ReflectionClass($ligoModel);
        $method = $reflection->getMethod('getSuperadminLigoConfig');
        $method->setAccessible(true);
        $ligoConfigResult = $method->invoke($ligoModel);
        
        if ($ligoConfigResult) {
            CLI::write('✅ LigoModel config result: ' . json_encode([
                'id' => $ligoConfigResult['id'],
                'environment' => $ligoConfigResult['environment'],
                'debtor_participant_code' => $ligoConfigResult['debtor_participant_code'] ?? 'NOT_SET'
            ]), 'green');
        } else {
            CLI::write('❌ No LigoModel config result found', 'red');
        }

        // Test 4: What would be used in performAccountInquiry
        CLI::write('4. What performAccountInquiry would use:', 'cyan');
        if ($ligoConfigResult) {
            $participantCode = $ligoConfigResult['debtor_participant_code'] ?? '0123';
            CLI::write('Participant code that would be used: ' . $participantCode, 'white');
            
            if ($participantCode === '0123') {
                CLI::write('❌ PROBLEM: Using fallback value 0123', 'red');
                CLI::write('This means debtor_participant_code is null/empty in the loaded config', 'red');
            } else {
                CLI::write('✅ Using correct value: ' . $participantCode, 'green');
            }
        }

        CLI::write('');
        CLI::write('=== End Debug ===', 'yellow');
    }
}
<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckLigoConfig extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'check:ligo-config';
    protected $description = 'Check superadmin Ligo configuration status and completeness';

    public function run(array $params)
    {
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        CLI::write('=== Superadmin Ligo Configuration Status ===', 'yellow');
        CLI::write('');

        // Get all configurations
        $configs = $superadminLigoConfigModel->findAll();
        
        if (empty($configs)) {
            CLI::error('No superadmin Ligo configurations found.');
            return;
        }

        foreach ($configs as $config) {
            CLI::write("Config ID: {$config['id']} ({$config['environment']})", 'cyan');
            CLI::write("  Enabled: " . ($config['enabled'] ? 'Yes' : 'No'));
            CLI::write("  Active: " . ($config['is_active'] ? 'Yes' : 'No'));
            CLI::write("  Username: " . ($config['username'] ?? 'NULL'));
            CLI::write("  Company ID: " . ($config['company_id'] ?? 'NULL'));
            CLI::write("  Account ID: " . ($config['account_id'] ?? 'NULL'));
            CLI::write("  Debtor Name: " . ($config['debtor_name'] ?? 'NULL'));
            CLI::write("  Debtor ID: " . ($config['debtor_id'] ?? 'NULL'));
            CLI::write("  Debtor ID Code: " . ($config['debtor_id_code'] ?? 'NULL'));
            CLI::write("  Debtor Participant Code: " . ($config['debtor_participant_code'] ?? 'NULL'));
            
            // Check completeness
            $isComplete = $superadminLigoConfigModel->isConfigurationComplete($config);
            CLI::write("  Configuration Complete: " . ($isComplete ? 'Yes' : 'No'), $isComplete ? 'green' : 'red');
            
            if (!$isComplete) {
                CLI::write("  Missing fields:");
                $requiredFields = [
                    'username', 'password', 'company_id', 'private_key',
                    'debtor_name', 'debtor_id', 'debtor_id_code', 'debtor_address_line', 'debtor_participant_code'
                ];
                foreach ($requiredFields as $field) {
                    if (empty($config[$field])) {
                        CLI::write("    - {$field}", 'red');
                    }
                }
            }
            
            CLI::write('');
        }

        // Check active config specifically
        CLI::write('=== Active Configuration Check ===', 'yellow');
        $activeConfig = $superadminLigoConfigModel->where('is_active', 1)->first();
        if ($activeConfig) {
            CLI::write("Active config: ID {$activeConfig['id']} ({$activeConfig['environment']})", 'green');
            $isActiveComplete = $superadminLigoConfigModel->isConfigurationComplete($activeConfig);
            CLI::write("Active config complete: " . ($isActiveComplete ? 'Yes' : 'No'), $isActiveComplete ? 'green' : 'red');
        } else {
            CLI::error('No active configuration found!');
        }
    }
}
<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ActivateDefaultLigoConfig extends BaseCommand
{
    protected $group = 'Config';
    protected $name = 'activate:ligo-config';
    protected $description = 'Activate a Ligo configuration for testing (requires environment parameter)';

    public function run(array $params)
    {
        $environment = $params[0] ?? null;
        
        if (!$environment || !in_array($environment, ['dev', 'prod'])) {
            CLI::error('Please specify environment: php spark activate:ligo-config dev|prod');
            return;
        }

        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Get config for specified environment
        $config = $superadminLigoConfigModel->where('environment', $environment)->first();
        
        if (!$config) {
            CLI::error("No configuration found for environment: {$environment}");
            return;
        }

        CLI::write("Found configuration ID {$config['id']} for {$environment} environment", 'yellow');
        
        // Show current status
        CLI::write("Current status:");
        CLI::write("  - Enabled: " . ($config['enabled'] ? 'Yes' : 'No'));
        CLI::write("  - Active: " . ($config['is_active'] ? 'Yes' : 'No'));
        CLI::write("  - Has credentials: " . (!empty($config['username']) && !empty($config['company_id']) ? 'Yes' : 'No'));
        
        if (!$config['enabled'] || !$config['is_active']) {
            // Activate configuration
            $result = $superadminLigoConfigModel->update($config['id'], [
                'enabled' => 1,
                'is_active' => 1
            ]);
            
            if ($result) {
                CLI::write("✓ Configuration ID {$config['id']} ({$environment}) activated successfully!", 'green');
            } else {
                CLI::error("Failed to activate configuration");
                return;
            }
        } else {
            CLI::write("Configuration is already enabled and active", 'green');
        }

        // Check if configuration is complete
        $isComplete = $superadminLigoConfigModel->isConfigurationComplete($config);
        if ($isComplete) {
            CLI::write("✓ Configuration is complete and ready to use", 'green');
        } else {
            CLI::write("⚠ Configuration is incomplete. Missing fields:", 'yellow');
            $requiredFields = [
                'username', 'password', 'company_id', 'private_key',
                'debtor_name', 'debtor_id', 'debtor_id_code', 'debtor_address_line', 'debtor_participant_code'
            ];
            foreach ($requiredFields as $field) {
                if (empty($config[$field])) {
                    CLI::write("    - {$field}", 'red');
                }
            }
            CLI::write('');
            CLI::write('You need to configure the missing fields through the web interface:', 'yellow');
            CLI::write('  URL: ' . site_url('superadmin/ligo-config'), 'cyan');
        }
    }
}
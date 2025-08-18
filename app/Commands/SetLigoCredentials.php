<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SetLigoCredentials extends BaseCommand
{
    protected $group = 'Config';
    protected $name = 'set:ligo-credentials';
    protected $description = 'Set Ligo credentials for a specific environment (interactive)';

    public function run(array $params)
    {
        $environment = $params[0] ?? null;
        
        if (!$environment || !in_array($environment, ['dev', 'prod'])) {
            CLI::error('Please specify environment: php spark set:ligo-credentials dev|prod');
            return;
        }

        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Get config for specified environment
        $config = $superadminLigoConfigModel->where('environment', $environment)->first();
        
        if (!$config) {
            CLI::error("No configuration found for environment: {$environment}");
            return;
        }

        CLI::write("Setting credentials for {$environment} environment (ID: {$config['id']})", 'yellow');
        CLI::write('Enter the required credentials (press Enter to keep current value):');
        CLI::write('');

        // Get credentials interactively
        $credentials = [];
        
        $currentUsername = $config['username'] ?? '';
        $username = CLI::prompt("Username [{$currentUsername}]:", $currentUsername);
        if ($username) $credentials['username'] = $username;
        
        $password = CLI::prompt("Password:", '', true); // Hidden input
        if ($password) $credentials['password'] = $password;
        
        $currentCompanyId = $config['company_id'] ?? '';
        $companyId = CLI::prompt("Company ID [{$currentCompanyId}]:", $currentCompanyId);
        if ($companyId) $credentials['company_id'] = $companyId;
        
        $currentAccountId = $config['account_id'] ?? '';
        $accountId = CLI::prompt("Account ID [{$currentAccountId}]:", $currentAccountId);
        if ($accountId) $credentials['account_id'] = $accountId;
        
        CLI::write('Private Key (paste the full RSA private key, end with empty line):');
        $privateKeyLines = [];
        while (true) {
            $line = CLI::prompt('');
            if (empty($line)) break;
            $privateKeyLines[] = $line;
        }
        if (!empty($privateKeyLines)) {
            $credentials['private_key'] = implode("\n", $privateKeyLines);
        }

        if (empty($credentials)) {
            CLI::write('No credentials entered. Nothing to update.', 'yellow');
            return;
        }

        // Ensure config is enabled and active
        $credentials['enabled'] = 1;
        $credentials['is_active'] = 1;

        // Update configuration
        $result = $superadminLigoConfigModel->update($config['id'], $credentials);
        
        if ($result) {
            CLI::write("✓ Credentials updated successfully for {$environment} environment!", 'green');
            
            // Verify completeness
            $updatedConfig = $superadminLigoConfigModel->find($config['id']);
            $isComplete = $superadminLigoConfigModel->isConfigurationComplete($updatedConfig);
            
            if ($isComplete) {
                CLI::write("✓ Configuration is now complete and ready to use!", 'green');
            } else {
                CLI::write("⚠ Some fields may still be missing. Check configuration status.", 'yellow');
            }
        } else {
            CLI::error("Failed to update credentials");
        }
    }
}
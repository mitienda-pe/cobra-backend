<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckLigoUrls extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'check:ligo-urls';
    protected $description = 'Check which Ligo API URLs are being used';

    public function run(array $params)
    {
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        CLI::write('=== Ligo API URLs Check ===', 'yellow');
        CLI::write('');

        // Check for both environments
        $environments = ['dev', 'prod'];
        
        foreach ($environments as $env) {
            CLI::write("Environment: {$env}", 'cyan');
            
            $urls = $superadminLigoConfigModel->getApiUrls($env);
            CLI::write("  Auth URL: " . $urls['auth_url']);
            CLI::write("  API URL: " . $urls['api_url']);
            
            // Check if there's a config for this environment
            $config = $superadminLigoConfigModel->getActiveConfig($env);
            if ($config) {
                CLI::write("  Config ID: " . $config['id']);
                CLI::write("  Custom auth_url: " . ($config['auth_url'] ?? 'None'));
                CLI::write("  Custom api_url: " . ($config['api_url'] ?? 'None'));
                CLI::write("  Active: " . ($config['is_active'] ? 'Yes' : 'No'));
                CLI::write("  Enabled: " . ($config['enabled'] ? 'Yes' : 'No'));
            } else {
                CLI::write("  No active config found", 'red');
            }
            CLI::write('');
        }
        
        CLI::write('=== Expected URLs (from Postman docs) ===', 'yellow');
        CLI::write('DEV:');
        CLI::write('  Auth: https://dev-auth.ligo.pe');
        CLI::write('  API: https://dev-api.ligo.pe');
        CLI::write('');
        CLI::write('PROD:');
        CLI::write('  Auth: https://auth.ligo.pe');
        CLI::write('  API: https://api.ligo.pe');
        CLI::write('');
        
        CLI::write('Current environment detection: ' . (env('CI_ENVIRONMENT', 'development') === 'production' ? 'prod' : 'dev'), 'green');
    }
}
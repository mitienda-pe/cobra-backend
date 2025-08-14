<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SwitchToDevCredentials extends BaseCommand
{
    protected $group = 'Config';
    protected $name = 'switch:to-dev-credentials';
    protected $description = 'Temporarily switch to DEV credentials that work';

    public function run(array $params)
    {
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        CLI::write('=== Switching to Working DEV Credentials ===', 'yellow');
        CLI::write('');

        // Deactivate current PROD config
        $prodConfig = $superadminLigoConfigModel->where('environment', 'prod')->where('is_active', 1)->first();
        if ($prodConfig) {
            $superadminLigoConfigModel->update($prodConfig['id'], ['is_active' => 0]);
            CLI::write("✓ Deactivated PROD config ID {$prodConfig['id']}", 'green');
        }

        // Activate DEV config
        $devConfig = $superadminLigoConfigModel->where('environment', 'dev')->first();
        if ($devConfig) {
            $superadminLigoConfigModel->update($devConfig['id'], ['is_active' => 1, 'enabled' => 1]);
            CLI::write("✓ Activated DEV config ID {$devConfig['id']}", 'green');
            
            CLI::write('');
            CLI::write('Configuration switched successfully:', 'green');
            CLI::write("  - Now using DEV environment credentials");
            CLI::write("  - Username: {$devConfig['username']}");
            CLI::write("  - Company ID: {$devConfig['company_id']}");
            CLI::write("  - URLs: cce-*-dev.ligocloud.tech");
            CLI::write('');
            CLI::write('This is a temporary fix. Contact Ligo to activate PROD credentials.', 'yellow');
        } else {
            CLI::error('DEV configuration not found');
        }
    }
}
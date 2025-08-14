<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckProductionConfig extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'debug:check-production-config';
    protected $description = 'Check all Ligo configurations to find the active one';

    public function run(array $params)
    {
        CLI::write('=== Checking All Ligo Configurations ===', 'yellow');
        CLI::write('');

        $db = \Config\Database::connect();
        $query = $db->query("SELECT id, environment, enabled, is_active, username, company_id, debtor_participant_code, CASE WHEN password IS NULL THEN 'NULL' WHEN password = '' THEN 'EMPTY' ELSE 'HAS_VALUE' END as password_status, CASE WHEN private_key IS NULL THEN 'NULL' WHEN private_key = '' THEN 'EMPTY' ELSE 'HAS_VALUE' END as private_key_status FROM superadmin_ligo_config ORDER BY id");
        $results = $query->getResultArray();
        
        if (!$results) {
            CLI::write('❌ No configurations found', 'red');
            return;
        }

        CLI::write('All configurations:', 'cyan');
        foreach ($results as $config) {
            $status = '';
            if ($config['enabled'] && $config['is_active']) {
                $status = ' [ACTIVE]';
            } elseif ($config['enabled']) {
                $status = ' [ENABLED]';
            } else {
                $status = ' [DISABLED]';
            }
            
            CLI::write('ID: ' . $config['id'] . ' | Env: ' . $config['environment'] . ' | Username: ' . ($config['username'] ?: 'NULL') . ' | Password: ' . $config['password_status'] . ' | Private Key: ' . $config['private_key_status'] . ' | Participant: ' . ($config['debtor_participant_code'] ?: 'NULL') . $status, 'white');
        }
        
        CLI::write('');
        CLI::write('Active configuration details:', 'cyan');
        $activeQuery = $db->query("SELECT * FROM superadmin_ligo_config WHERE enabled=1 AND is_active=1");
        $activeConfig = $activeQuery->getRowArray();
        
        if ($activeConfig) {
            CLI::write('Active Config ID: ' . $activeConfig['id'], 'green');
            CLI::write('Environment: ' . $activeConfig['environment'], 'white');
            CLI::write('Username: ' . ($activeConfig['username'] ?: 'NULL'), 'white');
            CLI::write('Company ID: ' . ($activeConfig['company_id'] ?: 'NULL'), 'white');
            CLI::write('Account ID: ' . ($activeConfig['account_id'] ?: 'NULL'), 'white');
            CLI::write('Has Password: ' . (!empty($activeConfig['password']) ? 'YES' : 'NO'), 'white');
            CLI::write('Has Private Key: ' . (!empty($activeConfig['private_key']) ? 'YES' : 'NO'), 'white');
            CLI::write('Debtor Participant Code: ' . ($activeConfig['debtor_participant_code'] ?: 'NULL'), 'white');
        } else {
            CLI::write('❌ No active configuration found', 'red');
        }

        CLI::write('');
        CLI::write('=== End Check ===', 'yellow');
    }
}
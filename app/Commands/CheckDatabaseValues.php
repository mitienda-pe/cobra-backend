<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class CheckDatabaseValues extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'debug:check-db-values';
    protected $description = 'Check exact database values for Ligo config';

    public function run(array $params)
    {
        CLI::write('=== Checking Database Values ===', 'yellow');
        CLI::write('');

        $db = \Config\Database::connect();
        $query = $db->query("SELECT * FROM superadmin_ligo_config WHERE id=1");
        $result = $query->getRowArray();
        
        if (!$result) {
            CLI::write('❌ No record found with ID=1', 'red');
            return;
        }

        CLI::write('Raw database values:', 'cyan');
        CLI::write('ID: ' . $result['id'], 'white');
        CLI::write('Username: ' . ($result['username'] ?: 'NULL'), 'white');
        CLI::write('Password: ' . ($result['password'] ? 'HAS_VALUE (' . strlen($result['password']) . ' chars)' : 'NULL'), 'white');
        CLI::write('Company ID: ' . ($result['company_id'] ?: 'NULL'), 'white');
        CLI::write('Account ID: ' . ($result['account_id'] ?: 'NULL'), 'white');
        CLI::write('Merchant Code: ' . ($result['merchant_code'] ?: 'NULL'), 'white');
        CLI::write('Private Key: ' . ($result['private_key'] ? 'HAS_VALUE (' . strlen($result['private_key']) . ' chars)' : 'NULL'), 'white');
        CLI::write('Debtor Name: ' . ($result['debtor_name'] ?: 'NULL'), 'white');
        CLI::write('Debtor ID: ' . ($result['debtor_id'] ?: 'NULL'), 'white');
        CLI::write('Debtor Participant Code: ' . ($result['debtor_participant_code'] ?: 'NULL'), 'white');
        CLI::write('');

        // Test model loading with callbacks
        CLI::write('Model loading (with callbacks):', 'cyan');
        $model = new \App\Models\SuperadminLigoConfigModel();
        $modelResult = $model->find(1);
        
        if ($modelResult) {
            CLI::write('Username: ' . ($modelResult['username'] ?: 'NULL'), 'white');
            CLI::write('Password: ' . ($modelResult['password'] ? 'HAS_VALUE (' . strlen($modelResult['password']) . ' chars)' : 'NULL'), 'white');
            CLI::write('Company ID: ' . ($modelResult['company_id'] ?: 'NULL'), 'white');
            CLI::write('Private Key: ' . ($modelResult['private_key'] ? 'HAS_VALUE (' . strlen($modelResult['private_key']) . ' chars)' : 'NULL'), 'white');
        } else {
            CLI::write('❌ Model find() returned NULL', 'red');
        }

        CLI::write('');
        CLI::write('=== End Check ===', 'yellow');
    }
}
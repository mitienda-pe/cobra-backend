<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class UpdateSuperadminDebtorData extends BaseCommand
{
    protected $group = 'Database';
    protected $name = 'update:superadmin-debtor';
    protected $description = 'Update superadmin Ligo config with correct debtor data from Postman documentation';

    public function run(array $params)
    {
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Get all configurations
        $configs = $superadminLigoConfigModel->findAll();
        
        if (empty($configs)) {
            CLI::error('No superadmin Ligo configurations found.');
            return;
        }

        // Correct debtor data from Postman documentation
        $debtorData = [
            'debtor_name' => 'DEMO LIGOPAY',
            'debtor_id' => '74369185', 
            'debtor_id_code' => '2',  // 2 = DNI (not 6 = RUC)
            'debtor_address_line' => 'AV 29 de Julio',
            'debtor_mobile_number' => '999999999',
            'debtor_participant_code' => '0123'  // From Postman environment
        ];

        $updated = 0;
        foreach ($configs as $config) {
            $result = $superadminLigoConfigModel->update($config['id'], $debtorData);
            if ($result) {
                $updated++;
                CLI::write('Updated config ID ' . $config['id'] . ' (' . $config['environment'] . ')');
            } else {
                CLI::error('Failed to update config ID ' . $config['id']);
            }
        }

        CLI::write('');
        if ($updated > 0) {
            CLI::write('Successfully updated ' . $updated . ' superadmin Ligo configurations with correct debtor data.', 'green');
            CLI::write('');
            CLI::write('Updated fields:');
            foreach ($debtorData as $field => $value) {
                CLI::write("  - {$field}: {$value}");
            }
        } else {
            CLI::error('No configurations were updated.');
        }
    }
}
<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ApplyLigoConfigMigration extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Database';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'ligo:migrate';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Apply Ligo configuration migration safely';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'ligo:migrate [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--force' => 'Force migration even if columns already exist'
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $force = CLI::getOption('force');
        
        CLI::write('Checking Ligo configuration migration...', 'green');
        
        $db = \Config\Database::connect();
        
        // Check if columns already exist
        $fields = $db->getFieldData('superadmin_ligo_config');
        $existingFields = array_column($fields, 'name');
        
        $newFields = [
            'debtor_phone_number',
            'debtor_type_of_person', 
            'creditor_address_line',
            'transaction_type',
            'channel'
        ];
        
        $missingFields = array_diff($newFields, $existingFields);
        $existingNewFields = array_intersect($newFields, $existingFields);
        
        if (empty($missingFields) && !$force) {
            CLI::write('All Ligo configuration fields already exist!', 'yellow');
            CLI::write('Existing fields: ' . implode(', ', $existingNewFields), 'cyan');
            CLI::write('Use --force to run migration anyway', 'white');
            return;
        }
        
        if (!empty($existingNewFields) && !$force) {
            CLI::write('Some fields already exist: ' . implode(', ', $existingNewFields), 'yellow');
            CLI::write('Missing fields: ' . implode(', ', $missingFields), 'red');
            CLI::write('Use --force to run migration anyway', 'white');
            return;
        }
        
        CLI::write('Running migration...', 'blue');
        
        try {
            // Run the specific migration
            $migrate = \Config\Services::migrations();
            $migrate->setNamespace('App');
            
            // This will run all pending migrations
            $migrate->latest();
            
            CLI::write('Migration completed successfully!', 'green');
            
            // Verify the fields were added
            $fields = $db->getFieldData('superadmin_ligo_config');
            $currentFields = array_column($fields, 'name');
            $stillMissing = array_diff($newFields, $currentFields);
            
            if (empty($stillMissing)) {
                CLI::write('All fields verified: ' . implode(', ', $newFields), 'green');
            } else {
                CLI::write('Some fields still missing: ' . implode(', ', $stillMissing), 'red');
            }
            
        } catch (\Exception $e) {
            CLI::write('Migration failed: ' . $e->getMessage(), 'red');
            CLI::write('You may need to run: php spark migrate', 'yellow');
        }
    }
}

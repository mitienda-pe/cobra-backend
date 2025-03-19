<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ClientModel;

class GenerateClientUuids extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'App';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'clients:generate-uuids';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates UUIDs for all clients that do not have one.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'clients:generate-uuids';

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
    protected $options = [];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        helper('uuid');
        $clientModel = new ClientModel();
        
        // Get all clients without UUIDs
        $clients = $clientModel->where('uuid IS NULL OR uuid = ""')->findAll();
        $totalClients = count($clients);
        
        if ($totalClients === 0) {
            CLI::write('All clients already have UUIDs.', 'green');
            return;
        }
        
        CLI::write("Found {$totalClients} clients without UUIDs.", 'yellow');
        $progress = CLI::showProgress(0, $totalClients);
        
        $count = 0;
        $db = \Config\Database::connect();
        
        foreach ($clients as $client) {
            // Generate a unique UUID
            $uuid = generate_unique_uuid('clients', 'uuid');
            
            // Update the client
            $db->table('clients')
               ->where('id', $client['id'])
               ->update(['uuid' => $uuid]);
            
            $count++;
            CLI::showProgress($count, $totalClients);
        }
        
        CLI::showProgress(false);
        CLI::write("Successfully generated UUIDs for {$count} clients.", 'green');
    }
}
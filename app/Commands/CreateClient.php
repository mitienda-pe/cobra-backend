<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ClientModel;

class CreateClient extends BaseCommand
{
    protected $group       = 'Development';
    protected $name        = 'create:client';
    protected $description = 'Creates a new client for testing';

    public function run(array $params)
    {
        $clientModel = new ClientModel();
        
        CLI::write('Creating a test client...', 'green');
        
        $data = [
            'organization_id' => 1, // From the seeder
            'business_name'   => 'Empresa de Prueba S.A.',
            'legal_name'      => 'Empresa de Prueba Sociedad Anónima',
            'document_number' => '12345678',
            'contact_name'    => 'Juan Pérez',
            'contact_phone'   => '123456789',
            'address'         => 'Calle Principal 123',
            'status'          => 'active',
        ];
        
        try {
            $clientId = $clientModel->insert($data);
            
            if ($clientId) {
                CLI::write("Client created successfully with ID: {$clientId}", 'green');
                
                // Add client to portfolio
                $db = \Config\Database::connect();
                $db->table('client_portfolio')->insert([
                    'client_id'    => $clientId,
                    'portfolio_id' => 1, // From the seeder
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
                
                CLI::write("Client assigned to portfolio.", 'green');
            } else {
                CLI::write("Failed to create client.", 'red');
                $errors = $clientModel->errors();
                foreach ($errors as $error) {
                    CLI::write("- {$error}", 'red');
                }
            }
        } catch (\Exception $e) {
            CLI::write("Error: {$e->getMessage()}", 'red');
        }
    }
}
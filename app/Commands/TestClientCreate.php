<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Models\ClientModel;

class TestClientCreate extends BaseCommand
{
    protected $group       = 'Testing';
    protected $name        = 'test:client-create';
    protected $description = 'Test client creation to debug form submission issues';

    public function run(array $params)
    {
        helper('form');
        
        CLI::write('Testing client creation...', 'green');
        
        $clientModel = new ClientModel();
        
        // Basic client data
        $data = [
            'organization_id' => 1, // Assuming first org
            'business_name'   => 'Test Client ' . date('YmdHis'),
            'legal_name'      => 'Test Legal Name ' . date('YmdHis'),
            'document_number' => 'DOC' . date('YmdHis'),
            'contact_name'    => 'Test Contact',
            'contact_phone'   => '123456789',
            'address'         => 'Test Address 123',
            'status'          => 'active'
        ];
        
        // Try to insert
        try {
            $clientId = $clientModel->insert($data);
            
            if ($clientId) {
                CLI::write('Client created successfully with ID: ' . $clientId, 'green');
                
                // Add to portfolio 1
                $db = \Config\Database::connect();
                $result = $db->table('client_portfolio')->insert([
                    'client_id'    => $clientId,
                    'portfolio_id' => 1, // First portfolio
                    'created_at'   => date('Y-m-d H:i:s'),
                    'updated_at'   => date('Y-m-d H:i:s'),
                ]);
                
                if ($result) {
                    CLI::write('Client assigned to portfolio 1 successfully', 'green');
                } else {
                    CLI::write('Failed to assign client to portfolio', 'red');
                }
                
                // Show the client data
                $client = $clientModel->find($clientId);
                CLI::write('Client data:', 'yellow');
                CLI::write(print_r($client, true), 'white');
            } else {
                CLI::error('Failed to create client. Errors: ' . json_encode($clientModel->errors()));
            }
        } catch (\Exception $e) {
            CLI::error('Exception: ' . $e->getMessage());
            CLI::error($e->getTraceAsString());
        }
    }
}
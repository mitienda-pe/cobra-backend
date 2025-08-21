<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestBalance extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:balance';
    protected $description = 'Test balance method directly';

    public function run(array $params)
    {
        CLI::write('Testing balance method...', 'yellow');
        
        try {
            // Simular sesión con organización  
            $session = session();
            $session->set('selected_organization_id', 1); // Usar ID que exista
            
            CLI::write('Session set with org ID: 1', 'cyan');
            
            // Test if we can create the controller
            CLI::write('Creating BackofficeController...', 'cyan');
            $controller = new \App\Controllers\BackofficeController();
            CLI::write('BackofficeController created successfully', 'green');
            
            // Test the balance method indirectly by calling LigoModel
            CLI::write('Testing LigoModel directly...', 'cyan');
            $ligoModel = new \App\Models\LigoModel();
            CLI::write('LigoModel created successfully', 'green');
            
            // Test getting organization balance
            CLI::write('Testing getAccountBalanceForOrganization...', 'cyan');
            $balanceResult = $ligoModel->getAccountBalanceForOrganization();
            
            CLI::write('Balance result: ' . json_encode($balanceResult), 'green');
            
        } catch (\Exception $e) {
            CLI::write('Error: ' . $e->getMessage(), 'red');
            CLI::write('File: ' . $e->getFile() . ':' . $e->getLine(), 'red');
            CLI::write('Trace: ' . $e->getTraceAsString(), 'red');
        }
    }
}
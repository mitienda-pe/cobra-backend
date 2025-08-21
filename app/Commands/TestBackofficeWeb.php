<?php
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class TestBackofficeWeb extends BaseCommand
{
    protected $group = 'Debug';
    protected $name = 'test:backoffice-web';
    protected $description = 'Test backoffice web flow without organization';

    public function run(array $params)
    {
        CLI::write('Testing Backoffice web flow...', 'yellow');
        
        try {
            // DO NOT set any organization in session - mimic web behavior
            $session = session();
            $session->remove('selected_organization_id');
            CLI::write('Session cleared (no organization)', 'cyan');
            
            // Test creating controller
            CLI::write('Creating BackofficeController...', 'cyan');
            $controller = new \App\Controllers\BackofficeController();
            CLI::write('✅ BackofficeController created', 'green');
            
            // Test calling balance method directly
            CLI::write('Calling balance() method...', 'cyan');
            
            // Simulate a GET request (not AJAX)
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_POST = [];
            
            $request = \Config\Services::request();
            
            // Use reflection to set the request in controller
            $reflection = new \ReflectionClass($controller);
            $requestProperty = $reflection->getProperty('request');
            $requestProperty->setAccessible(true);
            $requestProperty->setValue($controller, $request);
            
            // Call balance method
            $result = $controller->balance();
            
            CLI::write('✅ Balance method completed', 'green');
            CLI::write('Result type: ' . gettype($result), 'white');
            
            if (is_string($result)) {
                CLI::write('Result (first 200 chars): ' . substr($result, 0, 200), 'white');
            } else {
                CLI::write('Result: ' . print_r($result, true), 'white');
            }
            
        } catch (\Exception $e) {
            CLI::write('❌ Error: ' . $e->getMessage(), 'red');
            CLI::write('File: ' . $e->getFile() . ':' . $e->getLine(), 'red');
            CLI::write('Trace: ' . $e->getTraceAsString(), 'white');
        }
    }
}
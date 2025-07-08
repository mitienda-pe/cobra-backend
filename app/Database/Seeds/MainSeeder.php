<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run()
    {
        // Ejecutar seeders en el orden correcto
        $this->call('OrganizationSeeder');
        $this->call('UserSeeder');
        $this->call('SuperAdminSeeder');
        $this->call('ClientSeeder');
        $this->call('PortfolioSeeder');
        $this->call('InvoiceSeeder');
        $this->call('InstalmentSeeder');
        
        // Seeders adicionales
        $this->call('InitialSeeder');
        $this->call('SafeInitialSeeder');
        $this->call('SimpleSeeder');
        $this->call('InitialSetupSeeder');
        $this->call('UpdateInvoicesSeeder');
        $this->call('NewInvoiceSeeder');
    }
}
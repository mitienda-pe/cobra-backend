<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run()
    {
        // Limpiar datos existentes para evitar duplicados
        $this->db->table('payments')->truncate();
        $this->db->table('instalments')->truncate();
        $this->db->table('invoices')->truncate();
        $this->db->table('portfolio_clients')->truncate();
        $this->db->table('clients')->truncate();
        $this->db->table('portfolios')->truncate();
        $this->db->table('user_api_tokens')->truncate();
        $this->db->table('users')->truncate();
        $this->db->table('organizations')->truncate();
        
        // Ejecutar seeders en el orden correcto
        $this->call('OrganizationSeeder');
        $this->call('UserSeeder');
        $this->call('SuperAdminSeeder');
        $this->call('ClientSeeder');
        $this->call('PortfolioSeeder');
        $this->call('InvoiceSeeder');
        $this->call('InstalmentSeeder');
        
        // Seeders adicionales (solo los que no duplican datos)
        $this->call('InitialSetupSeeder');
    }
}
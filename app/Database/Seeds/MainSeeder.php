<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class MainSeeder extends Seeder
{
    public function run()
    {
        // Verificar si las tablas existen antes de limpiarlas
        $tables = ['payments', 'instalments', 'invoices', 'portfolio_clients', 'clients', 'portfolios', 'user_api_tokens', 'users', 'organizations'];
        
        foreach ($tables as $table) {
            try {
                $this->db->table($table)->truncate();
            } catch (\Exception $e) {
                // Si la tabla no existe, continuar
            }
        }
        
        // Ejecutar seeders en el orden correcto
        $this->call('OrganizationSeeder');
        $this->call('UserSeeder');
        $this->call('SuperAdminSeeder');
        $this->call('ClientSeeder');
        $this->call('PortfolioSeeder');
        $this->call('InvoiceSeeder');
        $this->call('InstalmentSeeder');
    }
}
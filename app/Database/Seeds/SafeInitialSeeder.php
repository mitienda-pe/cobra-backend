<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SafeInitialSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        // Check if we already have an organization
        $orgCount = $db->table('organizations')->countAllResults();

        if ($orgCount == 0) {
            // Create organization
            $orgId = $db->table('organizations')->insert([
                'name' => 'Sistema Administrador',
                'description' => 'Organización principal del sistema',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            // Create admin user
            $userId = $db->table('users')->insert([
                'organization_id' => $orgId,
                'name' => 'Administrador',
                'email' => 'admin@admin.com',
                'password' => password_hash('admin123', PASSWORD_BCRYPT),
                'role' => 'superadmin',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            // Create portfolios
            $commercialPortfolioId = $db->table('portfolios')->insert([
                'organization_id' => $orgId,
                'name' => 'Cartera Comercial',
                'description' => 'Clientes comerciales y empresas',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            $personalPortfolioId = $db->table('portfolios')->insert([
                'organization_id' => $orgId,
                'name' => 'Cartera Personal',
                'description' => 'Clientes particulares',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            // Check for existing portfolio_user relationships before inserting
            $existingCommercial = $db->table('portfolio_user')
                ->where('portfolio_id', $commercialPortfolioId)
                ->where('user_id', $userId)
                ->countAllResults();

            if ($existingCommercial == 0) {
                // Assign admin user to commercial portfolio
                $db->table('portfolio_user')->insert([
                    'portfolio_id' => $commercialPortfolioId,
                    'user_id' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $existingPersonal = $db->table('portfolio_user')
                ->where('portfolio_id', $personalPortfolioId)
                ->where('user_id', $userId)
                ->countAllResults();

            if ($existingPersonal == 0) {
                // Assign admin user to personal portfolio
                $db->table('portfolio_user')->insert([
                    'portfolio_id' => $personalPortfolioId,
                    'user_id' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            // Create sample client
            $clientId = $db->table('clients')->insert([
                'organization_id' => $orgId,
                'external_id' => bin2hex(random_bytes(16)),
                'business_name' => 'Empresa de Prueba S.A.',
                'legal_name' => 'Empresa de Prueba Sociedad Anónima',
                'document_number' => '12345678',
                'contact_name' => 'Juan Pérez',
                'contact_phone' => '123456789',
                'address' => 'Calle Principal 123',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ], true);

            // Check for existing client_portfolio relationships before inserting
            $existingClientPortfolio = $db->table('client_portfolio')
                ->where('client_id', $clientId)
                ->where('portfolio_id', $commercialPortfolioId)
                ->countAllResults();

            if ($existingClientPortfolio == 0) {
                // Assign client to commercial portfolio
                $db->table('client_portfolio')->insert([
                    'client_id' => $clientId,
                    'portfolio_id' => $commercialPortfolioId,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}

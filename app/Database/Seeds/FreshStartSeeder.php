<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class FreshStartSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();
        
        // Limpiar todas las tablas
        $db->query('DELETE FROM payments');
        $db->query('DELETE FROM instalments');
        $db->query('DELETE FROM invoices');
        $db->query('DELETE FROM portfolio_clients');
        $db->query('DELETE FROM client_portfolio');
        $db->query('DELETE FROM portfolio_user');
        $db->query('DELETE FROM clients');
        $db->query('DELETE FROM portfolios');
        $db->query('DELETE FROM user_api_tokens');
        $db->query('DELETE FROM users');
        $db->query('DELETE FROM organizations');
        
        // Crear organización
        $db->query("INSERT INTO organizations (id, name, description, status, created_at, updated_at) 
                   VALUES (1, 'Mi Tienda', 'Organización principal', 'active', datetime('now'), datetime('now'))");
        
        // Crear usuario Carlos
        $hashedPassword = password_hash('carlos123', PASSWORD_BCRYPT);
        $db->query("INSERT INTO users (id, organization_id, name, email, phone, password, role, status, created_at, updated_at) 
                   VALUES (1, 1, 'Carlos Vidal', 'carlos@mitienda.host', '999309748', '$hashedPassword', 'admin', 'active', datetime('now'), datetime('now'))");
        
        // Crear cartera
        $uuid = bin2hex(random_bytes(16));
        $db->query("INSERT INTO portfolios (id, organization_id, uuid, name, description, status, created_at, updated_at) 
                   VALUES (1, 1, '$uuid', 'Cartera de Carlos', 'Cartera personal de Carlos', 'active', datetime('now'), datetime('now'))");
        
        // Asignar cartera al usuario
        $db->query("INSERT INTO portfolio_user (portfolio_id, user_id, created_at, updated_at) 
                   VALUES (1, 1, datetime('now'), datetime('now'))");
        
        // Crear clientes
        $clients = [
            ['Bodega San Martín', 'Juan Martínez', '987654321'],
            ['Panadería La Esperanza', 'María López', '987123456'],
            ['Ferretería El Clavo', 'Pedro Gómez', '986543210'],
            ['Restaurante Sabor Criollo', 'Rosa Pérez', '985432109'],
            ['Farmacia Salud Total', 'Carlos Díaz', '984321098']
        ];
        
        $clientId = 1;
        foreach ($clients as $client) {
            $clientUuid = bin2hex(random_bytes(16));
            $docNumber = '2012345678' . $clientId;
            
            $db->query("INSERT INTO clients (id, organization_id, uuid, business_name, legal_name, document_number, contact_name, contact_phone, address, ubigeo, zip_code, latitude, longitude, status, created_at, updated_at) 
                       VALUES ($clientId, 1, '$clientUuid', '{$client[0]}', '{$client[0]}', '$docNumber', '{$client[1]}', '{$client[2]}', 'Av. Principal 123', '150101', 'LIMA01', -12.0464, -77.0428, 'active', datetime('now'), datetime('now'))");
            
            // Asignar cliente a cartera
            $db->query("INSERT INTO portfolio_clients (portfolio_id, client_id, created_at, updated_at) 
                       VALUES (1, $clientId, datetime('now'), datetime('now'))");
            
            $clientId++;
        }
        
        // Crear facturas con montos pequeños
        $concepts = ['Venta de productos', 'Servicio técnico', 'Consultoría', 'Reparación', 'Mantenimiento'];
        
        $invoiceId = 1;
        for ($i = 1; $i <= 5; $i++) {
            $amount = rand(15, 45) + (rand(0, 99) / 100);
            $invoiceNumber = 'F001-' . str_pad($invoiceId, 4, '0', STR_PAD_LEFT);
            $concept = $concepts[array_rand($concepts)];
            $dueDate = date('Y-m-d', strtotime('+' . rand(15, 30) . ' days'));
            
            $db->query("INSERT INTO invoices (id, organization_id, client_id, invoice_number, concept, amount, due_date, status, created_at, updated_at) 
                       VALUES ($invoiceId, 1, $i, '$invoiceNumber', '$concept', $amount, '$dueDate', 'pending', datetime('now'), datetime('now'))");
            
            // Crear 1-2 cuotas por factura
            $numInstalments = rand(1, 2);
            $instalmentAmount = round($amount / $numInstalments, 2);
            
            for ($j = 1; $j <= $numInstalments; $j++) {
                $instalmentUuid = bin2hex(random_bytes(16));
                $instalmentDueDate = date('Y-m-d', strtotime($dueDate . ' +' . ($j-1) . ' days'));
                
                if ($j === $numInstalments) {
                    $instalmentAmount = $amount - ($instalmentAmount * ($numInstalments - 1));
                }
                
                $db->query("INSERT INTO instalments (uuid, invoice_id, number, amount, due_date, status, created_at, updated_at) 
                           VALUES ('$instalmentUuid', $invoiceId, $j, $instalmentAmount, '$instalmentDueDate', 'pending', datetime('now'), datetime('now'))");
            }
            
            $invoiceId++;
        }
        
        echo "Base de datos poblada exitosamente!\n";
        echo "Usuario: carlos@mitienda.host\n";
        echo "Password: carlos123\n";
        echo "Teléfono: 999309748\n";
        echo "Facturas creadas: 5\n";
        echo "Clientes creados: 5\n";
    }
}
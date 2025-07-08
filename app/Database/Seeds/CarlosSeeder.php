<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class CarlosSeeder extends Seeder
{
    public function run()
    {
        helper('uuid');
        
        // Obtener la organización
        $organization = $this->db->table('organizations')->get()->getRow();
        if (!$organization) {
            throw new \Exception('No organizations found. Please run OrganizationSeeder first.');
        }

        // Crear usuario Carlos con teléfono específico
        $userData = [
            'organization_id' => $organization->id,
            'name' => 'Carlos Vidal',
            'email' => 'carlos@mitienda.host',
            'phone' => '999309748',
            'password' => password_hash('carlos123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Verificar si el usuario ya existe
        $existingUser = $this->db->table('users')->where('email', $userData['email'])->get()->getRow();
        
        if ($existingUser) {
            $userId = $existingUser->id;
            echo "Usuario Carlos ya existe con ID: {$userId}\n";
        } else {
            $this->db->table('users')->insert($userData);
            $userId = $this->db->insertID();
            echo "Usuario Carlos creado con ID: {$userId}\n";
        }

        // Crear cartera para Carlos
        $portfolioData = [
            'organization_id' => $organization->id,
            'name' => 'Cartera de Carlos',
            'description' => 'Cartera personal de clientes de Carlos Vidal',
            'status' => 'active',
            'uuid' => generate_uuid(),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table('portfolios')->insert($portfolioData);
        $portfolioId = $this->db->insertID();
        echo "Cartera creada con ID: {$portfolioId}\n";

        // Generar hash MD5 para la cartera
        $portfolio = $this->db->table('portfolios')->where('id', $portfolioId)->get()->getRow();
        $md5Hash = md5($portfolio->uuid);
        $this->db->table('portfolios')->where('id', $portfolioId)->update(['md5_hash' => $md5Hash]);

        // Asignar cartera al usuario
        $portfolioUserData = [
            'portfolio_id' => $portfolioId,
            'user_id' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->table('portfolio_user')->insert($portfolioUserData);
        echo "Cartera asignada al usuario Carlos\n";

        // Crear algunos clientes realistas con montos pequeños
        $clients = [
            [
                'business_name' => 'Bodega San Martín',
                'legal_name' => 'Bodega San Martín',
                'document_number' => '10123456789',
                'contact_name' => 'Juan Martínez',
                'contact_phone' => '987654321',
                'address' => 'Av. San Martín 123, Lima'
            ],
            [
                'business_name' => 'Panadería La Esperanza',
                'legal_name' => 'Panadería La Esperanza E.I.R.L.',
                'document_number' => '20234567890',
                'contact_name' => 'María López',
                'contact_phone' => '987123456',
                'address' => 'Jr. Esperanza 456, Lima'
            ],
            [
                'business_name' => 'Ferretería El Clavo',
                'legal_name' => 'Ferretería El Clavo S.A.C.',
                'document_number' => '20345678901',
                'contact_name' => 'Pedro Gómez',
                'contact_phone' => '986543210',
                'address' => 'Av. Industria 789, Lima'
            ],
            [
                'business_name' => 'Restaurante Sabor Criollo',
                'legal_name' => 'Restaurante Sabor Criollo S.R.L.',
                'document_number' => '20456789012',
                'contact_name' => 'Rosa Pérez',
                'contact_phone' => '985432109',
                'address' => 'Calle Sabor 321, Lima'
            ],
            [
                'business_name' => 'Farmacia Salud Total',
                'legal_name' => 'Farmacia Salud Total S.A.C.',
                'document_number' => '20567890123',
                'contact_name' => 'Carlos Díaz',
                'contact_phone' => '984321098',
                'address' => 'Av. Salud 654, Lima'
            ]
        ];

        $clientIds = [];
        foreach ($clients as $client) {
            $client['organization_id'] = $organization->id;
            $client['status'] = 'active';
            $client['uuid'] = generate_uuid();
            $client['ubigeo'] = '150101';
            $client['zip_code'] = 'LIMA01';
            $client['latitude'] = -12.0464 + (rand(-100, 100) / 10000);
            $client['longitude'] = -77.0428 + (rand(-100, 100) / 10000);
            $client['created_at'] = date('Y-m-d H:i:s');
            $client['updated_at'] = date('Y-m-d H:i:s');
            
            $this->db->table('clients')->insert($client);
            $clientId = $this->db->insertID();
            $clientIds[] = $clientId;
            
            // Asignar cliente a la cartera
            $portfolioClientData = [
                'portfolio_id' => $portfolioId,
                'client_id' => $clientId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            $this->db->table('portfolio_clients')->insert($portfolioClientData);
        }

        echo "Creados " . count($clients) . " clientes y asignados a la cartera\n";

        // Crear facturas con montos pequeños (menores a 50 soles)
        $currentDate = new \DateTime();
        $concepts = [
            'Venta de productos',
            'Servicio de mantenimiento',
            'Consultoría',
            'Reparación de equipo',
            'Servicio técnico'
        ];

        foreach ($clientIds as $clientId) {
            // Crear 1-2 facturas por cliente
            $numInvoices = rand(1, 2);
            
            for ($i = 0; $i < $numInvoices; $i++) {
                // Monto entre 10 y 45 soles
                $amount = rand(10, 45) + (rand(0, 99) / 100);
                
                // Fecha de vencimiento entre 15 y 30 días
                $daysToAdd = rand(15, 30);
                $dueDate = (clone $currentDate)->modify("+$daysToAdd days");
                
                // Fecha de emisión 5-10 días antes del vencimiento
                $issueDate = (clone $dueDate)->modify("-" . rand(5, 10) . " days");
                
                $concept = $concepts[array_rand($concepts)];
                
                $invoiceData = [
                    'organization_id' => $organization->id,
                    'client_id' => $clientId,
                    'external_id' => 'EXT-' . strtoupper(bin2hex(random_bytes(4))),
                    'invoice_number' => 'F001-' . str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT),
                    'concept' => $concept,
                    'amount' => $amount,
                    'due_date' => $dueDate->format('Y-m-d'),
                    'status' => 'pending',
                    'notes' => 'Factura de prueba para Carlos',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $this->db->table('invoices')->insert($invoiceData);
                $invoiceId = $this->db->insertID();
                
                // Crear 1-3 cuotas por factura
                $numInstalments = rand(1, 3);
                $instalmentAmount = round($amount / $numInstalments, 2);
                
                for ($j = 0; $j < $numInstalments; $j++) {
                    $instalmentDueDate = (clone $dueDate)->modify("+$j days");
                    
                    // Ajustar el último pago para que sume exactamente el total
                    if ($j === $numInstalments - 1) {
                        $instalmentAmount = $amount - ($instalmentAmount * ($numInstalments - 1));
                    }
                    
                    $instalmentData = [
                        'uuid' => generate_uuid(),
                        'invoice_id' => $invoiceId,
                        'number' => $j + 1,
                        'amount' => $instalmentAmount,
                        'due_date' => $instalmentDueDate->format('Y-m-d'),
                        'status' => 'pending',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $this->db->table('instalments')->insert($instalmentData);
                }
            }
        }

        echo "Facturas y cuotas creadas con montos menores a 50 soles\n";
        echo "Datos de acceso:\n";
        echo "Email: carlos@mitienda.host\n";
        echo "Password: carlos123\n";
        echo "Teléfono: 999309748\n";
    }
}
<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ClientSeeder extends Seeder
{
    public function run()
    {
        helper('uuid');
        
        // Definir los tres distritos con sus datos
        $districts = [
            [
                'name' => 'San Isidro',
                'ubigeo' => '150131',
                'zip_code' => 'LIMA27',
                'center_lat' => -12.0988,
                'center_lon' => -77.0338,
                'addresses' => [
                    'Av. Javier Prado Este',
                    'Av. República de Panamá',
                    'Calle Las Begonias'
                ]
            ],
            [
                'name' => 'Miraflores',
                'ubigeo' => '150133',
                'zip_code' => 'LIMA18',
                'center_lat' => -12.1219,
                'center_lon' => -77.0299,
                'addresses' => [
                    'Av. Benavides',
                    'Av. Larco',
                    'Av. 28 de Julio'
                ]
            ],
            [
                'name' => 'San Borja',
                'ubigeo' => '150140',
                'zip_code' => 'LIMA41',
                'center_lat' => -12.1067,
                'center_lon' => -76.9989,
                'addresses' => [
                    'Av. San Luis',
                    'Av. Aviación',
                    'Av. Angamos Este'
                ]
            ]
        ];

        $clients = [
            [
                'business_name' => 'Comercial Los Andes S.A.C.',
                'legal_name'    => 'Comercial Los Andes S.A.C.',
                'document_number' => '20123456789',
                'contact_name'  => 'Juan Pérez',
                'contact_phone' => '987654321'
            ],
            [
                'business_name' => 'Inversiones del Norte E.I.R.L.',
                'legal_name'    => 'Inversiones del Norte E.I.R.L.',
                'document_number' => '20987654321',
                'contact_name'  => 'María García',
                'contact_phone' => '987123456'
            ],
            [
                'business_name' => 'Distribuidora Sur S.R.L.',
                'legal_name'    => 'Distribuidora Sur S.R.L.',
                'document_number' => '20456789012',
                'contact_name'  => 'Carlos Rodríguez',
                'contact_phone' => '986543210'
            ],
            [
                'business_name' => 'Textiles del Este S.A.',
                'legal_name'    => 'Textiles del Este S.A.',
                'document_number' => '20345678901',
                'contact_name'  => 'Ana Torres',
                'contact_phone' => '985432109'
            ],
            [
                'business_name' => 'Importadora Central S.A.C.',
                'legal_name'    => 'Importadora Central S.A.C.',
                'document_number' => '20567890123',
                'contact_name'  => 'Pedro Díaz',
                'contact_phone' => '984321098'
            ],
            [
                'business_name' => 'Servicios Técnicos E.I.R.L.',
                'legal_name'    => 'Servicios Técnicos E.I.R.L.',
                'document_number' => '20678901234',
                'contact_name'  => 'Luis Vargas',
                'contact_phone' => '983210987'
            ],
            [
                'business_name' => 'Constructora del Pacífico S.A.',
                'legal_name'    => 'Constructora del Pacífico S.A.',
                'document_number' => '20789012345',
                'contact_name'  => 'Rosa Mendoza',
                'contact_phone' => '982109876'
            ],
            [
                'business_name' => 'Transportes Rápidos S.A.C.',
                'legal_name'    => 'Transportes Rápidos S.A.C.',
                'document_number' => '20890123456',
                'contact_name'  => 'Jorge Castro',
                'contact_phone' => '981098765'
            ]
        ];

        // Get the first organization's ID
        $organization = $this->db->table('organizations')->get()->getRow();
        if (!$organization) {
            throw new \Exception('No organizations found. Please run OrganizationSeeder first.');
        }

        // Limpiar la tabla antes de insertar nuevos registros
        $this->db->table('clients')->truncate();

        foreach ($clients as $client) {
            // Seleccionar un distrito aleatorio
            $district = $districts[array_rand($districts)];
            
            // Seleccionar una calle aleatoria
            $street = $district['addresses'][array_rand($district['addresses'])];
            
            // Generar un número aleatorio para la dirección
            $number = rand(100, 999);
            
            // Agregar una pequeña variación aleatoria a la latitud y longitud
            $latVariation = (rand(-100, 100) / 10000); // ±0.01 grados
            $lonVariation = (rand(-100, 100) / 10000);
            
            // Completar los datos del cliente con la información del distrito
            $client['address'] = $street . ' ' . $number . ', ' . $district['name'];
            $client['ubigeo'] = $district['ubigeo'];
            $client['zip_code'] = $district['zip_code'];
            $client['latitude'] = $district['center_lat'] + $latVariation;
            $client['longitude'] = $district['center_lon'] + $lonVariation;
            
            // Agregar campos comunes
            $client['organization_id'] = $organization->id;
            $client['status'] = 'active';
            $client['uuid'] = generate_uuid();
            $client['created_at'] = date('Y-m-d H:i:s');
            $client['updated_at'] = date('Y-m-d H:i:s');
            
            $this->db->table('clients')->insert($client);
        }
    }
}

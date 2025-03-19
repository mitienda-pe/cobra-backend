<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name' => 'Organización de Ejemplo',
                'description' => 'Esta es una organización de ejemplo para propósitos de demostración.',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('organizations')->insertBatch($data);
    }
}
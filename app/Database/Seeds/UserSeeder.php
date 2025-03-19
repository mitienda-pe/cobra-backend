<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'organization_id' => null,
                'name' => 'Super Admin',
                'email' => 'superadmin@example.com',
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'superadmin',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'organization_id' => 1, // Assuming organization with ID 1 exists
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'admin',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'organization_id' => 1, // Assuming organization with ID 1 exists
                'name' => 'Usuario',
                'email' => 'usuario@example.com',
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'user',
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->insertBatch($data);
    }
}
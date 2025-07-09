<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run()
    {
        // First create an organization
        $orgData = [
            'name'        => 'Sistema Administrador',
            'description' => 'Organización principal del sistema',
            'code'        => 'ADMIN',
            'status'      => 'active',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];
        
        $orgId = $this->db->table('organizations')->insert($orgData, true);
        
        // Then create the super admin user
        $password = password_hash('admin123', PASSWORD_BCRYPT);
        
        $userData = [
            'organization_id' => $orgId,
            'name'            => 'Administrador',
            'email'           => 'admin@admin.com',
            'password'        => $password,
            'role'            => 'superadmin',
            'status'          => 'active',
            'created_at'      => date('Y-m-d H:i:s'),
            'updated_at'      => date('Y-m-d H:i:s'),
        ];
        
        $this->db->table('users')->insert($userData);
        
        echo "Super Admin User Created! Email: admin@admin.com, Password: admin123\n";
    }
}
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToMainTables extends Migration
{
    public function up()
    {
        // Add uuid to users table
        $this->forge->addColumn('users', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'id'
            ],
        ]);
        
        // Add index on uuid
        $this->db->query('CREATE INDEX idx_users_uuid ON users(uuid);');
        
        // Add uuid to organizations table
        $this->forge->addColumn('organizations', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'id'
            ],
        ]);
        
        // Add index on uuid
        $this->db->query('CREATE INDEX idx_organizations_uuid ON organizations(uuid);');
        
        // Add uuid to invoices table
        $this->forge->addColumn('invoices', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'id'
            ],
        ]);
        
        // Add index on uuid
        $this->db->query('CREATE INDEX idx_invoices_uuid ON invoices(uuid);');
        
        // Add uuid to payments table
        $this->forge->addColumn('payments', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'id'
            ],
        ]);
        
        // Add index on uuid
        $this->db->query('CREATE INDEX idx_payments_uuid ON payments(uuid);');
    }

    public function down()
    {
        // Remove uuid from users table
        $this->forge->dropColumn('users', 'uuid');
        
        // Remove uuid from organizations table
        $this->forge->dropColumn('organizations', 'uuid');
        
        // Remove uuid from invoices table
        $this->forge->dropColumn('invoices', 'uuid');
        
        // Remove uuid from payments table
        $this->forge->dropColumn('payments', 'uuid');
    }
}
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToClientsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('clients', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'after'      => 'external_id'
            ],
        ]);
        
        // Create index on the UUID column for faster lookups
        $this->db->query('CREATE INDEX idx_clients_uuid ON clients(uuid)');
    }

    public function down()
    {
        $this->forge->dropColumn('clients', 'uuid');
    }
}
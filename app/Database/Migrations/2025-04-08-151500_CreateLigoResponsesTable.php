<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLigoResponsesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'organization_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'request_type' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'request_data' => [
                'type' => 'TEXT',
            ],
            'response_data' => [
                'type' => 'TEXT',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('organization_id');
        $this->forge->createTable('ligo_responses');
    }

    public function down()
    {
        $this->forge->dropTable('ligo_responses');
    }
}

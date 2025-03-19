<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClientsTable extends Migration
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
            'external_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'business_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'legal_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'document_number' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
            ],
            'contact_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'contact_phone' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ubigeo' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'zip_code' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'latitude' => [
                'type' => 'DECIMAL',
                'constraint' => '10,7',
                'null' => true,
            ],
            'longitude' => [
                'type' => 'DECIMAL',
                'constraint' => '10,7',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'active',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('external_id');
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('clients');
    }

    public function down()
    {
        $this->forge->dropTable('clients');
    }
}
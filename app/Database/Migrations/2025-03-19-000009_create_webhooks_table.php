<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWebhooksTable extends Migration
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
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'url' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'secret' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'events' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'default' => 'payment.created',
                // payment.created, payment.updated, invoice.created, invoice.updated, etc
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => true,
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
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('webhooks');
    }

    public function down()
    {
        $this->forge->dropTable('webhooks');
    }
}
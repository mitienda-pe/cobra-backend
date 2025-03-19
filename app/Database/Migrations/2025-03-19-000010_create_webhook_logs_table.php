<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWebhookLogsTable extends Migration
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
            'webhook_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'event' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'payload' => [
                'type' => 'TEXT',
            ],
            'response_code' => [
                'type' => 'INT',
                'constraint' => 5,
                'null' => true,
            ],
            'response_body' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'attempts' => [
                'type' => 'INT',
                'constraint' => 5,
                'default' => 0,
            ],
            'success' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('event');
        $this->forge->addKey('success');
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('webhook_id', 'webhooks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('webhook_logs');
    }

    public function down()
    {
        $this->forge->dropTable('webhook_logs');
    }
}
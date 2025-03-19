<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserApiTokensTable extends Migration
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
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'token' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
            ],
            'refresh_token' => [
                'type' => 'VARCHAR',
                'constraint' => 80,
                'null' => true,
            ],
            'scopes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked' => [
                'type' => 'BOOLEAN',
                'default' => false,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey('token', false, true); // Unique
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_api_tokens');
    }

    public function down()
    {
        $this->forge->dropTable('user_api_tokens');
    }
}
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixAllTables extends Migration
{
    public function up()
    {
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }

        // Drop and recreate users table with phone column
        $this->forge->dropTable('users', true);
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
                'null' => true,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
            ],
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ],
            'password' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
            ],
            'role' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'user',
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'active',
            ],
            'remember_token' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'reset_token' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'reset_token_expires_at' => [
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
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('users');

        // Add uuid column to clients if it doesn't exist
        try {
            $this->forge->addColumn('clients', [
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => true,
                    'after' => 'organization_id'
                ]
            ]);
        } catch (\Exception $e) {
            // Column might already exist
        }

        // Add uuid column to portfolios if it doesn't exist
        try {
            $this->forge->addColumn('portfolios', [
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => true,
                    'after' => 'organization_id'
                ]
            ]);
        } catch (\Exception $e) {
            // Column might already exist
        }
    }

    public function down()
    {
        // This migration fixes existing issues, no rollback needed
    }
}
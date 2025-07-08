<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CleanUsersTable extends Migration
{
    public function up()
    {
        // Eliminar tabla users si existe
        $this->forge->dropTable('users', true);
        
        // Crear tabla users limpia con todas las columnas necesarias
        $this->forge->addField([
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
            ],
            'organization_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true,
            ],
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
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
                'constraint' => 100,
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
        
        // Habilitar foreign keys para SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        // Agregar foreign key
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'SET NULL', 'CASCADE');
        
        // Crear la tabla
        $this->forge->createTable('users');
        
        // Crear índices
        $this->db->query('CREATE INDEX idx_users_uuid ON users(uuid)');
        $this->db->query('CREATE UNIQUE INDEX users_email_deleted_at_unique ON users(email) WHERE deleted_at IS NULL');
        $this->db->query('CREATE UNIQUE INDEX users_phone_deleted_at_unique ON users(phone) WHERE deleted_at IS NULL');
    }

    public function down()
    {
        $this->forge->dropTable('users');
    }
}
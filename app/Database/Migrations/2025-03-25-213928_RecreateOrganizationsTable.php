<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RecreateOrganizationsTable extends Migration
{
    public function up()
    {
        // Primero hacemos backup de los datos existentes
        $this->db->query('CREATE TABLE organizations_backup AS SELECT * FROM organizations');
        
        // Eliminamos la tabla original
        $this->forge->dropTable('organizations', true);
        
        // Creamos la tabla con la nueva estructura
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false,
            ],
            'name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => false,
            ],
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
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
        $this->forge->addUniqueKey('uuid');
        $this->forge->createTable('organizations');

        // Creamos el índice único para code
        $this->db->query('CREATE UNIQUE INDEX organizations_code_unique ON organizations(code) WHERE code IS NOT NULL');
        
        // Restauramos los datos del backup
        $this->db->query('INSERT INTO organizations (id, uuid, name, description, status, created_at, updated_at, deleted_at) SELECT id, uuid, name, description, status, created_at, updated_at, deleted_at FROM organizations_backup');
        
        // Eliminamos la tabla de backup
        $this->db->query('DROP TABLE organizations_backup');
    }

    public function down()
    {
        $this->forge->dropTable('organizations', true);
    }
}

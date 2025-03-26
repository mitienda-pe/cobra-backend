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
                'type' => 'INTEGER',
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

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('organizations');

        // Creamos el índice único para uuid
        $this->db->query('CREATE INDEX idx_organizations_uuid ON organizations(uuid)');
        
        // Creamos el índice único para code
        $this->db->query('CREATE UNIQUE INDEX idx_organizations_code ON organizations(code) WHERE code IS NOT NULL');
        
        // Restauramos los datos del backup, incluyendo uuid
        $this->db->query('INSERT INTO organizations (id, uuid, name, description, status, created_at, updated_at, deleted_at) SELECT id, uuid, name, description, status, created_at, updated_at, deleted_at FROM organizations_backup');
        
        // Eliminamos la tabla de backup
        $this->db->query('DROP TABLE organizations_backup');
    }

    public function down()
    {
        $this->forge->dropTable('organizations', true);
    }
}

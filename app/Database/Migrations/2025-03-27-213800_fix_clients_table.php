<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixClientsTable extends Migration
{
    public function up()
    {
        // Primero creamos una tabla temporal con la estructura correcta
        $this->forge->addField([
            'id' => [
                'type' => 'INTEGER',
                'auto_increment' => true,
            ],
            'uuid' => [
                'type' => 'VARCHAR',
                'null' => false,
            ],
            'organization_id' => [
                'type' => 'INT',
                'null' => false,
            ],
            'external_id' => [
                'type' => 'VARCHAR',
                'null' => true,
            ],
            'business_name' => [
                'type' => 'VARCHAR',
                'null' => false,
            ],
            'legal_name' => [
                'type' => 'VARCHAR',
                'null' => false,
            ],
            'document_number' => [
                'type' => 'VARCHAR',
                'null' => false,
            ],
            'contact_name' => [
                'type' => 'VARCHAR',
                'null' => true,
            ],
            'contact_phone' => [
                'type' => 'VARCHAR',
                'null' => true,
            ],
            'address' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ubigeo' => [
                'type' => 'VARCHAR',
                'null' => true,
            ],
            'zip_code' => [
                'type' => 'VARCHAR',
                'null' => true,
            ],
            'latitude' => [
                'type' => 'DECIMAL',
                'null' => true,
            ],
            'longitude' => [
                'type' => 'DECIMAL',
                'null' => true,
            ],
            'status' => [
                'type' => 'VARCHAR',
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
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey('document_number');
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        
        // Crear tabla temporal
        $this->forge->createTable('clients_new', true);

        // Copiar datos existentes
        $this->db->query('INSERT INTO clients_new (id, uuid, organization_id, external_id, business_name, legal_name, document_number, contact_name, contact_phone, address, ubigeo, zip_code, latitude, longitude, status, created_at, updated_at, deleted_at) SELECT id, COALESCE(uuid, hex(randomblob(16))), organization_id, external_id, business_name, legal_name, document_number, contact_name, contact_phone, address, ubigeo, zip_code, latitude, longitude, status, created_at, updated_at, deleted_at FROM clients');

        // Eliminar tabla vieja
        $this->forge->dropTable('clients');

        // Renombrar tabla nueva
        $this->db->query('ALTER TABLE clients_new RENAME TO clients');

        // Recrear índices
        $this->db->query('CREATE INDEX clients_external_id ON clients (external_id)');
    }

    public function down()
    {
        // No implementamos down ya que es una corrección de estructura
    }
}

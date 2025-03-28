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
                'constraint' => 36,
                'null' => false,
            ],
            'organization_id' => [
                'type' => 'INTEGER',
                'null' => false,
            ],
            'external_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'business_name' => [
                'type' => 'VARCHAR',
                'constraint' => 200,
                'null' => false,
            ],
            'legal_name' => [
                'type' => 'VARCHAR',
                'constraint' => 200,
                'null' => false,
            ],
            'document_number' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false,
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
                'constraint' => 6,
                'null' => true,
            ],
            'zip_code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
            ],
            'latitude' => [
                'type' => 'DECIMAL',
                'constraint' => '10,8',
                'null' => true,
            ],
            'longitude' => [
                'type' => 'DECIMAL',
                'constraint' => '11,8',
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
        $this->forge->addUniqueKey('uuid');
        $this->forge->addUniqueKey(['organization_id', 'document_number']);
        $this->forge->addForeignKey('organization_id', 'organizations', 'id', 'CASCADE', 'CASCADE');
        
        // Crear tabla temporal
        $this->forge->createTable('clients_new', true);

        // Copiar datos existentes y generar UUIDs
        $this->db->query('INSERT INTO clients_new (id, uuid, organization_id, external_id, business_name, legal_name, document_number, contact_name, contact_phone, address, ubigeo, zip_code, latitude, longitude, status, created_at, updated_at, deleted_at) 
                         SELECT id, hex(randomblob(16)), organization_id, external_id, business_name, legal_name, document_number, contact_name, contact_phone, address, ubigeo, zip_code, latitude, longitude, status, created_at, updated_at, deleted_at 
                         FROM clients');

        // Eliminar tabla vieja
        $this->forge->dropTable('clients');

        // Renombrar tabla nueva
        $this->db->query('ALTER TABLE clients_new RENAME TO clients');
    }

    public function down()
    {
        // No podemos revertir esta migración ya que se perderían los UUIDs generados
        // Lo mejor es mantener la estructura mejorada
    }
}

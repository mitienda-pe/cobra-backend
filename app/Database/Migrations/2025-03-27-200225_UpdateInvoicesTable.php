<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateInvoicesTable extends Migration
{
    public function up()
    {
        // En SQLite no podemos modificar columnas directamente, necesitamos recrear la tabla
        // 1. Renombrar la tabla actual
        $this->db->query('ALTER TABLE invoices RENAME TO invoices_old');
        
        // 2. Crear la nueva tabla con la estructura actualizada
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'organization_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'client_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'      => true,
            ],
            'external_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'      => true,
            ],
            'invoice_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'      => false,
            ],
            'concept' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => false,
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'      => false,
            ],
            'currency' => [
                'type'       => 'VARCHAR',
                'constraint' => 3,
                'null'      => false,
            ],
            'due_date' => [
                'type'       => 'DATE',
                'null'      => false,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'      => false,
            ],
            'notes' => [
                'type'       => 'TEXT',
                'null'      => true,
            ],
            'created_at' => [
                'type'       => 'DATETIME',
                'null'      => true,
            ],
            'updated_at' => [
                'type'       => 'DATETIME',
                'null'      => true,
            ],
            'deleted_at' => [
                'type'       => 'DATETIME',
                'null'      => true,
            ],
        ]);
        
        $this->forge->addKey('id', true);
        $this->forge->addKey(['organization_id', 'invoice_number']);
        $this->forge->createTable('invoices');
        
        // 3. Copiar los datos de la tabla antigua a la nueva
        $this->db->query('INSERT INTO invoices (
            id, organization_id, client_id, uuid, external_id, 
            invoice_number, concept, amount, currency, due_date, 
            status, notes, created_at, updated_at, deleted_at
        ) 
        SELECT 
            id, organization_id, client_id, uuid, external_id,
            COALESCE(series || number, "MIGRATED-" || id) as invoice_number,
            "Migrado desde factura anterior" as concept,
            COALESCE(total_amount, 0) as amount,
            currency,
            due_date,
            status,
            notes,
            created_at,
            updated_at,
            deleted_at
        FROM invoices_old');
        
        // 4. Eliminar la tabla antigua
        $this->db->query('DROP TABLE invoices_old');
    }

    public function down()
    {
        // En el down simplemente eliminamos la tabla ya que no podemos recuperar la estructura exacta anterior
        $this->forge->dropTable('invoices');
    }
}

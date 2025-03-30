<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInstalmentIdToPayments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payments', [
            'instalment_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'after'      => 'invoice_id'
            ]
        ]);

        // Agregar clave foránea solo si no estamos usando SQLite
        if ($this->db->DBDriver != 'SQLite3') {
            // Add foreign key constraint
            $this->db->query('ALTER TABLE payments ADD CONSTRAINT fk_payments_instalment FOREIGN KEY (instalment_id) REFERENCES instalments(id) ON DELETE SET NULL');
            
            // Add index for better performance
            $this->db->query('CREATE INDEX idx_instalment_id ON payments(instalment_id)');
        } else {
            // Para SQLite, solo creamos el índice
            $this->db->query('CREATE INDEX idx_instalment_id ON payments(instalment_id)');
            
            // Y un trigger para mantener la integridad referencial
            $this->db->query('PRAGMA foreign_keys = ON');
            $this->db->query('CREATE TRIGGER fk_payments_instalment
                BEFORE DELETE ON instalments
                FOR EACH ROW BEGIN
                    UPDATE payments SET instalment_id = NULL WHERE instalment_id = OLD.id;
                END');
        }
    }

    public function down()
    {
        // Eliminar la clave foránea y el índice solo si no estamos usando SQLite
        if ($this->db->DBDriver != 'SQLite3') {
            // Remove foreign key constraint first
            $this->db->query('ALTER TABLE payments DROP CONSTRAINT fk_payments_instalment');
            
            // Remove index
            $this->db->query('DROP INDEX idx_instalment_id ON payments');
        } else {
            // Para SQLite, eliminamos el trigger y el índice
            $this->db->query('DROP TRIGGER IF EXISTS fk_payments_instalment');
            $this->db->query('DROP INDEX IF EXISTS idx_instalment_id');
        }
        
        // Remove column
        $this->forge->dropColumn('payments', 'instalment_id');
    }
}

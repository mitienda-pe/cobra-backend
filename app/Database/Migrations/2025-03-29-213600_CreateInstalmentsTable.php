<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInstalmentsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => false,
            ],
            'invoice_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'number' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'comment'    => 'Número de cuota (1, 2, 3...)',
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => false,
                'comment'    => 'Monto de la cuota',
            ],
            'due_date' => [
                'type'    => 'DATE',
                'null'    => false,
                'comment' => 'Fecha de vencimiento',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'paid', 'cancelled'],
                'default'    => 'pending',
                'null'       => false,
            ],
            'notes' => [
                'type' => 'TEXT',
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
        $this->forge->addKey('invoice_id', false, false, 'idx_invoice_id');
        
        // SQLite no soporta nombres de claves foráneas, así que las omitimos
        if ($this->db->DBDriver != 'SQLite3') {
            $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE', 'fk_instalments_invoice');
        }
        
        $this->forge->createTable('instalments');
        
        // Para SQLite, agregamos la clave foránea sin nombre específico
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
            $this->db->query('CREATE TRIGGER fk_instalments_invoice
                BEFORE DELETE ON invoices
                FOR EACH ROW BEGIN
                    DELETE FROM instalments WHERE invoice_id = OLD.id;
                END');
        }
    }

    public function down()
    {
        $this->forge->dropTable('instalments');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePaymentsTable extends Migration
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
            'invoice_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => true,
                'null' => true, // Puede ser null si el pago viene del ERP
            ],
            'external_id' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
            ],
            'amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
            'payment_method' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                // cash, bank_transfer, check, credit_card, debit_card, app, other
            ],
            'reference_code' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'payment_date' => [
                'type' => 'DATETIME',
            ],
            'status' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'completed',
                // completed, pending, rejected, cancelled
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'latitude' => [
                'type' => 'DECIMAL',
                'constraint' => '10,7',
                'null' => true,
            ],
            'longitude' => [
                'type' => 'DECIMAL',
                'constraint' => '10,7',
                'null' => true,
            ],
            'is_notified' => [
                'type' => 'BOOLEAN',
                'default' => false,
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
        $this->forge->addKey('external_id');
        $this->forge->addKey('payment_date');
        $this->forge->addKey('status');
        $this->forge->addKey('is_notified');
        
        // Enable foreign key constraints for SQLite
        if ($this->db->DBDriver == 'SQLite3') {
            $this->db->query('PRAGMA foreign_keys = ON');
        }
        
        $this->forge->addForeignKey('invoice_id', 'invoices', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('payments');
    }

    public function down()
    {
        $this->forge->dropTable('payments');
    }
}
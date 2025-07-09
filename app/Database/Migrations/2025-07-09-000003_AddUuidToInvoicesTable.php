<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToInvoicesTable extends Migration
{
    public function up()
    {
        // Add uuid column to invoices table
        $this->forge->addColumn('invoices', [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true, // Allow null initially
                'after' => 'id'
            ]
        ]);
        
        // Generate UUIDs for existing invoices
        helper('uuid');
        $invoices = $this->db->table('invoices')->get()->getResult();
        
        foreach ($invoices as $invoice) {
            $uuid = generate_uuid();
            $this->db->table('invoices')
                ->where('id', $invoice->id)
                ->update(['uuid' => $uuid]);
        }
        
        // Make uuid column NOT NULL after populating existing records
        $this->forge->modifyColumn('invoices', [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => false
            ]
        ]);
        
        // Add unique index for uuid
        $this->forge->addKey('uuid', false, true);
        $this->forge->processIndexes('invoices');
    }

    public function down()
    {
        $this->forge->dropColumn('invoices', 'uuid');
    }
}
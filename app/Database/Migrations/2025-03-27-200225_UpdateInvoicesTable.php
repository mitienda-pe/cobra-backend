<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateInvoicesTable extends Migration
{
    public function up()
    {
        // Primero eliminamos las columnas antiguas que ya no usaremos
        $this->forge->dropColumn('invoices', ['series', 'number', 'issue_date', 'total_amount', 'paid_amount']);
        
        // Luego agregamos las nuevas columnas
        $this->forge->addColumn('invoices', [
            'invoice_number' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'      => false,
                'after'     => 'client_id'
            ],
            'concept' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => false,
                'after'     => 'invoice_number'
            ],
            'amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'      => false,
                'after'     => 'concept'
            ]
        ]);
    }

    public function down()
    {
        // Eliminamos las nuevas columnas
        $this->forge->dropColumn('invoices', ['invoice_number', 'concept', 'amount']);
        
        // Restauramos las columnas antiguas
        $this->forge->addColumn('invoices', [
            'series' => [
                'type'       => 'VARCHAR',
                'constraint' => 4,
                'null'      => false
            ],
            'number' => [
                'type'       => 'VARCHAR',
                'constraint' => 8,
                'null'      => false
            ],
            'issue_date' => [
                'type'       => 'DATE',
                'null'      => false
            ],
            'total_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'      => false
            ],
            'paid_amount' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'      => false,
                'default'   => 0.00
            ]
        ]);
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateInvoicesTable extends Migration
{
    public function up()
    {
        // Agregar nuevas columnas que faltan
        $fields = [
            'client_document_type' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'after' => 'client_id',
                'null' => true
            ],
            'client_document_number' => [
                'type' => 'VARCHAR',
                'constraint' => 15,
                'after' => 'client_document_type',
                'null' => true
            ],
            'client_name' => [
                'type' => 'VARCHAR',
                'constraint' => 200,
                'after' => 'client_document_number',
                'null' => true
            ],
            'client_address' => [
                'type' => 'VARCHAR',
                'constraint' => 200,
                'null' => true,
                'after' => 'client_name'
            ],
            'paid_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'after' => 'total_amount'
            ],
        ];

        $this->forge->addColumn('invoices', $fields);

        // Hacer concept nullable
        $this->forge->modifyColumn('invoices', [
            'concept' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ]
        ]);
    }

    public function down()
    {
        // Eliminar columnas agregadas
        $this->forge->dropColumn('invoices', [
            'client_document_type',
            'client_document_number',
            'client_name',
            'client_address',
            'paid_amount'
        ]);

        // Revertir concept a NOT NULL
        $this->forge->modifyColumn('invoices', [
            'concept' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => false,
            ]
        ]);
    }
}

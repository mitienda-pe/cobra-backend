<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateInvoicesTable extends Migration
{
    public function up()
    {
        // Renombrar columnas existentes
        $this->forge->modifyColumn('invoices', [
            'invoice_number' => [
                'name' => 'number',
                'type' => 'VARCHAR',
                'constraint' => 8,
            ],
            'amount' => [
                'name' => 'total_amount',
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
        ]);

        // Agregar nuevas columnas
        $fields = [
            'document_type' => [
                'type' => 'VARCHAR',
                'constraint' => 2,
                'default' => '01',
                'after' => 'external_id'
            ],
            'series' => [
                'type' => 'VARCHAR',
                'constraint' => 4,
                'after' => 'document_type'
            ],
            'issue_date' => [
                'type' => 'DATE',
                'after' => 'number'
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'default' => 'PEN',
                'after' => 'issue_date'
            ],
            'paid_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'after' => 'total_amount'
            ],
            'client_document_type' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'after' => 'client_id'
            ],
            'client_document_number' => [
                'type' => 'VARCHAR',
                'constraint' => 15,
                'after' => 'client_document_type'
            ],
            'client_name' => [
                'type' => 'VARCHAR',
                'constraint' => 200,
                'after' => 'client_document_number'
            ],
            'client_address' => [
                'type' => 'VARCHAR',
                'constraint' => 200,
                'null' => true,
                'after' => 'client_name'
            ],
        ];

        $this->forge->addColumn('invoices', $fields);

        // Eliminar columna concept que ya no se usará
        $this->forge->dropColumn('invoices', 'concept');
    }

    public function down()
    {
        // Revertir cambios de columnas
        $this->forge->modifyColumn('invoices', [
            'number' => [
                'name' => 'invoice_number',
                'type' => 'VARCHAR',
                'constraint' => 50,
            ],
            'total_amount' => [
                'name' => 'amount',
                'type' => 'DECIMAL',
                'constraint' => '10,2',
            ],
        ]);

        // Eliminar columnas agregadas
        $this->forge->dropColumn('invoices', [
            'document_type',
            'series',
            'issue_date',
            'currency',
            'paid_amount',
            'client_document_type',
            'client_document_number',
            'client_name',
            'client_address'
        ]);

        // Restaurar columna concept
        $this->forge->addColumn('invoices', [
            'concept' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'after' => 'invoice_number'
            ]
        ]);
    }
}

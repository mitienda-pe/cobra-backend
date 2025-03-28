<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateInvoicesTable extends Migration
{
    public function up()
    {
        // Agregar nuevas columnas que faltan
        $fields = [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
                'after' => 'id'
            ],
            'client_uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
                'after' => 'client_id'
            ],
            'client_document_type' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'after' => 'client_uuid',
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
            'document_type' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'null' => true,
                'after' => 'client_address'
            ],
            'series' => [
                'type' => 'VARCHAR',
                'constraint' => 4,
                'null' => true,
                'after' => 'document_type'
            ],
            'number' => [
                'type' => 'VARCHAR',
                'constraint' => 8,
                'null' => true,
                'after' => 'series'
            ],
            'total_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'null' => true,
                'after' => 'amount'
            ],
            'paid_amount' => [
                'type' => 'DECIMAL',
                'constraint' => '10,2',
                'default' => 0.00,
                'after' => 'total_amount'
            ],
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 3,
                'default' => 'PEN',
                'after' => 'paid_amount'
            ],
            'issue_date' => [
                'type' => 'DATE',
                'null' => true,
                'after' => 'currency'
            ]
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
            'uuid',
            'client_uuid',
            'client_document_type',
            'client_document_number',
            'client_name',
            'client_address',
            'document_type',
            'series',
            'number',
            'total_amount',
            'paid_amount',
            'currency',
            'issue_date'
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

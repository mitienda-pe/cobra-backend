<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIssueDateToInvoices extends Migration
{
    public function up()
    {
        try {
            // Verificar si la columna ya existe
            $query = $this->db->query("PRAGMA table_info(invoices)");
            $columns = $query->getResultArray();
            $columnNames = array_column($columns, 'name');
            
            if (!in_array('issue_date', $columnNames)) {
                $this->forge->addColumn('invoices', [
                    'issue_date' => [
                        'type'       => 'DATE',
                        'null'       => true,
                        'after'      => 'amount'
                    ]
                ]);
                echo "Columna 'issue_date' añadida a la tabla 'invoices'\n";
            } else {
                echo "La columna 'issue_date' ya existe en la tabla 'invoices'\n";
            }
        } catch (\Exception $e) {
            echo "Error al añadir la columna 'issue_date': " . $e->getMessage() . "\n";
        }
    }

    public function down()
    {
        $this->forge->dropColumn('invoices', 'issue_date');
    }
}

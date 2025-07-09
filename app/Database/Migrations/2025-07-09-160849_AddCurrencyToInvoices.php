<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCurrencyToInvoices extends Migration
{
    public function up()
    {
        // Add currency column to invoices table
        $this->forge->addColumn('invoices', [
            'currency' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => false,
                'default' => 'PEN',
                'after' => 'amount'
            ]
        ]);
    }

    public function down()
    {
        // Remove currency column from invoices table
        $this->forge->dropColumn('invoices', 'currency');
    }
}

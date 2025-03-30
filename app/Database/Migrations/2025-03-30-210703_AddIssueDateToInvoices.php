<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIssueDateToInvoices extends Migration
{
    public function up()
    {
        $this->forge->addColumn('invoices', [
            'issue_date' => [
                'type'       => 'DATE',
                'null'       => true,
                'after'      => 'amount'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('invoices', 'issue_date');
    }
}

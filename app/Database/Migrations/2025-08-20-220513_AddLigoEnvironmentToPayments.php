<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoEnvironmentToPayments extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payments', [
            'ligo_environment' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'comment' => 'Ligo credentials environment used for this payment (prod/dev)',
                'after' => 'payment_method'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('payments', 'ligo_environment');
    }
}

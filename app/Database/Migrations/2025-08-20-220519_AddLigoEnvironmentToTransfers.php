<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoEnvironmentToTransfers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('transfers', [
            'ligo_environment' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'comment' => 'Ligo credentials environment used for this transfer (prod/dev)',
                'after' => 'transfer_type'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('transfers', 'ligo_environment');
    }
}

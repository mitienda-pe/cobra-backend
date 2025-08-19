<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTransferTypeToTransfers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('transfers', [
            'transfer_type' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'regular',
                'null' => false,
                'after' => 'error_message'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('transfers', 'transfer_type');
    }
}

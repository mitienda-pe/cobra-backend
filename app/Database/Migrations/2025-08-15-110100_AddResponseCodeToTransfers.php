<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddResponseCodeToTransfers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('transfers', [
            'response_code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'after' => 'status'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('transfers', 'response_code');
    }
}
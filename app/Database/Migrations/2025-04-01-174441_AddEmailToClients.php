<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmailToClients extends Migration
{
    public function up()
    {
        $this->forge->addColumn('clients', [
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'contact_phone'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('clients', 'email');
    }
}

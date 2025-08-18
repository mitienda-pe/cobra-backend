<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCciToOrganizations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'cci' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'comment' => 'Cuenta Corriente Interbancaria de la organización para recibir transferencias'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', 'cci');
    }
}
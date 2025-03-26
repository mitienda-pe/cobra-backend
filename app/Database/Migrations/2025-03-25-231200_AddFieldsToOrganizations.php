<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFieldsToOrganizations extends Migration
{
    public function up()
    {
        $fields = [
            'ruc' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'name'
            ],
            'commercial_name' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'name'
            ]
        ];

        $this->forge->addColumn('organizations', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', ['ruc', 'commercial_name']);
    }
}

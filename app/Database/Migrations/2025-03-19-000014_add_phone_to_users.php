<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPhoneToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'email'
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'phone');
    }
}
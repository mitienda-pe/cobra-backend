<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoAuthTokenToOrganizations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'ligo_auth_token' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'ligo_enabled'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', 'ligo_auth_token');
    }
}

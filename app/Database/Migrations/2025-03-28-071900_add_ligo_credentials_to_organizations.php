<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoCredentialsToOrganizations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'ligo_api_key' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'ligo_api_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'ligo_webhook_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'ligo_enabled' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', ['ligo_api_key', 'ligo_api_secret', 'ligo_webhook_secret', 'ligo_enabled']);
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoTokenColumns extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'ligo_token' => [
                'type'       => 'TEXT',
                'null'       => true,
                'comment'    => 'Ligo API authentication token'
            ],
            'ligo_token_expiry' => [
                'type'       => 'DATETIME',
                'null'       => true,
                'comment'    => 'Expiration date/time of the Ligo token'
            ],
            'ligo_auth_error' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Last authentication error with Ligo API'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', ['ligo_token', 'ligo_token_expiry', 'ligo_auth_error']);
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoCredentialsToOrganizations extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'ligo_enabled' => [
                'type' => 'BOOLEAN',
                'null' => false,
                'default' => false,
            ],
            'ligo_username' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_password' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_company_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_private_key' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ligo_webhook_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', 'ligo_enabled');
        $this->forge->dropColumn('organizations', 'ligo_username');
        $this->forge->dropColumn('organizations', 'ligo_password');
        $this->forge->dropColumn('organizations', 'ligo_company_id');
        $this->forge->dropColumn('organizations', 'ligo_private_key');
        $this->forge->dropColumn('organizations', 'ligo_webhook_secret');
    }
}

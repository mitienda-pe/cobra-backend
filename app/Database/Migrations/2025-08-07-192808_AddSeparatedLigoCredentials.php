<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSeparatedLigoCredentials extends Migration
{
    public function up()
    {
        $this->forge->addColumn('organizations', [
            'ligo_dev_username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_private_key'
            ],
            'ligo_dev_password' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_dev_username'
            ],
            'ligo_dev_company_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_dev_password'
            ],
            'ligo_dev_account_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_dev_company_id'
            ],
            'ligo_dev_merchant_code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'after' => 'ligo_dev_account_id'
            ],
            'ligo_dev_private_key' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'ligo_dev_merchant_code'
            ],
            'ligo_dev_webhook_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_dev_private_key'
            ],
            'ligo_prod_username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_dev_webhook_secret'
            ],
            'ligo_prod_password' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_prod_username'
            ],
            'ligo_prod_company_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_prod_password'
            ],
            'ligo_prod_account_id' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_prod_company_id'
            ],
            'ligo_prod_merchant_code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'after' => 'ligo_prod_account_id'
            ],
            'ligo_prod_private_key' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'ligo_prod_merchant_code'
            ],
            'ligo_prod_webhook_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'after' => 'ligo_prod_private_key'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', [
            'ligo_dev_username',
            'ligo_dev_password',
            'ligo_dev_company_id',
            'ligo_dev_account_id',
            'ligo_dev_merchant_code',
            'ligo_dev_private_key',
            'ligo_dev_webhook_secret',
            'ligo_prod_username',
            'ligo_prod_password',
            'ligo_prod_company_id',
            'ligo_prod_account_id',
            'ligo_prod_merchant_code',
            'ligo_prod_private_key',
            'ligo_prod_webhook_secret'
        ]);
    }
}

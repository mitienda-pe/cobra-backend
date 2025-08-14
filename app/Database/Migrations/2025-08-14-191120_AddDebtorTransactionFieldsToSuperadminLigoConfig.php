<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDebtorTransactionFieldsToSuperadminLigoConfig extends Migration
{
    public function up()
    {
        // Add new fields to superadmin_ligo_config table
        $this->forge->addColumn('superadmin_ligo_config', [
            'debtor_phone_number' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'comment' => 'Phone number for debtor (different from mobile)'
            ],
            'debtor_type_of_person' => [
                'type' => 'VARCHAR',
                'constraint' => 5,
                'default' => 'N',
                'null' => false,
                'comment' => 'Type of person: N=Natural, J=Juridica'
            ],
            'creditor_address_line' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'default' => 'JR LIMA',
                'null' => false,
                'comment' => 'Default address line for creditors'
            ],
            'transaction_type' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'default' => '320',
                'null' => false,
                'comment' => 'Default transaction type for transfers'
            ],
            'channel' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'default' => '15',
                'null' => false,
                'comment' => 'Default channel for transfers'
            ]
        ]);
    }

    public function down()
    {
        // Remove the added columns
        $this->forge->dropColumn('superadmin_ligo_config', [
            'debtor_phone_number',
            'debtor_type_of_person', 
            'creditor_address_line',
            'transaction_type',
            'channel'
        ]);
    }
}

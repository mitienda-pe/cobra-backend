<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDebtorFieldsToSuperadminLigoConfig extends Migration
{
    public function up()
    {
        $this->forge->addColumn('superadmin_ligo_config', [
            'debtor_name' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Debtor name for transfers',
                'after' => 'account_id'
            ],
            'debtor_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'comment' => 'Debtor ID (RUC/DNI) for transfers',
                'after' => 'debtor_name'
            ],
            'debtor_id_code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'comment' => 'Debtor ID code type (2=DNI, 6=RUC) for transfers',
                'after' => 'debtor_id'
            ],
            'debtor_address_line' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Debtor address for transfers',
                'after' => 'debtor_id_code'
            ],
            'debtor_mobile_number' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'comment' => 'Debtor mobile number for transfers',
                'after' => 'debtor_address_line'
            ],
            'debtor_participant_code' => [
                'type' => 'VARCHAR',
                'constraint' => 10,
                'null' => true,
                'comment' => 'Debtor bank participant code for transfers',
                'after' => 'debtor_mobile_number'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('superadmin_ligo_config', [
            'debtor_name',
            'debtor_id', 
            'debtor_id_code',
            'debtor_address_line',
            'debtor_mobile_number',
            'debtor_participant_code'
        ]);
    }
}
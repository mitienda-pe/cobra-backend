<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOtpDeliveryFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('user_otp_codes', [
            'delivery_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'default'    => 'email',
                'after'      => 'expires_at'
            ],
            'delivery_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'default'    => 'pending',
                'after'      => 'delivery_method'
            ],
            'delivery_details' => [
                'type'       => 'TEXT',
                'null'       => true,
                'after'      => 'delivery_status'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('user_otp_codes', 'delivery_method');
        $this->forge->dropColumn('user_otp_codes', 'delivery_status');
        $this->forge->dropColumn('user_otp_codes', 'delivery_details');
    }
}

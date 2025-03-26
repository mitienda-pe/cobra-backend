<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserOtpCodes extends Migration
{
    public function up()
    {
        // Drop client_id column and add organization_code
        $this->forge->dropColumn('user_otp_codes', 'client_id');
        $this->forge->addColumn('user_otp_codes', [
            'organization_code' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'code'
            ]
        ]);

        // Rename delivery_details to delivery_info
        $this->forge->modifyColumn('user_otp_codes', [
            'delivery_details' => [
                'name' => 'delivery_info',
                'type' => 'TEXT',
                'null' => true,
            ]
        ]);

        // Drop unused columns
        $this->forge->dropColumn('user_otp_codes', 'used_at');
        $this->forge->dropColumn('user_otp_codes', 'delivery_method');
    }

    public function down()
    {
        // Add back the original columns
        $this->forge->addColumn('user_otp_codes', [
            'client_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'after' => 'id'
            ],
            'used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'delivery_method' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ]
        ]);

        // Rename delivery_info back to delivery_details
        $this->forge->modifyColumn('user_otp_codes', [
            'delivery_info' => [
                'name' => 'delivery_details',
                'type' => 'TEXT',
                'null' => true,
            ]
        ]);

        // Drop organization_code column
        $this->forge->dropColumn('user_otp_codes', 'organization_code');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserOtpCodesTable extends Migration
{
    public function up()
    {
        // Add missing columns to user_otp_codes table
        $this->forge->addColumn('user_otp_codes', [
            'phone' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'user_id'
            ],
            'email' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'phone'
            ],
            'delivery_info' => [
                'type' => 'TEXT',
                'null' => true,
                'after' => 'delivery_status'
            ]
        ]);
        
        // Rename delivery_details to delivery_info if it exists
        if ($this->db->fieldExists('delivery_details', 'user_otp_codes')) {
            $this->forge->modifyColumn('user_otp_codes', [
                'delivery_details' => [
                    'name' => 'delivery_info',
                    'type' => 'TEXT',
                    'null' => true
                ]
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('user_otp_codes', ['phone', 'email', 'delivery_info']);
    }
}
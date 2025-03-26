<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserOtpCodes extends Migration
{
    public function up()
    {
        // Drop existing table if it exists
        $this->forge->dropTable('user_otp_codes', true);

        // Create table with new structure
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
            ],
            'device_info' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'delivery_method' => [
                'type'       => 'ENUM',
                'constraint' => ['email', 'sms'],
                'null'       => true,
            ],
            'delivery_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'delivery_details' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('client_id');
        $this->forge->addKey('phone');
        $this->forge->addKey('email');
        $this->forge->addKey(['expires_at', 'used_at']);

        $this->forge->createTable('user_otp_codes');
    }

    public function down()
    {
        $this->forge->dropTable('user_otp_codes');
    }
}

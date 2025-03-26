<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveClientIdFromUserOtpCodes extends Migration
{
    public function up()
    {
        // SQLite doesn't support dropping columns directly, so we need to:
        // 1. Create new table without the column
        // 2. Copy data from old table
        // 3. Drop old table
        // 4. Rename new table to original name

        // Create new table
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'      => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 6,
            ],
            'organization_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'      => true,
            ],
            'device_info' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['phone', 'email']);
        $this->forge->createTable('user_otp_codes_new');

        // Copy data
        $this->db->query('INSERT INTO user_otp_codes_new (id, phone, email, code, organization_code, device_info, expires_at, created_at) 
                         SELECT id, phone, email, code, organization_code, device_info, expires_at, created_at 
                         FROM user_otp_codes');

        // Drop old table
        $this->forge->dropTable('user_otp_codes');

        // Rename new table
        $this->db->query('ALTER TABLE user_otp_codes_new RENAME TO user_otp_codes');
    }

    public function down()
    {
        // Create new table with client_id
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'client_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'      => false,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'      => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => 6,
            ],
            'organization_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'      => true,
            ],
            'device_info' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => true,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['phone', 'email']);
        $this->forge->createTable('user_otp_codes_new');

        // Copy data (note: this will fail if there are records without client_id)
        $this->db->query('INSERT INTO user_otp_codes_new (id, phone, email, code, organization_code, device_info, expires_at, created_at) 
                         SELECT id, phone, email, code, organization_code, device_info, expires_at, created_at 
                         FROM user_otp_codes');

        // Drop old table
        $this->forge->dropTable('user_otp_codes');

        // Rename new table
        $this->db->query('ALTER TABLE user_otp_codes_new RENAME TO user_otp_codes');
    }
}

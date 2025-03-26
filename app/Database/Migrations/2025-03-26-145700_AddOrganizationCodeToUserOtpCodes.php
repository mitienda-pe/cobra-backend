<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrganizationCodeToUserOtpCodes extends Migration
{
    public function up()
    {
        // Check if organization_code column exists
        $query = $this->db->query("PRAGMA table_info(user_otp_codes)");
        $columns = $query->getResultArray();
        $hasOrganizationCode = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'organization_code') {
                $hasOrganizationCode = true;
                break;
            }
        }

        // Add organization_code column if it doesn't exist
        if (!$hasOrganizationCode) {
            $this->forge->addColumn('user_otp_codes', [
                'organization_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'      => true,
                    'after'     => 'code'
                ]
            ]);
        }
    }

    public function down()
    {
        // Check if organization_code column exists
        $query = $this->db->query("PRAGMA table_info(user_otp_codes)");
        $columns = $query->getResultArray();
        $hasOrganizationCode = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'organization_code') {
                $hasOrganizationCode = true;
                break;
            }
        }

        if ($hasOrganizationCode) {
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
                'device_info' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'      => true,
                ],
                'delivery_method' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'      => true,
                ],
                'delivery_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'      => true,
                ],
                'delivery_info' => [
                    'type'       => 'TEXT',
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
            $this->db->query('INSERT INTO user_otp_codes_new (id, phone, email, code, device_info, delivery_method, delivery_status, delivery_info, expires_at, created_at) 
                             SELECT id, phone, email, code, device_info, delivery_method, delivery_status, delivery_info, expires_at, created_at 
                             FROM user_otp_codes');

            // Drop old table
            $this->forge->dropTable('user_otp_codes');

            // Rename new table
            $this->db->query('ALTER TABLE user_otp_codes_new RENAME TO user_otp_codes');
        }
    }
}

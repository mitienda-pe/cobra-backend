<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveryColumnsToUserOtpCodes extends Migration
{
    public function up()
    {
        // Check if columns exist
        $query = $this->db->query("PRAGMA table_info(user_otp_codes)");
        $columns = $query->getResultArray();
        $hasDeliveryStatus = false;
        $hasDeliveryInfo = false;
        $hasDeliveryMethod = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'delivery_status') {
                $hasDeliveryStatus = true;
            } elseif ($column['name'] === 'delivery_info') {
                $hasDeliveryInfo = true;
            } elseif ($column['name'] === 'delivery_method') {
                $hasDeliveryMethod = true;
            }
        }

        // Add missing columns
        if (!$hasDeliveryStatus) {
            $this->forge->addColumn('user_otp_codes', [
                'delivery_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'      => true,
                    'after'     => 'device_info'
                ]
            ]);
        }

        if (!$hasDeliveryInfo) {
            $this->forge->addColumn('user_otp_codes', [
                'delivery_info' => [
                    'type'       => 'TEXT',
                    'null'      => true,
                    'after'     => 'delivery_status'
                ]
            ]);
        }

        if (!$hasDeliveryMethod) {
            $this->forge->addColumn('user_otp_codes', [
                'delivery_method' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'      => true,
                    'after'     => 'device_info'
                ]
            ]);
        }
    }

    public function down()
    {
        // SQLite doesn't support dropping columns directly, so we need to:
        // 1. Create new table without the columns
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
}

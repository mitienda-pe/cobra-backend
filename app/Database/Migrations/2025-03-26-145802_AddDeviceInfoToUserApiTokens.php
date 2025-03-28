<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeviceInfoToUserApiTokens extends Migration
{
    public function up()
    {
        // Check if device_info column exists
        $query = $this->db->query("PRAGMA table_info(user_api_tokens)");
        $columns = $query->getResultArray();
        $hasDeviceInfo = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'device_info') {
                $hasDeviceInfo = true;
                break;
            }
        }

        // Add device_info column if it doesn't exist
        if (!$hasDeviceInfo) {
            $this->forge->addColumn('user_api_tokens', [
                'device_info' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'      => true,
                    'after'     => 'token'
                ]
            ]);
        }
    }

    public function down()
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
            'user_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'      => false,
            ],
            'token' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'      => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('token');
        $this->forge->createTable('user_api_tokens_new');

        // Copy data
        $this->db->query('INSERT INTO user_api_tokens_new (id, user_id, token, created_at, expires_at) 
                         SELECT id, user_id, token, created_at, expires_at 
                         FROM user_api_tokens');

        // Drop old table
        $this->forge->dropTable('user_api_tokens');

        // Rename new table
        $this->db->query('ALTER TABLE user_api_tokens_new RENAME TO user_api_tokens');
    }
}

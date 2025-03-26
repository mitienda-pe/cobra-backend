<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserApiTokens extends Migration
{
    public function up()
    {
        // Check if table exists
        $query = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_api_tokens'");
        $exists = !empty($query->getResultArray());

        if ($exists) {
            // Check if client_id column exists
            $query = $this->db->query("PRAGMA table_info(user_api_tokens)");
            $columns = $query->getResultArray();
            $hasClientId = false;
            $hasUserId = false;

            foreach ($columns as $column) {
                if ($column['name'] === 'client_id') {
                    $hasClientId = true;
                } elseif ($column['name'] === 'user_id') {
                    $hasUserId = true;
                }
            }

            // Rename client_id to user_id
            if ($hasClientId && !$hasUserId) {
                // SQLite doesn't support renaming columns directly, so we need to:
                // 1. Create new table with desired schema
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
                    ],
                    'token' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'device_info' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'      => true,
                    ],
                    'expires_at' => [
                        'type' => 'DATETIME',
                    ],
                    'revoked' => [
                        'type'    => 'BOOLEAN',
                        'default' => false,
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                    ],
                ]);

                $this->forge->addKey('id', true);
                $this->forge->addKey('token');
                $this->forge->addKey('user_id');
                $this->forge->createTable('user_api_tokens_new');

                // Copy data
                $this->db->query('INSERT INTO user_api_tokens_new (id, user_id, token, device_info, expires_at, revoked, created_at) 
                                 SELECT id, client_id, token, device_info, expires_at, revoked, created_at 
                                 FROM user_api_tokens');

                // Drop old table
                $this->forge->dropTable('user_api_tokens');

                // Rename new table
                $this->db->query('ALTER TABLE user_api_tokens_new RENAME TO user_api_tokens');
            }
        }
    }

    public function down()
    {
        // Check if table exists
        $query = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_api_tokens'");
        $exists = !empty($query->getResultArray());

        if ($exists) {
            // Check if user_id column exists
            $query = $this->db->query("PRAGMA table_info(user_api_tokens)");
            $columns = $query->getResultArray();
            $hasUserId = false;
            $hasClientId = false;

            foreach ($columns as $column) {
                if ($column['name'] === 'user_id') {
                    $hasUserId = true;
                } elseif ($column['name'] === 'client_id') {
                    $hasClientId = true;
                }
            }

            // Rename user_id back to client_id
            if ($hasUserId && !$hasClientId) {
                // Create new table
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
                    ],
                    'token' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                    ],
                    'device_info' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'      => true,
                    ],
                    'expires_at' => [
                        'type' => 'DATETIME',
                    ],
                    'revoked' => [
                        'type'    => 'BOOLEAN',
                        'default' => false,
                    ],
                    'created_at' => [
                        'type' => 'DATETIME',
                    ],
                ]);

                $this->forge->addKey('id', true);
                $this->forge->addKey('token');
                $this->forge->addKey('client_id');
                $this->forge->createTable('user_api_tokens_new');

                // Copy data
                $this->db->query('INSERT INTO user_api_tokens_new (id, client_id, token, device_info, expires_at, revoked, created_at) 
                                 SELECT id, user_id, token, device_info, expires_at, revoked, created_at 
                                 FROM user_api_tokens');

                // Drop old table
                $this->forge->dropTable('user_api_tokens');

                // Rename new table
                $this->db->query('ALTER TABLE user_api_tokens_new RENAME TO user_api_tokens');
            }
        }
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserApiTokens extends Migration
{
    public function up()
    {
        // Check if table exists
        $query = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_api_tokens'");
        $exists = !empty($query->getResultArray());

        if (!$exists) {
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
            $this->forge->createTable('user_api_tokens');
        }
    }

    public function down()
    {
        $this->forge->dropTable('user_api_tokens', true);
    }
}

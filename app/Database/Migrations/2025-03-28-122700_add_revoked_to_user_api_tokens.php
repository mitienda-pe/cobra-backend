<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRevokedToUserApiTokens extends Migration
{
    public function up()
    {
        // For SQLite, we need to check if the column exists using PRAGMA
        if ($this->db->DBDriver == 'SQLite3') {
            // Get the table info
            $tableInfo = $this->db->query("PRAGMA table_info(user_api_tokens)")->getResultArray();
            
            // Check if the revoked column exists
            $hasColumn = false;
            foreach ($tableInfo as $column) {
                if ($column['name'] === 'revoked') {
                    $hasColumn = true;
                    break;
                }
            }
            
            // Only add the column if it doesn't exist
            if (!$hasColumn) {
                // For SQLite, we can't use 'after' in addColumn
                $this->forge->addColumn('user_api_tokens', [
                    'revoked' => [
                        'type' => 'BOOLEAN',
                        'default' => false
                    ]
                ]);
            }
        } else {
            // For other databases like MySQL
            try {
                $this->forge->addColumn('user_api_tokens', [
                    'revoked' => [
                        'type' => 'BOOLEAN',
                        'default' => false,
                        'after' => 'updated_at'
                    ]
                ]);
            } catch (\Exception $e) {
                // Column might already exist, ignore the error
                log_message('info', 'Column revoked might already exist: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // For SQLite, we need to check if the column exists using PRAGMA
        if ($this->db->DBDriver == 'SQLite3') {
            // Get the table info
            $tableInfo = $this->db->query("PRAGMA table_info(user_api_tokens)")->getResultArray();
            
            // Check if the revoked column exists
            $hasColumn = false;
            foreach ($tableInfo as $column) {
                if ($column['name'] === 'revoked') {
                    $hasColumn = true;
                    break;
                }
            }
            
            // Only drop the column if it exists
            if ($hasColumn) {
                // SQLite doesn't support dropping columns directly
                // We would need to recreate the table, which is complex
                // For simplicity, we'll just log a message
                log_message('info', 'SQLite does not support dropping columns directly. The revoked column will remain.');
            }
        } else {
            // For other databases like MySQL
            try {
                $this->forge->dropColumn('user_api_tokens', 'revoked');
            } catch (\Exception $e) {
                // Column might not exist, ignore the error
                log_message('info', 'Error dropping column revoked: ' . $e->getMessage());
            }
        }
    }
}

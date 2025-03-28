<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNameToUserApiTokens extends Migration
{
    public function up()
    {
        // For SQLite, we need to check if the column exists using PRAGMA
        if ($this->db->DBDriver == 'SQLite3') {
            // Get the table info
            $tableInfo = $this->db->query("PRAGMA table_info(user_api_tokens)")->getResultArray();
            
            // Check if the name column exists
            $hasColumn = false;
            foreach ($tableInfo as $column) {
                if ($column['name'] === 'name') {
                    $hasColumn = true;
                    break;
                }
            }
            
            // Only add the column if it doesn't exist
            if (!$hasColumn) {
                // For SQLite, we can't use 'after' in addColumn
                $this->forge->addColumn('user_api_tokens', [
                    'name' => [
                        'type' => 'VARCHAR',
                        'constraint' => 100,
                        'null' => true // Make it nullable for existing records
                    ]
                ]);
            }
        } else {
            // For other databases like MySQL
            try {
                $this->forge->addColumn('user_api_tokens', [
                    'name' => [
                        'type' => 'VARCHAR',
                        'constraint' => 100,
                        'after' => 'user_id',
                        'null' => true // Make it nullable for existing records
                    ]
                ]);
            } catch (\Exception $e) {
                // Column might already exist, ignore the error
                log_message('info', 'Column name might already exist: ' . $e->getMessage());
            }
        }
    }

    public function down()
    {
        // For SQLite, we need to check if the column exists using PRAGMA
        if ($this->db->DBDriver == 'SQLite3') {
            // Get the table info
            $tableInfo = $this->db->query("PRAGMA table_info(user_api_tokens)")->getResultArray();
            
            // Check if the name column exists
            $hasColumn = false;
            foreach ($tableInfo as $column) {
                if ($column['name'] === 'name') {
                    $hasColumn = true;
                    break;
                }
            }
            
            // Only drop the column if it exists
            if ($hasColumn) {
                // SQLite doesn't support dropping columns directly
                // We would need to recreate the table, which is complex
                // For simplicity, we'll just log a message
                log_message('info', 'SQLite does not support dropping columns directly. The name column will remain.');
            }
        } else {
            // For other databases like MySQL
            try {
                $this->forge->dropColumn('user_api_tokens', 'name');
            } catch (\Exception $e) {
                // Column might not exist, ignore the error
                log_message('info', 'Error dropping column name: ' . $e->getMessage());
            }
        }
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRevokedToUserApiTokens extends Migration
{
    public function up()
    {
        // Check if the column already exists to avoid errors
        $hasColumn = false;
        
        try {
            // Try to select from the column - if it exists, this won't throw an error
            $this->db->query("SELECT revoked FROM user_api_tokens LIMIT 1");
            $hasColumn = true;
        } catch (\Exception $e) {
            // Column doesn't exist, we'll add it
            $hasColumn = false;
        }
        
        // Only add the column if it doesn't exist
        if (!$hasColumn) {
            $this->forge->addColumn('user_api_tokens', [
                'revoked' => [
                    'type' => 'BOOLEAN',
                    'default' => false,
                    'after' => 'updated_at'
                ]
            ]);
        }
    }

    public function down()
    {
        // Only attempt to drop if the column exists
        $hasColumn = false;
        
        try {
            // Try to select from the column - if it exists, this won't throw an error
            $this->db->query("SELECT revoked FROM user_api_tokens LIMIT 1");
            $hasColumn = true;
        } catch (\Exception $e) {
            // Column doesn't exist
            $hasColumn = false;
        }
        
        if ($hasColumn) {
            $this->forge->dropColumn('user_api_tokens', 'revoked');
        }
    }
}

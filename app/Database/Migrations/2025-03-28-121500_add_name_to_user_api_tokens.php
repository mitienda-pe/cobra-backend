<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNameToUserApiTokens extends Migration
{
    public function up()
    {
        // Check if the column already exists to avoid errors
        $fields = $this->db->getFieldData('user_api_tokens');
        $nameExists = false;
        
        foreach ($fields as $field) {
            if ($field->name === 'name') {
                $nameExists = true;
                break;
            }
        }
        
        // Only add the column if it doesn't exist
        if (!$nameExists) {
            $this->forge->addColumn('user_api_tokens', [
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'after' => 'user_id',
                    'null' => true, // Make it nullable for existing records
                ]
            ]);
        }
    }

    public function down()
    {
        // Only attempt to drop if the column exists
        $fields = $this->db->getFieldData('user_api_tokens');
        $nameExists = false;
        
        foreach ($fields as $field) {
            if ($field->name === 'name') {
                $nameExists = true;
                break;
            }
        }
        
        if ($nameExists) {
            $this->forge->dropColumn('user_api_tokens', 'name');
        }
    }
}

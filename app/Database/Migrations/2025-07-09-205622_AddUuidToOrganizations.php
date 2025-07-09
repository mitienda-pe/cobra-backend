<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToOrganizations extends Migration
{
    public function up()
    {
        // Add uuid column to organizations table
        $this->forge->addColumn('organizations', [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => false,
                'after' => 'id'
            ]
        ]);
        
        // Generate UUIDs for existing organizations
        helper('uuid');
        $db = \Config\Database::connect();
        $organizations = $db->table('organizations')->select('id')->get()->getResultArray();
        
        foreach ($organizations as $org) {
            $uuid = generate_uuid();
            $db->table('organizations')
                ->where('id', $org['id'])
                ->update(['uuid' => $uuid]);
        }
        
        // Make uuid unique
        $this->forge->addUniqueKey('organizations', 'uuid');
    }

    public function down()
    {
        // Remove uuid column from organizations table
        $this->forge->dropColumn('organizations', 'uuid');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToOrganizations extends Migration
{
    public function up()
    {
        // Add uuid column to organizations table (nullable first)
        $this->forge->addColumn('organizations', [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
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
        
        // Now modify column to be NOT NULL and add unique constraint
        $db->query('CREATE UNIQUE INDEX organizations_uuid ON organizations (uuid)');
        
        // Note: SQLite doesn't support ALTER COLUMN, so we keep it nullable but with unique constraint
    }

    public function down()
    {
        // Remove uuid column from organizations table
        $this->forge->dropColumn('organizations', 'uuid');
    }
}

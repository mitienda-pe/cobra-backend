<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCodeToOrganizationsTable extends Migration
{
    public function up()
    {
        // Add code column to organizations table (without unique constraint initially)
        $this->forge->addColumn('organizations', [
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
                'after' => 'name'
            ]
        ]);
        
        // Generate codes for existing organizations
        $organizations = $this->db->table('organizations')->get()->getResult();
        
        foreach ($organizations as $organization) {
            // Generate a simple code based on organization name
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $organization->name), 0, 8));
            if (empty($code)) {
                $code = 'ORG' . $organization->id;
            }
            
            // Make sure code is unique
            $existingCode = $this->db->table('organizations')
                ->where('code', $code)
                ->countAllResults();
            
            if ($existingCode > 0) {
                $code = $code . $organization->id;
            }
            
            $this->db->table('organizations')
                ->where('id', $organization->id)
                ->update(['code' => $code]);
        }
        
        // Now make the column NOT NULL and add unique index
        $this->forge->modifyColumn('organizations', [
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => false
            ]
        ]);
        
        // Add unique index
        $this->forge->addKey('code', false, true);
        $this->forge->processIndexes('organizations');
    }

    public function down()
    {
        $this->forge->dropColumn('organizations', 'code');
    }
}
<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToPortfolios extends Migration
{
    public function up()
    {
        // Check if uuid column already exists
        try {
            $this->db->query('SELECT uuid FROM portfolios LIMIT 1');
            $uuidExists = true;
        } catch (\Exception $e) {
            $uuidExists = false;
        }

        if (!$uuidExists) {
            // Add UUID column only if it doesn't exist
            $this->forge->addColumn('portfolios', [
                'uuid' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                    'after'      => 'id'
                ]
            ]);
        }

        // Generate UUIDs for records that don't have one
        $db = \Config\Database::connect();
        $portfolios = $db->table('portfolios')
                        ->where('uuid IS NULL')
                        ->get()
                        ->getResultArray();

        foreach ($portfolios as $portfolio) {
            $db->table('portfolios')
               ->where('id', $portfolio['id'])
               ->update(['uuid' => bin2hex(random_bytes(16))]);
        }

        // Make UUID column required and unique if it's not already
        $this->forge->modifyColumn('portfolios', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => false,
                'unique'     => true,
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('portfolios', 'uuid');
    }
}

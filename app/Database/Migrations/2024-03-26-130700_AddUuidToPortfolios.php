<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToPortfolios extends Migration
{
    public function up()
    {
        // Add UUID column
        $this->forge->addColumn('portfolios', [
            'uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'id'
            ]
        ]);

        // Generate UUIDs for existing records
        $db = \Config\Database::connect();
        $portfolios = $db->table('portfolios')->get()->getResultArray();

        foreach ($portfolios as $portfolio) {
            $db->table('portfolios')
               ->where('id', $portfolio['id'])
               ->update(['uuid' => bin2hex(random_bytes(16))]);
        }

        // Make UUID column required and unique
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

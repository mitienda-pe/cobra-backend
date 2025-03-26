<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Ramsey\Uuid\Uuid;

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
               ->update(['uuid' => Uuid::uuid4()->toString()]);
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

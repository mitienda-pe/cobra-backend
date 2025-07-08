<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUuidToPortfolios extends Migration
{
    public function up()
    {
        // Agregar la columna uuid a la tabla portfolios
        $this->forge->addColumn('portfolios', [
            'uuid' => [
                'type' => 'VARCHAR',
                'constraint' => 36,
                'null' => true,
                'after' => 'organization_id'
            ]
        ]);
        
        // Generar UUIDs para los registros existentes
        $db = \Config\Database::connect();
        $portfolios = $db->table('portfolios')->get()->getResultArray();
        
        helper('uuid');
        foreach ($portfolios as $portfolio) {
            $uuid = generate_uuid();
            $db->table('portfolios')
               ->where('id', $portfolio['id'])
               ->update(['uuid' => $uuid]);
        }
    }

    public function down()
    {
        // Eliminar la columna uuid
        $this->forge->dropColumn('portfolios', 'uuid');
    }
}
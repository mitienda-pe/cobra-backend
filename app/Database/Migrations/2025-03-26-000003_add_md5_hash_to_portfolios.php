<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMd5HashToPortfolios extends Migration
{
    public function up()
    {
        // Agregar la columna md5_hash
        $this->forge->addColumn('portfolios', [
            'md5_hash' => [
                'type' => 'VARCHAR',
                'constraint' => 32,
                'null' => true,
                'after' => 'uuid'
            ]
        ]);
        
        // Actualizar los registros existentes
        $db = \Config\Database::connect();
        $portfolios = $db->table('portfolios')->get()->getResultArray();
        
        foreach ($portfolios as $portfolio) {
            $db->table('portfolios')
               ->where('id', $portfolio['id'])
               ->update(['md5_hash' => md5($portfolio['uuid'])]);
        }
        
        // Crear un índice para búsquedas rápidas
        $this->forge->addKey('md5_hash');
    }

    public function down()
    {
        // Eliminar la columna
        $this->forge->dropColumn('portfolios', 'md5_hash');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdatePortfolioUuids extends Migration
{
    public function up()
    {
        // Actualizar los UUIDs existentes a formato corto
        $db = \Config\Database::connect();
        $portfolios = $db->table('portfolios')->get()->getResultArray();
        
        foreach ($portfolios as $portfolio) {
            // Generar nuevo UUID corto
            helper('uuid');
            $uuid = generate_uuid();
            $shortUuid = substr($uuid, 0, 8);
            
            $db->table('portfolios')
               ->where('id', $portfolio['id'])
               ->update(['uuid' => $shortUuid]);
        }
    }

    public function down()
    {
        // No es necesario un down() ya que no estamos modificando la estructura
    }
}

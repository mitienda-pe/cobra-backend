<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCodeToOrganizations extends Migration
{
    public function up()
    {
        // Paso 1: Agregar la columna sin el índice único
        $this->forge->addColumn('organizations', [
            'code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'name'
            ]
        ]);

        // Paso 2: Crear el índice único
        $this->db->query('CREATE UNIQUE INDEX idx_organizations_code ON organizations(code) WHERE code IS NOT NULL');
    }

    public function down()
    {
        // Eliminar el índice primero
        $this->db->query('DROP INDEX IF EXISTS idx_organizations_code');
        
        // Luego eliminar la columna
        $this->forge->dropColumn('organizations', 'code');
    }
}

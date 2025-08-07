<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoProductionFields extends Migration
{
    public function up()
    {
        // Agregar campos para configuración dinámica de entornos
        $fields = [
            'ligo_environment' => [
                'type' => 'ENUM',
                'constraint' => ['dev', 'prod'],
                'default' => 'dev',
                'null' => false,
                'comment' => 'Entorno de Ligo: desarrollo o producción'
            ],
            'ligo_auth_url' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'URL de autenticación de Ligo (personalizable por entorno)'
            ],
            'ligo_api_url' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'URL de API Gateway de Ligo (personalizable por entorno)'
            ],
            'ligo_ssl_verify' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'null' => false,
                'comment' => 'Verificar certificados SSL (true para producción)'
            ]
        ];

        $this->forge->addColumn('organizations', $fields);
        
        // Crear índice para environment
        $this->forge->addKey('ligo_environment');
        $this->db->query('CREATE INDEX idx_organizations_ligo_environment ON organizations (ligo_environment)');
    }

    public function down()
    {
        // Eliminar índice
        $this->db->query('DROP INDEX IF EXISTS idx_organizations_ligo_environment ON organizations');
        
        // Eliminar campos
        $this->forge->dropColumn('organizations', [
            'ligo_environment',
            'ligo_auth_url', 
            'ligo_api_url',
            'ligo_ssl_verify'
        ]);
    }
}

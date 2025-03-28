<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoFieldsToOrganizations extends Migration
{
    public function up()
    {
        // Añadir todos los campos necesarios para la integración con Ligo
        $fields = [
            'ligo_enabled' => [
                'type' => 'BOOLEAN',
                'null' => false,
                'default' => false,
            ],
            'ligo_username' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_password' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_company_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_account_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ],
            'ligo_merchant_code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ],
            'ligo_webhook_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ]
        ];
        
        // Añadir los campos a la tabla
        try {
            $this->forge->addColumn('organizations', $fields);
        } catch (\Exception $e) {
            // Si hay un error, lo registramos pero continuamos
            log_message('error', 'Error al añadir campos a la tabla organizations: ' . $e->getMessage());
        }
    }

    public function down()
    {
        // Lista de campos a eliminar
        $fields = [
            'ligo_enabled',
            'ligo_username',
            'ligo_password',
            'ligo_company_id',
            'ligo_account_id',
            'ligo_merchant_code',
            'ligo_webhook_secret'
        ];
        
        // Eliminar los campos de la tabla
        try {
            $this->forge->dropColumn('organizations', $fields);
        } catch (\Exception $e) {
            // Si hay un error, lo registramos pero continuamos
            log_message('error', 'Error al eliminar campos de la tabla organizations: ' . $e->getMessage());
        }
    }
}

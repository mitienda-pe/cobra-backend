<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoFieldsToOrganizations extends Migration
{
    public function up()
    {
        // Obtener la lista de columnas existentes en la tabla
        $existingColumns = [];
        try {
            $query = $this->db->query("PRAGMA table_info(organizations)");
            $results = $query->getResultArray();
            foreach ($results as $col) {
                $existingColumns[] = $col['name'];
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al obtener columnas existentes: ' . $e->getMessage());
        }
        
        // Añadir solo las columnas que no existen
        if (!in_array('ligo_enabled', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_enabled' => [
                    'type' => 'BOOLEAN',
                    'null' => false,
                    'default' => false,
                ]
            ]);
        }
        
        if (!in_array('ligo_username', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_username' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ]
            ]);
        }
        
        if (!in_array('ligo_password', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_password' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ]
            ]);
        }
        
        if (!in_array('ligo_company_id', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_company_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ]
            ]);
        }
        
        if (!in_array('ligo_account_id', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_account_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ]
            ]);
        }
        
        if (!in_array('ligo_merchant_code', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_merchant_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                ]
            ]);
        }
        
        if (!in_array('ligo_webhook_secret', $existingColumns) && !in_array('ligo_private_key', $existingColumns)) {
            $this->forge->addColumn('organizations', [
                'ligo_webhook_secret' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ]
            ]);
        }
    }

    public function down()
    {
        // Obtener la lista de columnas existentes en la tabla
        $existingColumns = [];
        try {
            $query = $this->db->query("PRAGMA table_info(organizations)");
            $results = $query->getResultArray();
            foreach ($results as $col) {
                $existingColumns[] = $col['name'];
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al obtener columnas existentes: ' . $e->getMessage());
        }
        
        // Eliminar solo las columnas que existen
        $columnsToRemove = [
            'ligo_enabled',
            'ligo_username',
            'ligo_password',
            'ligo_company_id',
            'ligo_account_id',
            'ligo_merchant_code',
            'ligo_webhook_secret'
        ];
        
        foreach ($columnsToRemove as $column) {
            if (in_array($column, $existingColumns)) {
                try {
                    $this->forge->dropColumn('organizations', $column);
                } catch (\Exception $e) {
                    log_message('error', 'Error al eliminar columna ' . $column . ': ' . $e->getMessage());
                }
            }
        }
    }
}

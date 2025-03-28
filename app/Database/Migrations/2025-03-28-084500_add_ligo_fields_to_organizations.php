<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLigoFieldsToOrganizations extends Migration
{
    public function up()
    {
        // Primero verificamos si las columnas ya existen para evitar errores
        $fields = [];
        
        if (!$this->db->fieldExists('ligo_enabled', 'organizations')) {
            $fields['ligo_enabled'] = [
                'type' => 'BOOLEAN',
                'null' => false,
                'default' => false,
            ];
        }
        
        if (!$this->db->fieldExists('ligo_username', 'organizations')) {
            $fields['ligo_username'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        
        if (!$this->db->fieldExists('ligo_password', 'organizations')) {
            $fields['ligo_password'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        
        if (!$this->db->fieldExists('ligo_company_id', 'organizations')) {
            $fields['ligo_company_id'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        
        if (!$this->db->fieldExists('ligo_account_id', 'organizations')) {
            $fields['ligo_account_id'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        
        if (!$this->db->fieldExists('ligo_merchant_code', 'organizations')) {
            $fields['ligo_merchant_code'] = [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
            ];
        }
        
        if (!$this->db->fieldExists('ligo_webhook_secret', 'organizations')) {
            $fields['ligo_webhook_secret'] = [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
            ];
        }
        
        // Solo añadimos los campos si hay alguno que añadir
        if (!empty($fields)) {
            $this->forge->addColumn('organizations', $fields);
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
        
        // Eliminamos solo los campos que existen
        foreach ($fields as $field) {
            if ($this->db->fieldExists($field, 'organizations')) {
                $this->forge->dropColumn('organizations', $field);
            }
        }
    }
}

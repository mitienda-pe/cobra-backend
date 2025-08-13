<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSuperadminLigoConfig extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'auto_increment' => true,
            ],
            'config_key' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'comment' => 'Unique key for the configuration (e.g., "ligo_global")'
            ],
            'environment' => [
                'type' => 'ENUM',
                'constraint' => ['dev', 'prod'],
                'default' => 'dev',
                'comment' => 'Environment: dev or prod'
            ],
            'username' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Ligo username'
            ],
            'password' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Encrypted Ligo password'
            ],
            'company_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'comment' => 'Ligo company UUID'
            ],
            'account_id' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'comment' => 'Ligo account ID for QR generation'
            ],
            'merchant_code' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'comment' => 'Ligo merchant code'
            ],
            'private_key' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'RSA private key for JWT signing'
            ],
            'webhook_secret' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Webhook secret for validation'
            ],
            'auth_url' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Custom auth URL (optional)'
            ],
            'api_url' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
                'comment' => 'Custom API URL (optional)'
            ],
            'ssl_verify' => [
                'type' => 'BOOLEAN',
                'default' => true,
                'comment' => 'Whether to verify SSL certificates'
            ],
            'enabled' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'comment' => 'Whether this configuration is enabled'
            ],
            'is_active' => [
                'type' => 'BOOLEAN',
                'default' => false,
                'comment' => 'Whether this is the active configuration'
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'Admin notes about this configuration'
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['config_key', 'environment']);
        $this->forge->addKey(['enabled', 'is_active']);
        $this->forge->createTable('superadmin_ligo_config');

        // Insert default configurations
        $this->db->table('superadmin_ligo_config')->insertBatch([
            [
                'config_key' => 'ligo_global',
                'environment' => 'dev',
                'enabled' => false,
                'is_active' => false,
                'notes' => 'Global Ligo configuration for development environment',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ],
            [
                'config_key' => 'ligo_global',
                'environment' => 'prod',
                'enabled' => false,
                'is_active' => false,
                'notes' => 'Global Ligo configuration for production environment',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('superadmin_ligo_config');
    }
}

<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateUserOtpCodes extends Migration
{
    public function up()
    {
        // Check if client_id column exists before dropping it
        $query = $this->db->query("PRAGMA table_info(user_otp_codes)");
        $columns = $query->getResultArray();
        $hasClientId = false;
        $hasOrganizationCode = false;
        $hasDeliveryDetails = false;
        $hasDeliveryInfo = false;
        $hasUsedAt = false;
        $hasDeliveryMethod = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'client_id') {
                $hasClientId = true;
            } elseif ($column['name'] === 'organization_code') {
                $hasOrganizationCode = true;
            } elseif ($column['name'] === 'delivery_details') {
                $hasDeliveryDetails = true;
            } elseif ($column['name'] === 'delivery_info') {
                $hasDeliveryInfo = true;
            } elseif ($column['name'] === 'used_at') {
                $hasUsedAt = true;
            } elseif ($column['name'] === 'delivery_method') {
                $hasDeliveryMethod = true;
            }
        }

        // Drop client_id column if it exists
        if ($hasClientId) {
            $this->forge->dropColumn('user_otp_codes', 'client_id');
        }

        // Add organization_code if it doesn't exist
        if (!$hasOrganizationCode) {
            $this->forge->addColumn('user_otp_codes', [
                'organization_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                    'after' => 'code'
                ]
            ]);
        }

        // Rename delivery_details to delivery_info if needed
        if ($hasDeliveryDetails && !$hasDeliveryInfo) {
            $this->forge->modifyColumn('user_otp_codes', [
                'delivery_details' => [
                    'name' => 'delivery_info',
                    'type' => 'TEXT',
                    'null' => true,
                ]
            ]);
        }

        // Drop unused columns if they exist
        if ($hasUsedAt) {
            $this->forge->dropColumn('user_otp_codes', 'used_at');
        }
        if ($hasDeliveryMethod) {
            $this->forge->dropColumn('user_otp_codes', 'delivery_method');
        }
    }

    public function down()
    {
        // Check existing columns
        $query = $this->db->query("PRAGMA table_info(user_otp_codes)");
        $columns = $query->getResultArray();
        $hasClientId = false;
        $hasDeliveryInfo = false;

        foreach ($columns as $column) {
            if ($column['name'] === 'client_id') {
                $hasClientId = true;
            } elseif ($column['name'] === 'delivery_info') {
                $hasDeliveryInfo = true;
            }
        }

        // Add back the original columns if they don't exist
        if (!$hasClientId) {
            $this->forge->addColumn('user_otp_codes', [
                'client_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'after' => 'id'
                ]
            ]);
        }

        // Add back other columns
        $this->forge->addColumn('user_otp_codes', [
            'used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'delivery_method' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'null' => true,
            ]
        ]);

        // Rename delivery_info back to delivery_details if needed
        if ($hasDeliveryInfo) {
            $this->forge->modifyColumn('user_otp_codes', [
                'delivery_info' => [
                    'name' => 'delivery_details',
                    'type' => 'TEXT',
                    'null' => true,
                ]
            ]);
        }

        // Drop organization_code column
        $this->forge->dropColumn('user_otp_codes', 'organization_code');
    }
}

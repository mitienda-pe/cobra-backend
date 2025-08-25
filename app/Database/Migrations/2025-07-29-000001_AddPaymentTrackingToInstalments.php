<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPaymentTrackingToInstalments extends Migration
{
    public function up()
    {
        // Add payment tracking columns to instalments table for better auditing
        $this->forge->addColumn('instalments', [
            'payment_method' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'status',
                'comment' => 'Method used for payment (cash, transfer, ligo_qr, etc.)'
            ],
            'ligo_qr_id' => [
                'type' => 'VARCHAR',
                'constraint' => 50,
                'null' => true,
                'after' => 'payment_method',
                'comment' => 'Ligo QR identifier for tracking payments'
            ],
            'payment_reference' => [
                'type' => 'VARCHAR',
                'constraint' => 100,
                'null' => true,
                'after' => 'ligo_qr_id',
                'comment' => 'Payment reference code or transaction ID'
            ]
        ]);
        
        // Add index for faster lookups
        $this->forge->addKey(['ligo_qr_id'], false, false, 'idx_instalments_ligo_qr_id');
    }

    public function down()
    {
        // Drop the index first
        $this->forge->dropKey('instalments', 'idx_instalments_ligo_qr_id');
        
        // Drop the columns
        $this->forge->dropColumn('instalments', ['payment_method'", 'qr_id', 'payment_reference']);
    }
}
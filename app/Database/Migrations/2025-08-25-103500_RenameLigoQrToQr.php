<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenameLigoQrToQr extends Migration
{
    public function up()
    {
        // Update payment_method from 'ligo_qr' to 'qr' in payments table
        $this->db->query("UPDATE payments SET payment_method = 'qr' WHERE payment_method = 'ligo_qr'");
        log_message('info', '[Migration] Updated payments: ligo_qr -> qr');

        // Update payment_method from 'ligo_qr' to 'qr' in instalments table
        $this->db->query("UPDATE instalments SET payment_method = 'qr' WHERE payment_method = 'ligo_qr'");
        log_message('info', '[Migration] Updated instalments: ligo_qr -> qr');

        // Log the number of affected records
        $paymentCount = $this->db->query("SELECT COUNT(*) as count FROM payments WHERE payment_method = 'qr'")->getRow()->count;
        $instalmentCount = $this->db->query("SELECT COUNT(*) as count FROM instalments WHERE payment_method = 'qr'")->getRow()->count;
        
        log_message('info', '[Migration] RenameLigoQrToQr completed. Affected: ' . $paymentCount . ' payments, ' . $instalmentCount . ' instalments');
    }

    public function down()
    {
        // Revert payment_method from 'qr' back to 'ligo_qr' in payments table
        // Note: This assumes ALL 'qr' payments were originally 'ligo_qr'
        // In a real scenario, you might want to track this more carefully
        $this->db->query("UPDATE payments SET payment_method = 'ligo_qr' WHERE payment_method = 'qr'");
        log_message('info', '[Migration Rollback] Reverted payments: qr -> ligo_qr');

        // Revert payment_method from 'qr' back to 'ligo_qr' in instalments table
        $this->db->query("UPDATE instalments SET payment_method = 'ligo_qr' WHERE payment_method = 'qr'");
        log_message('info', '[Migration Rollback] Reverted instalments: qr -> ligo_qr');
        
        log_message('info', '[Migration] RenameLigoQrToQr rollback completed');
    }
}
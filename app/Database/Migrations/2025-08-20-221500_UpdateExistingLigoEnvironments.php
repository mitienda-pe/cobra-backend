<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateExistingLigoEnvironments extends Migration
{
    public function up()
    {
        // Conservative approach: Mark obvious test payments as 'dev'
        // Leave unclear payments as NULL for manual review
        
        $db = \Config\Database::connect();
        
        // Only mark obvious test payments containing "test" or "TEST"
        $testQuery = "UPDATE payments 
                     SET ligo_environment = 'dev' 
                     WHERE payment_method'", 'qr' 
                       AND ligo_environment IS NULL 
                       AND (external_id LIKE '%test%' 
                            OR external_id LIKE '%TEST%')";
        
        $db->query($testQuery);
        $testUpdated = $db->affectedRows();
        
        // Update existing transfers to default prod (safer assumption for transfers)
        $transferQuery = "UPDATE transfers 
                         SET ligo_environment = 'prod' 
                         WHERE ligo_environment IS NULL";
        
        $db->query($transferQuery);
        $transfersUpdated = $db->affectedRows();
        
        log_message('info', "[Migration] Conservative Ligo environment update - Test payments: {$testUpdated}, Transfers: {$transfersUpdated}. Other payments left as NULL for manual review.");
    }

    public function down()
    {
        // Set all ligo_environment fields back to NULL
        $db = \Config\Database::connect();
        
        $db->query("UPDATE payments SET ligo_environment = NULL WHERE payment_method'", 'qr'");
        $db->query("UPDATE transfers SET ligo_environment = NULL");
        
        log_message('info', "[Migration] Reverted all ligo_environment fields to NULL");
    }
}
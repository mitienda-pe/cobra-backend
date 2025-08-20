<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateExistingLigoEnvironments extends Migration
{
    public function up()
    {
        // Update existing Ligo payments based on external_id patterns
        // Production payments typically have long numeric external_ids
        // Development/test payments contain "test" or have shorter IDs
        
        $db = \Config\Database::connect();
        
        // Update payments that look like production (long numeric IDs, no "test")
        $prodQuery = "UPDATE payments 
                     SET ligo_environment = 'prod' 
                     WHERE payment_method = 'ligo_qr' 
                       AND ligo_environment IS NULL 
                       AND external_id IS NOT NULL
                       AND external_id NOT LIKE '%test%' 
                       AND external_id NOT LIKE '%TEST%'
                       AND LENGTH(external_id) > 20";
        
        $db->query($prodQuery);
        $prodUpdated = $db->affectedRows();
        
        // Update payments that look like development/test
        $devQuery = "UPDATE payments 
                    SET ligo_environment = 'dev' 
                    WHERE payment_method = 'ligo_qr' 
                      AND ligo_environment IS NULL 
                      AND (external_id LIKE '%test%' 
                           OR external_id LIKE '%TEST%' 
                           OR LENGTH(external_id) <= 20
                           OR external_id IS NULL)";
        
        $db->query($devQuery);
        $devUpdated = $db->affectedRows();
        
        // Update any remaining NULL ligo_environment payments to 'dev' as default
        $defaultQuery = "UPDATE payments 
                        SET ligo_environment = 'dev' 
                        WHERE payment_method = 'ligo_qr' 
                          AND ligo_environment IS NULL";
        
        $db->query($defaultQuery);
        $defaultUpdated = $db->affectedRows();
        
        // Update existing transfers (if any have NULL ligo_environment)
        $transferQuery = "UPDATE transfers 
                         SET ligo_environment = 'prod' 
                         WHERE ligo_environment IS NULL";
        
        $db->query($transferQuery);
        $transfersUpdated = $db->affectedRows();
        
        log_message('info', "[Migration] Updated Ligo environments - Prod payments: {$prodUpdated}, Dev payments: {$devUpdated}, Default payments: {$defaultUpdated}, Transfers: {$transfersUpdated}");
    }

    public function down()
    {
        // Set all ligo_environment fields back to NULL
        $db = \Config\Database::connect();
        
        $db->query("UPDATE payments SET ligo_environment = NULL WHERE payment_method = 'ligo_qr'");
        $db->query("UPDATE transfers SET ligo_environment = NULL");
        
        log_message('info', "[Migration] Reverted all ligo_environment fields to NULL");
    }
}
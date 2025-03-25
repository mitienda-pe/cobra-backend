<?php
/**
 * Database Check Script
 * 
 * This script tests database connectivity and verifies
 * that tables needed for the OTP functionality exist.
 */

// Basic setup
define('ROOTPATH', dirname(__DIR__) . '/');
define('WRITEPATH', ROOTPATH . 'writable/');

// Function to write to log
function writeLog($message) {
    $logFile = WRITEPATH . 'logs/db_check.log';
    file_put_contents(
        $logFile, 
        "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL,
        FILE_APPEND
    );
}

// Set content type
header('Content-Type: application/json');

try {
    // Connect to SQLite database
    $dbPath = WRITEPATH . 'db/cobranzas.db';
    
    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found: {$dbPath}");
    }
    
    $db = new SQLite3($dbPath);
    
    // Check if user_otp_codes table exists
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='user_otp_codes'");
    $tableExists = $result->fetchArray() !== false;
    
    if (!$tableExists) {
        throw new Exception("Table 'user_otp_codes' does not exist");
    }
    
    // Get table structure
    $result = $db->query("PRAGMA table_info(user_otp_codes)");
    $columns = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[$row['name']] = [
            'type' => $row['type'],
            'notnull' => $row['notnull'],
            'dflt_value' => $row['dflt_value']
        ];
    }
    
    // Check for required columns
    $requiredColumns = [
        'id', 'user_id', 'code', 'expires_at', 'used_at', 'created_at',
        'delivery_method', 'delivery_status', 'delivery_details'
    ];
    
    $missingColumns = [];
    foreach ($requiredColumns as $col) {
        if (!isset($columns[$col])) {
            $missingColumns[] = $col;
        }
    }
    
    // Check user table for phone field
    $result = $db->query("PRAGMA table_info(users)");
    $userColumns = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $userColumns[$row['name']] = [
            'type' => $row['type'],
            'notnull' => $row['notnull'],
            'dflt_value' => $row['dflt_value']
        ];
    }
    
    // Response with all data
    $response = [
        'success' => true,
        'database' => [
            'file' => $dbPath,
            'exists' => file_exists($dbPath),
            'size' => file_exists($dbPath) ? filesize($dbPath) : 0
        ],
        'tables' => [
            'user_otp_codes' => [
                'exists' => $tableExists,
                'columns' => $columns,
                'missing_columns' => $missingColumns
            ],
            'users' => [
                'has_phone_field' => isset($userColumns['phone']),
                'phone_field_details' => isset($userColumns['phone']) ? $userColumns['phone'] : null
            ]
        ]
    ];
    
    writeLog("Database check completed successfully");
    
} catch (Exception $e) {
    writeLog("Error: " . $e->getMessage());
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ];
}

echo json_encode($response, JSON_PRETTY_PRINT);
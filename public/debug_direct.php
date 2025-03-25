<?php
// Script que no pasa por el sistema de enrutamiento de CodeIgniter

// Asegurar que este script se ejecuta directamente
define('BYPASS_CODEIGNITER', true);

// Disable error reporting for production
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define path to database
$dbPath = dirname(__DIR__) . '/writable/db/cobranzas.db';

// Set content type
header('Content-Type: text/plain');

echo "=== DIRECT DATABASE TEST ===\n";
echo "Executed at: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "DB Path: {$dbPath}\n\n";

try {
    // Open database connection
    $db = new SQLite3($dbPath);
    
    // Test read permissions
    echo "Testing read permissions...\n";
    $result = $db->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    echo "Read successful: Found {$row['count']} users\n";
    
    // Test write permissions with direct SQLite3
    echo "Testing write permissions with direct SQLite3...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS write_test (id INTEGER PRIMARY KEY, value TEXT)");
    $timestamp = date('Y-m-d H:i:s');
    $db->exec("INSERT INTO write_test (value) VALUES ('Direct test at {$timestamp}')");
    echo "Write successful using direct SQLite3\n";
    
    // Test with PDO
    echo "Testing with PDO...\n";
    $pdo = new PDO("sqlite:{$dbPath}");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("INSERT INTO write_test (value) VALUES ('PDO test at {$timestamp}')");
    echo "Write successful using PDO\n";
    
    // Show last record
    $result = $db->query("SELECT * FROM write_test ORDER BY id DESC LIMIT 1");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    echo "Last record: " . json_encode($row) . "\n";
    
    // Test inserting into organizations table
    try {
        echo "Testing insert into organizations table...\n";
        $orgName = "Test Org " . uniqid();
        $db->exec("INSERT INTO organizations (name, description, status, created_at, updated_at) 
                  VALUES ('{$orgName}', 'Test organization created at {$timestamp}', 'active', 
                  '{$timestamp}', '{$timestamp}')");
        echo "Organization insert successful\n";
        
        // Show last organization
        $result = $db->query("SELECT * FROM organizations ORDER BY id DESC LIMIT 1");
        $row = $result->fetchArray(SQLITE3_ASSOC);
        echo "Last organization: " . json_encode($row) . "\n";
    } catch (Exception $e) {
        echo "ERROR inserting organization: " . $e->getMessage() . "\n";
    }
    
    echo "All tests completed successfully!";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    
    // Check file permissions
    echo "File info:\n";
    echo "Exists: " . (file_exists($dbPath) ? "Yes" : "No") . "\n";
    echo "Readable: " . (is_readable($dbPath) ? "Yes" : "No") . "\n";
    echo "Writable: " . (is_writable($dbPath) ? "Yes" : "No") . "\n";
    
    if (function_exists('posix_getpwuid')) {
        echo "Owner: " . posix_getpwuid(fileowner($dbPath))['name'] . "\n";
        echo "Group: " . posix_getgrgid(filegroup($dbPath))['name'] . "\n";
    } else {
        echo "Owner ID: " . fileowner($dbPath) . "\n";
        echo "Group ID: " . filegroup($dbPath) . "\n";
    }
    
    echo "Permissions: " . substr(sprintf('%o', fileperms($dbPath)), -4) . "\n";
    echo "Current user: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : get_current_user()) . "\n";
}
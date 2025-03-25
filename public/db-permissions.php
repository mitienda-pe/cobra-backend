<?php
/**
 * Database Permissions Check
 * 
 * This script checks if the SQLite database is writable and performs
 * a simple write operation to verify permissions are correct.
 */

// Define path to database
$dbPath = dirname(__DIR__) . '/writable/db/cobranzas.db';

// Set content type
header('Content-Type: application/json');

try {
    // Check if database file exists
    if (!file_exists($dbPath)) {
        throw new Exception("Database file not found at: {$dbPath}");
    }
    
    // Check file permissions
    $perms = substr(sprintf('%o', fileperms($dbPath)), -4);
    $isWritable = is_writable($dbPath);
    $owner = posix_getpwuid(fileowner($dbPath));
    $group = posix_getgrgid(filegroup($dbPath));
    
    // Try to open database connection
    $db = new SQLite3($dbPath);
    
    // Try to create a temporary table
    $db->exec('CREATE TABLE IF NOT EXISTS db_test (id INTEGER PRIMARY KEY, test_value TEXT)');
    
    // Try to insert a value
    $timestamp = date('Y-m-d H:i:s');
    $db->exec("INSERT INTO db_test (test_value) VALUES ('Test write at {$timestamp}')");
    
    // Query to verify insertion
    $result = $db->query('SELECT * FROM db_test ORDER BY id DESC LIMIT 1');
    $lastRow = $result->fetchArray(SQLITE3_ASSOC);
    
    // Response data
    echo json_encode([
        'success' => true,
        'message' => 'Database is writable!',
        'database' => [
            'path' => $dbPath,
            'exists' => true,
            'writable' => $isWritable,
            'permissions' => $perms,
            'owner' => $owner['name'] ?? 'unknown',
            'group' => $group['name'] ?? 'unknown',
            'size' => filesize($dbPath)
        ],
        'test_write' => [
            'success' => true,
            'last_row' => $lastRow
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'database' => [
            'path' => $dbPath,
            'exists' => file_exists($dbPath),
            'writable' => isset($isWritable) ? $isWritable : (file_exists($dbPath) ? is_writable($dbPath) : false),
            'permissions' => isset($perms) ? $perms : (file_exists($dbPath) ? substr(sprintf('%o', fileperms($dbPath)), -4) : 'unknown'),
            'owner' => isset($owner) ? ($owner['name'] ?? 'unknown') : (file_exists($dbPath) ? posix_getpwuid(fileowner($dbPath))['name'] ?? 'unknown' : 'unknown'),
            'group' => isset($group) ? ($group['name'] ?? 'unknown') : (file_exists($dbPath) ? posix_getgrgid(filegroup($dbPath))['name'] ?? 'unknown' : 'unknown'),
            'size' => file_exists($dbPath) ? filesize($dbPath) : 0
        ]
    ], JSON_PRETTY_PRINT);
}
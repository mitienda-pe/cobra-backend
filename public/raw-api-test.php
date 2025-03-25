<?php
/**
 * Raw API Test
 * 
 * This script completely bypasses the framework
 * to help diagnose server configuration issues
 */

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Get request info
$info = [
    'time' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
    'query' => $_SERVER['QUERY_STRING'] ?? '',
    'post' => $_POST,
    'raw_input' => file_get_contents('php://input'),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'php_version' => phpversion(),
    'message' => 'This response comes from a raw PHP file without using any framework code.'
];

// Write diagnostic log
$logFile = dirname(__DIR__) . '/writable/logs/raw_api_test.log';
file_put_contents(
    $logFile,
    "=== Raw API Test at " . date('Y-m-d H:i:s') . " ===\n" . 
    print_r($info, true) . "\n\n",
    FILE_APPEND
);

// Return JSON response
echo json_encode([
    'success' => true,
    'data' => $info,
    'note' => 'If this works but your API endpoints fail, the issue is likely in your framework configuration, not the web server.'
], JSON_PRETTY_PRINT);
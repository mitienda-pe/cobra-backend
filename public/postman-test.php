<?php
/**
 * Postman Test Script
 * 
 * This script helps diagnose issues with Postman API requests
 * and verifies that the .htaccess routing is working properly.
 */

// Check if URL has index.php in it
$hasIndexPhp = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false;
$isApiRequest = strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

// Get request information
$requestInfo = [
    'request_uri' => $_SERVER['REQUEST_URI'],
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'path_info' => $_SERVER['PATH_INFO'] ?? 'Not set',
    'has_index_php' => $hasIndexPhp,
    'is_api_request' => $isApiRequest,
    'script_name' => $_SERVER['SCRIPT_NAME'],
    'php_self' => $_SERVER['PHP_SELF'],
    'query_string' => $_SERVER['QUERY_STRING'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Not set',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
    'authorization' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'Present (not shown)' : 'Not set',
    'route_info' => 'Not available (outside CodeIgniter)'
];

// Get request body
$body = file_get_contents('php://input');
$jsonBody = json_decode($body, true);

// Response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Postman test script',
    'request_info' => $requestInfo,
    'headers' => getallheaders(),
    'get_data' => $_GET,
    'post_data' => $_POST,
    'json_body' => $jsonBody,
    'raw_body' => $body,
    'instructions' => [
        'Use this file to test that your request is reaching the server correctly',
        'If this works but the API endpoint doesn\'t, the issue is with routing inside CodeIgniter',
        'Try these variations in Postman:',
        '1. /postman-test.php',
        '2. /postman-test.php/api/auth/request-otp',
        '3. /index.php/postman-test.php',
        '4. /api/auth/request-otp'
    ]
], JSON_PRETTY_PRINT);
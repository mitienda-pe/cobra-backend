<?php
/**
 * API Diagnostic Script
 * 
 * This script is designed to help troubleshoot API routing issues
 * by providing a form-based interface to test the API endpoints
 * directly from the browser, bypassing Postman.
 */

// Define endpoints to test
$endpoints = [
    'api/auth/request-otp' => [
        'method' => 'POST',
        'fields' => [
            'email' => ['type' => 'text', 'placeholder' => 'Email address'],
            'phone' => ['type' => 'text', 'placeholder' => 'Phone number (alternative to email)'],
            'client_id' => ['type' => 'number', 'placeholder' => 'Client ID (required with phone)'],
            'method' => ['type' => 'select', 'options' => ['email' => 'Email', 'sms' => 'SMS']],
            'device_info' => ['type' => 'text', 'placeholder' => 'Device Info']
        ]
    ],
    'api/auth/verify-otp' => [
        'method' => 'POST',
        'fields' => [
            'email' => ['type' => 'text', 'placeholder' => 'Email address'],
            'phone' => ['type' => 'text', 'placeholder' => 'Phone number (alternative to email)'],
            'client_id' => ['type' => 'number', 'placeholder' => 'Client ID (required with phone)'],
            'code' => ['type' => 'text', 'placeholder' => 'OTP Code'],
            'device_name' => ['type' => 'text', 'placeholder' => 'Device Name']
        ]
    ]
];

// Set up the environment to test different access methods
$baseUrl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
$baseUrl .= $_SERVER['HTTP_HOST'];
$baseUrl = rtrim($baseUrl, '/');

$variants = [
    'direct' => $baseUrl . '/',
    'with_index' => $baseUrl . '/index.php/'
];

// Test if we're actually processing a test request
$response = null;
$requestInfo = null;
$testUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['endpoint']) && isset($_POST['variant'])) {
    $endpoint = $_POST['endpoint'];
    $variant = $_POST['variant'];
    $testUrl = $variants[$variant] . $endpoint;
    
    // Build request data
    $postFields = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, ['endpoint', 'variant', 'submit']) && !empty($value)) {
            $postFields[$key] = $value;
        }
    }
    
    // Special option to test both formats
    if ($variant === 'both') {
        $results = [];
        foreach ($variants as $v => $baseUrl) {
            $url = $baseUrl . $endpoint;
            $results[$v] = testEndpoint($url, $postFields);
        }
        $response = $results;
    } else {
        $response = testEndpoint($testUrl, $postFields);
    }
    
    $requestInfo = [
        'url' => $testUrl,
        'method' => 'POST',
        'post_data' => $postFields
    ];
}

// Function to test an endpoint
function testEndpoint($url, $data) {
    $result = [];
    
    // Initialize cURL
    $ch = curl_init($url);
    
    // Set options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    // Include request and response headers
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
    
    // Execute request
    $response = curl_exec($ch);
    
    // Get response info
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headerSent = curl_getinfo($ch, CURLINFO_HEADER_OUT);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Split response into headers and body
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Check if body is valid JSON
    $jsonData = json_decode($body, true);
    $isJson = json_last_error() === JSON_ERROR_NONE;
    
    $result = [
        'url' => $url,
        'status' => $httpCode,
        'headers_sent' => $headerSent,
        'headers_received' => $headers,
        'raw_body' => $body,
        'json_data' => $isJson ? $jsonData : null,
        'is_json' => $isJson,
        'error' => curl_error($ch)
    ];
    
    curl_close($ch);
    return $result;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Diagnostic Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            color: #333;
        }
        .container {
            display: flex;
            gap: 20px;
        }
        .form-container {
            flex: 1;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .result-container {
            flex: 1;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            max-width: 50%;
        }
        h1, h2, h3 {
            color: #444;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4285f4;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #3367d6;
        }
        pre {
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
        }
        .status {
            padding: 5px 10px;
            border-radius: 3px;
            font-weight: bold;
        }
        .status-success {
            background-color: #d4edda;
            color: #155724;
        }
        .status-error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .url-variants {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        .url-variant {
            background-color: #e9ecef;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 15px;
            cursor: pointer;
            margin-right: 5px;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
        }
        .tab.active {
            border-color: #ddd;
            background-color: #f8f9fa;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <h1>API Diagnostic Tool</h1>
    <p>Use this tool to test API endpoints with different URL formats to diagnose routing issues.</p>
    
    <div class="url-variants">
        <div class="url-variant"><?= $variants['direct'] ?><span style="color:#888">api/endpoint</span></div>
        <div class="url-variant"><?= $variants['with_index'] ?><span style="color:#888">api/endpoint</span></div>
    </div>
    
    <div class="container">
        <div class="form-container">
            <h2>Test API Endpoint</h2>
            
            <div class="tabs">
                <?php $first = true; foreach ($endpoints as $endpoint => $config): ?>
                <div class="tab <?= $first ? 'active' : '' ?>" data-tab="<?= md5($endpoint) ?>"><?= $endpoint ?></div>
                <?php $first = false; endforeach; ?>
            </div>
            
            <?php $first = true; foreach ($endpoints as $endpoint => $config): ?>
            <div id="<?= md5($endpoint) ?>" class="tab-content <?= $first ? 'active' : '' ?>">
                <form method="post">
                    <input type="hidden" name="endpoint" value="<?= $endpoint ?>">
                    
                    <div class="form-group">
                        <label for="variant">URL Format:</label>
                        <select name="variant" id="variant">
                            <option value="direct">Direct URL (without index.php)</option>
                            <option value="with_index">With index.php</option>
                            <option value="both">Test both formats</option>
                        </select>
                    </div>
                    
                    <h3>Request Data</h3>
                    <?php foreach ($config['fields'] as $field => $options): ?>
                        <div class="form-group">
                            <label for="<?= $field ?>"><?= ucfirst(str_replace('_', ' ', $field)) ?>:</label>
                            <?php if ($options['type'] === 'select'): ?>
                                <select name="<?= $field ?>" id="<?= $field ?>">
                                    <?php foreach ($options['options'] as $value => $label): ?>
                                        <option value="<?= $value ?>"><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input 
                                    type="<?= $options['type'] ?>" 
                                    name="<?= $field ?>" 
                                    id="<?= $field ?>" 
                                    placeholder="<?= $options['placeholder'] ?>" 
                                >
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <button type="submit" name="submit">Test Endpoint</button>
                </form>
            </div>
            <?php $first = false; endforeach; ?>
        </div>
        
        <div class="result-container">
            <h2>Test Results</h2>
            
            <?php if ($response): ?>
                <h3>Request Information</h3>
                <pre><?= htmlspecialchars(json_encode($requestInfo, JSON_PRETTY_PRINT)) ?></pre>
                
                <?php if (is_array($response) && isset($response['direct']) && isset($response['with_index'])): ?>
                    <!-- Results for both formats -->
                    <div class="tabs">
                        <div class="tab active" data-tab="direct-result">Direct URL Result</div>
                        <div class="tab" data-tab="index-result">With index.php Result</div>
                    </div>
                    
                    <div id="direct-result" class="tab-content active">
                        <h3>Status: <span class="status <?= $response['direct']['status'] < 400 ? 'status-success' : 'status-error' ?>">
                            <?= $response['direct']['status'] ?>
                        </span></h3>
                        
                        <?php if ($response['direct']['error']): ?>
                            <h3>Error:</h3>
                            <pre><?= htmlspecialchars($response['direct']['error']) ?></pre>
                        <?php endif; ?>
                        
                        <h3>Response Headers:</h3>
                        <pre><?= htmlspecialchars($response['direct']['headers_received']) ?></pre>
                        
                        <h3>Response Body:</h3>
                        <pre><?= htmlspecialchars($response['direct']['raw_body']) ?></pre>
                    </div>
                    
                    <div id="index-result" class="tab-content">
                        <h3>Status: <span class="status <?= $response['with_index']['status'] < 400 ? 'status-success' : 'status-error' ?>">
                            <?= $response['with_index']['status'] ?>
                        </span></h3>
                        
                        <?php if ($response['with_index']['error']): ?>
                            <h3>Error:</h3>
                            <pre><?= htmlspecialchars($response['with_index']['error']) ?></pre>
                        <?php endif; ?>
                        
                        <h3>Response Headers:</h3>
                        <pre><?= htmlspecialchars($response['with_index']['headers_received']) ?></pre>
                        
                        <h3>Response Body:</h3>
                        <pre><?= htmlspecialchars($response['with_index']['raw_body']) ?></pre>
                    </div>
                <?php else: ?>
                    <!-- Result for single format -->
                    <h3>Status: <span class="status <?= $response['status'] < 400 ? 'status-success' : 'status-error' ?>">
                        <?= $response['status'] ?>
                    </span></h3>
                    
                    <?php if ($response['error']): ?>
                        <h3>Error:</h3>
                        <pre><?= htmlspecialchars($response['error']) ?></pre>
                    <?php endif; ?>
                    
                    <h3>Response Headers:</h3>
                    <pre><?= htmlspecialchars($response['headers_received']) ?></pre>
                    
                    <h3>Response Body:</h3>
                    <pre><?= htmlspecialchars($response['raw_body']) ?></pre>
                <?php endif; ?>
            <?php else: ?>
                <p>No test has been run yet. Please use the form to test an endpoint.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Tab switching functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Find the parent tabs container
                const tabsContainer = this.closest('.tabs');
                // Find all tabs in this container
                const tabs = tabsContainer.querySelectorAll('.tab');
                // Find all tab contents that could be targeted by tabs in this container
                const tabContents = document.querySelectorAll('.tab-content');
                
                // Get the target tab content
                const target = this.getAttribute('data-tab');
                
                // Deactivate all tabs in this container
                tabs.forEach(t => t.classList.remove('active'));
                
                // Find tab contents that match the pattern
                const targetContent = document.getElementById(target);
                
                if (targetContent) {
                    // Find siblings (tab contents at the same level)
                    let siblings = [];
                    if (targetContent.parentElement) {
                        siblings = Array.from(targetContent.parentElement.children)
                            .filter(el => el.classList.contains('tab-content'));
                    }
                    
                    // Deactivate siblings
                    siblings.forEach(s => s.classList.remove('active'));
                    
                    // Activate target
                    targetContent.classList.add('active');
                }
                
                // Activate this tab
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>
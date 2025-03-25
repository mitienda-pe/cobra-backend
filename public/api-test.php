<?php
/**
 * API Test Script
 * 
 * This script helps test that API endpoints are working correctly
 * without having to use external tools like Postman
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Test Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], textarea, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
        .response {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .response pre {
            white-space: pre-wrap;
            margin: 0;
        }
        .tabs {
            display: flex;
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f1f1f1;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
        }
        .tab.active {
            background-color: #4CAF50;
            color: white;
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
    <h1>API Test Tool</h1>
    
    <div class="tabs">
        <div class="tab active" data-tab="requestOtp">Request OTP</div>
        <div class="tab" data-tab="verifyOtp">Verify OTP</div>
        <div class="tab" data-tab="custom">Custom Request</div>
    </div>
    
    <div id="requestOtp" class="tab-content active">
        <h2>Request OTP</h2>
        <form id="requestOtpForm">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="text" id="email" name="email" placeholder="user@example.com">
            </div>
            <div class="form-group">
                <label for="phone">Phone (alternative to email):</label>
                <input type="text" id="phone" name="phone" placeholder="+51999999999">
            </div>
            <div class="form-group">
                <label for="clientId">Client ID (required with phone):</label>
                <input type="text" id="clientId" name="clientId" placeholder="2">
            </div>
            <div class="form-group">
                <label for="deviceInfo">Device Info:</label>
                <input type="text" id="deviceInfo" name="deviceInfo" placeholder="Test Device">
            </div>
            <div class="form-group">
                <label for="method">Delivery Method:</label>
                <select id="method" name="method">
                    <option value="email">Email</option>
                    <option value="sms">SMS</option>
                </select>
            </div>
            <button type="submit">Send Request</button>
        </form>
        <div class="response" id="requestOtpResponse">
            <pre>Response will appear here</pre>
        </div>
    </div>
    
    <div id="verifyOtp" class="tab-content">
        <h2>Verify OTP</h2>
        <form id="verifyOtpForm">
            <div class="form-group">
                <label for="verifyEmail">Email:</label>
                <input type="text" id="verifyEmail" name="email" placeholder="user@example.com">
            </div>
            <div class="form-group">
                <label for="verifyPhone">Phone (alternative to email):</label>
                <input type="text" id="verifyPhone" name="phone" placeholder="+51999999999">
            </div>
            <div class="form-group">
                <label for="verifyClientId">Client ID (required with phone):</label>
                <input type="text" id="verifyClientId" name="clientId" placeholder="2">
            </div>
            <div class="form-group">
                <label for="code">OTP Code:</label>
                <input type="text" id="code" name="code" placeholder="123456">
            </div>
            <div class="form-group">
                <label for="deviceName">Device Name:</label>
                <input type="text" id="deviceName" name="deviceName" placeholder="Test Device">
            </div>
            <button type="submit">Verify OTP</button>
        </form>
        <div class="response" id="verifyOtpResponse">
            <pre>Response will appear here</pre>
        </div>
    </div>
    
    <div id="custom" class="tab-content">
        <h2>Custom API Request</h2>
        <form id="customForm">
            <div class="form-group">
                <label for="url">URL Path:</label>
                <input type="text" id="url" name="url" placeholder="api/auth/request-otp">
            </div>
            <div class="form-group">
                <label for="method">Method:</label>
                <select id="customMethod" name="method">
                    <option value="GET">GET</option>
                    <option value="POST">POST</option>
                    <option value="PUT">PUT</option>
                    <option value="DELETE">DELETE</option>
                    <option value="OPTIONS">OPTIONS</option>
                </select>
            </div>
            <div class="form-group">
                <label for="payload">Request Payload (JSON):</label>
                <textarea id="payload" name="payload" rows="5" placeholder='{"email": "user@example.com"}'></textarea>
            </div>
            <div class="form-group">
                <label for="headers">Headers (one per line, format: Key: Value):</label>
                <textarea id="headers" name="headers" rows="3" placeholder='Content-Type: application/json
Accept: application/json'></textarea>
            </div>
            <button type="submit">Send Request</button>
        </form>
        <div class="response" id="customResponse">
            <pre>Response will appear here</pre>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
                
                tab.classList.add('active');
                document.getElementById(tab.getAttribute('data-tab')).classList.add('active');
            });
        });
        
        // Request OTP form
        document.getElementById('requestOtpForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const responseEl = document.getElementById('requestOtpResponse').querySelector('pre');
            responseEl.textContent = 'Sending request...';
            
            try {
                const formData = {
                    email: document.getElementById('email').value || undefined,
                    phone: document.getElementById('phone').value || undefined,
                    client_id: document.getElementById('clientId').value || undefined,
                    device_info: document.getElementById('deviceInfo').value || 'Test Device',
                    method: document.getElementById('method').value
                };
                
                // Clean empty values
                Object.keys(formData).forEach(key => 
                    formData[key] === undefined && delete formData[key]
                );
                
                const response = await fetch('/api/auth/request-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                responseEl.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                responseEl.textContent = `Error: ${error.message}`;
            }
        });
        
        // Verify OTP form
        document.getElementById('verifyOtpForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const responseEl = document.getElementById('verifyOtpResponse').querySelector('pre');
            responseEl.textContent = 'Sending request...';
            
            try {
                const formData = {
                    email: document.getElementById('verifyEmail').value || undefined,
                    phone: document.getElementById('verifyPhone').value || undefined,
                    client_id: document.getElementById('verifyClientId').value || undefined,
                    code: document.getElementById('code').value,
                    device_name: document.getElementById('deviceName').value || 'Test Device'
                };
                
                // Clean empty values
                Object.keys(formData).forEach(key => 
                    formData[key] === undefined && delete formData[key]
                );
                
                const response = await fetch('/api/auth/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const data = await response.json();
                responseEl.textContent = JSON.stringify(data, null, 2);
                
                // If successful, save token to localStorage
                if (data.token) {
                    localStorage.setItem('apiToken', data.token);
                    localStorage.setItem('refreshToken', data.refresh_token);
                }
            } catch (error) {
                responseEl.textContent = `Error: ${error.message}`;
            }
        });
        
        // Custom form
        document.getElementById('customForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const responseEl = document.getElementById('customResponse').querySelector('pre');
            responseEl.textContent = 'Sending request...';
            
            try {
                const url = document.getElementById('url').value;
                const method = document.getElementById('customMethod').value;
                let payload = document.getElementById('payload').value;
                const headersText = document.getElementById('headers').value;
                
                // Parse headers
                const headers = {};
                headersText.split('\n').forEach(line => {
                    if (line.trim()) {
                        const [key, value] = line.split(':');
                        headers[key.trim()] = value.trim();
                    }
                });
                
                // Parse payload if any
                let body = undefined;
                if (payload && ['POST', 'PUT'].includes(method)) {
                    try {
                        body = JSON.parse(payload);
                    } catch (e) {
                        body = payload;
                    }
                }
                
                const options = {
                    method,
                    headers
                };
                
                if (body) {
                    options.body = typeof body === 'string' ? body : JSON.stringify(body);
                }
                
                const response = await fetch(`/${url}`, options);
                const contentType = response.headers.get('content-type');
                
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    responseEl.textContent = JSON.stringify(data, null, 2);
                } else {
                    const text = await response.text();
                    responseEl.textContent = text;
                }
            } catch (error) {
                responseEl.textContent = `Error: ${error.message}`;
            }
        });
    </script>
</body>
</html>
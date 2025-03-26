<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\UserOtpModel;
use App\Models\UserApiTokenModel;
use App\Libraries\Twilio;
use CodeIgniter\HTTP\ResponseInterface;

class AuthController extends BaseApiController
{
    protected $format = 'json';

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        
        // Create API log directory if it doesn't exist
        if (!is_dir(WRITEPATH . 'logs/api')) {
            mkdir(WRITEPATH . 'logs/api', 0755, true);
        }
        
        // Log API request to dedicated file
        $logFile = WRITEPATH . 'logs/api/requests-' . date('Y-m-d') . '.log';
        $logMessage = date('Y-m-d H:i:s') . ' - URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . 
                     ', Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . 
                     ', User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Desactivar session completamente
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Log para depuración
        log_message('debug', 'API Request: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ' - Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
    }

    /**
     * Request OTP for login
     */
    public function request_otp()
    {
        try {
            // Create API log file for detailed request diagnostics
            $logFile = WRITEPATH . 'logs/api/otp-requests-' . date('Y-m-d') . '.log';
            
            // Log request details
            ob_start();
            echo "=== OTP Request at " . date('Y-m-d H:i:s') . " ===\n";
            echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'unknown') . "\n";
            echo "Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'unknown') . "\n";
            echo "Content Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'unknown') . "\n";
            echo "Raw Input: " . file_get_contents('php://input') . "\n";
            echo "POST Data: " . print_r($_POST, true) . "\n";
            echo "Headers: \n";
            foreach (getallheaders() as $name => $value) {
                echo "  $name: $value\n";
            }
            echo "Route Info: " . print_r(service('router')->getMatchedRoute(), true) . "\n";
            echo "CodeIgniter Version: " . \CodeIgniter\CodeIgniter::CI_VERSION . "\n";
            echo "PHP Version: " . phpversion() . "\n";
            $logOutput = ob_get_clean();
            file_put_contents($logFile, $logOutput . "\n\n", FILE_APPEND);
            
            // Standard logs
            log_message('debug', 'OTP Request Start: ' . file_get_contents('php://input'));
            log_message('debug', 'Request URI: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));
            log_message('debug', 'Request Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
            log_message('debug', 'POST Data: ' . print_r($_POST, true));
            log_message('debug', 'Route Info: ' . print_r(service('router')->getMatchedRoute(), true));
            
            // Get request data
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            
            // Check for JSON decode error
            if (json_last_error() !== JSON_ERROR_NONE && !empty($rawBody)) {
                file_put_contents($logFile, "JSON Decode Error: " . json_last_error_msg() . "\n", FILE_APPEND);
            }
            
            $email = $_POST['email'] ?? $jsonData['email'] ?? null;
            $phone = $_POST['phone'] ?? $jsonData['phone'] ?? null;
            $clientId = $_POST['client_id'] ?? $jsonData['client_id'] ?? null;
            $deviceInfo = $_POST['device_info'] ?? $jsonData['device_info'] ?? 'Unknown Device';
            $method = $_POST['method'] ?? $jsonData['method'] ?? 'email';
        
            log_message('debug', 'OTP Request Email: ' . ($email ?? 'not provided'));
            log_message('debug', 'OTP Request Phone: ' . ($phone ?? 'not provided'));
            log_message('debug', 'OTP Request Client ID: ' . ($clientId ?? 'not provided'));
            
            // Validar datos requeridos
            $errors = [];
            
            if (!empty($email)) {
                // Validación de email
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Invalid email format';
                }
            }
            
            if (!empty($phone)) {
                // Validación básica de teléfono (debe empezar con + y tener entre 8 y 15 dígitos)
                if (!preg_match('/^\+[0-9]{8,15}$/', $phone)) {
                    $errors[] = 'Invalid phone format. Must start with + and have 8-15 digits';
                }
            }
            
            if (empty($email) && empty($phone)) {
                $errors[] = 'Either email or phone is required';
            }
            
            if (empty($clientId)) {
                $errors[] = 'Client ID is required';
            }
            
            if (!empty($errors)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $errors
                ]);
            }

            // Generate OTP
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store OTP
            $otpModel = new UserOtpModel();
            $otpData = [
                'otp' => password_hash($otp, PASSWORD_DEFAULT),
                'email' => $email,
                'phone' => $phone,
                'client_id' => $clientId,
                'device_info' => $deviceInfo,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $otpModel->insert($otpData);
            
            // Send OTP
            if ($method === 'email' && !empty($email)) {
                // TODO: Implement email sending
                log_message('info', 'OTP sent via email to: ' . $email);
            } elseif ($method === 'sms' && !empty($phone)) {
                try {
                    $twilio = new Twilio();
                    $message = "Your OTP is: {$otp}";
                    $twilio->sendSMS($phone, $message);
                    log_message('info', 'OTP sent via SMS to: ' . $phone);
                } catch (\Exception $e) {
                    log_message('error', 'Twilio error: ' . $e->getMessage());
                    return $this->response->setStatusCode(500)->setJSON([
                        'status' => 'error',
                        'message' => 'Failed to send OTP via SMS'
                    ]);
                }
            }
            
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'OTP sent successfully',
                'data' => [
                    'expires_in' => 300 // 5 minutes in seconds
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'OTP Request Error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Internal server error'
            ]);
        }
    }

    /**
     * Verify OTP and generate JWT token
     */
    public function verifyOtp()
    {
        // Get request data
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody, true);
        
        $email = $_POST['email'] ?? $jsonData['email'] ?? null;
        $phone = $_POST['phone'] ?? $jsonData['phone'] ?? null;
        $clientId = $_POST['client_id'] ?? $jsonData['client_id'] ?? null;
        $code = $_POST['code'] ?? $jsonData['code'] ?? null;
        $deviceName = $_POST['device_name'] ?? $jsonData['device_name'] ?? 'Mobile App';
        
        // Validar parámetros
        $errors = [];
        
        if (empty($code)) {
            $errors[] = 'Código OTP es requerido';
        }
        
        if (!empty($email)) {
            // Validación de email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
        } else if (!empty($phone)) {
            // Validación de teléfono
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $errors[] = 'El teléfono debe tener entre 10 y 15 caracteres';
            }
            
            if (empty($clientId) || !is_numeric($clientId)) {
                $errors[] = 'Client ID es requerido y debe ser numérico';
            }
        } else {
            $errors[] = 'Either email or phone is required';
        }
        
        // Si hay errores, responder con 400
        if (!empty($errors)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
        }

        // Buscar usuario
        $userModel = new UserModel();
        
        if (!empty($email)) {
            $user = $userModel->where('email', $email)
                ->where('status', 'active')
                ->first();
        } else {
            $user = $userModel->where('phone', $phone)
                ->where('client_id', $clientId)
                ->where('status', 'active')
                ->first();
        }

        if (!$user) {
            $identifier = !empty($email) ? $email : $phone;
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Usuario no encontrado o inactivo'
            ]);
        }

        // Verificar OTP
        $otpModel = new UserOtpModel();
        $verified = $otpModel->verifyOTP(
            $user['id'],
            $code
        );

        if (!$verified) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Código OTP inválido o expirado'
            ]);
        }

        // Generar token
        $tokenModel = new UserApiTokenModel();
        $token = $tokenModel->createToken(
            $user['id'],
            $deviceName,
            ['*'] // All scopes
        );

        // Preparar datos de usuario
        $userData = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'organization_id' => $user['organization_id']
        ];

        // Responder con token y datos
        return $this->response->setJSON([
            'status' => 'success',
            'user' => $userData,
            'token' => $token['accessToken'],
            'refresh_token' => $token['refreshToken'],
            'expires_at' => $token['expiresAt']
        ]);
    }

    /**
     * Refresh token
     */
    public function refreshToken()
    {
        // Get refresh token
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody, true);
        
        $refreshToken = $_POST['refresh_token'] ?? $jsonData['refresh_token'] ?? null;
        
        if (empty($refreshToken)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status' => 'error',
                'message' => 'Refresh token es requerido'
            ]);
        }

        // Refrescar token
        $tokenModel = new UserApiTokenModel();
        $token = $tokenModel->refreshToken($refreshToken);

        if (!$token) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Refresh token inválido o expirado'
            ]);
        }

        // Responder con nuevo token
        return $this->response->setJSON([
            'status' => 'success',
            'token' => $token['accessToken'],
            'refresh_token' => $token['refreshToken'],
            'expires_at' => $token['expiresAt']
        ]);
    }

    /**
     * Logout (revoke token)
     */
    public function logout()
    {
        // Get token from header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token = '';
        
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
        }

        if (empty($token)) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'No se proporcionó token'
            ]);
        }

        // Revocar token
        $tokenModel = new UserApiTokenModel();
        $tokenModel->revokeToken($token);

        // Responder
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
    
    /**
     * Debug endpoint - used for testing API connectivity and route handling
     */
    public function debug()
    {
        // Write to a special debug log file
        $debugLogFile = WRITEPATH . 'logs/api_debug.log';
        
        // Get request info
        $requestData = [
            'time' => date('Y-m-d H:i:s'),
            'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'headers' => function_exists('getallheaders') ? getallheaders() : [],
            'get' => $_GET,
            'post' => $_POST,
            'input' => file_get_contents('php://input'),
            'server' => $_SERVER,
            'route' => service('router')->getMatchedRoute(),
            'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
            'php_version' => phpversion()
        ];
        
        // Log to file
        file_put_contents(
            $debugLogFile, 
            "=== API Debug at " . date('Y-m-d H:i:s') . " ===\n" . 
            print_r($requestData, true) . "\n\n", 
            FILE_APPEND
        );
        
        // Return debug info
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'API debug info',
            'time' => date('Y-m-d H:i:s'),
            'server_info' => [
                'php_version' => phpversion(),
                'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
                'environment' => ENVIRONMENT
            ],
            'request_info' => [
                'uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'path' => $_SERVER['PATH_INFO'] ?? 'unknown',
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'query_string' => $_SERVER['QUERY_STRING'] ?? '',
                'route' => service('router')->getMatchedRoute()
            ]
        ]);
    }
    
    /**
     * Test OTP without authentication - PUBLIC TESTING ONLY
     */
    public function testOtp()
    {
        // Create a log
        $logFile = WRITEPATH . 'logs/test_otp.log';
        file_put_contents(
            $logFile, 
            "=== Test OTP at " . date('Y-m-d H:i:s') . " ===\n", 
            FILE_APPEND
        );
        
        // Define database path
        $dbPath = WRITEPATH . 'db/cobranzas.db';
        
        try {
            // Check database permissions
            $dbInfo = [
                'exists' => file_exists($dbPath),
                'writable' => is_writable($dbPath),
                'permissions' => substr(sprintf('%o', fileperms($dbPath)), -4),
                'size' => file_exists($dbPath) ? filesize($dbPath) : 0
            ];
            
            file_put_contents($logFile, "Database info: " . json_encode($dbInfo) . "\n", FILE_APPEND);
            
            // Try to create a test user if not exists
            $db = new \SQLite3($dbPath);
            $timestamp = date('Y-m-d H:i:s');
            
            // Create a test user if not exists
            $testUser = 'test_api_user';
            $testEmail = 'test@example.com';
            $testPhone = '+51999309748';
            
            // Check if user exists
            $result = $db->query("SELECT id FROM users WHERE email = '{$testEmail}' LIMIT 1");
            $user = $result->fetchArray(SQLITE3_ASSOC);
            
            $userId = null;
            if (!$user) {
                // Create test user
                $db->exec("INSERT INTO users (name, email, phone, role, status, created_at) 
                          VALUES ('{$testUser}', '{$testEmail}', '{$testPhone}', 'user', 'active', '{$timestamp}')");
                
                $result = $db->query("SELECT last_insert_rowid() as id");
                $userId = $result->fetchArray(SQLITE3_ASSOC)['id'];
                file_put_contents($logFile, "Created test user with ID: {$userId}\n", FILE_APPEND);
            } else {
                $userId = $user['id'];
                file_put_contents($logFile, "Using existing test user with ID: {$userId}\n", FILE_APPEND);
            }
            
            // Generate OTP manually without auth
            $otp = rand(100000, 999999);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            
            // Try to delete old OTPs for this user
            $db->exec("UPDATE user_otp_codes SET used_at = '{$timestamp}' WHERE user_id = {$userId} AND used_at IS NULL");
            
            // Insert new OTP
            $db->exec("INSERT INTO user_otp_codes (user_id, code, device_info, expires_at, created_at, delivery_method, delivery_status) 
                    VALUES ({$userId}, '{$otp}', 'Test Device', '{$expiresAt}', '{$timestamp}', 'email', 'sent')");
            
            // Get the OTP from database to verify it worked
            $result = $db->query("SELECT * FROM user_otp_codes WHERE user_id = {$userId} ORDER BY id DESC LIMIT 1");
            $otpData = $result->fetchArray(SQLITE3_ASSOC);
            
            // Return success
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'OTP test successful',
                'database_info' => $dbInfo,
                'test_user' => [
                    'id' => $userId,
                    'email' => $testEmail,
                    'phone' => $testPhone
                ],
                'otp_data' => [
                    'code' => $otp,
                    'expires_at' => $expiresAt
                ],
                'db_query_result' => $otpData
            ]);
            
        } catch (\Exception $e) {
            file_put_contents($logFile, "Error: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "Trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'database_info' => isset($dbInfo) ? $dbInfo : [
                    'exists' => file_exists($dbPath),
                    'writable' => is_writable($dbPath),
                    'permissions' => substr(sprintf('%o', fileperms($dbPath)), -4),
                    'size' => file_exists($dbPath) ? filesize($dbPath) : 0
                ]
            ]);
        }
    }
}
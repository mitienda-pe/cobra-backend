<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\UserOtpModel;
use App\Models\UserApiTokenModel;
use App\Libraries\Twilio;
use CodeIgniter\API\ResponseTrait;
use CodeIgniter\RESTful\ResourceController;

class AuthController extends ResourceController
{
    use ResponseTrait;

    protected $userModel;
    protected $userOtpModel;
    protected $userApiTokenModel;
    protected $db;
    protected $twilioService;
    protected $developmentMode = false;
    protected $developmentOtp = '123456';
    protected $developmentPhone = '+51 999309748';

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->userOtpModel = new UserOtpModel();
        $this->userApiTokenModel = new UserApiTokenModel();
        $this->db = \Config\Database::connect();
        $this->twilioService = new Twilio();
        
        // Habilitar modo desarrollo si TWILIO_ENABLED está deshabilitado
        $this->developmentMode = getenv('TWILIO_ENABLED') !== 'true';
    }

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
                     ', User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown') . 
                     ', Controller: ' . get_class($this) . 
                     ', Action: ' . ($_SERVER['PATH_INFO'] ?? 'unknown') . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Desactivar session completamente
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Log para depuración
        log_message('debug', 'API Request: ' . ($_SERVER['REQUEST_URI'] ?? 'unknown') . ' - Method: ' . ($_SERVER['REQUEST_METHOD'] ?? 'unknown'));
        log_message('debug', 'Controller: ' . get_class($this) . ' - Action: ' . ($_SERVER['PATH_INFO'] ?? 'unknown'));
    }

    /**
     * Request OTP for login
     */
    public function requestOtp()
    {
        $requestHash = substr(md5(uniqid()), 0, 8);
        log_message('info', "[{$requestHash}] === REQUEST OTP START ===");
        error_log("[{$requestHash}] DEBUG: Method requestOtp called at " . date('Y-m-d H:i:s'));
        
        try {
            // Get request data
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            
            log_message('info', "[{$requestHash}] Raw request body: " . $rawBody);
            log_message('info', "[{$requestHash}] JSON data: " . json_encode($jsonData));
            log_message('info', "[{$requestHash}] POST data: " . json_encode($_POST));
            
            $email = $_POST['email'] ?? $jsonData['email'] ?? null;
            $phone = $_POST['phone'] ?? $jsonData['phone'] ?? null;
            $organizationCode = $_POST['organization_code'] ?? $jsonData['organization_code'] ?? null;
            $deviceInfo = $_POST['device_info'] ?? $jsonData['device_info'] ?? 'Unknown Device';
            
            // Normalize phone number format
            if (!empty($phone)) {
                // Remove any spaces and add the standard format
                $cleanPhone = str_replace(' ', '', $phone);
                log_message('info', "[{$requestHash}] Normalizing phone: '{$phone}' -> cleaned: '{$cleanPhone}'");
                if (preg_match('/^\+51(\d{9})$/', $cleanPhone, $matches)) {
                    $phone = '+51 ' . $matches[1];
                    log_message('info', "[{$requestHash}] Normalized phone: '{$phone}'");
                } else {
                    log_message('info', "[{$requestHash}] Phone does not match normalization pattern: '{$cleanPhone}'");
                }
            }
            
            log_message('info', "[{$requestHash}] Parsed - Phone: {$phone}, Email: {$email}, OrgCode: {$organizationCode}");
            
            // Validate required fields
            if (empty($phone) && empty($email)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Either phone or email is required'
                ]);
            }

            // If phone is provided, check if user exists and get organizations
            if (!empty($phone)) {
                log_message('info', "[{$requestHash}] Searching for user with phone: {$phone}");
                $users = $this->userModel->getOrganizationsByPhone($phone);
                
                log_message('info', "[{$requestHash}] Found users: " . json_encode($users));
                
                if (empty($users)) {
                    log_message('error', "[{$requestHash}] No user found with phone: {$phone}");
                    return $this->response->setStatusCode(404)->setJSON([
                        'status' => 'error',
                        'message' => 'No user found with this phone number'
                    ]);
                }
                
                // If user has multiple organizations and no organization_code provided
                if (count($users) > 1 && empty($organizationCode)) {
                    $organizations = array_map(function($user) {
                        return [
                            'code' => $user['org_code'],
                            'name' => $user['org_name']
                        ];
                    }, $users);
                    
                    return $this->response->setJSON([
                        'status' => 'multiple_organizations',
                        'message' => 'Please specify which organization you want to access',
                        'data' => [
                            'organizations' => $organizations
                        ]
                    ]);
                }
                
                // If organization_code is provided, validate it
                if (!empty($organizationCode)) {
                    $validOrg = false;
                    foreach ($users as $user) {
                        if ($user['org_code'] === $organizationCode) {
                            $validOrg = true;
                            break;
                        }
                    }
                    
                    if (!$validOrg) {
                        return $this->response->setStatusCode(400)->setJSON([
                            'status' => 'error',
                            'message' => 'Invalid organization code'
                        ]);
                    }
                } else if (count($users) == 1) {
                    // If user has only one organization, use it automatically
                    $organizationCode = $users[0]['org_code'];
                    log_message('info', "[{$requestHash}] User has single organization, using: {$organizationCode}");
                }
            }

            // Generate OTP - Use hardcoded OTP for specific test phone (works in any mode)
            log_message('info', "[{$requestHash}] Comparing phone '{$phone}' with development phone '{$this->developmentPhone}'");
            if ($phone === $this->developmentPhone || $phone === str_replace(' ', '', $this->developmentPhone)) {
                $otp = $this->developmentOtp;
                log_message('info', "[{$requestHash}] Test mode: Using hardcoded OTP {$otp} for phone {$phone}");
            } else {
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                log_message('info', "[{$requestHash}] Generated random OTP: {$otp} for phone {$phone}");
            }
            
            // Get user ID for OTP storage
            $userId = null;
            error_log("[{$requestHash}] DEBUG: About to search for user ID. Phone: {$phone}, Users count: " . count($users ?? []));
            if (!empty($phone) && !empty($users)) {
                error_log("[{$requestHash}] DEBUG: Searching for user with organizationCode: {$organizationCode}");
                // Find the user for the selected organization
                foreach ($users as $user) {
                    error_log("[{$requestHash}] DEBUG: Checking user: " . json_encode($user));
                    if (empty($organizationCode) || $user['org_code'] === $organizationCode) {
                        $userId = $user['id'];
                        error_log("[{$requestHash}] DEBUG: Found user ID: {$userId} for user: {$user['name']}");
                        log_message('info', "[{$requestHash}] Using user ID: {$userId} for OTP storage (from user: {$user['name']})");
                        break;
                    }
                }
                
                if (!$userId) {
                    error_log("[{$requestHash}] DEBUG: Could not find user ID for organization: {$organizationCode}");
                    log_message('error', "[{$requestHash}] Could not find user ID for organization: {$organizationCode}");
                }
            } else {
                error_log("[{$requestHash}] DEBUG: Phone empty or users empty. Phone: {$phone}, Users: " . json_encode($users));
            }
            
            // Validate user ID before storing OTP
            error_log("[{$requestHash}] DEBUG: About to validate userId: " . ($userId ?? 'NULL'));
            if (!$userId) {
                error_log("[{$requestHash}] DEBUG: User ID is null, returning error");
                log_message('error', "[{$requestHash}] Cannot store OTP: user_id is null");
                return $this->response->setStatusCode(500)->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to identify user for OTP storage'
                ]);
            }
            error_log("[{$requestHash}] DEBUG: User ID validation passed: {$userId}");
            
            // Store OTP in database
            $otpData = [
                'user_id' => $userId,
                'phone' => $phone,
                'email' => $email,
                'code' => $otp,
                'organization_code' => $organizationCode,
                'device_info' => $deviceInfo,
                'expires_at' => date('Y-m-d H:i:s', strtotime('+5 minutes')),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            log_message('info', "[{$requestHash}] Storing OTP data: " . json_encode($otpData));
            $insertResult = $this->userOtpModel->insert($otpData);
            log_message('info', "[{$requestHash}] OTP insert result: " . ($insertResult ? 'SUCCESS' : 'FAILED'));
            
            if (!$insertResult) {
                log_message('error', "[{$requestHash}] Failed to insert OTP data");
                return $this->response->setStatusCode(500)->setJSON([
                    'status' => 'error',
                    'message' => 'Failed to store OTP'
                ]);
            }
            
            // Send OTP via SMS if phone is provided (skip for test phone)
            if (!empty($phone) && $phone !== $this->developmentPhone && $phone !== str_replace(' ', '', $this->developmentPhone)) {
                try {
                    $message = "Your verification code is: {$otp}. Valid for 5 minutes.";
                    $result = $this->twilioService->sendSms($phone, $message);
                    
                    if (!$result['success']) {
                        log_message('error', "Failed to send OTP via SMS: " . ($result['message'] ?? 'Unknown error'));
                        return $this->response->setStatusCode(500)->setJSON([
                            'status' => 'error',
                            'message' => 'Failed to send OTP via SMS',
                            'details' => $result['message'] ?? 'Unknown error'
                        ]);
                    }
                    
                    // Update delivery status
                    $this->userOtpModel->updateDeliveryStatus(
                        $phone,
                        $otp,
                        'sent',
                        'Twilio SID: ' . ($result['sid'] ?? 'unknown')
                    );
                    
                    log_message('info', "[{$requestHash}] OTP sent successfully to {$phone}");
                } catch (\Exception $e) {
                    log_message('error', "[{$requestHash}] Twilio error: " . $e->getMessage());
                    return $this->response->setStatusCode(500)->setJSON([
                        'status' => 'error',
                        'message' => 'Failed to send OTP via SMS',
                        'details' => $e->getMessage()
                    ]);
                }
            } else {
                log_message('info', "[{$requestHash}] Skipping SMS for test phone: {$phone}");
            }
            
            // Send OTP via email if email is provided
            if (!empty($email)) {
                // TODO: Implement email sending
                log_message('info', "Email OTP functionality not implemented yet");
            }
            
            log_message('info', "[{$requestHash}] === REQUEST OTP SUCCESS ===");
            return $this->response->setJSON([
                'status' => 'success',
                'message' => 'OTP sent successfully',
                'data' => [
                    'email' => $email,
                    'phone' => $phone,
                    'organization_code' => $organizationCode,
                    'device_info' => $deviceInfo,
                    'expires_in' => '5 minutes',
                    'request_hash' => $requestHash
                ]
            ]);
            
        } catch (\Exception $e) {
            log_message('error', "[{$requestHash}] Error in requestOtp: " . $e->getMessage());
            log_message('error', "[{$requestHash}] Stack trace: " . $e->getTraceAsString());
            
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Internal server error',
                'details' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verify OTP and generate JWT token
     */
    public function verifyOtp()
    {
        $verifyHash = substr(md5(uniqid()), 0, 8);
        log_message('info', "[{$verifyHash}] === VERIFY OTP START ===");
        
        try {
            // Get request data
            $rawBody = file_get_contents('php://input');
            $jsonData = json_decode($rawBody, true);
            
            log_message('info', "[{$verifyHash}] Verify OTP raw body: " . $rawBody);
            log_message('info', "[{$verifyHash}] Verify OTP JSON data: " . json_encode($jsonData));
            
            $email = $_POST['email'] ?? $jsonData['email'] ?? null;
            $phone = $_POST['phone'] ?? $jsonData['phone'] ?? null;
            $code = $_POST['code'] ?? $jsonData['code'] ?? null;
            $organizationCode = $_POST['organization_code'] ?? $jsonData['organization_code'] ?? null;
            $deviceInfo = $_POST['device_info'] ?? $jsonData['device_info'] ?? 'Unknown Device';
            
            // Normalize phone number format
            if (!empty($phone)) {
                // Remove any spaces and add the standard format
                $cleanPhone = str_replace(' ', '', $phone);
                log_message('info', "[{$verifyHash}] Normalizing phone: '{$phone}' -> cleaned: '{$cleanPhone}'");
                if (preg_match('/^\+51(\d{9})$/', $cleanPhone, $matches)) {
                    $phone = '+51 ' . $matches[1];
                    log_message('info', "[{$verifyHash}] Normalized phone: '{$phone}'");
                } else {
                    log_message('info', "[{$verifyHash}] Phone does not match normalization pattern: '{$cleanPhone}'");
                }
            }
            
            log_message('info', "[{$verifyHash}] Verify OTP - Phone: {$phone}, Email: {$email}, Code: {$code}, OrgCode: {$organizationCode}");
            
            // Validate required fields
            if (empty($code)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'OTP code is required'
                ]);
            }

            if (empty($phone) && empty($email)) {
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Either phone or email is required'
                ]);
            }

            // Get user data
            if (!empty($phone)) {
                log_message('info', "Getting user data for phone: {$phone}");
                $users = $this->userModel->getOrganizationsByPhone($phone);
                
                log_message('info', 'Users found for verification: ' . json_encode($users));
                
                if (empty($users)) {
                    log_message('error', "No users found for phone: {$phone}");
                    return $this->response->setStatusCode(404)->setJSON([
                        'status' => 'error',
                        'message' => 'No user found with this phone number'
                    ]);
                }
                
                // If user has multiple organizations, organization_code is required
                if (count($users) > 1 && empty($organizationCode)) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status' => 'error',
                        'message' => 'Organization code is required for users with multiple organizations'
                    ]);
                } else if (count($users) == 1 && empty($organizationCode)) {
                    // If user has only one organization, use it automatically
                    $organizationCode = $users[0]['org_code'];
                    log_message('info', "[{$verifyHash}] User has single organization, using: {$organizationCode}");
                }
                
                // Get user data for the specified organization
                $userData = null;
                foreach ($users as $user) {
                    if (empty($organizationCode) || $user['org_code'] === $organizationCode) {
                        $userData = $user;
                        break;
                    }
                }
                
                if (!$userData) {
                    return $this->response->setStatusCode(400)->setJSON([
                        'status' => 'error',
                        'message' => 'Invalid organization code'
                    ]);
                }
            }

            // Verify OTP
            log_message('info', "[{$verifyHash}] Verifying OTP with params - Phone: {$phone}, Email: {$email}, Code: {$code}, OrgCode: {$organizationCode}");
            $otpData = $this->userOtpModel->verifyOTP($phone, $email, $code, $organizationCode);
            
            if (!$otpData) {
                log_message('error', "[{$verifyHash}] OTP verification failed - Invalid or expired OTP code");
                return $this->response->setStatusCode(400)->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid or expired OTP code'
                ]);
            }
            
            log_message('info', "[{$verifyHash}] OTP verification successful");
            
            // Generate API token
            $token = bin2hex(random_bytes(32)); // 64 caracteres hexadecimales
            
            // Store token in database using direct query
            $sql = "INSERT INTO user_api_tokens (user_id, token, name, expires_at, created_at) VALUES (?, ?, ?, ?, ?)";
            $this->db->query($sql, [
                $userData['id'],
                $token,
                'OTP Login', // Nombre descriptivo para el token
                date('Y-m-d H:i:s', strtotime('+30 days')),
                date('Y-m-d H:i:s')
            ]);
            
            // Return success response with token and user data
            $response = [
                'status' => 'success',
                'message' => 'OTP verified successfully',
                'data' => [
                    'token' => $token,
                    'expires_in' => '30 days',
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $userData['id'],
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'phone' => $userData['phone'],
                        'role' => $userData['role'],
                        'organization' => [
                            'id' => $userData['org_id'],
                            'name' => $userData['org_name'],
                            'code' => $userData['org_code']
                        ]
                    ]
                ]
            ];
            
            log_message('info', "[{$verifyHash}] OTP verification successful, returning response: " . json_encode($response));
            
            return $this->response->setJSON($response);
            
        } catch (\Exception $e) {
            log_message('error', "[{$verifyHash}] Error in verifyOtp: " . $e->getMessage());
            log_message('error', "[{$verifyHash}] Stack trace: " . $e->getTraceAsString());
            
            return $this->response->setStatusCode(500)->setJSON([
                'status' => 'error',
                'message' => 'Internal server error',
                'error' => $e->getMessage()
            ]);
        }
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
        $debugHash = substr(md5(uniqid()), 0, 8);
        log_message('info', "[{$debugHash}] === DEBUG ENDPOINT CALLED ===");
        
        // Test phone normalization
        $testPhone = '+51999309748';
        $userModel = new UserModel();
        $users = $userModel->getOrganizationsByPhone($testPhone);
        
        log_message('info', "[{$debugHash}] Test phone: {$testPhone}");
        log_message('info', "[{$debugHash}] Users found: " . count($users));
        log_message('info', "[{$debugHash}] Users data: " . json_encode($users));
        
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'API is working correctly',
            'data' => [
                'request_method' => $this->request->getMethod(),
                'request_headers' => $this->request->headers(),
                'request_body' => $this->request->getBody(),
                'server_time' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'codeigniter_version' => \CodeIgniter\CodeIgniter::CI_VERSION,
                'debug_hash' => $debugHash,
                'test_phone' => $testPhone,
                'users_found' => count($users),
                'users_data' => $users,
                'development_phone' => $this->developmentPhone
            ]
        ]);
    }
    
    /**
     * Test OTP without authentication - PUBLIC TESTING ONLY
     */
    public function testOtp()
    {
        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Test OTP endpoint',
            'data' => [
                'test_otp' => '123456',
                'expires_in' => '5 minutes'
            ]
        ]);
    }

    /**
     * Get authenticated user information
     * Used by mobile app
     */
    public function me()
    {
        // Get user from request (set by the apiAuth filter)
        $userData = $this->request->user ?? null;
        
        if (!$userData) {
            return $this->failUnauthorized('Not authenticated');
        }
        
        // Remove sensitive information
        unset($userData['password']);
        unset($userData['remember_token']);
        unset($userData['reset_token']);
        unset($userData['reset_token_expires_at']);
        
        // Get user's organization
        $orgModel = new \App\Models\OrganizationModel();
        $organization = null;
        
        if (!empty($userData['organization_id'])) {
            $organization = $orgModel->find($userData['organization_id']);
        }
        
        // Format the response
        $response = [
            'user' => $userData,
            'organization' => $organization
        ];
        
        return $this->respond([
            'success' => true,
            'data' => $response
        ]);
    }

    /**
     * Get user profile information
     * This endpoint can be used by the mobile app to get user information
     * after successful OTP verification
     */
    public function profile()
    {
        // Get token from Authorization header
        $authHeader = $this->request->getHeaderLine('Authorization');
        $token = null;
        
        if (!empty($authHeader) && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        // If no token in header, try from query string
        if (!$token) {
            $token = $this->request->getGet('token');
        }
        
        // If still no token, check if we have a session token
        if (!$token && session()->has('token')) {
            $token = session()->get('token');
        }
        
        if (!$token) {
            return $this->respond([
                'success' => false,
                'message' => 'No authentication token provided',
                'data' => null
            ]);
        }
        
        // Validate token
        $tokenModel = new UserApiTokenModel();
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            return $this->respond([
                'success' => false,
                'message' => 'Invalid token',
                'data' => null
            ]);
        }
        
        // Get user data
        $userModel = new UserModel();
        $userData = $userModel->find($tokenData['user_id']);
        
        if (!$userData) {
            return $this->respond([
                'success' => false,
                'message' => 'User not found',
                'data' => null
            ]);
        }
        
        // Remove sensitive information
        unset($userData['password']);
        unset($userData['remember_token']);
        unset($userData['reset_token']);
        unset($userData['reset_token_expires_at']);
        
        // Get user's organization
        $orgModel = new \App\Models\OrganizationModel();
        $organization = null;
        
        if (!empty($userData['organization_id'])) {
            $organization = $orgModel->find($userData['organization_id']);
        }
        
        // Format the response
        $response = [
            'user' => $userData,
            'organization' => $organization,
            'token' => $token
        ];
        
        return $this->respond([
            'success' => true,
            'message' => 'User profile retrieved successfully',
            'data' => $response
        ]);
    }
}
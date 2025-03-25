<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\UserOtpModel;
use App\Models\UserApiTokenModel;
use App\Libraries\Twilio;

class AuthController extends ResourceController
{
    protected $format = 'json';

    /**
     * Constructor - establece headers CORS y maneja OPTIONS
     */
    public function __construct()
    {
        // Desactivar session completamente
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Desactivar CSRF
        $security = \Config\Services::security();
        try {
            $reflectionClass = new \ReflectionClass($security);
            $property = $reflectionClass->getProperty('CSRFVerify');
            if ($property) {
                $property->setAccessible(true);
                $property->setValue($security, false);
            }
        } catch (\Exception $e) {
            log_message('error', 'Error al desactivar CSRF: ' . $e->getMessage());
        }
        
        // Log para depuración
        log_message('debug', 'API Request: ' . $_SERVER['REQUEST_URI'] . ' - Method: ' . $_SERVER['REQUEST_METHOD']);
        
        // Set CORS headers manually
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Content-Type: application/json');
        
        // Si es OPTIONS, responde inmediatamente con 200
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit;
        }
    }
    
    /**
     * Una función especial para responder en JSON y terminar la ejecución
     */
    private function jsonResponse($data, $code = 200) 
    {
        header('HTTP/1.1 ' . $code . ' ' . $this->getStatusMessage($code));
        echo json_encode($data);
        exit;
    }
    
    /**
     * Obtiene mensaje para cada código HTTP
     */
    private function getStatusMessage($code)
    {
        $statusMessages = [
            200 => 'OK',
            201 => 'Created',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            404 => 'Not Found',
            500 => 'Internal Server Error'
        ];
        
        return $statusMessages[$code] ?? 'Unknown Status';
    }

    /**
     * Request OTP for login
     */
    public function requestOtp()
    {
        // Log request for debugging
        log_message('debug', 'OTP Request Start: ' . file_get_contents('php://input'));
        log_message('debug', 'Request URI: ' . $_SERVER['REQUEST_URI']);
        log_message('debug', 'Request Method: ' . $_SERVER['REQUEST_METHOD']);
        log_message('debug', 'POST Data: ' . print_r($_POST, true));
        log_message('debug', 'Route Info: ' . print_r(service('router')->getMatchedRoute(), true));
        
        // Get request data
        $rawBody = file_get_contents('php://input');
        $jsonData = json_decode($rawBody, true);
        
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
                $errors['email'] = 'Email inválido';
            }
        } else if (!empty($phone)) {
            // Validación de teléfono
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $errors['phone'] = 'El teléfono debe tener entre 10 y 15 caracteres';
            }
            
            if (empty($clientId) || !is_numeric($clientId)) {
                $errors['client_id'] = 'Client ID es requerido y debe ser numérico';
            }
        } else {
            $errors['identifier'] = 'Email o teléfono es requerido';
        }
        
        // Validar método
        if (!in_array($method, ['email', 'sms'])) {
            $errors['method'] = 'Método debe ser email o sms';
        }
        
        // Si hay errores, responder con 400
        if (!empty($errors)) {
            log_message('error', 'OTP Validation Error: ' . json_encode($errors));
            $this->jsonResponse(['success' => false, 'errors' => $errors], 400);
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
            log_message('error', 'OTP User Not Found: ' . $identifier);
            $this->jsonResponse([
                'success' => false, 
                'message' => 'Usuario no encontrado o inactivo'
            ], 404);
        }

        // Para SMS, verificar que el usuario tenga teléfono
        if ($method === 'sms' && empty($user['phone'])) {
            log_message('error', 'OTP No Phone: User ID ' . $user['id']);
            $this->jsonResponse([
                'success' => false,
                'message' => 'Usuario no tiene número telefónico registrado'
            ], 400);
        }

        // Generar OTP
        $otpModel = new UserOtpModel();
        $otpData = $otpModel->generateOTP(
            $user['id'],
            $deviceInfo,
            $method
        );

        if (!$otpData) {
            log_message('error', 'OTP Generation Error: User ID ' . $user['id']);
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al generar OTP'
            ], 500);
        }

        log_message('debug', 'OTP Generated: ' . $otpData['code'] . ' for User ID ' . $user['id']);

        // Enviar OTP por el método seleccionado
        if ($method === 'sms') {
            $twilio = new Twilio();
            $message = "Your OTP code is: {$otpData['code']}";
            $result = $twilio->sendSMS($user['phone'], $message);

            log_message('debug', 'OTP SMS Result: ' . ($result ? 'Success' : 'Failed'));
            if (!$result) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Error al enviar OTP por SMS'
                ], 500);
            }
        } else {
            // For testing, log the OTP code (remove in production)
            log_message('debug', 'OTP Email would be sent: ' . $otpData['code']);
            // TODO: Implement email sending
        }

        // Preparar respuesta
        $response = [
            'success' => true,
            'message' => 'OTP enviado exitosamente',
            'data' => [
                'expires_at' => $otpData['expires_at'],
                'method' => $method
            ]
        ];
        
        // Solo en desarrollo, incluir el código OTP
        if (ENVIRONMENT === 'development') {
            $response['data']['otp'] = $otpData['code'];
        }

        log_message('debug', 'OTP Response: ' . json_encode($response));
        $this->jsonResponse($response);
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
            $errors['code'] = 'Código OTP es requerido';
        }
        
        if (!empty($email)) {
            // Validación de email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Email inválido';
            }
        } else if (!empty($phone)) {
            // Validación de teléfono
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                $errors['phone'] = 'El teléfono debe tener entre 10 y 15 caracteres';
            }
            
            if (empty($clientId) || !is_numeric($clientId)) {
                $errors['client_id'] = 'Client ID es requerido y debe ser numérico';
            }
        } else {
            $errors['identifier'] = 'Email o teléfono es requerido';
        }
        
        // Si hay errores, responder con 400
        if (!empty($errors)) {
            $this->jsonResponse(['success' => false, 'errors' => $errors], 400);
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
            $this->jsonResponse([
                'success' => false, 
                'message' => 'Usuario no encontrado o inactivo'
            ], 404);
        }

        // Verificar OTP
        $otpModel = new UserOtpModel();
        $verified = $otpModel->verifyOTP(
            $user['id'],
            $code
        );

        if (!$verified) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Código OTP inválido o expirado'
            ], 401);
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
        $this->jsonResponse([
            'success' => true,
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
            $this->jsonResponse([
                'success' => false,
                'message' => 'Refresh token es requerido'
            ], 400);
        }

        // Refrescar token
        $tokenModel = new UserApiTokenModel();
        $token = $tokenModel->refreshToken($refreshToken);

        if (!$token) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Refresh token inválido o expirado'
            ], 401);
        }

        // Responder con nuevo token
        $this->jsonResponse([
            'success' => true,
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
            $this->jsonResponse([
                'success' => false,
                'message' => 'No se proporcionó token'
            ], 401);
        }

        // Revocar token
        $tokenModel = new UserApiTokenModel();
        $tokenModel->revokeToken($token);

        // Responder
        $this->jsonResponse([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }
}
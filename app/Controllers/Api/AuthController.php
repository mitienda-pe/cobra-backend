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

    public function __construct()
    {
        // Ensure response is JSON
        $this->response->setContentType('application/json');
    }

    /**
     * Request OTP for login
     */
    public function requestOtp()
    {
        // Log request for debugging
        log_message('debug', 'OTP Request: ' . json_encode($this->request->getJSON()));
        
        $rules = [
            'email'        => 'required|valid_email',
            'device_info'  => 'permit_empty',
            'method'       => 'permit_empty|in_list[email,sms]'
        ];

        if (!$this->validate($rules)) {
            log_message('error', 'OTP Validation Error: ' . json_encode($this->validator->getErrors()));
            return $this->fail($this->validator->getErrors(), 400);
        }

        $userModel = new UserModel();
        $user = $userModel->where('email', $this->request->getVar('email'))
            ->where('status', 'active')
            ->first();

        if (!$user) {
            log_message('error', 'OTP User Not Found: ' . $this->request->getVar('email'));
            return $this->failNotFound('User not found or inactive');
        }

        // Determine delivery method
        $method = $this->request->getVar('method') ?? 'email';
        log_message('debug', 'OTP Delivery Method: ' . $method);

        // For SMS method, check if user has a phone number
        if ($method === 'sms') {
            if (empty($user['phone'])) {
                log_message('error', 'OTP No Phone: User ID ' . $user['id']);
                return $this->fail('User does not have a registered phone number for OTP authentication', 400);
            }
        }

        // Get device info
        $deviceInfo = $this->request->getVar('device_info') ?? 'Unknown Device';
        log_message('debug', 'OTP Device Info: ' . $deviceInfo);

        $otpModel = new UserOtpModel();
        $otpData = $otpModel->generateOTP(
            $user['id'],
            $deviceInfo,
            $method
        );

        if (!$otpData) {
            log_message('error', 'OTP Generation Error: User ID ' . $user['id']);
            return $this->fail('Error generating OTP', 500);
        }

        log_message('debug', 'OTP Generated: ' . $otpData['code'] . ' for User ID ' . $user['id']);

        // Send OTP via selected method
        if ($method === 'sms') {
            $twilio = new Twilio();
            $message = "Your OTP code is: {$otpData['code']}";
            $result = $twilio->sendSMS($user['phone'], $message);

            log_message('debug', 'OTP SMS Result: ' . ($result ? 'Success' : 'Failed'));
            if (!$result) {
                return $this->fail('Error sending OTP via SMS', 500);
            }
        } else {
            // For testing, log the OTP code (remove in production)
            log_message('debug', 'OTP Email would be sent: ' . $otpData['code']);
            // TODO: Implement email sending
        }

        // For easier testing, include the OTP code in development
        $response = [
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'expires_at' => $otpData['expires_at'],
                'method' => $method
            ]
        ];
        
        // Only in development mode, return the OTP code
        if (ENVIRONMENT === 'development') {
            $response['data']['otp'] = $otpData['code'];
        }

        log_message('debug', 'OTP Response: ' . json_encode($response));
        return $this->respond($response);
    }

    /**
     * Verify OTP and generate JWT token
     */
    public function verifyOtp()
    {
        $rules = [
            'email' => 'required',  
            'code' => 'required',
            'device_name' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 400);
        }

        $userModel = new UserModel();
        $identifier = $this->request->getVar('email');
        
        // Check if identifier is a phone number or email
        $isPhone = preg_match('/^\+?[1-9]\d{1,14}$/', $identifier);
        
        $query = $userModel->where('status', 'active');
        if ($isPhone) {
            $query->where('phone', $identifier);
        } else {
            $query->where('email', $identifier);
        }
        
        $user = $query->first();

        if (!$user) {
            return $this->failNotFound('User not found or inactive');
        }

        $otpModel = new UserOtpModel();
        $verified = $otpModel->verifyOTP(
            $user['id'],
            $this->request->getVar('code')
        );

        if (!$verified) {
            return $this->failUnauthorized('Invalid or expired OTP code');
        }

        // Generate API token
        $tokenModel = new UserApiTokenModel();
        $deviceName = $this->request->getVar('device_name') ?? 'Mobile App';

        $token = $tokenModel->createToken(
            $user['id'],
            $deviceName,
            ['*'] // All scopes
        );

        // Prepare user data to return
        $userData = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'organization_id' => $user['organization_id']
        ];

        return $this->respond([
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
        $rules = [
            'refresh_token' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->fail($this->validator->getErrors(), 400);
        }

        $tokenModel = new UserApiTokenModel();
        $token = $tokenModel->refreshToken($this->request->getVar('refresh_token'));

        if (!$token) {
            return $this->failUnauthorized('Invalid or expired refresh token');
        }

        return $this->respond([
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
        $token = $this->request->getHeaderLine('Authorization');

        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }

        if (empty($token)) {
            return $this->failUnauthorized('No token provided');
        }

        $tokenModel = new UserApiTokenModel();
        $tokenModel->revokeToken($token);

        return $this->respondNoContent();
    }
}
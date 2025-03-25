<?php

namespace App\Controllers\Api;

use App\Libraries\Auth;
use App\Libraries\ApiResponse;
use App\Libraries\TwilioService;

class AuthController extends BaseApiController
{
    use ApiResponse;

    protected $auth;
    protected $twilio;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->twilio = new TwilioService();
    }

    /**
     * Request OTP for login
     */
    public function requestOtp()
    {
        log_message('info', '[AuthController::requestOtp] Starting OTP request');
        log_message('debug', '[AuthController::requestOtp] Request body: ' . json_encode($this->request->getJSON()));

        // Validate request
        if (!$this->validate([
            'phone' => 'required|min_length[10]|max_length[15]',
            'method' => 'required|in_list[sms,email]',
            'device_info' => 'required',
            'client_id' => 'required|numeric'
        ])) {
            log_message('error', '[AuthController::requestOtp] Validation failed: ' . json_encode($this->validator->getErrors()));
            return $this->failValidation($this->validator->getErrors());
        }

        try {
            $data = $this->request->getJSON();
            
            // Look up user by phone and client_id
            $userModel = new \App\Models\UserModel();
            $user = $userModel->where([
                'phone' => $data->phone,
                'client_id' => $data->client_id,
                'status' => 'active'
            ])->first();
            
            if (!$user) {
                log_message('error', '[AuthController::requestOtp] User not found for phone: ' . $data->phone . ' and client_id: ' . $data->client_id);
                return $this->failUnauthorized('User not found or inactive');
            }

            // Generate OTP
            $otp = rand(100000, 999999);
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            // Save OTP
            $otpModel = new \App\Models\OtpModel();
            $otpModel->insert([
                'user_id' => $user['id'],
                'otp' => $otp,
                'method' => $data->method,
                'device_info' => $data->device_info,
                'expires_at' => $expires
            ]);

            // Send OTP
            if ($data->method === 'sms') {
                $message = "Your OTP is: {$otp}. Valid for 5 minutes.";
                $this->twilio->sendSms($data->phone, $message);
            } else {
                // TODO: Implement email sending
                log_message('info', '[AuthController::requestOtp] Email OTP not implemented yet');
            }

            log_message('info', '[AuthController::requestOtp] OTP sent successfully to: ' . $data->phone);
            return $this->successResponse(null, 'OTP sent successfully');

        } catch (\Exception $e) {
            log_message('error', '[AuthController::requestOtp] Error: ' . $e->getMessage());
            return $this->errorResponse('Error sending OTP', 500);
        }
    }

    /**
     * Verify OTP and generate JWT token
     */
    public function verifyOtp()
    {
        // Validate request
        if (!$this->validate([
            'phone' => 'required|min_length[10]|max_length[15]',
            'otp' => 'required|exact_length[6]',
            'device_info' => 'required',
            'client_id' => 'required|numeric'
        ])) {
            return $this->failValidation($this->validator->getErrors());
        }

        try {
            $data = $this->request->getJSON();
            
            // Look up user
            $userModel = new \App\Models\UserModel();
            $user = $userModel->where([
                'phone' => $data->phone,
                'client_id' => $data->client_id,
                'status' => 'active'
            ])->first();

            if (!$user) {
                return $this->failUnauthorized('User not found or inactive');
            }

            // Verify OTP
            $otpModel = new \App\Models\OtpModel();
            $otp = $otpModel->where([
                'user_id' => $user['id'],
                'otp' => $data->otp,
                'verified' => 0,
                'device_info' => $data->device_info
            ])->orderBy('created_at', 'DESC')->first();

            if (!$otp || strtotime($otp['expires_at']) < time()) {
                return $this->failUnauthorized('Invalid or expired OTP');
            }

            // Mark OTP as verified
            $otpModel->update($otp['id'], ['verified' => 1]);

            // Generate JWT token
            $token = $this->auth->generateToken($user['id']);

            return $this->successResponse([
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'phone' => $user['phone'],
                    'client_id' => $user['client_id']
                ]
            ], 'Login successful');

        } catch (\Exception $e) {
            log_message('error', '[AuthController::verifyOtp] Error: ' . $e->getMessage());
            return $this->errorResponse('Error verifying OTP', 500);
        }
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken()
    {
        $token = $this->request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            return $this->failUnauthorized('Token not provided');
        }

        $newToken = $this->auth->refreshToken($token);
        if (!$newToken) {
            return $this->failUnauthorized('Invalid token');
        }

        return $this->successResponse(['token' => $newToken], 'Token refreshed successfully');
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
            return $this->errorResponse('No token provided', 401);
        }

        $tokenModel = new \App\Models\UserApiTokenModel();
        $tokenModel->revokeToken($token);

        return $this->successResponse(null, 'Logged out successfully');
    }
}

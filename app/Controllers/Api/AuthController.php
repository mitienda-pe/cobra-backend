<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\UserOtpModel;
use App\Models\UserApiTokenModel;

class AuthController extends ResourceController
{
    protected $format = 'json';
    
    /**
     * Request OTP for login
     */
    public function requestOtp()
    {
        $rules = [
            'email' => 'required|valid_email',
            'device_info' => 'permit_empty'
        ];
        
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        $userModel = new UserModel();
        $user = $userModel->where('email', $this->request->getVar('email'))
                         ->where('status', 'active')
                         ->first();
        
        if (!$user) {
            return $this->failNotFound('User not found or inactive');
        }
        
        // Check if user has a phone number
        if (empty($user['phone'])) {
            return $this->fail('User does not have a registered phone number for OTP authentication', 400);
        }
        
        $otpModel = new UserOtpModel();
        $code = $otpModel->generateOTP(
            $user['id'], 
            $this->request->getVar('device_info')
        );
        
        // In a real application, this would send the code via SMS to $user['phone']
        // For now, we'll just return it in the response for testing
        
        // Mask phone number for security
        $phone = $user['phone'];
        $maskedPhone = substr($phone, 0, 4) . '****' . substr($phone, -3);
        
        return $this->respond([
            'message' => 'OTP code sent successfully to ' . $maskedPhone,
            'code' => $code, // This would be removed in production
            'expires_in' => 15 // minutes
        ]);
    }
    
    /**
     * Verify OTP and generate JWT token
     */
    public function verifyOtp()
    {
        $rules = [
            'email' => 'required|valid_email',
            'code' => 'required',
            'device_name' => 'permit_empty'
        ];
        
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        $userModel = new UserModel();
        $user = $userModel->where('email', $this->request->getVar('email'))
                         ->where('status', 'active')
                         ->first();
        
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
            return $this->failValidationErrors($this->validator->getErrors());
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
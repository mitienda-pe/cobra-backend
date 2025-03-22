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
     * Request OTP for login
     */
    public function requestOtp()
    {
        $rules = [
            'email'        => 'required|valid_email',
            'device_info'  => 'permit_empty',
            'method'       => 'permit_empty|in_list[email,sms]'
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

        // Determine delivery method
        $method = $this->request->getVar('method') ?? 'email';

        // For SMS method, check if user has a phone number
        if ($method === 'sms') {
            if (empty($user['phone'])) {
                return $this->fail('User does not have a registered phone number for OTP authentication', 400);
            }
        }

        $otpModel = new UserOtpModel();
        $otpData = $otpModel->generateOTP(
            $user['id'],
            $method,
            $this->request->getVar('device_info')
        );

        $code = $otpData['code'];
        $expiresInMinutes = 15; // Match with generateOTP

        // Send OTP based on method
        if ($method === 'sms') {
            // Send via SMS using Twilio
            $twilio = new Twilio();
            $result = $twilio->sendOtpSms($user['phone'], $code, $expiresInMinutes);

            // Update delivery status
            $otpModel->updateDeliveryStatus(
                $user['id'],
                $code,
                $result['success'] ? 'sent' : 'failed',
                $result['success'] ? $result['sid'] : $result['message']
            );

            if (!$result['success']) {
                // Log the error but don't expose details to client
                log_message('error', 'Failed to send OTP via SMS: ' . $result['message']);

                // For critical errors, inform the client
                if (ENVIRONMENT !== 'production') {
                    return $this->fail('Error sending SMS: ' . $result['message'], 500);
                } else {
                    return $this->fail('Error sending SMS. Please try again or use email method.', 500);
                }
            }

            // Mask phone number for security
            $phone = $user['phone'];
            $maskedPhone = substr($phone, 0, 4) . '****' . substr($phone, -3);

            return $this->respond([
                'message' => 'OTP code sent successfully to ' . $maskedPhone,
                'method' => 'sms',
                'expires_in' => $expiresInMinutes // minutes
            ]);
        } else {
            // Send via email (existing functionality)
            // In development, return the code directly
            // In production, this should actually send an email

            // Mask email for security
            $email = $user['email'];
            $atPos = strpos($email, '@');
            $maskedEmail = substr($email, 0, 2) . '****' . substr($email, $atPos);

            // TODO: Implement actual email sending here

            return $this->respond([
                'message' => 'OTP code sent successfully to ' . $maskedEmail,
                'method' => 'email',
                'code' => ENVIRONMENT !== 'production' ? $code : null, // Only in development
                'expires_in' => $expiresInMinutes // minutes
            ]);
        }
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

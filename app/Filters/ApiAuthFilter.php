<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UserApiTokenModel;
use App\Models\UserModel;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Ensure response is JSON
        $response = service('response');
        $response->setContentType('application/json');

        // Log the request for debugging
        log_message('debug', '[ApiAuthFilter] Processing request for URI: ' . $request->uri->getPath());

        $token = $this->extractToken($request);
        
        if (!$token) {
            log_message('error', '[ApiAuthFilter] No token provided for protected route: ' . $request->uri->getPath());
            return $response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token not provided'
                ]);
        }
        
        $tokenModel = new UserApiTokenModel();
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            log_message('error', '[ApiAuthFilter] Invalid token provided: ' . $token);
            return $response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid token'
                ]);
        }

        if ($tokenData['expires_at'] && strtotime($tokenData['expires_at']) < time()) {
            log_message('error', '[ApiAuthFilter] Token expired: ' . $token);
            return $response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token expired'
                ]);
        }

        // Get user data
        $userModel = new UserModel();
        $user = $userModel->find($tokenData['user_id']);
        
        if (!$user) {
            log_message('error', '[ApiAuthFilter] User not found for token: ' . $token);
            return $response
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'User not found'
                ]);
        }

        // Store user data in request for later use
        $request->user = $user;
        $request->token = $tokenData;
        
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }

    private function extractToken($request)
    {
        $authHeader = $request->getHeaderLine('Authorization');
        
        if (!empty($authHeader)) {
            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                return $matches[1];
            }
        }
        
        // Try from query string
        return $request->getGet('token');
    }
}
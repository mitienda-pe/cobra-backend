<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UserApiTokenModel;
use App\Models\UserModel;

class ApiAuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $token = $this->extractToken($request);
        
        if (!$token) {
            return $this->failUnauthorized('Token not provided');
        }
        
        $tokenModel = new UserApiTokenModel();
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            return $this->failUnauthorized('Invalid or expired token');
        }
        
        // Update last used timestamp
        $tokenModel->updateLastUsed($tokenData['id']);
        
        // Get user data
        $userModel = new UserModel();
        $user = $userModel->find($tokenData['user_id']);
        
        if (!$user || $user['status'] !== 'active') {
            return $this->failUnauthorized('User inactive or not found');
        }
        
        // Store user data in session for API controllers
        unset($user['password']);
        unset($user['remember_token']);
        unset($user['reset_token']);
        unset($user['reset_token_expires_at']);
        
        session()->set('api_user', $user);
        session()->set('api_token', $tokenData);
    }
    
    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
    
    /**
     * Extract token from Authorization header
     */
    private function extractToken(RequestInterface $request)
    {
        $token = $request->getHeaderLine('Authorization');
        
        if (strpos($token, 'Bearer ') === 0) {
            return substr($token, 7);
        }
        
        return null;
    }
    
    /**
     * Return unauthorized error
     */
    private function failUnauthorized($message)
    {
        $response = service('response');
        $response->setStatusCode(401);
        $response->setJSON([
            'status' => 401,
            'error' => 'Unauthorized',
            'messages' => [
                'error' => $message
            ]
        ]);
        
        return $response;
    }
}
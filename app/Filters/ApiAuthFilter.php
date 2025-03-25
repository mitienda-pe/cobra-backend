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
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token not provided'
                ]);
        }
        
        $tokenModel = new UserApiTokenModel();
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ]);
        }
        
        // Update last used timestamp
        $tokenModel->updateLastUsed($tokenData['id']);
        
        // Get user data
        $userModel = new UserModel();
        $user = $userModel->find($tokenData['user_id']);
        
        if (!$user || $user['status'] !== 'active') {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'User inactive or not found'
                ]);
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
     * Extract token from request headers or query string
     */
    private function extractToken(RequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');
        
        if (!empty($header)) {
            if (preg_match('/Bearer\s(\S+)/', $header, $matches)) {
                return $matches[1];
            }
        }
        
        return $request->getServer('HTTP_AUTHORIZATION');
    }
}
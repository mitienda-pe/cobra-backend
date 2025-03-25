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
        // Log the request for debugging
        log_message('debug', '[ApiAuthFilter] Processing request for: ' . $request->getUri());
        log_message('debug', '[ApiAuthFilter] Headers: ' . json_encode($request->headers()));

        // Check if route is in exceptions list
        $uri = $request->getUri()->getPath();
        $exceptions = [
            'api/auth/request-otp',
            'api/auth/verify-otp',
            'api/auth/refresh-token',
            'api/users',
            'api/clients',
            'debug/client-create',
            'debug/auth-info',
        ];

        // Check if the current URI matches any exception pattern
        foreach ($exceptions as $exception) {
            if (strpos($uri, $exception) !== false) {
                log_message('debug', '[ApiAuthFilter] Route {uri} is in exceptions list, skipping auth', ['uri' => $uri]);
                return;
            }
        }

        try {
            // Extract token from Authorization header
            $token = $this->extractToken($request);
            
            if (!$token) {
                log_message('error', '[ApiAuthFilter] No token provided for protected route: {uri}', ['uri' => $uri]);
                return $this->failUnauthorized('Token not provided');
            }
            
            $tokenModel = new UserApiTokenModel();
            $tokenData = $tokenModel->getByToken($token);
            
            if (!$tokenData) {
                log_message('error', '[ApiAuthFilter] Invalid or expired token for route: {uri}', ['uri' => $uri]);
                return $this->failUnauthorized('Invalid or expired token');
            }
            
            // Update last used timestamp
            $tokenModel->updateLastUsed($tokenData['id']);
            
            // Get user data
            $userModel = new UserModel();
            $user = $userModel->find($tokenData['user_id']);
            
            if (!$user || $user['status'] !== 'active') {
                log_message('error', '[ApiAuthFilter] User inactive or not found. User ID: {user_id}', ['user_id' => $tokenData['user_id']]);
                return $this->failUnauthorized('User inactive or not found');
            }
            
            // Store user data in session for API controllers
            unset($user['password']);
            unset($user['remember_token']);
            unset($user['reset_token']);
            unset($user['reset_token_expires_at']);
            
            session()->set('api_user', $user);
            session()->set('api_token', $tokenData);

            log_message('info', '[ApiAuthFilter] Authentication successful for user ID: {user_id}', ['user_id' => $user['id']]);
        } catch (\Exception $e) {
            log_message('error', '[ApiAuthFilter] An error occurred during authentication: {message}', ['message' => $e->getMessage()]);
            return $this->failUnauthorized('An error occurred during authentication');
        }
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
            'status' => false,
            'message' => $message,
            'error' => 'Unauthorized'
        ]);
        
        return $response;
    }
}
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
        // Ensure response is JSON
        $response = service('response');
        $response->setContentType('application/json');

        // Add CORS headers to all responses
        $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
                ->setHeader('Access-Control-Allow-Credentials', 'true');

        // Si es una petición OPTIONS, permitir sin token
        if ($request->getMethod(true) === 'OPTIONS') {
            return $response;
        }

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
            log_message('error', '[ApiAuthFilter] Invalid token for route: ' . $request->uri->getPath());
            return $response
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
            log_message('error', '[ApiAuthFilter] User inactive or not found: User ID ' . $tokenData['user_id']);
            return $response
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

        log_message('debug', '[ApiAuthFilter] Authentication successful for user ID: ' . $user['id']);
        return $request;
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Ensure response is JSON
        if (!$response->hasHeader('Content-Type')) {
            $response->setContentType('application/json');
        }

        // Add CORS headers to all responses
        $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
                ->setHeader('Access-Control-Allow-Credentials', 'true');

        return $response;
    }

    /**
     * Extract token from request headers or query string
     */
    private function extractToken(RequestInterface $request): ?string
    {
        // Try Authorization header first
        $header = $request->getHeaderLine('Authorization');
        if (!empty($header)) {
            if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                return $matches[1];
            }
        }
        
        // Try X-API-Key header next
        $apiKey = $request->getHeaderLine('X-API-Key');
        if (!empty($apiKey)) {
            return $apiKey;
        }
        
        // Finally try query string
        $token = $request->uri->getQuery(['only' => ['token']]);
        return $token['token'] ?? null;
    }
}
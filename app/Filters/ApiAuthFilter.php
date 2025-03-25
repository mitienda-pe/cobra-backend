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
        // Si es una petición OPTIONS, permitir sin token
        if ($request->getMethod(true) === 'OPTIONS') {
            return service('response')
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', '*')
                ->setHeader('Access-Control-Allow-Methods', '*');
        }

        $token = $this->extractToken($request);
        
        if (!$token) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Token not provided'
                ])
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', '*')
                ->setHeader('Access-Control-Allow-Methods', '*');
        }
        
        $tokenModel = new UserApiTokenModel();
        $tokenData = $tokenModel->getByToken($token);
        
        if (!$tokenData) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON([
                    'success' => false,
                    'message' => 'Invalid or expired token'
                ])
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', '*')
                ->setHeader('Access-Control-Allow-Methods', '*');
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
                ])
                ->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', '*')
                ->setHeader('Access-Control-Allow-Methods', '*');
        }
        
        // Store user data in session for API controllers
        unset($user['password']);
        unset($user['remember_token']);
        unset($user['reset_token']);
        unset($user['reset_token_expires_at']);
        
        session()->set('api_user', $user);
        session()->set('api_token', $tokenData);

        // Add CORS headers
        return service('response')
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Headers', '*')
            ->setHeader('Access-Control-Allow-Methods', '*');
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Add CORS headers to all responses
        $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', '*')
                ->setHeader('Access-Control-Allow-Methods', '*');
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
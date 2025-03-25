<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\Auth;
use App\Libraries\ApiResponse;

class ApiAuthFilter implements FilterInterface
{
    use ApiResponse;

    protected $excludedRoutes = [
        'api/auth/request-otp',
        'api/auth/verify-otp',
        'api/auth/refresh-token',
        'api/users',
        'api/clients',
        'debug/client-create',
        'debug/auth-info',
    ];

    /**
     * Do whatever processing this filter needs to do.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        log_message('debug', '[ApiAuthFilter] Processing request for: ' . $request->getUri());
        log_message('debug', '[ApiAuthFilter] Headers: ' . json_encode($request->headers()));

        $uri = $request->getUri()->getPath();
        $uri = trim($uri, '/');

        // Remove index.php from path if present
        $uri = str_replace('index.php/', '', $uri);

        // Check if route is excluded
        foreach ($this->excludedRoutes as $route) {
            if (strpos($uri, $route) !== false) {
                log_message('debug', '[ApiAuthFilter] Route {uri} is in exceptions list, skipping auth', ['uri' => $uri]);
                return;
            }
        }

        $token = $request->getHeaderLine('Authorization');
        if (!$token) {
            log_message('error', '[ApiAuthFilter] No token provided for protected route: {uri}', ['uri' => $uri]);
            return $this->failUnauthorized('Token not provided');
        }

        $token = str_replace('Bearer ', '', $token);
        $auth = new Auth();

        try {
            $user = $auth->validateToken($token);
            if (!$user) {
                log_message('error', '[ApiAuthFilter] Invalid token for route: {uri}', ['uri' => $uri]);
                return $this->failUnauthorized('Invalid token');
            }

            // Get user data
            $userModel = new \App\Models\UserModel();
            $user = $userModel->find($user['user_id']);

            if (!$user || $user['status'] !== 'active') {
                log_message('error', '[ApiAuthFilter] User inactive or not found. User ID: {user_id}', ['user_id' => $user['user_id']]);
                return $this->failUnauthorized('User inactive or not found');
            }

            // Store user data in session for API controllers
            unset($user['password']);
            unset($user['remember_token']);
            unset($user['reset_token']);
            unset($user['reset_token_expires_at']);

            session()->set('api_user', $user);

            log_message('info', '[ApiAuthFilter] Authentication successful for user ID: {user_id}', ['user_id' => $user['id']]);
        } catch (\Exception $e) {
            log_message('error', '[ApiAuthFilter] Token validation error: ' . $e->getMessage());
            return $this->failUnauthorized('Token validation error');
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
}
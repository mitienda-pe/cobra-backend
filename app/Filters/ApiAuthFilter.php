<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ApiAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $uri = $request->uri->getPath();
        log_message('debug', '[ApiAuthFilter] Processing request for URI: ' . $uri);

        // Get excluded paths from config
        $excludedPaths = [
            'api/auth/request-otp',
            'api/auth/verify-otp',
            'api/auth/refresh-token'
        ];

        // Check if current path is excluded
        foreach ($excludedPaths as $path) {
            if (strpos($uri, $path) !== false) {
                log_message('debug', '[ApiAuthFilter] Path is excluded from auth check: ' . $uri);
                return $request;
            }
        }

        // Get token from header
        $token = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s(\S+)/', $token, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            log_message('error', '[ApiAuthFilter] No token provided for protected route: ' . $uri);
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Token not provided'
                ]);
        }

        // Validate token
        $auth = Services::auth();
        $decoded = $auth->validateToken($token);

        if (!$decoded) {
            log_message('error', '[ApiAuthFilter] Invalid token for route: ' . $uri);
            return Services::response()
                ->setStatusCode(401)
                ->setJSON([
                    'status' => 'error',
                    'message' => 'Invalid token'
                ]);
        }

        // Set user data in request for controller use
        $request->user = $decoded;
        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\Auth;

class AuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Skip API routes
        if (strpos($request->uri->getPath(), 'api/') === 0) {
            log_message('debug', '[AuthFilter] Skipping API route: ' . $request->uri->getPath());
            return $request;
        }
        
        log_message('debug', '[AuthFilter] Processing web route: ' . $request->uri->getPath());
        
        $auth = new Auth();
        
        if (!$auth->check()) {
            log_message('info', '[AuthFilter] Authentication failed, redirecting to login');
            return redirect()->to('/auth/login')->with('error', 'Debe iniciar sesión para acceder a esta página.');
        }
        
        log_message('debug', '[AuthFilter] Authentication successful for web route');
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
        // Do nothing
        return $response;
    }
}
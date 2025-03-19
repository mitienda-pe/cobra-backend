<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Libraries\Auth;

class RoleFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = new Auth();
        
        // Must be logged in first
        if (!$auth->check()) {
            return redirect()->to('/auth/login')->with('error', 'Debe iniciar sesión para acceder a esta página.');
        }
        
        // Check if user has at least one of the required roles
        if (!empty($arguments)) {
            $allowedRoles = $arguments;
            
            if (!$auth->hasAnyRole($allowedRoles)) {
                return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
            }
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
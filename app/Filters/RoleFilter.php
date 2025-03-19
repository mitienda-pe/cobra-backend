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
            // Convert roles string to array
            $roles = [];
            if (is_string($arguments)) {
                $roles = explode(',', $arguments);
            } elseif (is_array($arguments)) {
                foreach ($arguments as $arg) {
                    if (is_string($arg)) {
                        $exploded = explode(',', $arg);
                        $roles = array_merge($roles, $exploded);
                    } else {
                        $roles[] = $arg;
                    }
                }
            }
            
            if (!$auth->hasAnyRole($roles)) {
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
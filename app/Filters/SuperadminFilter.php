<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SuperadminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        log_message('info', 'SuperadminFilter::before() - Filter called for URI: ' . $request->getUri());
        
        // Verificar si el usuario está autenticado y es superadmin
        $user = session()->get('user');
        
        log_message('debug', 'SuperadminFilter::before() - User data: ' . json_encode($user));
        
        if (!$user || $user['role'] !== 'superadmin') {
            log_message('error', 'SuperadminFilter::before() - Access denied. User role: ' . ($user['role'] ?? 'null'));
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Página no encontrada');
        }

        log_message('info', 'SuperadminFilter::before() - Access granted for superadmin');
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do here
    }
}
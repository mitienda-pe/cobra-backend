<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SuperadminRedirectFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $auth = service('auth');
        
        // Solo aplica a superadmins autenticados
        if (!$auth->check() || !$auth->hasRole('superadmin')) {
            return;
        }
        
        // Si el superadmin no tiene organización seleccionada
        if (!$auth->organizationId()) {
            $currentPath = $request->getUri()->getPath();
            
            // No redireccionar si ya está en organizations o auth
            if (strpos($currentPath, '/organizations') === 0 || 
                strpos($currentPath, '/auth') === 0) {
                return;
            }
            
            // Redireccionar a organizations para seleccionar contexto
            return redirect()->to('organizations')->with('info', 'Selecciona una organización para continuar.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Nothing to do here
    }
}
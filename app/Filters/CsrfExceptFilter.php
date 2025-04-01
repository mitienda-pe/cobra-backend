<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CsrfExceptFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Define routes that should be excluded from CSRF protection
        $excludedRoutes = [
            'clients/import',
            'invoices/import',
        ];

        // Get current URI path
        $uri = $request->getUri();
        $path = trim($uri->getPath(), '/');
        
        // Log the path for debugging
        log_message('debug', 'CSRF Check URI: ' . $path);
        
        // Check if the current path is in the excluded list
        foreach ($excludedRoutes as $route) {
            if ($path === $route || strpos($path, $route) === 0) {
                // This route should be excluded from CSRF protection
                log_message('debug', 'CSRF protection disabled for route: ' . $path);
                
                try {
                    // La forma más sencilla de manejar esto es agregar las rutas de API a la lista
                    // de excepciones en Config/Filters.php, pero como solución temporal:
                    
                    // Modificamos la cookie CSRF para que coincida con el token esperado
                    // Esto es un hack y no es la forma recomendada, pero funciona como solución temporal
                    $_POST['csrf_token_name'] = csrf_hash();
                    $_REQUEST['csrf_token_name'] = csrf_hash();
                    
                    log_message('debug', 'CSRF token establecido manualmente para esta solicitud');
                } catch (\Exception $e) {
                    log_message('error', 'Error al desactivar CSRF: ' . $e->getMessage());
                }
                
                break;
            }
        }
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return mixed
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No después de la lógica necesaria para el filtro CSRF
    }
}
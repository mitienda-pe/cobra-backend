<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class CorsFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Log for debugging
        log_message('debug', 'CORS Filter: ' . $request->getMethod(true) . ' ' . $request->uri->getPath());
        
        // Ensure response is JSON for API routes
        $response = service('response');
        if (strpos($request->uri->getPath(), 'api/') === 0) {
            $response->setContentType('application/json');
        }

        // Add CORS headers to all responses
        $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
                ->setHeader('Access-Control-Allow-Credentials', 'true');

        // Si es una petición OPTIONS, permitir sin token
        if ($request->getMethod(true) === 'OPTIONS') {
            $response->setStatusCode(200);
            return $response;
        }

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
        // Log for debugging
        log_message('debug', 'CORS Filter (after): ' . $request->getMethod(true) . ' ' . $request->uri->getPath());
        
        // Only set content type for API routes if not already set
        if (strpos($request->uri->getPath(), 'api/') === 0 && !$response->hasHeader('Content-Type')) {
            $response->setContentType('application/json');
        }

        // Add CORS headers to all responses
        $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
                ->setHeader('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}

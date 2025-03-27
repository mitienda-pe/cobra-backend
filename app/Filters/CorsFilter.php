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
        $logFile = WRITEPATH . 'logs/cors-' . date('Y-m-d') . '.log';
        $logMessage = date('Y-m-d H:i:s') . "\n" .
                     "URI: " . $request->uri->getPath() . "\n" .
                     "Method: " . $request->getMethod(true) . "\n" .
                     "Headers: " . json_encode(getallheaders()) . "\n" .
                     "Arguments: " . json_encode($arguments) . "\n" .
                     "Route Info: " . json_encode(service('router')->getMatchedRoute()) . "\n" .
                     "Controller: " . service('router')->controllerName() . "\n" .
                     "Method: " . service('router')->methodName() . "\n\n";
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        log_message('debug', 'CORS Filter: ' . $request->getMethod(true) . ' ' . $request->uri->getPath());
        
        $response = service('response');

        // Add CORS headers to all responses
        $response->setHeader('Access-Control-Allow-Origin', '*')
                ->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept, Authorization, X-API-Key')
                ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS, PUT, DELETE')
                ->setHeader('Access-Control-Allow-Credentials', 'true')
                ->setHeader('Access-Control-Max-Age', '86400'); // 24 hours

        // Si es una petición OPTIONS, permitir sin token
        if ($request->getMethod(true) === 'OPTIONS') {
            $response->setStatusCode(200);
            $response->setBody('');
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
        
        return $response;
    }
}

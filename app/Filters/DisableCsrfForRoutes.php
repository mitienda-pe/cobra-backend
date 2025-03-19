<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * DisableCsrfForRoutes Filter
 * 
 * This filter explicitly disables CSRF for specific routes
 */
class DisableCsrfForRoutes implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return mixed
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // Get the current URI path
        $uri = $request->getUri()->getPath();
        
        // Routes that should bypass CSRF
        $bypassRoutes = [
            'clients/import',
            'invoices/import'
        ];
        
        // Check if the current URI should bypass CSRF
        $shouldBypass = false;
        foreach ($bypassRoutes as $route) {
            if (strpos($uri, $route) !== false) {
                $shouldBypass = true;
                break;
            }
        }
        
        if ($shouldBypass) {
            // Log this for debugging
            log_message('debug', 'CSRF protection disabled for URI: ' . $uri);
            
            // Store the current URI in the bypass log
            $logFile = WRITEPATH . 'logs/csrf_bypass.log';
            $logMessage = date('Y-m-d H:i:s') . " - URI: {$uri}, Method: {$request->getMethod()}\n";
            
            if (!file_exists($logFile)) {
                file_put_contents($logFile, "=== CSRF Bypass Log ===\n\n");
            }
            
            file_put_contents($logFile, $logMessage, FILE_APPEND);
            
            // Get the security service
            $security = \Config\Services::security();
            
            // If this is a POST request, directly inject the CSRF token into the POST data
            if ($request->getMethod() === 'post') {
                $tokenName = $security->getTokenName();
                $tokenValue = $security->getHash();
                
                if ($tokenName && $tokenValue) {
                    $_POST[$tokenName] = $tokenValue;
                    log_message('debug', "Added CSRF token to POST data: {$tokenName}={$tokenValue}");
                }
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
        // Nothing to do after
    }
}
<?php

namespace App\Hooks;

/**
 * DisableCsrfHook Class
 *
 * This hook is registered via Events.php and runs before the CSRF filter
 * to selectively disable CSRF protection for certain routes.
 */
class DisableCsrfHook
{
    /**
     * This method runs before any controller is executed
     *
     * @param array $params
     * @return void
     */
    public function disableCsrf($params)
    {
        $router = service('router');

        // Get current controller and method
        $controller = $router->controllerName();
        $method = $router->methodName();

        // Log for debugging
        log_message('debug', "DisableCsrfHook - Controller: {$controller}, Method: {$method}");

        // List of routes to exclude from CSRF protection
        $excludedRoutes = [
            'App\Controllers\ClientController::import',
            'App\Controllers\InvoiceController::import',
        ];

        // Check if current route should be excluded
        $currentRoute = $controller . '::' . $method;
        if (in_array($currentRoute, $excludedRoutes)) {
            // Disable CSRF protection for this request
            log_message('debug', "Disabling CSRF for route: {$currentRoute}");

            // Access the security service and attempt to disable verification
            $security = \Config\Services::security();
            if (method_exists($security, 'setCSRFProtection')) {
                $security->setCSRFProtection(false);
                log_message('debug', "CSRF protection disabled via setCSRFProtection method");
            }

            // Alternative approach: use Reflection to modify the internal state
            try {
                $reflectionClass = new \ReflectionClass($security);
                $property = $reflectionClass->getProperty('CSRFVerify');
                if ($property) {
                    $property->setAccessible(true);
                    $property->setValue($security, false);
                    log_message('debug', "CSRF protection disabled via Reflection");
                }
            } catch (\Exception $e) {
                log_message('error', "Failed to disable CSRF via Reflection: " . $e->getMessage());
            }
        }
    }
}

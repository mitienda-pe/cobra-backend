<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class OrganizationFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();
        
        // Skip if not logged in or not a superadmin
        if (!$session->get('isLoggedIn')) {
            return;
        }
        
        $user = $session->get('user');
        if ($user['role'] !== 'superadmin') {
            return;
        }
        
        // Log current session state
        $currentOrgId = $session->get('selected_organization_id');
        log_message('debug', '[OrganizationFilter] Current session selected_organization_id: ' . 
                  ($currentOrgId ? $currentOrgId : 'null'));
        
        // Handle organization selection from query parameter
        if ($request->getGet('org_id')) {
            $organizationId = $request->getGet('org_id');
            $session->set('selected_organization_id', $organizationId);
            log_message('info', '[OrganizationFilter] Setting organization ID in session: ' . $organizationId);
            
            // Store original URL without org_id parameter for redirect
            $uri = $request->getUri();
            $query = $uri->getQuery();
            
            // Remove org_id from query string
            parse_str($query, $params);
            unset($params['org_id']);
            
            // Build redirect URL without org_id parameter
            $redirectQuery = http_build_query($params);
            
            // Get the current path without index.php duplication
            $currentPath = $_SERVER['REQUEST_URI'];
            // Remove query string if exists
            $currentPath = strtok($currentPath, '?');
            
            $redirectUri = $currentPath;
            if (!empty($redirectQuery)) {
                $redirectUri .= '?' . $redirectQuery;
            }
            
            // Check if session was actually set
            $checkOrgId = $session->get('selected_organization_id');
            log_message('debug', '[OrganizationFilter] After setting, session selected_organization_id: ' . 
                      ($checkOrgId ? $checkOrgId : 'null'));
            
            // Redirect to the same page without the org_id parameter
            return redirect()->to($redirectUri);
        }
        
        // Handle clearing organization selection
        if ($request->getGet('clear_org') == '1') {
            log_message('info', '[OrganizationFilter] Clearing organization ID from session');
            $session->remove('selected_organization_id');
            
            // Redirect to the same page without the clear_org parameter
            $uri = $request->getUri();
            $query = $uri->getQuery();
            
            // Remove clear_org from query string
            parse_str($query, $params);
            unset($params['clear_org']);
            
            // Build redirect URL without clear_org parameter
            $redirectQuery = http_build_query($params);
            
            // Get the current path without index.php duplication
            $currentPath = $_SERVER['REQUEST_URI'];
            // Remove query string if exists
            $currentPath = strtok($currentPath, '?');
            
            $redirectUri = $currentPath;
            if (!empty($redirectQuery)) {
                $redirectUri .= '?' . $redirectQuery;
            }
            
            // Check if session was actually removed
            $checkOrgId = $session->get('selected_organization_id');
            log_message('debug', '[OrganizationFilter] After clearing, session selected_organization_id: ' . 
                      ($checkOrgId ? $checkOrgId : 'null'));
            
            return redirect()->to($redirectUri);
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing after the controller execution
    }
}
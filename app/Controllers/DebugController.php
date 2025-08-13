<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class DebugController extends Controller
{
    use ResponseTrait;

    public function ligoConfig()
    {
        // Verificar organización en sesión
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        $user = $session->get('user');
        
        $organizationModel = new \App\Models\OrganizationModel();
        $organization = null;
        
        if ($organizationId) {
            $organization = $organizationModel->find($organizationId);
        }
        
        $environment = env('CI_ENVIRONMENT', 'development');
        
        $debugInfo = [
            'session_data' => [
                'organization_id' => $organizationId,
                'user_id' => $user['id'] ?? null,
                'user_role' => $user['role'] ?? null,
                'is_logged_in' => $session->get('isLoggedIn')
            ],
            'environment' => $environment,
            'organization' => $organization ? [
                'id' => $organization['id'],
                'name' => $organization['name'],
                'has_ligo_username' => !empty($organization['ligo_username']),
                'has_ligo_password' => !empty($organization['ligo_password']),
                'has_ligo_company_id' => !empty($organization['ligo_company_id']),
                'has_ligo_prod_username' => !empty($organization['ligo_prod_username']),
                'has_ligo_prod_password' => !empty($organization['ligo_prod_password']),
                'has_ligo_prod_company_id' => !empty($organization['ligo_prod_company_id']),
                'ligo_enabled' => $organization['ligo_enabled'] ?? false
            ] : null,
            'urls' => [
                'environment' => $environment,
                'ligo_base_url' => $environment === 'production' 
                    ? env('LIGO_PROD_URL', 'https://api.ligo.pe')
                    : env('LIGO_DEV_URL', 'https://dev-api.ligo.pe'),
                'ligo_auth_url' => $environment === 'production'
                    ? env('LIGO_PROD_AUTH_URL', 'https://auth.ligo.pe')
                    : env('LIGO_DEV_AUTH_URL', 'https://dev-auth.ligo.pe')
            ]
        ];
        
        return $this->respond($debugInfo);
    }
}
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
                'ligo_environment' => $organization['ligo_environment'] ?? 'dev',
                'has_ligo_dev_username' => !empty($organization['ligo_dev_username']),
                'has_ligo_dev_password' => !empty($organization['ligo_dev_password']),
                'has_ligo_dev_company_id' => !empty($organization['ligo_dev_company_id']),
                'has_ligo_prod_username' => !empty($organization['ligo_prod_username']),
                'has_ligo_prod_password' => !empty($organization['ligo_prod_password']),
                'has_ligo_prod_company_id' => !empty($organization['ligo_prod_company_id']),
                'ligo_enabled' => $organization['ligo_enabled'] ?? false,
                'actual_fields' => [
                    'ligo_dev_username' => $organization['ligo_dev_username'] ?? null,
                    'ligo_prod_username' => $organization['ligo_prod_username'] ?? null
                ]
            ] : null,
            'urls' => [
                'environment' => $environment,
                'org_environment' => $organization ? ($organization['ligo_environment'] ?? 'dev') : 'dev',
                'final_environment' => ($environment === 'production' || ($organization && ($organization['ligo_environment'] ?? 'dev') === 'prod')) ? 'prod' : 'dev',
                'ligo_base_url' => ($environment === 'production' || ($organization && ($organization['ligo_environment'] ?? 'dev') === 'prod')) 
                    ? 'https://cce-api-gateway-prod.ligocloud.tech'
                    : 'https://cce-api-gateway-dev.ligocloud.tech',
                'ligo_auth_url' => ($environment === 'production' || ($organization && ($organization['ligo_environment'] ?? 'dev') === 'prod'))
                    ? 'https://cce-auth-prod.ligocloud.tech'
                    : 'https://cce-auth-dev.ligocloud.tech'
            ]
        ];
        
        return $this->respond($debugInfo);
    }
}
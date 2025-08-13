<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class DebugBackofficeController extends Controller
{
    use ResponseTrait;

    public function checkOrganization()
    {
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        
        if (!$organizationId) {
            return $this->respond([
                'error' => 'No organization selected',
                'session_data' => [
                    'user' => $session->get('user'),
                    'isLoggedIn' => $session->get('isLoggedIn'),
                    'selected_organization_id' => $organizationId
                ]
            ]);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($organizationId);
        
        if (!$organization) {
            return $this->respond([
                'error' => 'Organization not found',
                'organization_id' => $organizationId
            ]);
        }

        // Obtener información relevante sin exponer passwords completas
        $debugInfo = [
            'organization' => [
                'id' => $organization['id'],
                'name' => $organization['name'],
                'ligo_environment' => $organization['ligo_environment'] ?? 'not_set'
            ],
            'dev_credentials' => [
                'username' => $organization['ligo_dev_username'] ?? 'not_set',
                'password_set' => !empty($organization['ligo_dev_password']),
                'company_id' => $organization['ligo_dev_company_id'] ?? 'not_set',
                'account_id' => $organization['ligo_dev_account_id'] ?? 'not_set'
            ],
            'prod_credentials' => [
                'username' => $organization['ligo_prod_username'] ?? 'not_set',
                'password_set' => !empty($organization['ligo_prod_password']),
                'company_id' => $organization['ligo_prod_company_id'] ?? 'not_set',
                'account_id' => $organization['ligo_prod_account_id'] ?? 'not_set'
            ]
        ];

        return $this->respond($debugInfo);
    }
}
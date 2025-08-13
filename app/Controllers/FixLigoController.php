<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class FixLigoController extends Controller
{
    use ResponseTrait;

    public function switchToDevEnvironment()
    {
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        
        if (!$organizationId) {
            return $this->respond(['error' => 'No organization selected']);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($organizationId);
        
        if (!$organization) {
            return $this->respond(['error' => 'Organization not found']);
        }

        // Cambiar a entorno de desarrollo
        $updateData = [
            'ligo_environment' => 'dev'
        ];

        $updated = $organizationModel->update($organizationId, $updateData);
        
        if ($updated) {
            return $this->respond([
                'success' => true,
                'message' => 'Organization switched to development environment',
                'organization' => [
                    'id' => $organization['id'],
                    'name' => $organization['name'],
                    'old_environment' => $organization['ligo_environment'] ?? 'prod',
                    'new_environment' => 'dev'
                ]
            ]);
        } else {
            return $this->respond(['error' => 'Failed to update organization']);
        }
    }

    public function switchToProdEnvironment()
    {
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        
        if (!$organizationId) {
            return $this->respond(['error' => 'No organization selected']);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($organizationId);
        
        if (!$organization) {
            return $this->respond(['error' => 'Organization not found']);
        }

        // Cambiar a entorno de producción
        $updateData = [
            'ligo_environment' => 'prod'
        ];

        $updated = $organizationModel->update($organizationId, $updateData);
        
        if ($updated) {
            return $this->respond([
                'success' => true,
                'message' => 'Organization switched to production environment',
                'organization' => [
                    'id' => $organization['id'],
                    'name' => $organization['name'],
                    'old_environment' => $organization['ligo_environment'] ?? 'dev',
                    'new_environment' => 'prod'
                ]
            ]);
        } else {
            return $this->respond(['error' => 'Failed to update organization']);
        }
    }

    public function testDevCredentials()
    {
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        
        if (!$organizationId) {
            return $this->respond(['error' => 'No organization selected']);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($organizationId);
        
        if (!$organization) {
            return $this->respond(['error' => 'Organization not found']);
        }

        // Test credenciales de desarrollo
        $username = $organization['ligo_dev_username'];
        $password = $organization['ligo_dev_password'];
        $companyId = $organization['ligo_dev_company_id'];
        $authUrl = 'https://cce-auth-dev.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;

        $authData = [
            'username' => $username,
            'password' => $password
        ];

        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $authUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($authData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        $result = [
            'test' => 'Testing DEV credentials',
            'organization' => [
                'id' => $organization['id'],
                'name' => $organization['name'],
                'current_environment' => $organization['ligo_environment'] ?? 'not_set'
            ],
            'request' => [
                'url' => $authUrl,
                'method' => 'POST',
                'body' => [
                    'username' => $username,
                    'password' => str_repeat('*', strlen($password))
                ]
            ],
            'response' => [
                'http_code' => $httpCode,
                'curl_error' => $err,
                'raw_response' => $response
            ]
        ];
        
        if ($response) {
            $result['parsed_response'] = json_decode($response, true);
        }
        
        return $this->respond($result);
    }
}
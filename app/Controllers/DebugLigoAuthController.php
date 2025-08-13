<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class DebugLigoAuthController extends Controller
{
    use ResponseTrait;

    public function testCurrentCredentials()
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

        // Usar la misma lógica que el LigoModel
        $environment = $organization['ligo_environment'] ?? 'dev';
        
        if ($environment === 'prod') {
            $username = $organization['ligo_prod_username'];
            $password = $organization['ligo_prod_password'];
            $companyId = $organization['ligo_prod_company_id'];
            $authUrl = 'https://cce-auth-prod.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
        } else {
            $username = $organization['ligo_dev_username'];
            $password = $organization['ligo_dev_password'];
            $companyId = $organization['ligo_dev_company_id'];
            $authUrl = 'https://cce-auth-dev.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
        }

        // Preparar datos para la prueba
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
            CURLOPT_SSL_VERIFYHOST => 0,  // Desactivar verificación SSL temporalmente
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        $result = [
            'organization' => [
                'id' => $organization['id'],
                'name' => $organization['name'],
                'environment' => $environment
            ],
            'request' => [
                'url' => $authUrl,
                'method' => 'POST',
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'body' => [
                    'username' => $username,
                    'password' => str_repeat('*', strlen($password)) // Ocultar password
                ]
            ],
            'response' => [
                'http_code' => $httpCode,
                'curl_error' => $err,
                'raw_response' => $response,
                'curl_info' => $info
            ]
        ];
        
        if ($response) {
            $result['parsed_response'] = json_decode($response, true);
        }
        
        return $this->respond($result);
    }

    public function testWithCorrectCredentials()
    {
        // Test con las credenciales que sabemos que funcionaban antes
        $correctCredentials = [
            'username' => 'mi-tienda-prod',
            'password' => 'l9OxWS5QeGq5pGroAlTb-',
            'companyId' => 'c639e0ba-20d2-4ecb-80ce-333ac2b16d0d'
        ];

        $authUrl = 'https://cce-auth-prod.ligocloud.tech/v1/auth/sign-in?companyId=' . $correctCredentials['companyId'];
        
        $authData = [
            'username' => $correctCredentials['username'],
            'password' => $correctCredentials['password']
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
            CURLOPT_SSL_VERIFYHOST => 0,  // Desactivar verificación SSL temporalmente
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        $result = [
            'test' => 'Using known working credentials',
            'request' => [
                'url' => $authUrl,
                'method' => 'POST',
                'body' => [
                    'username' => $correctCredentials['username'],
                    'password' => str_repeat('*', strlen($correctCredentials['password']))
                ]
            ],
            'response' => [
                'http_code' => $httpCode,
                'curl_error' => $err,
                'raw_response' => $response,
                'curl_info' => $info
            ]
        ];
        
        if ($response) {
            $result['parsed_response'] = json_decode($response, true);
        }
        
        return $this->respond($result);
    }
}
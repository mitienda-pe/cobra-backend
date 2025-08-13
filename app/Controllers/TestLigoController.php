<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class TestLigoController extends Controller
{
    use ResponseTrait;

    public function testAuth()
    {
        $organizationModel = new \App\Models\OrganizationModel();
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        
        if (!$organizationId) {
            return $this->fail('No organization selected', 400);
        }
        
        $organization = $organizationModel->find($organizationId);
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        
        // Test authentication manually
        $orgEnvironment = $organization['ligo_environment'] ?? 'dev';
        
        if ($orgEnvironment === 'prod') {
            $authData = [
                'username' => $organization['ligo_prod_username'],
                'password' => $organization['ligo_prod_password']
            ];
            $companyId = $organization['ligo_prod_company_id'];
            $authUrl = 'https://cce-auth-prod.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
        } else {
            $authData = [
                'username' => $organization['ligo_dev_username'],
                'password' => $organization['ligo_dev_password']
            ];
            $companyId = $organization['ligo_dev_company_id'];
            $authUrl = 'https://cce-auth-dev.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
        }
        
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
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        $result = [
            'request' => [
                'url' => $authUrl,
                'method' => 'POST',
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                'body' => $authData,
                'environment' => $orgEnvironment
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
    
    public function testBalance()
    {
        // Get auth token first
        $authResult = $this->testAuth();
        $authData = $authResult->getBody();
        $authArray = json_decode($authData, true);
        
        if ($authArray['response']['http_code'] !== 200) {
            return $this->fail('Authentication failed', 400);
        }
        
        $parsedAuth = $authArray['parsed_response'];
        if (!isset($parsedAuth['data']['token'])) {
            return $this->fail('No token in auth response', 400);
        }
        
        $token = $parsedAuth['data']['token'];
        
        // Test balance request
        $organizationModel = new \App\Models\OrganizationModel();
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        $organization = $organizationModel->find($organizationId);
        
        $orgEnvironment = $organization['ligo_environment'] ?? 'dev';
        $baseUrl = $orgEnvironment === 'prod' 
            ? 'https://cce-api-gateway-prod.ligocloud.tech'
            : 'https://cce-api-gateway-dev.ligocloud.tech';
            
        $testCCI = '92100171571742601040'; // CCI de prueba
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . '/v1/accountBalance',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode(['debtorCCI' => $testCCI]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $token
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        $result = [
            'auth_token' => substr($token, 0, 20) . '...',
            'request' => [
                'url' => $baseUrl . '/v1/accountBalance',
                'method' => 'POST',
                'headers' => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . substr($token, 0, 20) . '...'
                ],
                'body' => ['debtorCCI' => $testCCI],
                'environment' => $orgEnvironment
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
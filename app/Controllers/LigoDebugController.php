<?php

namespace App\Controllers;

use App\Models\OrganizationModel;

class LigoDebugController extends BaseController
{
    protected $organizationModel;
    
    public function __construct()
    {
        $this->organizationModel = new OrganizationModel();
    }
    
    /**
     * Get Ligo credentials based on active environment
     */
    private function getLigoCredentials($organization)
    {
        $environment = $organization['ligo_environment'] ?? 'dev';
        $prefix = $environment === 'prod' ? 'prod' : 'dev';
        
        // Try to get environment-specific credentials first
        $credentials = [
            'username' => $organization["ligo_{$prefix}_username"] ?? null,
            'password' => $organization["ligo_{$prefix}_password"] ?? null,
            'company_id' => $organization["ligo_{$prefix}_company_id"] ?? null,
            'account_id' => $organization["ligo_{$prefix}_account_id"] ?? null,
            'merchant_code' => $organization["ligo_{$prefix}_merchant_code"] ?? null,
            'private_key' => $organization["ligo_{$prefix}_private_key"] ?? null,
            'webhook_secret' => $organization["ligo_{$prefix}_webhook_secret"] ?? null,
        ];
        
        // Fallback to legacy fields if environment-specific fields are empty
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            $credentials = [
                'username' => $organization['ligo_username'] ?? null,
                'password' => $organization['ligo_password'] ?? null,
                'company_id' => $organization['ligo_company_id'] ?? null,
                'account_id' => $organization['ligo_account_id'] ?? null,
                'merchant_code' => $organization['ligo_merchant_code'] ?? null,
                'private_key' => $organization['ligo_private_key'] ?? null,
                'webhook_secret' => $organization['ligo_webhook_secret'] ?? null,
            ];
        }
        
        return $credentials;
    }
    
    /**
     * Get Ligo API configuration based on organization settings
     */
    private function getLigoConfig($organization)
    {
        $environment = $organization['ligo_environment'] ?? 'dev';
        $sslVerify = isset($organization['ligo_ssl_verify']) ? (bool)$organization['ligo_ssl_verify'] : ($environment === 'prod');
        
        return [
            'environment' => $environment,
            'auth_url' => $organization['ligo_auth_url'] ?? "https://cce-auth-{$environment}.ligocloud.tech",
            'api_url' => $organization['ligo_api_url'] ?? "https://cce-api-gateway-{$environment}.ligocloud.tech",
            'ssl_verify' => $sslVerify,
            'ssl_verify_host' => $sslVerify ? 2 : 0,
            'prefix' => $environment
        ];
    }
    
    /**
     * Debug Ligo configuration and test connectivity
     */
    public function debug($orgId = null)
    {
        if (!$orgId) {
            $organization = $this->organizationModel->where('ligo_enabled', 1)->first();
        } else {
            $organization = $this->organizationModel->find($orgId);
        }
        
        if (!$organization) {
            return $this->response->setJSON([
                'error' => 'Organization not found or no Ligo-enabled organization available'
            ]);
        }
        
        $credentials = $this->getLigoCredentials($organization);
        $config = $this->getLigoConfig($organization);
        
        // Mask sensitive data
        $debugInfo = [
            'organization' => [
                'id' => $organization['id'],
                'name' => $organization['name'],
                'ligo_enabled' => $organization['ligo_enabled'],
                'ligo_environment' => $organization['ligo_environment'] ?? 'dev'
            ],
            'config' => $config,
            'credentials' => [
                'username' => $credentials['username'],
                'password' => $credentials['password'] ? '[CONFIGURED]' : '[EMPTY]',
                'company_id' => $credentials['company_id'],
                'account_id' => $credentials['account_id'],
                'merchant_code' => $credentials['merchant_code'],
                'private_key' => $credentials['private_key'] ? '[CONFIGURED]' : '[EMPTY]',
                'webhook_secret' => $credentials['webhook_secret'] ? '[CONFIGURED]' : '[EMPTY]'
            ],
            'credential_source' => [
                'using_environment_specific' => !empty($organization["ligo_{$config['environment']}_username"]),
                'fallback_to_legacy' => empty($organization["ligo_{$config['environment']}_username"])
            ]
        ];
        
        // Test connectivity
        try {
            $authUrl = $config['auth_url'] . '/v1/auth/sign-in?companyId=' . $credentials['company_id'];
            
            // Generate JWT token
            $authorizationToken = null;
            if (!empty($credentials['private_key'])) {
                try {
                    $privateKey = \App\Libraries\JwtGenerator::formatPrivateKey($credentials['private_key']);
                    $payload = ['companyId' => $credentials['company_id']];
                    $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $privateKey, [
                        'issuer' => 'ligo',
                        'audience' => 'ligo-calidad.com',
                        'subject' => 'ligo@gmail.com',
                        'expiresIn' => 3600
                    ]);
                    $debugInfo['jwt_generation'] = 'success';
                } catch (\Exception $e) {
                    $debugInfo['jwt_generation'] = 'error: ' . $e->getMessage();
                    $authorizationToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55SWQiOiJlOGI0YTM2ZC02ZjFkLTRhMmEtYmYzYS1jZTkzNzFkZGU0YWIiLCJpYXQiOjE3NDQxMzkwNDEsImV4cCI6MTc0NDE0MjY0MSwiYXVkIjoibGlnby1jYWxpZGFkLmNvbSIsImlzcyI6ImxpZ28iLCJzdWIiOiJsaWdvQGdtYWlsLmNvbSJ9.chWrhOkQXo2Yc9mOhB8kIHbSmQECtA_PxTsSCcOTCC6OJs7IkDAyj3vkISW7Sm6G88R3KXgxSWhPT4QmShw3xV9a4Jl0FTBQy2KRdTCzbTgRifs9GN0X5KR7KhfChnDSKNosnVQD9QrqTCdlqpvW75vO1rWfTRSXpMtKZRUvy6fPyESv2QxERlo-441e2EwwCly1kgLftpTcMa0qCr-OplD4Iv_YaOw-J5IPAdYqkVPqHQQZO2LCLjP-Q51KPW04VtTyf7UbO6g4OvUb6a423XauAhUFtSw0oGZS11hAYOPSIKO0w6JERLOvJr48lKaouogf0g_M18nZeSDPMZwCWw';
                }
            }
            
            $curl = curl_init();
            $authData = [
                'username' => $credentials['username'],
                'password' => $credentials['password']
            ];
            
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
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authorizationToken
                ],
                CURLOPT_SSL_VERIFYHOST => $config['ssl_verify_host'],
                CURLOPT_SSL_VERIFYPEER => $config['ssl_verify'],
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            
            $debugInfo['connectivity_test'] = [
                'auth_url' => $authUrl,
                'http_code' => $httpCode,
                'curl_error' => $error ?: null,
                'ssl_settings' => [
                    'ssl_verify' => $config['ssl_verify'],
                    'ssl_verify_host' => $config['ssl_verify_host']
                ],
                'response_preview' => substr($response, 0, 200) . '...'
            ];
            
            if (!$error && $httpCode == 200) {
                $decoded = json_decode($response, true);
                if ($decoded && isset($decoded['data']['access_token'])) {
                    $debugInfo['auth_test'] = 'success';
                } else {
                    $debugInfo['auth_test'] = 'failed - no token in response';
                }
            } else {
                $debugInfo['auth_test'] = 'failed - ' . ($error ?: "HTTP $httpCode");
            }
            
        } catch (\Exception $e) {
            $debugInfo['connectivity_test'] = [
                'error' => $e->getMessage()
            ];
        }
        
        return $this->response->setJSON($debugInfo);
    }
}
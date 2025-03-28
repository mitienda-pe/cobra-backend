<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class LigoAuthController extends ResourceController
{
    use ResponseTrait;
    
    protected $organizationModel;

    public function __construct()
    {
        $this->organizationModel = new \App\Models\OrganizationModel();
    }

    /**
     * Authenticate with Ligo API and get JWT token
     *
     * @return mixed
     */
    public function getToken()
    {
        // Get organization ID from request
        $organizationId = $this->request->getGet('organization_id');
        
        if (!$organizationId) {
            return $this->fail('Organization ID is required', 400);
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($organizationId);
        
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_api_key']) || empty($organization['ligo_api_secret'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Authenticate with Ligo API
        $response = $this->authenticateWithLigo($organization['ligo_api_key'], $organization['ligo_api_secret']);
        
        if (isset($response->error)) {
            return $this->fail($response->error, 400);
        }
        
        return $this->respond([
            'success' => true,
            'token' => $response->token ?? null,
            'expires_at' => $response->expires_at ?? null
        ]);
    }
    
    /**
     * Authenticate with Ligo API
     *
     * @param string $apiKey API Key
     * @param string $apiSecret API Secret
     * @return object Response from Ligo API
     */
    private function authenticateWithLigo($apiKey, $apiSecret)
    {
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.ligo.pe/v1/auth/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                'apiKey' => $apiKey,
                'apiSecret' => $apiSecret
            ]),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return (object)['error' => 'Failed to connect to Ligo API'];
        }
        
        return json_decode($response);
    }
}

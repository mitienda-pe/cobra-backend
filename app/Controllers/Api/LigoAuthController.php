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
        $endpoint = 'https://api.ligo.pe/v1/auth/token';
        log_message('info', '[LigoAuth] Iniciando autenticación con Ligo. Endpoint: ' . $endpoint);
        // Enmascara apiKey y apiSecret para evitar exponerlos completos
        $maskedApiKey = substr($apiKey, 0, 4) . str_repeat('*', max(strlen($apiKey) - 8, 0)) . substr($apiKey, -4);
        $maskedApiSecret = substr($apiSecret, 0, 4) . str_repeat('*', max(strlen($apiSecret) - 8, 0)) . substr($apiSecret, -4);
        log_message('debug', '[LigoAuth] Usando apiKey: ' . $maskedApiKey . ', apiSecret: ' . $maskedApiSecret);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $endpoint,
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
            CURLOPT_SSL_VERIFYHOST => 0, // Deshabilitar verificación de host SSL
            CURLOPT_SSL_VERIFYPEER => false, // Deshabilitar verificación de certificado SSL
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        log_message('debug', '[LigoAuth] Respuesta cruda de Ligo: ' . $response);
        if ($err) {
            log_message('error', '[LigoAuth] Error cURL al autenticar con Ligo: ' . $err);
        }

        $decoded = json_decode($response, true);
        if (!$decoded || !isset($decoded['status'])) {
            log_message('error', '[LigoAuth] Respuesta inválida de Ligo (no es JSON o falta status): ' . $response);
        }
        if (isset($decoded['status']) && $decoded['status'] == 0) {
            log_message('error', '[LigoAuth] Error de autenticación Ligo: ' . ($decoded['errors'] ?? 'Error desconocido') . ' | Código: ' . ($decoded['code'] ?? 'N/A'));
        }
        if (!isset($decoded['data']['token']) || empty($decoded['data']['token'])) {
            log_message('error', '[LigoAuth] No se recibió token en la respuesta de Ligo. Respuesta: ' . $response);
        } else {
            log_message('info', '[LigoAuth] Token recibido correctamente de Ligo.');
        }
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return (object)['error' => 'Failed to connect to Ligo API'];
        }
        
        return json_decode($response);
    }
}

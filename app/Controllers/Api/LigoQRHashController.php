<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class LigoQRHashController extends ResourceController
{
    use ResponseTrait;
    
    protected $ligoQRHashModel;
    protected $organizationModel;
    
    public function __construct()
    {
        $this->ligoQRHashModel = new \App\Models\LigoQRHashModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
    }
    
    /**
     * Get centralized Ligo credentials from superadmin configuration
     */
    private function getLigoCredentials($organization = null)
    {
        // Use centralized superadmin configuration
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Get any active configuration regardless of environment
        $configs = $superadminLigoConfigModel->where('enabled', 1)
                                             ->where('is_active', 1)
                                             ->findAll();
        $config = !empty($configs) ? $configs[0] : null;
        
        if (!$config || !$superadminLigoConfigModel->isConfigurationComplete($config)) {
            log_message('error', 'LigoQRHashController API: No valid centralized Ligo configuration found');
            return [
                'username' => null,
                'password' => null,
                'company_id' => null,
                'account_id' => null,
                'merchant_code' => null,
                'private_key' => null,
                'webhook_secret' => null,
            ];
        }

        log_message('debug', 'LigoQRHashController API: Using centralized Ligo credentials for environment: ' . $config['environment']);
        
        return [
            'username' => $config['username'],
            'password' => $config['password'],
            'company_id' => $config['company_id'],
            'account_id' => $config['account_id'],
            'merchant_code' => $config['merchant_code'] ?? null,
            'private_key' => $config['private_key'],
            'webhook_secret' => $config['webhook_secret'] ?? null,
        ];
    }

    /**
     * Get centralized Ligo configuration for API endpoints URLs
     * @return array|false Ligo configuration array or false if not found
     */
    private function getLigoConfig()
    {
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Get active configuration
        $configs = $superadminLigoConfigModel->where('enabled', 1)
                                             ->where('is_active', 1)
                                             ->findAll();
        $config = !empty($configs) ? $configs[0] : null;
        
        if (!$config) {
            log_message('error', 'LigoQRHashController API: No active Ligo configuration found');
            return false;
        }

        // Build URLs based on environment from centralized config
        $environment = $config['environment'];
        $prefix = $environment === 'prod' ? 'prod' : 'dev';
        
        return [
            'environment' => $environment,
            'api_base_url' => "https://cce-api-gateway-{$prefix}.ligocloud.tech",
            'auth_base_url' => "https://cce-auth-{$prefix}.ligocloud.tech"
        ];
    }
    
    public function index()
    {
        $hashes = $this->ligoQRHashModel->orderBy('created_at', 'DESC')->findAll(50);
        return $this->respond($hashes);
    }
    
    /**
     * Get hash details by ID
     *
     * @param string $id
     * @return mixed
     */
    public function details($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Hash ID is required');
        }
        
        $hash = $this->ligoQRHashModel->find($id);
        
        if (!$hash) {
            return $this->failNotFound('Hash not found');
        }
        
        return $this->respond([
            'success' => true,
            'hash' => $hash
        ]);
    }
    
    /**
     * Request real hash from LIGO API
     *
     * @param string $id
     * @return mixed
     */
    public function requestRealHash($id = null)
    {
        log_message('info', '[requestRealHash] Called with ID: ' . ($id ?? 'null'));
        
        if (!$id) {
            log_message('error', '[requestRealHash] No ID provided');
            return $this->failValidationErrors('Hash ID is required');
        }
        
        $hash = $this->ligoQRHashModel->find($id);
        log_message('info', '[requestRealHash] Hash found: ' . ($hash ? 'Yes' : 'No'));
        
        if (!$hash) {
            log_message('error', '[requestRealHash] Hash not found for ID: ' . $id);
            return $this->failNotFound('Hash not found');
        }
        
        // Si ya tiene hash real, no solicitar de nuevo
        if (!empty($hash['real_hash'])) {
            return $this->respond([
                'success' => true,
                'message' => 'Hash real ya existe',
                'hash' => $hash['real_hash']
            ]);
        }
        
        try {
            // Obtener la organización de la factura
            $organization = null;
            if ($hash['invoice_id']) {
                $invoiceModel = new \App\Models\InvoiceModel();
                $invoice = $invoiceModel->find($hash['invoice_id']);
                if ($invoice) {
                    $organization = $this->organizationModel->find($invoice['organization_id']);
                }
            }
            
            if (!$organization) {
                return $this->fail('Organization not found', 400);
            }
            
            // Obtener token de autenticación
            $authToken = $this->getLigoAuthToken($organization);
            
            if (isset($authToken->error)) {
                $this->ligoQRHashModel->update($id, [
                    'hash_error' => 'Auth error: ' . $authToken->error
                ]);
                return $this->fail($authToken->error, 400);
            }
            
            // Solicitar detalles del QR usando el order_id
            $qrDetails = $this->getQRDetailsById($hash['order_id'], $authToken->token, $organization);
            
            if (isset($qrDetails->error)) {
                $this->ligoQRHashModel->update($id, [
                    'hash_error' => 'API error: ' . $qrDetails->error
                ]);
                return $this->fail($qrDetails->error, 400);
            }
            
            // Extraer el hash real de la respuesta
            $realHash = null;
            if (isset($qrDetails->data->hash)) {
                $realHash = $qrDetails->data->hash;
            } else if (isset($qrDetails->data->qr)) {
                $realHash = $qrDetails->data->qr;
            } else if (isset($qrDetails->data->qrString)) {
                $realHash = $qrDetails->data->qrString;
            }
            
            if ($realHash) {
                // Actualizar el hash real en la base de datos
                $this->ligoQRHashModel->update($id, [
                    'real_hash' => $realHash,
                    'hash_error' => null
                ]);
                
                log_message('info', 'Hash real obtenido para ID ' . $id . ': ' . substr($realHash, 0, 50));
                
                return $this->respond([
                    'success' => true,
                    'message' => 'Hash real obtenido exitosamente',
                    'hash' => $realHash
                ]);
            } else {
                $errorMsg = 'QR hash not available from Ligo (QR may have expired or been consumed)';
                $this->ligoQRHashModel->update($id, [
                    'hash_error' => $errorMsg . '. Response: ' . json_encode($qrDetails)
                ]);
                return $this->fail($errorMsg, 400);
            }
            
        } catch (\Exception $e) {
            $errorMsg = 'Exception: ' . $e->getMessage();
            log_message('error', 'Error requesting real hash for ID ' . $id . ': ' . $errorMsg);
            
            $this->ligoQRHashModel->update($id, [
                'hash_error' => $errorMsg
            ]);
            
            return $this->fail($errorMsg, 500);
        }
    }
    
    /**
     * Get authentication token from Ligo API
     *
     * @param array $organization Organization with Ligo credentials
     * @return object Response with token or error
     */
    private function getLigoAuthToken($organization)
    {
        // Verificar si hay un token almacenado y si aún es válido
        if (!empty($organization['ligo_token']) && !empty($organization['ligo_token_expiry'])) {
            $expiryDate = strtotime($organization['ligo_token_expiry']);
            $now = time();
            
            // Si el token aún es válido (con 5 minutos de margen), usarlo
            if ($expiryDate > ($now + 300)) {
                log_message('info', 'Using stored valid token until: ' . $organization['ligo_token_expiry']);
                return (object)[
                    'token' => $organization['ligo_token']
                ];
            }
        }
        
        // Si no hay token válido almacenado, intentar obtener uno nuevo
        $credentials = $this->getLigoCredentials($organization);
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            return (object)['error' => 'Incomplete Ligo credentials'];
        }
        
        try {
            // Verificar que la clave privada exista
            if (empty($credentials['private_key'])) {
                return (object)['error' => 'Ligo private key not configured'];
            }
            
            // Cargar la clase JwtGenerator
            $privateKey = $credentials['private_key'];
            $formattedKey = \App\Libraries\JwtGenerator::formatPrivateKey($privateKey);
            
            // Preparar payload
            $payload = [
                'companyId' => $credentials['company_id']
            ];
            
            // Generar token JWT
            $authorizationToken = \App\Libraries\JwtGenerator::generateToken($payload, $formattedKey, [
                'issuer' => 'ligo',
                'audience' => 'ligo-calidad.com',
                'subject' => 'ligo@gmail.com',
                'expiresIn' => 3600 // 1 hora
            ]);
            
            // Get centralized Ligo configuration for auth URL
            $ligoConfig = $this->getLigoConfig();
            if (!$ligoConfig) {
                return (object)['error' => 'Ligo URL configuration not available'];
            }
            
            $authUrl = $ligoConfig['auth_base_url'] . "/v1/auth/sign-in?companyId=" . $credentials['company_id'];
            
            $curl = curl_init();
            
            // Datos de autenticación para la solicitud POST
            $authData = [
                'username' => $credentials['username'],
                'password' => $credentials['password']
            ];
            $requestBody = json_encode($authData);
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $authUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $requestBody,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $authorizationToken,
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($requestBody)
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if ($err) {
                return (object)['error' => 'cURL error: ' . $err];
            }
            
            if ($info['http_code'] != 200) {
                return (object)['error' => 'HTTP error: ' . $info['http_code'] . ' - ' . $response];
            }
            
            $decoded = json_decode($response);
            
            if (!$decoded || !isset($decoded->data) || !isset($decoded->data->access_token)) {
                return (object)['error' => 'No token in auth response: ' . $response];
            }
            
            // Guardar el token en la base de datos para futuros usos
            try {
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $this->organizationModel->update($organization['id'], [
                    'ligo_token' => $decoded->data->access_token,
                    'ligo_token_expiry' => $expiryDate,
                    'ligo_auth_error' => null
                ]);
            } catch (\Exception $e) {
                log_message('error', 'Error saving token: ' . $e->getMessage());
            }
            
            return (object)[
                'token' => $decoded->data->access_token
            ];
            
        } catch (\Exception $e) {
            return (object)['error' => 'Exception in auth: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get QR details by ID from Ligo API
     *
     * @param string $qrId QR ID
     * @param string $token Authentication token
     * @param array $organization Organization data
     * @return object Response from Ligo API
     */
    private function getQRDetailsById($qrId, $token, $organization)
    {
        try {
            $curl = curl_init();
            
            // Get centralized Ligo configuration for API URL
            $ligoConfig = $this->getLigoConfig();
            if (!$ligoConfig) {
                return (object)['error' => 'Ligo URL configuration not available'];
            }
            
            $url = $ligoConfig['api_base_url'] . '/v1/getCreateQRById/' . $qrId;
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            
            $response = curl_exec($curl);
            $info = curl_getinfo($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
                return (object)['error' => 'cURL Error: ' . $err];
            }
            
            $decoded = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return (object)['error' => 'Invalid JSON: ' . json_last_error_msg()];
            }
            
            if (!isset($decoded->data)) {
                return (object)['error' => 'No data in response: ' . $response];
            }
            
            // Check if data is empty object (QR expired or not found)
            if (is_object($decoded->data) && empty((array)$decoded->data)) {
                return (object)['error' => 'QR not found in Ligo (may have expired)'];
            }
            
            return $decoded;
        } catch (\Exception $e) {
            return (object)['error' => 'Exception: ' . $e->getMessage()];
        }
    }
}

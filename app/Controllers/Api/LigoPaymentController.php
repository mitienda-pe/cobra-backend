<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class LigoPaymentController extends ResourceController
{
    use ResponseTrait;
    
    protected $invoiceModel;
    protected $organizationModel;
    protected $user;
    
    public function __construct()
    {
        $this->invoiceModel = new \App\Models\InvoiceModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * Generate QR code for invoice payment via mobile app
     *
     * @param string $invoiceId
     * @return mixed
     */
    public function generateQR($invoiceId = null)
    {
        if (!$invoiceId) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        // Get invoice details
        $invoice = $this->invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return $this->fail('Invoice not found', 404);
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Prepare order data for Ligo
        $orderData = [
            'amount' => $invoice['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $invoice['id'],
            'description' => "Pago factura #{$invoice['invoice_number']}"
        ];
        
        // Create order in Ligo
        $response = $this->createLigoOrder($orderData, $organization);
        
        if (isset($response->error)) {
            return $this->fail($response->error, 400);
        }
        
        return $this->respond([
            'success' => true,
            'qr_data' => $response->qr_data ?? null,
            'qr_image_url' => $response->qr_image_url ?? null,
            'order_id' => $response->order_id ?? null,
            'expiration' => $response->expiration ?? null
        ]);
    }
    
    /**
     * Generate QR code for instalment payment via mobile app
     *
     * @param string $instalmentId
     * @return mixed
     */
    public function generateInstalmentQR($instalmentId = null)
    {
        log_message('info', 'LigoPaymentController::generateInstalmentQR called with instalmentId: ' . $instalmentId);
        
        if (!$instalmentId) {
            return $this->fail('Instalment ID is required', 400);
        }
        
        // Get instalment details
        $instalmentModel = new \App\Models\InstalmentModel();
        $instalment = $instalmentModel->find($instalmentId);
        
        if (!$instalment) {
            return $this->fail('Instalment not found', 404);
        }
        
        // Get invoice details
        $invoiceModel = new \App\Models\InvoiceModel();
        $invoice = $invoiceModel->find($instalment['invoice_id']);
        
        if (!$invoice) {
            return $this->fail('Invoice not found for this instalment', 404);
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Get the invoice number from the appropriate field
        $invoiceNumber = $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A';
        
        // Prepare order data for Ligo
        $orderData = [
            'amount' => $instalment['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $instalment['id'],
            'description' => "Pago cuota #{$instalment['number']} de factura #{$invoiceNumber}",
            'qr_type' => 'dynamic'
        ];
        
        // Log the invoice and instalment data for debugging
        log_message('debug', 'Invoice data: ' . json_encode($invoice));
        log_message('debug', 'Instalment data: ' . json_encode($instalment));
        log_message('debug', 'Order data for QR generation: ' . json_encode($orderData));
        
        // Get auth token
        $authToken = $this->getAuthToken($organization);
        
        if (isset($authToken->error)) {
            return $this->fail($authToken->error, 400);
        }
        
        // URL para generar QR según la documentación
        $prefix = 'dev'; // Cambiar a 'dev' para entorno de desarrollo
        $url = "https://cce-api-gateway-{$prefix}.ligocloud.tech/v1/createQr";
        
        // Asegurar que tenemos valores válidos para los campos requeridos
        $idCuenta = !empty($organization['ligo_account_id']) ? $organization['ligo_account_id'] : '92100178794744781044';
        $codigoComerciante = !empty($organization['ligo_merchant_code']) ? $organization['ligo_merchant_code'] : '4829';
        
        // Preparar datos para la generación de QR según la documentación
        $qrData = [
            'header' => [
                'sisOrigen' => '0921'
            ],
            'data' => [
                'qrTipo' => '12', // QR dinámico con monto
                'idCuenta' => $idCuenta,
                'moneda' => $orderData['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                'importe' => (int)($orderData['amount'] * 100), // Convertir a centavos
                'codigoComerciante' => $codigoComerciante,
                'nombreComerciante' => $organization['name'],
                'ciudadComerciante' => $organization['city'] ?? 'Lima',
                'glosa' => $orderData['description'],
                'info' => [
                    [
                        'codigo' => 'instalment_id',
                        'valor' => $instalment['id']
                    ],
                    [
                        'codigo' => 'invoice_id',
                        'valor' => $invoice['id']
                    ],
                    [
                        'codigo' => 'nombreCliente',
                        'valor' => $organization['name'] ?? 'Cliente'
                    ],
                    [
                        'codigo' => 'documentoIdentidad',
                        'valor' => $organization['tax_id'] ?? '00000000'
                    ]
                ]
            ],
            'type' => 'TEXT'
        ];
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($qrData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $authToken->token,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        // Registrar información detallada de la solicitud y respuesta
        log_message('debug', 'Solicitud a Ligo - URL: ' . $url);
        log_message('debug', 'Solicitud a Ligo - Datos: ' . json_encode($qrData));
        log_message('debug', 'Solicitud a Ligo - Headers: Authorization Bearer ' . substr($authToken->token, 0, 20) . '...');
        log_message('debug', 'Respuesta de Ligo - HTTP Code: ' . $info['http_code']);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return $this->fail('Failed to connect to Ligo API: ' . $err, 400);
        }
        
        $decoded = json_decode($response);
        
        if (!$decoded || !isset($decoded->data) || !isset($decoded->data->id)) {
            log_message('error', 'Invalid response from Ligo API: ' . $response);
            return $this->fail('Invalid response from Ligo API', 400);
        }
        
        // Crear objeto de respuesta con formato estandarizado
        $qrData = json_encode([
            'id' => $decoded->data->id,
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'description' => $orderData['description'],
            'merchant' => $organization['name'],
            'timestamp' => time(),
            'hash' => 'LIGO-' . $decoded->data->id,
            'instalment_id' => $instalment['id'],
            'invoice_id' => $invoice['id']
        ]);
        
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrData);
        
        return $this->respond([
            'success' => true,
            'qr_data' => $qrData,
            'qr_image_url' => $qrImageUrl,
            'order_id' => $decoded->data->id,
            'instalment_id' => $instalment['id'],
            'invoice_id' => $invoice['id']
        ]);
    }
    
    /**
     * Generate static QR code for organization via mobile app
     * This QR code doesn't have a predefined amount and can be used for multiple payments
     *
     * @param string $organizationId
     * @return mixed
     */
    public function generateStaticQR($organizationId = null)
    {
        if (!$organizationId) {
            return $this->failValidationErrors('Organization ID is required');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($organizationId);
        
        if (!$organization) {
            // Try to find by UUID
            $organization = $this->organizationModel->where('uuid', $organizationId)->first();
            
            if (!$organization) {
                return $this->fail('Organization not found', 404);
            }
        }
        
        // Check if user has access to this organization
        if (!$this->canAccessOrganization($organization)) {
            return $this->failForbidden('You do not have access to this organization');
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        
        // Check if Ligo credentials are configured
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // Prepare order data for Ligo
        $orderData = [
            'amount' => null, // No amount for static QR
            'currency' => 'PEN',
            'orderId' => 'static-' . $organization['id'] . '-' . time(),
            'description' => 'QR Estático para ' . $organization['name'],
            'qr_type' => 'static'
        ];
        
        // Create order in Ligo
        $response = $this->createLigoOrder($orderData, $organization);
        
        if (isset($response->error)) {
            return $this->fail($response->error, 400);
        }
        
        return $this->respond([
            'success' => true,
            'organization_name' => $organization['name'],
            'qr_data' => $response->qr_data ?? null,
            'qr_image_url' => $response->qr_image_url ?? null,
            'order_id' => $response->order_id ?? null
        ]);
    }
    
    /**
     * Check if user can access organization
     *
     * @param array $organization
     * @return bool
     */
    private function canAccessOrganization($organization)
    {
        // Superadmin can access any organization
        if ($this->user['role'] === 'superadmin') {
            return true;
        }
        
        // Admin can access their own organization
        if ($this->user['role'] === 'admin' && $this->user['organization_id'] == $organization['id']) {
            return true;
        }
        
        // Regular users can access their organization
        if ($this->user['organization_id'] == $organization['id']) {
            return true;
        }
        
        return false;
    }
    

    
    /**
     * Create order in Ligo API
     *
     * @param array $data Order data
     * @param array $organization Organization with Ligo credentials
     * @return object Response from Ligo API
     */
    private function createLigoOrder($data, $organization)
    {
        $curl = curl_init();
        
        // Obtener token de autenticación si no existe o está vencido
        $authToken = $this->getAuthToken($organization);
        
        if (isset($authToken->error)) {
            return (object)['error' => $authToken->error];
        }
        
        // URL para generar QR según la documentación
        $prefix = 'dev'; // Cambiar a 'dev' para entorno de desarrollo
        $url = "https://cce-api-gateway-{$prefix}.ligocloud.tech/v1/createQr";
        
        // Asegurar que tenemos valores válidos para los campos requeridos
        $idCuenta = !empty($organization['ligo_account_id']) ? $organization['ligo_account_id'] : '92100178794744781044';
        $codigoComerciante = !empty($organization['ligo_merchant_code']) ? $organization['ligo_merchant_code'] : '4829';
        
        // Determinar el tipo de QR a generar (estático o dinámico)
        $qrTipo = isset($data['qr_type']) && $data['qr_type'] === 'static' ? '11' : '12';
        
        // Preparar datos para la generación de QR según la documentación
        $qrData = [
            'header' => [
                'sisOrigen' => '0921'
            ],
            'data' => [
                'qrTipo' => $qrTipo, // 11 = Estático, 12 = Dinámico con monto
                'idCuenta' => $idCuenta,
                'moneda' => $data['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                'codigoComerciante' => $codigoComerciante,
                'nombreComerciante' => $organization['name'],
                'ciudadComerciante' => $organization['city'] ?? 'Lima'
            ],
            'type' => 'TEXT'
        ];
        
        // Agregar campos adicionales para QR dinámico
        if ($qrTipo === '12') {
            $qrData['data']['importe'] = (int)($data['amount'] * 100); // Convertir a centavos
            $qrData['data']['glosa'] = $data['description'];
            $qrData['data']['info'] = [
                [
                    'codigo' => 'invoice_id',
                    'valor' => $data['orderId']
                ],
                [
                    'codigo' => 'nombreCliente',
                    'valor' => $organization['name'] ?? 'Cliente'
                ],
                [
                    'codigo' => 'documentoIdentidad',
                    'valor' => $organization['tax_id'] ?? '00000000'
                ]
            ];
        } else {
            // Para QR estático estos campos son null
            $qrData['data']['importe'] = null;
            $qrData['data']['glosa'] = null;
            $qrData['data']['info'] = null;
        }
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($qrData),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $authToken->token,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0, // Deshabilitar verificación de host SSL
            CURLOPT_SSL_VERIFYPEER => false, // Deshabilitar verificación de certificado SSL
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        // Registrar información detallada de la solicitud y respuesta
        log_message('debug', 'Solicitud a Ligo - URL: ' . $url);
        log_message('debug', 'Solicitud a Ligo - Datos: ' . json_encode($qrData));
        log_message('debug', 'Solicitud a Ligo - Headers: Authorization Bearer ' . substr($authToken->token, 0, 20) . '...');
        log_message('debug', 'Respuesta de Ligo - HTTP Code: ' . $info['http_code']);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
        }
        
        $decoded = json_decode($response);
        
        if (!$decoded || !isset($decoded->data) || !isset($decoded->data->id)) {
            log_message('error', 'Invalid response from Ligo API: ' . $response);
            return (object)['error' => 'Invalid response from Ligo API'];
        }
        
        // Crear objeto de respuesta con formato estandarizado
        $result = new \stdClass();
        $result->qr_data = json_encode([
            'id' => $decoded->data->id,
            'amount' => $qrTipo === '12' ? $data['amount'] : null,
            'currency' => $data['currency'] ?? 'PEN',
            'description' => $data['description'] ?? null,
            'merchant' => $organization['name'],
            'timestamp' => time(),
            'hash' => 'LIGO-' . $decoded->data->id
        ]);
        $result->qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($result->qr_data);
        $result->order_id = $decoded->data->id;
        
        return $result;
    }
    
    /**
     * Get authentication token for Ligo API
     * 
     * @param array $organization Organization with Ligo credentials
     * @return object Token object with token and expiry or error
     */
    private function getAuthToken($organization)
    {
        // Check if token exists and is not expired
        if (!empty($organization['ligo_token']) && !empty($organization['ligo_token_expiry'])) {
            $tokenExpiry = strtotime($organization['ligo_token_expiry']);
            if ($tokenExpiry > time()) {
                // Token is still valid
                $token = new \stdClass();
                $token->token = $organization['ligo_token'];
                $token->expiry = $organization['ligo_token_expiry'];
                return $token;
            }
        }
        
        // Token doesn't exist or is expired, get a new one
        $curl = curl_init();
        
        // URL para autenticación según la documentación
        $prefix = 'dev'; // Cambiar a 'dev' para entorno de desarrollo
        $url = "https://cce-auth-{$prefix}.ligocloud.tech/v1/auth/sign-in?companyId={$organization['ligo_company_id']}";
        
        // Datos de autenticación
        $authData = [
            'username' => $organization['ligo_username'],
            'password' => $organization['ligo_password']
        ];
        
        $companyId = trim($organization['ligo_company_id']);
        
        // Generar el token de autorización usando la llave privada almacenada en la organización
        if (!empty($organization['ligo_private_key'])) {
            try {
                // Usar la biblioteca Firebase JWT para generar el token
                require_once APPPATH . '../vendor/autoload.php';
                
                // Definir los datos del payload
                $now = time();
                $payload = [
                    'companyId' => $companyId,
                    'iat' => $now,
                    'exp' => $now + 3600, // Expira en 1 hora
                    'aud' => 'ligo-calidad.com',
                    'iss' => 'ligo',
                    'sub' => 'ligo@gmail.com'
                ];
                
                // Opciones de firma
                $privateKey = trim($organization['ligo_private_key']);
                
                // Generar el token
                $authorizationToken = \Firebase\JWT\JWT::encode($payload, $privateKey, 'RS256');
                
                log_message('info', 'API: Token de autorización Ligo generado correctamente para la organización ID: ' . $organization['id']);
            } catch (\Exception $e) {
                log_message('error', 'API: Error al generar token JWT: ' . $e->getMessage());
                // Usar un token predeterminado si hay error al generar el token
                $authorizationToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55SWQiOiJlOGI0YTM2ZC02ZjFkLTRhMmEtYmYzYS1jZTkzNzFkZGU0YWIiLCJpYXQiOjE3NDQxNjA2ODUsImV4cCI6MTc0NDE2NDI4NSwiYXVkIjoibGlnby1jYWxpZGFkLmNvbSIsImlzcyI6ImxpZ28iLCJzdWIiOiJsaWdvQGdtYWlsLmNvbSJ9.FDEMXiaDZsAMC7yZo_jpwS7QBAbEUz6qpy8mmnxed17HQO2nkrBAqfD51OLn2tsnFuYJQrJs4kMxyyr3-omOYxWGR1-U3Eyt4qUVEZZy6wdlMlGfxwd4CIjtSHwrNQoZc4Kcp4br7Y6MYIuIwC7Mb0H5Ul_QZ3WyYIYX9RKHFfplI1KJorD8dL2_piv3AifcdFmjyIMrK--UuXxfoeh4i_z3Wgt2DH0kkb8w8GSfYaKZKk10Ra8Yl16zqgVvjHy6PtBOEODXupPFzdz4aotZSE7d-FPuQSxKfwjH_Hemy1D6DFQZeEiOfMDC7PPw-9JQLUs99YmCy8cBYnY5_9VSfw';
            }
        } else {
            // Si no hay llave privada en la organización, usar un token predeterminado
            $authorizationToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55SWQiOiJlOGI0YTM2ZC02ZjFkLTRhMmEtYmYzYS1jZTkzNzFkZGU0YWIiLCJpYXQiOjE3NDQxNjA2ODUsImV4cCI6MTc0NDE2NDI4NSwiYXVkIjoibGlnby1jYWxpZGFkLmNvbSIsImlzcyI6ImxpZ28iLCJzdWIiOiJsaWdvQGdtYWlsLmNvbSJ9.FDEMXiaDZsAMC7yZo_jpwS7QBAbEUz6qpy8mmnxed17HQO2nkrBAqfD51OLn2tsnFuYJQrJs4kMxyyr3-omOYxWGR1-U3Eyt4qUVEZZy6wdlMlGfxwd4CIjtSHwrNQoZc4Kcp4br7Y6MYIuIwC7Mb0H5Ul_QZ3WyYIYX9RKHFfplI1KJorD8dL2_piv3AifcdFmjyIMrK--UuXxfoeh4i_z3Wgt2DH0kkb8w8GSfYaKZKk10Ra8Yl16zqgVvjHy6PtBOEODXupPFzdz4aotZSE7d-FPuQSxKfwjH_Hemy1D6DFQZeEiOfMDC7PPw-9JQLUs99YmCy8cBYnY5_9VSfw';
            log_message('warning', 'API: No se encontró llave privada para generar el token de autorización Ligo. Usando token predeterminado.');
        }
        
        log_message('debug', 'URL de autenticación Ligo API: ' . $url);
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
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
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo Auth Error: ' . $err);
            return (object)['error' => 'Failed to authenticate with Ligo API: ' . $err];
        }
        
        $decoded = json_decode($response);
        
        if (!$decoded || !isset($decoded->data) || !isset($decoded->data->access_token)) {
            log_message('error', 'Invalid auth response from Ligo API: ' . $response);
            
            // Extraer mensaje de error
            $errorMsg = 'Invalid authentication response from Ligo API';
            if (isset($decoded->message)) {
                $errorMsg = $decoded->message;
            } elseif (isset($decoded->error)) {
                $errorMsg = $decoded->error;
            }
            
            return (object)['error' => $errorMsg];
        }
        
        // Create token object
        $token = new \stdClass();
        $token->token = $decoded->data->access_token;
        
        // Calculate expiry (usually 1 hour)
        $expiry = time() + 3600; // 1 hour from now
        $token->expiry = date('Y-m-d H:i:s', $expiry);
        
        // Update organization with new token
        $this->organizationModel->update($organization['id'], [
            'ligo_token' => $token->token,
            'ligo_token_expiry' => $token->expiry
        ]);
        
        return $token;
    }
    
    /**
     * Check if user can access an invoice
     * 
     * @param array $invoice
     * @return bool
     */
    private function canAccessInvoice($invoice)
    {
        // If user is not set (mobile app might be using token auth without session)
        if (!$this->user) {
            // For mobile app requests, we'll allow access if the request has a valid token
            // The apiAuth filter has already validated the token at this point
            log_message('debug', 'canAccessInvoice: User not found in session, but token is valid. Allowing access.');            
            return true;
        }
        
        // Superadmin can access any invoice
        if (isset($this->user['role']) && $this->user['role'] === 'superadmin') {
            return true;
        }
        
        // Admin can access invoices from their organization
        if (isset($this->user['role']) && $this->user['role'] === 'admin' && 
            isset($this->user['organization_id']) && $this->user['organization_id'] == $invoice['organization_id']) {
            return true;
        }
        
        // For regular users, check if they have access to the client through portfolios
        if (isset($this->user['role']) && $this->user['role'] === 'user') {
            $portfolioModel = new \App\Models\PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            // Get all client IDs from user's portfolios
            $clientIds = [];
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $client) {
                    $clientIds[] = $client['id'];
                }
            }
            
            return in_array($invoice['client_id'], $clientIds);
        }
        
        return false;
    }
}

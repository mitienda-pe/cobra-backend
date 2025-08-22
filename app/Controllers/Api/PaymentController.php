<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Models\InstalmentModel;

class PaymentController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    protected $paymentModel;
    
    public function __construct()
    {
        $this->organizationModel = new \App\Models\OrganizationModel();
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
        
        // Si el usuario no está en la sesión, intentar obtenerlo del request
        $request = service('request');
        if (empty($this->user) && property_exists($request, 'user')) {
            $this->user = $request->user;
            log_message('debug', 'Usuario obtenido desde request: ' . json_encode($this->user));
        }
        
        // Verificar si el usuario está definido
        if (empty($this->user)) {
            log_message('error', 'PaymentController: Usuario no definido en constructor');
        } else {
            log_message('debug', 'PaymentController: Usuario inicializado correctamente: ' . json_encode($this->user));
        }
        
        $this->paymentModel = new \App\Models\PaymentModel();
    }
    
    /**
     * Get centralized Ligo credentials from superadmin configuration
     */
    private function getLigoCredentials($organization = null)
    {
        // Use centralized superadmin configuration - same method as web controller
        $ligoModel = new \App\Models\LigoModel();
        $config = $ligoModel->getSuperadminLigoConfig();
        
        if (!$config) {
            log_message('error', 'API PaymentController: No centralized Ligo config found');
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

        log_message('debug', 'API PaymentController: Using centralized credentials (' . $config['environment'] . ')');
        
        $credentials = [
            'username' => $config['username'],
            'password' => $config['password'],
            'company_id' => $config['company_id'],
            'account_id' => $config['account_id'],
            'merchant_code' => $config['merchant_code'] ?? null,
            'private_key' => $config['private_key'],
            'webhook_secret' => $config['webhook_secret'] ?? null,
        ];
        
        // Manual password decryption if needed
        if (isset($credentials['password']) && strpos($credentials['password'], 'ENC:') === 0) {
            $credentials['password'] = base64_decode(substr($credentials['password'], 4));
        }
        
        return $credentials;
    }

    /**
     * Get centralized Ligo configuration for API endpoints URLs
     * @return array|false Ligo configuration array or false if not found
     */
    private function getLigoConfig()
    {
        // Use centralized superadmin configuration - same method as web controller
        $ligoModel = new \App\Models\LigoModel();
        $config = $ligoModel->getSuperadminLigoConfig();
        
        if (!$config) {
            log_message('error', 'API PaymentController: No active Ligo configuration found');
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
    
    // Los métodos de generación de QR se han movido a LigoPaymentController
    // para evitar redundancia y mantener un único punto de entrada

    /**
     * Generate QR code for instalment payment via mobile app (for mobile compatibility)
     * Mirrors LigoPaymentController::generateInstalmentQR but saves hash here as well
     * @param string $instalmentId
     * @return mixed
     */
    public function generateInstalmentQR($instalmentId = null)
    {
        log_message('error', '[API QR] generateInstalmentQR llamado con instalmentId: ' . $instalmentId);
        
        if (!$instalmentId) {
            return $this->fail('Instalment ID is required', 400);
        }
        $instalmentModel = new \App\Models\InstalmentModel();
        $instalment = $instalmentModel->find($instalmentId);
        if (!$instalment) {
            return $this->fail('Instalment not found', 404);
        }
        $invoiceModel = new \App\Models\InvoiceModel();
        $invoice = $invoiceModel->find($instalment['invoice_id']);
        if (!$invoice) {
            return $this->fail('Invoice not found for this instalment', 404);
        }
        // Access control, same logic as canAccessInvoice
        if (method_exists($this, 'canAccessInvoice') && !$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        $organization = $this->organizationModel->find($invoice['organization_id']);
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        $credentials = $this->getLigoCredentials($organization);
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        
        // --- NUEVO: Revisar si ya existe un hash válido para este instalment (cache de 60 min) ---
        $qrHashModel = new \App\Models\LigoQRHashModel();
        $cacheMinutes = 60; // Match QR expiration time (1 hour)
        $cacheTime = date('Y-m-d H:i:s', strtotime("-{$cacheMinutes} minutes"));
        
        $existingQR = $qrHashModel
            ->where('instalment_id', $instalmentId)
            ->where('created_at >', $cacheTime) // Only valid cache entries
            ->orderBy('created_at', 'desc')
            ->first();
            
            
        // TEMP FIX: Disable cache to force fresh QR generation
        if (false && $existingQR && !empty($existingQR['hash'])) {
            // Devolver el QR guardado (mismo formato que respuesta normal)
            return $this->respond([
                'success' => true,
                'qr_data' => json_encode([
                    'id' => $existingQR['order_id'] ?? null,
                    'amount' => $existingQR['amount'] ?? $instalment['amount'],
                    'currency' => $existingQR['currency'] ?? $invoice['currency'],
                    'description' => $existingQR['description'] ?? '',
                    'merchant' => $organization['name'],
                    'timestamp' => strtotime($existingQR['created_at']),
                    'hash' => $existingQR['real_hash'] ?? $existingQR['hash'],
                    'instalment_id' => $instalmentId,
                    'invoice_id' => $invoice['id']
                ]),
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode(json_encode([
                    'id' => $existingQR['order_id'] ?? null,
                    'amount' => $existingQR['amount'] ?? $instalment['amount'],
                    'currency' => $existingQR['currency'] ?? $invoice['currency'],
                    'description' => $existingQR['description'] ?? '',
                    'merchant' => $organization['name'],
                    'timestamp' => strtotime($existingQR['created_at']),
                    'hash' => $existingQR['real_hash'] ?? $existingQR['hash'],
                    'instalment_id' => $instalmentId,
                    'invoice_id' => $invoice['id']
                ])),
                'order_id' => $existingQR['order_id'] ?? null,
                'instalment_id' => $instalmentId,
                'invoice_id' => $invoice['id'],
                'created_at' => $existingQR['created_at'],
                'expires_at' => date('Y-m-d H:i:s', strtotime($existingQR['created_at'] . ' +1 hour'))
            ]);
        }
        // --- FIN NUEVO ---
        // Obtener token de Ligo
        $authToken = $this->getLigoAuthToken($organization);
        if (isset($authToken['error'])) {
            return $this->fail($authToken['error'], 400);
        }
        // Preparar datos para Ligo (igual que antes)
        $orderData = [
            'amount' => $instalment['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $instalment['id'],
            'description' => "Pago cuota #{$instalment['number']} de factura #{$invoice['invoice_number']}"
        ];
        // Aquí iría la llamada a Ligo y el guardado del hash, igual que antes
        // ...
        // (El resto del método sigue igual)

        if (method_exists($this, 'canAccessInvoice') && !$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($invoice['organization_id']);
        if (!$organization) {
            return $this->fail('Organization not found', 404);
        }
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->fail('Ligo payments not enabled for this organization', 400);
        }
        $credentials = $this->getLigoCredentials($organization);
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            return $this->fail('Ligo API credentials not configured', 400);
        }
        $invoiceNumber = $invoice['invoice_number'] ?? 'N/A';
        $orderData = [
            'amount' => $instalment['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $instalment['id'],
            'description' => "Pago cuota #{$instalment['number']} de factura #{$invoiceNumber}",
            'qr_type' => 'dynamic'
        ];
        log_message('debug', 'Invoice data: ' . json_encode($invoice));
        log_message('debug', 'Instalment data: ' . json_encode($instalment));
        log_message('debug', 'Order data for QR generation: ' . json_encode($orderData));
        // Get auth token (reuse logic from LigoPaymentController)
        $authToken = $this->getLigoAuthToken($organization);
        if (isset($authToken['error'])) {
            return $this->fail($authToken['error'], 400);
        }
        // Get centralized credentials and environment from superadmin config
        $credentials = $this->getLigoCredentials($organization);
        
        // Get centralized Ligo configuration for URL
        $ligoConfig = $this->getLigoConfig();
        if (!$ligoConfig) {
            log_message('error', 'PaymentController API: No valid centralized Ligo URL configuration found');
            return $this->fail('Error de configuración: configuración de Ligo no disponible', 500);
        }
        
        $url = $ligoConfig['api_base_url'] . '/v1/createQr';
        $idCuenta = !empty($credentials['account_id']) ? $credentials['account_id'] : '92100178794744781044';
        $codigoComerciante = !empty($credentials['merchant_code']) ? $credentials['merchant_code'] : '4829';
        // Calcular fecha de vencimiento: 2 días posteriores a hoy (mismo que web)
        $fechaVencimiento = date('Ymd', strtotime('+2 days'));
        
        $qrData = [
            'header' => [ 'sisOrigen' => '0921' ],
            'data' => [
                'qrTipo' => '12',
                'idCuenta' => $idCuenta,
                'moneda' => $orderData['currency'] == 'PEN' ? '604' : '840',
                'importe' => (int)($orderData['amount'] * 100),
                'fechaVencimiento' => $fechaVencimiento,
                'cantidadPagos' => 1, // Cantidad de pagos permitidos por QR
                'codigoComerciante' => $codigoComerciante,
                'nombreComerciante' => $organization['name'],
                'ciudadComerciante' => $organization['city'] ?? 'Lima',
                'glosa' => $orderData['description'],
                'info' => [
                    [ 'codigo' => 'instalment_id', 'valor' => $instalment['id'] ],
                    [ 'codigo' => 'invoice_id', 'valor' => $invoice['id'] ]
                ]
            ],
            'type' => 'TEXT'
        ];
        $curl = curl_init();
        // LOG DE DEPURACIÓN: payload y token
        log_message('debug', 'LIGO DEBUG qrData: ' . json_encode($qrData));
        log_message('debug', 'LIGO DEBUG token: ' . $authToken['token']);
        // Log de payload real enviado a Ligo
        log_message('error', 'PaymentController LIGO PAYLOAD: ' . json_encode($qrData));
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
                'Authorization: Bearer ' . $authToken['token'],
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($curl);
        // Log de respuesta cruda de Ligo
        log_message('error', 'PaymentController LIGO RESPONSE: ' . $response);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        log_message('debug', 'Solicitud a Ligo - URL: ' . $url);
        log_message('debug', 'Solicitud a Ligo - Datos: ' . json_encode($qrData));
        log_message('debug', 'Solicitud a Ligo - Headers: Authorization Bearer ' . substr($authToken['token'], 0, 20) . '...');
        log_message('debug', 'Respuesta de Ligo - HTTP Code: ' . $info['http_code']);
        curl_close($curl);
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return $this->fail('Failed to connect to Ligo API: ' . $err, 400);
        }
        
        log_message('debug', 'PaymentController - Raw response from createQr: ' . $response);
        $decoded = json_decode($response);
        log_message('debug', 'PaymentController - Decoded response: ' . json_encode($decoded));
        
        if (!$decoded) {
            log_message('error', 'Failed to decode JSON response: ' . json_last_error_msg());
            return $this->fail('Failed to decode JSON response', 400);
        }
        
        if (!isset($decoded->data)) {
            log_message('error', 'No data field in response: ' . json_encode($decoded));
            return $this->fail('No data field in response', 400);
        }
        
        if (!isset($decoded->data->id)) {
            log_message('error', 'No ID field in data: ' . json_encode($decoded->data));
            return $this->fail('No ID field in response data', 400);
        }
        
        // Obtener el hash real usando getCreateQRByID
        $qrId = $decoded->data->id;
        
        // Agregar un pequeño delay para que LIGO procese el QR
        sleep(2);
        
        $qrDetails = $this->getQRDetailsById($qrId, $authToken['token'], $organization);
        
        if (isset($qrDetails->error)) {
            log_message('error', 'Error al obtener detalles del QR para instalment en PaymentController: ' . $qrDetails->error);
            return $this->fail('Error obtaining QR details: ' . $qrDetails->error, 400);
        }
        
        // Verificar si data está vacío
        if (empty($qrDetails->data) || !is_object($qrDetails->data)) {
            log_message('warning', 'getCreateQRByID returned empty data. QR may not be ready yet. Using ID as fallback.');
            $qrHash = $qrId; // Usar el ID como fallback temporal
        } else {
            // Extraer el hash real de la respuesta
            $qrHash = null;
            if (isset($qrDetails->data->hash)) {
                $qrHash = $qrDetails->data->hash;
                log_message('info', 'Found hash in data.hash: ' . substr($qrHash, 0, 50) . '...');
            } else if (isset($qrDetails->data->qr)) {
                $qrHash = $qrDetails->data->qr;
                log_message('info', 'Found hash in data.qr: ' . substr($qrHash, 0, 50) . '...');
            } else if (isset($qrDetails->data->qrString)) {
                $qrHash = $qrDetails->data->qrString;
                log_message('info', 'Found hash in data.qrString: ' . substr($qrHash, 0, 50) . '...');
            } else {
                // Usar el ID como fallback
                $qrHash = $qrId;
                log_message('warning', 'No hash found in getCreateQRByID response data. Available fields: ' . json_encode(array_keys((array)$qrDetails->data)));
            }
        }
        
        // Determinar si es hash real de LIGO o temporal
        $isRealHash = ($qrHash !== $qrId) && (strpos($qrHash, 'LIGO-') !== 0) && (strlen($qrHash) > 50);
        
        $qrDataArr = [
            'id' => $qrId,
            'amount' => $orderData['amount'],
            'currency' => $orderData['currency'],
            'description' => $orderData['description'],
            'merchant' => $organization['name'],
            'timestamp' => time(),
            'hash' => $qrHash,
            'instalment_id' => $instalment['id'],
            'invoice_id' => $invoice['id']
        ];
        $qrDataJson = json_encode($qrDataArr);
        // Generate QR image URL using the LIGO hash, not metadata
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrHash);
        
        // Guardar hash en la base de datos con las nuevas columnas
        log_message('error', '[LIGO] QR ID obtenido: ' . $qrId);
        log_message('error', '[LIGO] Hash obtenido de getCreateQRByID: ' . $qrHash);
        log_message('error', '[LIGO] Es hash real (no temporal): ' . ($isRealHash ? 'Sí' : 'No'));
        
        $hashModel = new \App\Models\LigoQRHashModel();
        
        $errorMessage = null;
        if (!$isRealHash) {
            if ($qrHash === $qrId) {
                $errorMessage = 'getCreateQRByID returned empty data - QR may not be ready yet';
            } else {
                $errorMessage = 'Hash temporal generado, necesita solicitar hash real';
            }
        }
        
        // Extract idQr from Ligo response for webhook matching
        $idQr = null;
        if (isset($qrDetails->data)) {
            $idQr = $qrDetails->data->idQr ?? 
                    $qrDetails->data->idqr ?? 
                    $qrDetails->data->id_qr ?? 
                    $qrDetails->data->qr_id ?? 
                    $qrDetails->data->id ?? 
                    $qrId ?? 
                    null;
        }
        log_message('error', '[LIGO_DEBUG] PaymentController - Extracted idQr: ' . json_encode($idQr) . ' from qrDetails');

        // Get organization environment for tracking
        $environment = $organization['ligo_environment'] ?? 'dev';
        
        $dataInsert = [
            'hash' => $qrId, // Hash ID (order_id) para la columna "Hash ID (Order)"
            'real_hash' => $isRealHash ? $qrHash : null, // Hash real solo si no es temporal
            'hash_error' => $errorMessage,
            'order_id' => $qrId,
            'id_qr' => $idQr, // Add idQr for webhook matching
            'invoice_id' => $invoice['id'],
            'instalment_id' => $instalment['id'],
            'amount' => $qrDataArr['amount'],
            'currency' => $qrDataArr['currency'],
            'description' => $qrDataArr['description'],
            'environment' => $environment
        ];
        
        $insertResult = $hashModel->insert($dataInsert);
        log_message('info', '[LIGO] Hash insertado en ligo_qr_hashes: ' . json_encode($dataInsert) . ' | Resultado: ' . json_encode($insertResult));
        return $this->respond([
            'success' => true,
            'qr_data' => [
                'id' => $qrDataArr['id'],
                'amount' => $qrDataArr['amount'],
                'currency' => $qrDataArr['currency'],
                'description' => $qrDataArr['description'],
                'merchant' => $qrDataArr['merchant'],
                'timestamp' => $qrDataArr['timestamp'],
                'hash' => $qrHash, // The actual LIGO payment hash
                'instalment_id' => $qrDataArr['instalment_id'],
                'invoice_id' => $qrDataArr['invoice_id']
            ],
            'qr_image_url' => $qrImageUrl,
            'order_id' => $decoded->data->id,
            'instalment_id' => $instalment['id'],
            'invoice_id' => $invoice['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ]);
    }

    /**
     * Get Ligo API Auth Token (utility for PaymentController)
     * @param array $organization
     * @return array [token => string] or [error => string]
     */
    private function getLigoAuthToken($organization)
    {
        
        try {
            // Use the same centralized LigoModel that the web uses
            $ligoModel = new \App\Models\LigoModel();
            $authResult = $ligoModel->getAuthenticationToken();
            
            if (isset($authResult['error'])) {
                log_message('error', 'API PaymentController: Error from LigoModel authentication: ' . $authResult['error']);
                return ['error' => $authResult['error']];
            }
            
            if (!isset($authResult['token'])) {
                log_message('error', 'API PaymentController: No token received from LigoModel');
                return ['error' => 'No authentication token received'];
            }
            
            
            // Return in the format expected by this controller
            return [
                'token' => $authResult['token'],
                'userId' => $authResult['userId'] ?? 'centralized-user',
                'companyId' => $authResult['companyId'] ?? 'centralized-company',
                'is_cached' => $authResult['is_cached'] ?? false
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'API PaymentController: Exception in getLigoAuthToken: ' . $e->getMessage());
            return ['error' => 'Authentication error: ' . $e->getMessage()];
        }
    }
    
    /**
     * List payments based on user role and filters
     */
    public function index()
    {
        $status = $this->request->getGet('status');
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $clientId = $this->request->getGet('client_id');
        
        // Different queries based on user role
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can see all payments for their organization
            $payments = $this->paymentModel->getByOrganization(
                $this->user['organization_id'],
                $status,
                $dateStart,
                $dateEnd
            );
            
            if ($clientId) {
                $payments = array_filter($payments, function($payment) use ($clientId) {
                    return $payment['client_id'] == $clientId;
                });
            }
        } else {
            // Regular users can only see payments from their assigned portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            $clientIds = [];
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $client) {
                    $clientIds[] = $client['id'];
                }
            }
            
            $payments = $this->paymentModel->getByClients($clientIds, $status);
            
            if ($dateStart) {
                $payments = array_filter($payments, function($payment) use ($dateStart) {
                    return $payment['payment_date'] >= $dateStart;
                });
            }
            
            if ($dateEnd) {
                $payments = array_filter($payments, function($payment) use ($dateEnd) {
                    return $payment['payment_date'] <= $dateEnd;
                });
            }
        }
        
        return $this->respond(['payments' => array_values($payments)]);
    }
    
    /**
     * Get a single payment
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Payment ID is required');
        }
        
        $payment = $this->paymentModel->find($id);
        
        if (!$payment) {
            return $this->failNotFound('Payment not found');
        }
        
        // Check if user has access to this payment
        if (!$this->canAccessPayment($payment)) {
            return $this->failForbidden('You do not have access to this payment');
        }
        
        return $this->respond(['payment' => $payment]);
    }
    
    /**
     * Search for invoices to register payments
     */
    public function searchInvoices()
    {
        $search = $this->request->getGet('search');
        $portfolioModel = new PortfolioModel();
        $clientModel = new ClientModel();
        
        if (!$search) {
            return $this->respond(['invoices' => []]);
        }
        
        // Get user's portfolio
        $portfolio = $portfolioModel->where('collector_id', $this->user['id'])->first();
        
        if (!$portfolio) {
            return $this->failForbidden('No portfolio assigned to this collector');
        }
        
        // Get clients assigned to this portfolio
        $clients = $portfolioModel->getAssignedClients($portfolio['id']);
        
        if (empty($clients)) {
            return $this->respond(['invoices' => []]);
        }
        
        // Get client IDs
        $clientIds = array_column($clients, 'id');
        
        // Search invoices by invoice number or client name
        $invoiceModel = new InvoiceModel();
        $invoices = $invoiceModel->searchByPortfolio(
            $portfolio['id'],
            $search,
            $clientIds
        );
        
        // Include client information
        if (!empty($invoices)) {
            $clientIds = array_unique(array_column($invoices, 'client_id'));
            $clients = [];
            
            // Get all clients in a single query
            if (!empty($clientIds)) {
                $clientsData = $clientModel->whereIn('id', $clientIds)->findAll();
                foreach ($clientsData as $client) {
                    $clients[$client['id']] = $client;
                }
            }
            
            // Add client data to each invoice
            foreach ($invoices as $key => $invoice) {
                if (isset($clients[$invoice['client_id']])) {
                    $invoices[$key]['client'] = $clients[$invoice['client_id']];
                }
            }
        }
        
        return $this->respond(['invoices' => $invoices]);
    }
    
    /**
     * Register a payment as a collector from the mobile app
     */
    public function registerMobilePayment()
    {
        log_message('debug', '====== INICIO REGISTER MOBILE PAYMENT ======');
        
        // Verificar que el usuario esté autenticado
        if (empty($this->user) || !is_array($this->user)) {
            log_message('error', 'Usuario no autenticado o inválido en registerMobilePayment');
            return $this->failUnauthorized('Usuario no autenticado o sesión expirada');
        }
        
        log_message('debug', 'User data: ' . json_encode($this->user));
        log_message('debug', 'Request data: ' . json_encode($this->request->getVar()));
        
        // Validate request
        $rules = [
            'invoice_id'    => 'required|is_natural_no_zero',
            'instalment_id' => 'permit_empty|is_natural_no_zero',
            'amount'        => 'required|numeric',
            'payment_date'  => 'required|valid_date',
            'payment_method'=> 'required|in_list[cash,transfer,deposit,check,other]',
            'notes'         => 'permit_empty'
        ];
        
        if (!$this->validate($rules)) {
            log_message('debug', 'Validation errors: ' . json_encode($this->validator->getErrors()));
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        // Get invoice
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($this->request->getVar('invoice_id'));
        
        if (!$invoice) {
            log_message('debug', 'Invoice not found: ' . $this->request->getVar('invoice_id'));
            return $this->failNotFound('Invoice not found');
        }
        
        log_message('debug', 'Invoice found: ' . json_encode($invoice));
        
        // Asegurarnos de que la propiedad 'amount' esté definida
        if (!isset($invoice['amount']) && isset($invoice['total_amount'])) {
            $invoice['amount'] = $invoice['total_amount'];
            log_message('debug', 'Using total_amount as amount: ' . $invoice['amount']);
        } else if (!isset($invoice['amount'])) {
            // Si no hay amount ni total_amount, intentar calcular el total de las cuotas
            $instalmentModel = new InstalmentModel();
            $instalments = $instalmentModel->where('invoice_id', $invoice['id'])->findAll();
            $totalAmount = 0;
            foreach ($instalments as $instalment) {
                $totalAmount += floatval($instalment['amount']);
            }
            
            if ($totalAmount > 0) {
                $invoice['amount'] = $totalAmount;
                log_message('debug', 'Calculated amount from instalments: ' . $invoice['amount']);
            } else {
                // Si todo lo anterior falla, establecer un valor por defecto
                $invoice['amount'] = 0;
                log_message('warning', 'Could not determine invoice amount, setting to 0');
            }
        }
        
        // Si se proporciona un ID de cuota, verificar primero el acceso a la cuota
        $instalmentId = $this->request->getVar('instalment_id');
        if (!empty($instalmentId)) {
            $instalmentModel = new InstalmentModel();
            $instalment = $instalmentModel->find($instalmentId);
            
            if (!$instalment) {
                log_message('debug', 'Instalment not found: ' . $instalmentId);
                return $this->failNotFound('Instalment not found');
            }
            
            log_message('debug', 'Instalment found: ' . json_encode($instalment));
            
            // Verificar que la cuota pertenece a la factura
            if ($instalment['invoice_id'] != $invoice['id']) {
                log_message('debug', 'Instalment does not belong to invoice');
                return $this->failValidationErrors('Instalment does not belong to this invoice');
            }
            
            // Verificar acceso al cliente de la factura
            $clientModel = new ClientModel();
            $client = $clientModel->find($invoice['client_id']);
            
            if (!$client) {
                log_message('debug', 'Client not found for invoice: ' . $invoice['id']);
                return $this->failNotFound('Client not found');
            }
            
            log_message('debug', 'Client found: ' . json_encode($client));
            
            // Verificar si el cliente está en el portafolio del usuario
            $db = \Config\Database::connect();
            
            // Verificar que el usuario tenga un UUID
            if (!isset($this->user) || !is_array($this->user) || !isset($this->user['uuid'])) {
                log_message('debug', 'User is null or does not have UUID');
                return $this->failForbidden('Authentication error: invalid user data');
            }
            
            // Obtener las carteras asignadas al usuario
            $userPortfolios = $db->table('portfolio_user')
                ->select('portfolio_uuid')
                ->where('user_uuid', $this->user['uuid'])
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
                
            if (empty($userPortfolios)) {
                log_message('debug', 'User does not have portfolios');
                return $this->failForbidden('You do not have access to this invoice');
            }
            
            $portfolioUuids = array_column($userPortfolios, 'portfolio_uuid');
            
            // Verificar si el cliente está en alguna de las carteras del usuario
            $clientInPortfolio = $db->table('client_portfolio')
                ->where('client_uuid', $client['uuid'])
                ->whereIn('portfolio_uuid', $portfolioUuids)
                ->where('deleted_at IS NULL')
                ->countAllResults();
                
            log_message('debug', 'Client in portfolio: ' . ($clientInPortfolio > 0 ? 'true' : 'false'));
            log_message('debug', 'Client UUID: ' . $client['uuid']);
            log_message('debug', 'Portfolio UUIDs: ' . json_encode($portfolioUuids));
            
            if ($clientInPortfolio == 0) {
                return $this->failForbidden('You do not have access to this client');
            }
            
            // Verificar que la cuota puede ser pagada (todas las cuotas anteriores están pagadas)
            if (!$instalmentModel->canBePaid($instalmentId)) {
                log_message('debug', 'Instalment cannot be paid yet');
                return $this->failValidationErrors('Previous instalments must be paid first');
            }
            
            // Verificar que el monto del pago no es mayor que el monto restante de la cuota
            $instalmentPayments = $this->paymentModel
                ->where('instalment_id', $instalmentId)
                ->where('status', 'completed')
                ->findAll();
                
            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }
            
            $instalmentRemaining = $instalment['amount'] - $instalmentPaid;
            $paymentAmount = (float)$this->request->getVar('amount');
            
            if ($paymentAmount > $instalmentRemaining) {
                log_message('debug', 'Payment amount exceeds instalment remaining amount');
                return $this->failValidationErrors('Payment amount cannot exceed the instalment remaining amount');
            }
            
            log_message('debug', 'Instalment validation passed');
        } else {
            // Si no se proporciona un ID de cuota, verificar el acceso a la factura
            // Check if user has access to this invoice
            $hasAccess = $this->canAccessInvoice($invoice);
            log_message('debug', 'Has access to invoice: ' . ($hasAccess ? 'true' : 'false'));
            
            if (!$hasAccess) {
                return $this->failForbidden('You do not have access to this invoice');
            }
        }
        
        // Create payment
        $data = [
            'organization_id' => $invoice['organization_id'],
            'invoice_id'      => $invoice['id'],
            'instalment_id'   => $instalmentId ?: null,
            'client_id'       => $invoice['client_id'],
            'amount'          => $this->request->getVar('amount'),
            'payment_date'    => $this->request->getVar('payment_date'),
            'payment_method'  => $this->request->getVar('payment_method'),
            'status'          => 'completed',
            'notes'           => $this->request->getVar('notes'),
            'registered_by'   => $this->user['id']
        ];
        
        log_message('debug', 'Registrando pago con estado: completed');
        
        $paymentId = $this->paymentModel->insert($data);
        
        if (!$paymentId) {
            return $this->failServerError('Failed to register payment');
        }
        
        // Update invoice status if payment is full
        // Calcular el total pagado para esta factura
        $totalPaid = 0;
        $payments = $this->paymentModel
            ->where('invoice_id', $invoice['id'])
            ->where('status', 'completed')
            ->findAll();
            
        foreach ($payments as $payment) {
            $totalPaid += $payment['amount'];
        }
        
        log_message('debug', 'Total pagado para factura ' . $invoice['id'] . ': ' . $totalPaid);
        
        // Asegurarnos de que la propiedad 'amount' esté definida antes de comparar
        $invoiceAmount = isset($invoice['amount']) ? floatval($invoice['amount']) : 0;
        if ($invoiceAmount <= 0 && isset($invoice['total_amount'])) {
            $invoiceAmount = floatval($invoice['total_amount']);
        }
        
        log_message('debug', 'Monto de la factura: ' . $invoiceAmount);
        
        if ($totalPaid >= $invoiceAmount && $invoiceAmount > 0) {
            $invoiceModel->update($invoice['id'], ['status' => 'paid']);
            log_message('debug', 'Factura marcada como pagada');
        } else if ($totalPaid > 0) {
            $invoiceModel->update($invoice['id'], ['status' => 'partially_paid']);
            log_message('debug', 'Factura marcada como pago parcial');
        }
        
        // Update instalment status if applicable
        if (!empty($instalmentId)) {
            $instalmentModel = new InstalmentModel();
            $instalmentModel->updateStatus($instalmentId);
            
            // Check if all instalments are paid
            if ($instalmentModel->areAllPaid($invoice['id'])) {
                $invoiceModel->update($invoice['id'], ['status' => 'paid']);
            }
        }
        
        $payment = $this->paymentModel->find($paymentId);
        
        return $this->respondCreated(['payment' => $payment]);
    }
    
    /**
     * Get instalments for an invoice
     */
    public function getInstalments($invoiceId)
    {
        if (!$invoiceId) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        $instalmentModel = new InstalmentModel();
        // Usar el método que ordena las cuotas por prioridad de cobranza
        $instalments = $instalmentModel->getByInvoiceForCollection($invoiceId);
        
        // Calculate remaining amount for each instalment
        foreach ($instalments as &$instalment) {
            $instalmentPayments = $this->paymentModel
                ->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();
                
            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }
            
            $instalment['paid_amount'] = $instalmentPaid;
            $instalment['remaining_amount'] = $instalment['amount'] - $instalmentPaid;
        }
        
        return $this->respond(['instalments' => $instalments]);
    }
    
    /**
     * Check if user can access a payment
     */
    private function canAccessPayment($payment)
    {
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can access any payment in their organization
            return $payment['organization_id'] == $this->user['organization_id'];
        } else {
            // For regular users, check if they have access to the client through portfolios
            return $this->canAccessInvoice(['id' => $payment['invoice_id'], 'organization_id' => $payment['organization_id']]);
        }
    }
    
    /**
     * Verifica si el usuario puede acceder a una factura
     */
    private function canAccessInvoice($invoice)
    {
        log_message('debug', '====== INICIO CAN ACCESS INVOICE ======');
        log_message('debug', 'Invoice data: ' . json_encode($invoice));
        log_message('debug', 'User data: ' . json_encode($this->user));
        
        // Check if invoice is null or not an array
        if (!$invoice || !is_array($invoice)) {
            log_message('debug', 'Invoice is not an array');
            return false;
        }
        
        // Check if invoice has required fields
        if (!isset($invoice['id']) || !isset($invoice['organization_id']) || !isset($invoice['client_id'])) {
            log_message('debug', 'Invoice does not have required fields');
            return false;
        }
        
        // Check if user has role
        if (!isset($this->user['role'])) {
            log_message('debug', 'User does not have role');
            return false;
        }
        
        // Superadmin can access everything
        if ($this->user['role'] === 'superadmin') {
            log_message('debug', 'User is superadmin');
            return true;
        }
        
        // Admin can access invoices from their organization
        if ($this->user['role'] === 'admin') {
            if (!isset($this->user['organization_id'])) {
                log_message('debug', 'User does not have organization ID');
                return false;
            }
            log_message('debug', 'User organization ID: ' . $this->user['organization_id']);
            log_message('debug', 'Invoice organization ID: ' . $invoice['organization_id']);
            return $invoice['organization_id'] == $this->user['organization_id'];
        }
        
        // Regular user can only access invoices from clients in their portfolios
        $clientModel = new \App\Models\ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        if (!$client) {
            log_message('debug', 'Client not found');
            return false;
        }
        
        log_message('debug', 'Client data: ' . json_encode($client));
        
        // Verificar si el cliente está en el portafolio del usuario
        if (!isset($this->user) || !is_array($this->user) || !isset($this->user['uuid'])) {
            log_message('debug', 'User is null or does not have UUID');
            return $this->failForbidden('Authentication error: invalid user data');
        }
        
        $db = \Config\Database::connect();
        
        // Obtener las carteras asignadas al usuario
        $userPortfolios = $db->table('portfolio_user')
            ->select('portfolio_uuid')
            ->where('user_uuid', $this->user['uuid'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();
            
        if (empty($userPortfolios)) {
            log_message('debug', 'User does not have portfolios');
            return false;
        }
        
        $portfolioUuids = array_column($userPortfolios, 'portfolio_uuid');
        
        // Verificar si el cliente está en alguna de las carteras del usuario
        if (!isset($client['uuid'])) {
            log_message('debug', 'Client does not have UUID');
            return false;
        }
        
        $clientInPortfolio = $db->table('client_portfolio')
            ->where('client_uuid', $client['uuid'])
            ->whereIn('portfolio_uuid', $portfolioUuids)
            ->where('deleted_at IS NULL')
            ->countAllResults();
        
        log_message('debug', 'Client in portfolio: ' . ($clientInPortfolio > 0 ? 'true' : 'false'));
        log_message('debug', 'Client UUID: ' . $client['uuid']);
        log_message('debug', 'Portfolio UUIDs: ' . json_encode($portfolioUuids));
        
        return $clientInPortfolio > 0;
    }
    
    /**
     * Get QR details by ID from Ligo API
     *
     * @param string $qrId QR ID
     * @param string $token Authentication token
     * @param array $organization Organization data
     * @return array Response from Ligo API
     */
    private function getQRDetailsById($qrId, $token, $organization)
    {
        log_message('debug', 'PaymentController - Obteniendo detalles de QR con ID: ' . $qrId);

        try {
            $curl = curl_init();
            
            // Get centralized Ligo configuration for API URL
            $ligoConfig = $this->getLigoConfig();
            if (!$ligoConfig) {
                log_message('error', 'PaymentController - Ligo URL configuration not available');
                return ['error' => 'Configuration error', 'message' => 'Ligo URL configuration not available'];
            }
            
            $url = $ligoConfig['api_base_url'] . '/v1/getCreateQRById/' . $qrId;
            
            log_message('debug', 'PaymentController - URL para obtener detalles del QR: ' . $url);
            
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
                log_message('error', 'PaymentController - Error de cURL al obtener detalles del QR: ' . $err);
                return (object)['error' => 'cURL Error: ' . $err];
            }
            
            log_message('info', 'PaymentController - Respuesta de getCreateQRByID: ' . $response);
            
            $decoded = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'PaymentController - Error decodificando respuesta de detalles de QR: ' . json_last_error_msg());
                return (object)['error' => 'Invalid JSON in QR details response: ' . json_last_error_msg()];
            }
            
            // Verificar si hay errores en la respuesta
            if (!isset($decoded->data)) {
                log_message('error', 'PaymentController - Error en la respuesta de detalles de QR: ' . json_encode($decoded));
                return (object)['error' => 'Error in QR details response: ' . json_encode($decoded)];
            }
            
            return $decoded;
        } catch (\Exception $e) {
            log_message('error', 'PaymentController - Error al obtener detalles del QR: ' . $e->getMessage());
            return (object)['error' => 'QR details error: ' . $e->getMessage()];
        }
    }
}

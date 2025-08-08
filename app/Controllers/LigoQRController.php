<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Exception;

class LigoQRController extends Controller
{
    protected $invoiceModel;
    protected $organizationModel;

    public function __construct()
    {
        $this->invoiceModel = new \App\Models\InvoiceModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        helper(['form', 'url']);
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
     * Debug endpoint to verify deployment
     */
    public function debug()
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => 'LigoQRController loaded successfully',
            'timestamp' => date('Y-m-d H:i:s'),
            'version' => 'v2.0-fixed-duplicate-method',
            'methods_available' => [
                'ajaxQR',
                'generateInstalmentQRInternal', 
                'generateQRUsingPaymentControllerLogic',
                'getQRDetailsById'
            ]
        ]);
    }
    
    /**
     * Generate static QR code for an organization
     *
     * @param string $organizationUuid UUID de la organización
     * @return mixed
     */
    public function staticQR($organizationUuid)
    {
        // Registrar información de depuración
        log_message('debug', 'staticQR llamado para organización UUID: ' . $organizationUuid);

        // Obtener detalles de la organización
        $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();

        if (!$organization) {
            log_message('error', 'Organización no encontrada con UUID: ' . $organizationUuid);
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Organización no encontrada'
            ]);
        }

        log_message('debug', 'Organización encontrada: ' . $organization['name']);

        // Check if Ligo is enabled for this organization and has valid credentials
        $ligoEnabled = isset($organization['ligo_enabled']) && $organization['ligo_enabled'];
        $credentials = $this->getLigoCredentials($organization);
        $hasValidCredentials = !empty($credentials['username']) && 
                              !empty($credentials['password']) && 
                              !empty($credentials['company_id']);
        $hasValidToken = !empty($organization['ligo_token']) && 
                         !empty($organization['ligo_token_expiry']) && 
                         strtotime($organization['ligo_token_expiry']) > time();
        
        if (!$ligoEnabled || (!$hasValidCredentials && !$hasValidToken)) {
            // Si Ligo no está habilitado o no hay credenciales válidas, usar un QR de demostración temporal
            log_message('info', 'Ligo no está configurado correctamente para la organización ID: ' . $organization['id'] . '. Usando QR de demostración.');
            log_message('debug', 'Estado Ligo: habilitado=' . ($ligoEnabled ? 'Sí' : 'No') . 
                                ', credenciales=' . ($hasValidCredentials ? 'Sí' : 'No') . 
                                ', token=' . ($hasValidToken ? 'Sí' : 'No'));

            // Prepare demo QR data
            return $this->response->setJSON([
                'success' => true,
                'organization_name' => $organization['name'],
                'qr_data' => json_encode([
                    'organization_id' => $organization['id'],
                    'name' => $organization['name'],
                    'demo' => true
                ]),
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - " . $organization['name']),
                'order_id' => 'DEMO-' . time(),
                'is_demo' => true
            ]);
        }

        // Prepare response data
        $responseData = [
            'success' => true,
            'organization_name' => $organization['name'],
            'qr_data' => null,
            'qr_image_url' => null,
            'order_id' => null
        ];

        // Preparar datos para la orden estática
        $orderData = [
            'amount' => null, // No amount for static QR
            'currency' => 'PEN',
            'orderId' => 'static-' . $organization['id'] . '-' . time(),
            'description' => 'QR Estático para ' . $organization['name'],
            'qr_type' => 'static'
        ];

        // Log para depuración
        log_message('debug', 'Intentando crear QR estático en Ligo con datos: ' . json_encode($orderData));

        // Crear orden en Ligo
        $response = $this->createLigoOrder($orderData, $organization);

        // Log de respuesta
        log_message('debug', 'Respuesta de Ligo: ' . json_encode($response));

        if (!isset($response->error)) {
            $responseData['qr_data'] = $response->qr_data ?? null;
            $responseData['qr_image_url'] = $response->qr_image_url ?? null;
            $responseData['order_id'] = $response->order_id ?? null;

            // Log de éxito
            log_message('info', 'QR estático generado exitosamente para organización: ' . $organization['name']);
        } else {
            log_message('error', 'Error generando QR estático Ligo: ' . json_encode($response));

            // Si hay un error, usar QR de demostración como fallback
            log_message('info', 'Usando QR de demostración como fallback debido a error de Ligo');

            $responseData['success'] = true; // Cambiamos a true para mostrar el QR de demostración
            $responseData['qr_image_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - " . $organization['name']);
            $responseData['order_id'] = 'DEMO-' . time();
            $responseData['is_demo'] = true;
            $responseData['error_message'] = 'Usando QR de demostración. Error original: ' . (is_string($response->error) ? $response->error : json_encode($response->error));
        }

        return $this->response->setJSON($responseData);
    }

    /**
     * Generate QR code and return JSON data for AJAX requests
     *
     * @param string $invoiceIdentifier UUID o ID de la factura
     * @param int|null $instalmentId ID de la cuota (opcional)
     * @return mixed
     */
    public function ajaxQR($invoiceIdentifier, $instalmentId = null, $qrType = 'dynamic')
    {
        // Registrar información de depuración
        log_message('error', '[WEB QR] ajaxQR llamado con identificador: ' . $invoiceIdentifier . ', instalmentId: ' . ($instalmentId ?? 'null') . ', qrType: ' . $qrType);
        log_message('debug', 'ajaxQR llamado con identificador: ' . $invoiceIdentifier . ', instalmentId: ' . ($instalmentId ?? 'null') . ', qrType: ' . $qrType);

        // Intentar obtener la factura por UUID primero
        $invoice = $this->invoiceModel->where('uuid', $invoiceIdentifier)->first();

        // Si no se encuentra por UUID, intentar por ID regular
        if (!$invoice && is_numeric($invoiceIdentifier)) {
            log_message('debug', 'Factura no encontrada por UUID, intentando por ID: ' . $invoiceIdentifier);
            $invoice = $this->invoiceModel->find($invoiceIdentifier);
        }

        if (!$invoice) {
            log_message('error', 'Factura no encontrada con identificador: ' . $invoiceIdentifier);
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Factura no encontrada (ID/UUID: ' . $invoiceIdentifier . ')'
            ]);
        }

        log_message('debug', 'Factura encontrada: ' . json_encode($invoice));

        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);

        if (!$organization) {
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Organización no encontrada'
            ]);
        }

        // Si se proporciona ID de cuota, obtener los detalles de la cuota
        $instalment = null;

        // Determinar el monto de la factura, manejando diferentes estructuras de datos
        $paymentAmount = 0;
        if (isset($invoice['total_amount'])) {
            $paymentAmount = $invoice['total_amount'];
        } elseif (isset($invoice['amount'])) {
            $paymentAmount = $invoice['amount'];
        } else {
            // Si no hay monto directo, calcular el monto pendiente
            $invoiceModel = new \App\Models\InvoiceModel();
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
            $paymentAmount = $paymentInfo['remaining'] ?? $paymentInfo['invoice_amount'] ?? 0;
        }

        $paymentDescription = 'Pago de factura ' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A');

        if ($instalmentId) {
            $instalmentModel = new \App\Models\InstalmentModel();
            $instalment = $instalmentModel->find($instalmentId);

            if (!$instalment || $instalment['invoice_id'] != $invoice['id']) {
                return $this->response->setJSON([
                    'success' => false,
                    'error_message' => 'Cuota no encontrada o no pertenece a esta factura'
                ]);
            }

            // Verificar que se puedan pagar las cuotas en orden
            if (!$instalmentModel->canBePaid($instalment['id'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error_message' => 'No se puede pagar esta cuota porque hay cuotas anteriores pendientes de pago'
                ]);
            }

            // Calcular el monto pendiente de la cuota
            $paymentModel = new \App\Models\PaymentModel();
            $instalmentPayments = $paymentModel->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();

            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }

            $paymentAmount = $instalment['amount'] - $instalmentPaid;
            $paymentDescription = 'Pago de cuota ' . $instalment['number'] . ' de factura ' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A');
        }

        // Check if Ligo is enabled for this organization and has valid credentials
        $ligoEnabled = isset($organization['ligo_enabled']) && $organization['ligo_enabled'];
        $credentials = $this->getLigoCredentials($organization);
        $hasValidCredentials = !empty($credentials['username']) && 
                              !empty($credentials['password']) && 
                              !empty($credentials['company_id']);
        $hasValidToken = !empty($organization['ligo_token']) && 
                         !empty($organization['ligo_token_expiry']) && 
                         strtotime($organization['ligo_token_expiry']) > time();
        
        if (!$ligoEnabled || (!$hasValidCredentials && !$hasValidToken)) {
            // Si Ligo no está habilitado o no hay credenciales válidas, usar un QR de demostración temporal
            log_message('info', 'Ligo no está configurado correctamente para la organización ID: ' . $organization['id'] . '. Usando QR de demostración.');
            log_message('debug', 'Estado Ligo: habilitado=' . ($ligoEnabled ? 'Sí' : 'No') . 
                                ', credenciales=' . ($hasValidCredentials ? 'Sí' : 'No') . 
                                ', token=' . ($hasValidToken ? 'Sí' : 'No'));

            // Prepare demo QR data
            return $this->response->setJSON([
                'success' => true,
                'invoice_number' => $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A',
                'amount' => number_format($paymentAmount, 2),
                'currency' => $invoice['currency'] ?? 'PEN',
                'qr_data' => json_encode([
                    'invoice_id' => $invoice['id'],
                    'amount' => $paymentAmount,
                    'currency' => $invoice['currency'] ?? 'PEN',
                    'description' => $paymentDescription,
                    'demo' => true
                ]),
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - Factura #" . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A')),
                'order_id' => 'DEMO-' . time(),
                'expiration' => date('d/m/Y H:i', strtotime('+30 minutes')),
                'is_demo' => true
            ]);
        }

        // Prepare response data
        $responseData = [
            'success' => true,
            'invoice_number' => $invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A',
            'amount' => number_format($paymentAmount, 2),
            'currency' => $invoice['currency'] ?? 'PEN',
            'qr_data' => null,
            'qr_image_url' => null,
            'order_id' => null,
            'expiration' => null
        ];

        // Intentar generar QR solo si las credenciales están configuradas
        if (!empty($credentials['username']) && !empty($credentials['password']) && !empty($credentials['company_id'])) {
            // 🚀 CACHE: Verificar cache antes de generar nuevo QR
            $hashModel = new \App\Models\LigoQRHashModel();
            $cacheMinutes = 15;
            $cacheTime = date('Y-m-d H:i:s', strtotime("-{$cacheMinutes} minutes"));
            
            $existingQR = $hashModel
                ->where('invoice_id', $invoice['id'])
                ->where('instalment_id', $instalmentId)
                ->where('amount', $paymentAmount)
                ->where('created_at >', $cacheTime)
                ->orderBy('created_at', 'DESC')
                ->first();
            
            if ($existingQR && !empty($existingQR['real_hash'])) {
                // ✅ Retornar QR desde cache
                log_message('info', '🚀 AJAX CACHE HIT: Usando QR en cache para invoice_id=' . $invoice['id'] . ', instalment_id=' . ($instalmentId ?? 'null'));
                
                return $this->response->setJSON([
                    'success' => true,
                    'qr_data' => $existingQR['real_hash'],
                    'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($existingQR['real_hash']),
                    'order_id' => $existingQR['order_id'],
                    'expiration' => date('Y-m-d H:i:s', strtotime($existingQR['created_at'] . ' +1 hour')),
                    'is_cached' => true,
                    'cache_age' => round((strtotime('now') - strtotime($existingQR['created_at'])) / 60, 1)
                ]);
            }
            
            log_message('info', '❌ AJAX CACHE MISS: Generando nuevo QR para invoice_id=' . $invoice['id'] . ', instalment_id=' . ($instalmentId ?? 'null'));
            
            // Si hay un instalmentId, usar el método que obtiene hash real
            if ($instalmentId) {
                log_message('info', '[ajaxQR] Using generateInstalmentQRInternal for instalment ' . $instalmentId);
                return $this->generateInstalmentQRInternal($instalmentId);
            }
            
            // Para pagos de factura completa, usar el método original
            // Preparar datos para la orden
            $orderData = [
                'amount' => $paymentAmount,
                'currency' => $invoice['currency'] ?? 'PEN',
                'orderId' => $invoice['id'],
                'description' => $paymentDescription
            ];

            // Log para depuración
            log_message('debug', 'Intentando crear orden en Ligo con datos: ' . json_encode($orderData));

            // Crear orden en Ligo (usar método original que funciona)
            $response = $this->createLigoOrder($orderData, $organization);
            
            // Si QR se generó exitosamente, guardar en base de datos para webhook matching
            if (!isset($response->error) && isset($response->order_id)) {
                try {
                    $hashModel = new \App\Models\LigoQRHashModel();
                    
                    // Extraer idQr de la respuesta (misma lógica que PaymentController)
                    $idQr = null;
                    if (isset($response->qr_id)) {
                        $idQr = $response->qr_id;
                    } elseif (isset($response->data) && isset($response->data->id)) {
                        $idQr = $response->data->id;
                    } elseif (isset($response->order_id)) {
                        $idQr = $response->order_id;
                    }
                    
                    if ($idQr) {
                        $hashData = [
                            'invoice_id' => $invoice['id'],
                            'instalment_id' => $instalmentId,
                            'id_qr' => $idQr,
                            'hash' => $response->qr_data ?? $idQr,
                            'amount' => $paymentAmount,
                            'currency' => $invoice['currency'] ?? 'PEN',
                            'is_real_hash' => !empty($response->qr_data) ? 1 : 0,
                            'created_at' => date('Y-m-d H:i:s')
                        ];
                        
                        $hashModel->insert($hashData);
                        log_message('info', '[ajaxQR] QR hash saved for webhook matching with id_qr: ' . $idQr);
                    }
                } catch (\Exception $e) {
                    log_message('error', '[ajaxQR] Error saving QR hash to database: ' . $e->getMessage());
                    // Continue execution even if database save fails
                }
            }

            // Log de respuesta
            log_message('debug', 'Respuesta de Ligo: ' . json_encode($response));

            if (!isset($response->error)) {
                $responseData['qr_data'] = $response->qr_data ?? null;
                $responseData['qr_image_url'] = $response->qr_image_url ?? null;
                $responseData['order_id'] = $response->order_id ?? null;
                $responseData['expiration'] = date('d/m/Y H:i', strtotime($response->expiration ?? '+1 hour'));

                // Log de éxito
                log_message('info', 'QR generado exitosamente para factura #' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A'));
            } else {
                log_message('error', 'Error generando QR Ligo: ' . json_encode($response));

                // Si hay un error, usar QR de demostración como fallback
                log_message('info', 'Usando QR de demostración como fallback debido a error de Ligo');

                $responseData['success'] = true; // Cambiamos a true para mostrar el QR de demostración
                $responseData['qr_image_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - Factura #" . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A'));
                $responseData['order_id'] = 'DEMO-' . time();
                $responseData['expiration'] = date('d/m/Y H:i', strtotime('+30 minutes'));
                $responseData['is_demo'] = true;
                $responseData['error_message'] = 'Usando QR de demostración. Error original: ' . (is_string($response->error) ? $response->error : json_encode($response->error));
            }
        } else {
            log_message('error', 'Credenciales de Ligo no configuradas para la organización ID: ' . $organization['id']);
            log_message('info', 'Usando QR de demostración como fallback debido a falta de credenciales');

            // Usar QR de demostración como fallback
            $responseData['success'] = true;
            $responseData['qr_image_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - Factura #" . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A'));
            $responseData['order_id'] = 'DEMO-' . time();
            $responseData['expiration'] = date('d/m/Y H:i', strtotime('+30 minutes'));
            $responseData['is_demo'] = true;
            $responseData['error_message'] = 'Usando QR de demostración. Credenciales de Ligo no configuradas correctamente.';
        }

        return $this->response->setJSON($responseData);
    }
    
    /**
     * Generate QR for instalment using the same proven logic from PaymentController API
     * This ensures DRY principle and consistency
     */
    private function generateInstalmentQRInternal($instalmentId)
    {
        // Copy the exact working logic from PaymentController API but adapted for web context
        $instalmentModel = new \App\Models\InstalmentModel();
        $invoiceModel = new \App\Models\InvoiceModel();
        $organizationModel = new \App\Models\OrganizationModel();
        
        $instalment = $instalmentModel->find($instalmentId);
        if (!$instalment) {
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Instalment not found'
            ]);
        }
        
        $invoice = $invoiceModel->find($instalment['invoice_id']);
        if (!$invoice) {
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Invoice not found'
            ]);
        }
        
        $organization = $organizationModel->find($invoice['organization_id']);
        if (!$organization) {
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Organization not found'
            ]);
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Ligo payments not enabled for this organization'
            ]);
        }
        
        // Get Ligo credentials
        $credentials = $this->getLigoCredentials($organization);
        
        // Check if Ligo credentials are configured
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Ligo API credentials not configured'
            ]);
        }
        
        // Prepare order data for Ligo (exact same logic as PaymentController)
        $orderData = [
            'amount' => $instalment['amount'],
            'currency' => $invoice['currency'] ?? 'PEN',
            'orderId' => $instalment['id'],
            'description' => "Pago cuota #{$instalment['number']} de factura #{$invoice['invoice_number']}",
            'qr_type' => 'dynamic'
        ];
        
        try {
            // Get auth token (same as PaymentController)
            $authToken = $this->getLigoAuthToken($organization);
            if (isset($authToken->error)) {
                return $this->response->setJSON([
                    'success' => false,
                    'error_message' => $authToken->error
                ]);
            }
            
            // Generate QR using the same logic
            $result = $this->generateQRUsingPaymentControllerLogic($orderData, ['token' => $authToken->token], $organization, $invoice, $instalment);
            
            if (isset($result['error'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'error_message' => $result['error']
                ]);
            }
            
            return $this->response->setJSON([
                'success' => true,
                'invoice_number' => $invoice['invoice_number'] ?? $invoice['number'] ?? 'N/A',
                'amount' => number_format($instalment['amount'], 2),
                'currency' => $invoice['currency'] ?? 'PEN',
                'qr_data' => $result['qr_data'],
                'qr_image_url' => $result['qr_image_url'],
                'order_id' => $result['order_id'],
                'expiration' => date('d/m/Y H:i', strtotime('+1 hour'))
            ]);
            
        } catch (\Exception $e) {
            log_message('error', 'Error in generateInstalmentQRInternal: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'error_message' => 'Internal error generating QR: ' . $e->getMessage()
            ]);
        }
    }

    
    /**
     * Generate QR using the exact same logic as PaymentController
     */
    private function generateQRUsingPaymentControllerLogic($orderData, $authToken, $organization, $invoice, $instalment)
    {
        try {
            // Step 1: Create QR (same as PaymentController)
            $curl = curl_init();
            $config = $this->getLigoConfig($organization);
            $url = $config['api_url'] . '/v1/createQr';
            
            $idCuenta = !empty($organization['ligo_account_id']) ? $organization['ligo_account_id'] : '92100178794744781044';
            $codigoComerciante = !empty($organization['ligo_merchant_code']) ? $organization['ligo_merchant_code'] : '4829';
            $fechaVencimiento = date('Ymd', strtotime('+2 days'));
            
            $qrData = [
                'header' => ['sisOrigen' => '0921'],
                'data' => [
                    'qrTipo' => '12',
                    'idCuenta' => $idCuenta,
                    'moneda' => $orderData['currency'] == 'PEN' ? '604' : '840',
                    'importe' => (int)($orderData['amount'] * 100),
                    'fechaVencimiento' => $fechaVencimiento,
                    'cantidadPagos' => 1,
                    'codigoComerciante' => $codigoComerciante,
                    'nombreComerciante' => $organization['name'],
                    'ciudadComerciante' => $organization['city'] ?? 'Lima',
                    'glosa' => $orderData['description'],
                    'info' => [
                        ['codigo' => 'instalment_id', 'valor' => $instalment['id']],
                        ['codigo' => 'invoice_id', 'valor' => $invoice['id']]
                    ]
                ],
                'type' => 'TEXT'
            ];
            
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
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $authToken['token']
                ],
                CURLOPT_SSL_VERIFYHOST => $config['ssl_verify_host'],
                CURLOPT_SSL_VERIFYPEER => $config['ssl_verify'],
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            
            if ($err) {
                return ['error' => 'Failed to connect to Ligo API: ' . $err];
            }
            
            $decoded = json_decode($response);
            if (!$decoded || !isset($decoded->data) || !isset($decoded->data->id)) {
                log_message('error', 'LIGO QR DETAILS ERROR - Raw response: ' . $response);
                log_message('error', 'LIGO QR DETAILS ERROR - Decoded: ' . json_encode($decoded));
                return ['error' => 'Invalid response from Ligo API (QR Details)'];
            }
            
            // Step 2: Get QR details (same as PaymentController)
            $qrId = $decoded->data->id;
            sleep(2); // Wait for Ligo to process
            
            // Get fresh auth token for QR details call
            $freshAuthToken = $this->getLigoAuthToken($organization);
            if (isset($freshAuthToken->error)) {
                return ['error' => 'Auth error for QR details: ' . $freshAuthToken->error];
            }
            
            $qrDetails = $this->getQRDetailsById($qrId, $freshAuthToken->token, $organization);
            if (isset($qrDetails->error)) {
                return ['error' => 'Error obtaining QR details: ' . $qrDetails->error];
            }
            
            // Extract data exactly like PaymentController
            $qrHash = null;
            if (isset($qrDetails->data->hash)) {
                $qrHash = $qrDetails->data->hash;
            } else if (isset($qrDetails->data->qr)) {
                $qrHash = $qrDetails->data->qr;
            } else if (isset($qrDetails->data->qrString)) {
                $qrHash = $qrDetails->data->qrString;
            } else {
                $qrHash = $qrId;
            }
            
            // Extract idQr with same fallback logic
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
            
            // Save to database exactly like PaymentController
            $hashModel = new \App\Models\LigoQRHashModel();
            $isRealHash = !empty($qrHash) && strlen($qrHash) > 50;
            
            $dataInsert = [
                'hash' => $qrId,
                'real_hash' => $isRealHash ? $qrHash : null,
                'hash_error' => !$isRealHash ? 'Hash temporal generado, necesita solicitar hash real' : null,
                'order_id' => $qrId,
                'id_qr' => $idQr,
                'invoice_id' => $invoice['id'],
                'instalment_id' => $instalment['id'],
                'amount' => $orderData['amount'],
                'currency' => $orderData['currency'],
                'description' => $orderData['description']
            ];
            
            $hashModel->insert($dataInsert);
            log_message('info', '[LIGO] Hash insertado desde LigoQRController: ' . json_encode($dataInsert));
            
            // Create response data
            $qrDataJson = json_encode([
                'id' => $qrId,
                'id_qr' => $idQr,
                'amount' => $orderData['amount'],
                'currency' => $orderData['currency'],
                'description' => $orderData['description'],
                'hash' => $qrHash,
                'instalment_id' => $instalment['id'],
                'invoice_id' => $invoice['id']
            ]);
            
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrDataJson);
            
            return [
                'qr_data' => $qrDataJson,
                'qr_image_url' => $qrImageUrl,
                'order_id' => $qrId
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Error in generateQRUsingPaymentControllerLogic: ' . $e->getMessage());
            return ['error' => 'Exception: ' . $e->getMessage()];
        }
    }

    /**
     * Display QR code page for invoice payment
     *
     * @param string $invoiceUuid UUID de la factura
     * @param int|null $instalmentId ID de la cuota (opcional)
     * @return mixed
     */
    public function index($invoiceUuid, $instalmentId = null)
    {
        // Get invoice details
        $invoice = $this->invoiceModel->where('uuid', $invoiceUuid)->first();

        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }

        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);

        if (!$organization) {
            return redirect()->to('/invoices')->with('error', 'Organización no encontrada');
        }

        // Si se proporciona ID de cuota, obtener los detalles de la cuota
        $instalment = null;

        // Determinar el monto de la factura, manejando diferentes estructuras de datos
        $paymentAmount = 0;
        if (isset($invoice['total_amount'])) {
            $paymentAmount = $invoice['total_amount'];
        } elseif (isset($invoice['amount'])) {
            $paymentAmount = $invoice['amount'];
        } else {
            // Si no hay monto directo, calcular el monto pendiente
            $invoiceModel = new \App\Models\InvoiceModel();
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
            $paymentAmount = $paymentInfo['remaining'] ?? $paymentInfo['invoice_amount'] ?? 0;
        }

        $paymentDescription = 'Pago de factura ' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A');

        if ($instalmentId) {
            $instalmentModel = new \App\Models\InstalmentModel();
            $instalment = $instalmentModel->find($instalmentId);

            if (!$instalment || $instalment['invoice_id'] != $invoice['id']) {
                return redirect()->to('/invoices/view/' . $invoiceUuid)->with('error', 'Cuota no encontrada o no pertenece a esta factura');
            }

            // Verificar que se puedan pagar las cuotas en orden
            if (!$instalmentModel->canBePaid($instalment['id'])) {
                return redirect()->to('/invoices/view/' . $invoiceUuid)->with('error', 'No se puede pagar esta cuota porque hay cuotas anteriores pendientes de pago');
            }

            // Calcular el monto pendiente de la cuota
            $paymentModel = new \App\Models\PaymentModel();
            $instalmentPayments = $paymentModel->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();

            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }

            $paymentAmount = $instalment['amount'] - $instalmentPaid;
            $paymentDescription = 'Pago de cuota ' . $instalment['number'] . ' de factura ' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A');
        }

        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            // Si Ligo no está habilitado, usar un QR de demostración temporal
            log_message('info', 'Ligo no está habilitado para la organización ID: ' . $organization['id'] . '. Usando QR de demostración.');

            // Prepare data for view with demo QR
            $data = [
                'title' => 'Pago con QR - Ligo (Demo)',
                'invoice' => $invoice,
                'qr_data' => json_encode([
                    'invoice_id' => $invoice['id'],
                    'amount' => $paymentAmount,
                    'currency' => $invoice['currency'] ?? 'PEN',
                    'description' => $paymentDescription,
                    'demo' => true
                ]),
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - Factura #" . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A')),
                'order_id' => 'DEMO-' . time(),
                'expiration' => date('Y-m-d H:i:s', strtotime('+30 minutes')),
                'is_demo' => true
            ];

            return view('payments/ligo_qr', $data);
        }

        // Prepare data for view
        $data = [
            'title' => 'Pago con QR - Ligo',
            'invoice' => $invoice,
            'qr_data' => null,
            'qr_image_url' => null,
            'order_id' => null,
            'expiration' => null
        ];

        // Intentar generar QR solo si las credenciales están configuradas
        if (!empty($credentials['username']) && !empty($credentials['password']) && !empty($credentials['company_id'])) {
            // 🚀 CACHE: Verificar si existe QR válido reciente (15 minutos)
            $hashModel = new \App\Models\LigoQRHashModel();
            $cacheMinutes = 15;
            $cacheTime = date('Y-m-d H:i:s', strtotime("-{$cacheMinutes} minutes"));
            
            $existingQR = $hashModel
                ->where('invoice_id', $invoice['id'])
                ->where('instalment_id', $instalmentId)
                ->where('amount', $paymentAmount)
                ->where('created_at >', $cacheTime)
                ->orderBy('created_at', 'DESC')
                ->first();
            
            if ($existingQR && !empty($existingQR['real_hash'])) {
                // ✅ Usar QR en cache
                log_message('info', '🚀 CACHE HIT: Usando QR en cache para invoice_id=' . $invoice['id'] . ', instalment_id=' . ($instalmentId ?? 'null') . ', age=' . (strtotime('now') - strtotime($existingQR['created_at'])) . 's');
                
                $data['qr_data'] = $existingQR['real_hash'];
                $data['qr_image_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($existingQR['real_hash']);
                $data['order_id'] = $existingQR['order_id'];
                $data['expiration'] = date('Y-m-d H:i:s', strtotime($existingQR['created_at'] . ' +1 hour'));
                $data['is_cached'] = true;
                $data['cache_age'] = round((strtotime('now') - strtotime($existingQR['created_at'])) / 60, 1); // minutos
                
                log_message('info', '✅ QR servido desde cache (edad: ' . $data['cache_age'] . ' minutos)');
                
                // 🧹 Limpieza oportunista de cache viejo (10% de probabilidad)
                if (rand(1, 10) === 1) {
                    $this->cleanupOldQRCache();
                }
            } else {
                // ❌ Cache miss: Generar nuevo QR
                log_message('info', '❌ CACHE MISS: Generando nuevo QR para invoice_id=' . $invoice['id'] . ', instalment_id=' . ($instalmentId ?? 'null'));
                
                // Preparar datos para la orden
                $orderData = [
                    'amount' => $paymentAmount,
                    'currency' => $invoice['currency'] ?? 'PEN',
                    'orderId' => $invoice['id'],
                    'description' => $paymentDescription
                ];

                // Log para depuración
                log_message('debug', 'Intentando crear orden en Ligo con datos: ' . json_encode($orderData));
                log_message('debug', 'Organización: ' . $organization['id'] . ' - Username: ' . $credentials['username']);

                // Crear orden en Ligo
                $response = $this->createLigoOrder($orderData, $organization);

                // Log de respuesta
                log_message('debug', 'Respuesta de Ligo: ' . json_encode($response));

                if (!isset($response->error)) {
                    $data['qr_data'] = $response->qr_data ?? null;
                    $data['qr_image_url'] = $response->qr_image_url ?? null;
                    $data['order_id'] = $response->order_id ?? null;
                    $data['expiration'] = $response->expiration ?? null;
                    $data['is_cached'] = false;

                    // Log de éxito
                    log_message('info', '🆕 QR generado exitosamente para factura #' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A'));
                } else {
                    log_message('error', 'Error generando QR Ligo: ' . json_encode($response));

                    // Si hay un error, mostrar un mensaje en la vista
                    $data['error_message'] = 'No se pudo generar el código QR. Error: ' . (is_string($response->error) ? $response->error : json_encode($response->error));
                }
            }
        } else {
            log_message('error', 'Credenciales de Ligo no configuradas para la organización ID: ' . $organization['id']);
            $data['error_message'] = 'Credenciales de Ligo no configuradas correctamente. Por favor, contacte al administrador.';
        }

        return view('payments/ligo_qr', $data);
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
        // Log para depuración
        log_message('debug', 'Iniciando generación de QR con Ligo para organización ID: ' . $organization['id']);

        try {
            // 1. Obtener token de autenticación
            $authToken = $this->getLigoAuthToken($organization);

            if (isset($authToken->error)) {
                log_message('error', 'Error al obtener token de autenticación de Ligo: ' . $authToken->error);
                return $authToken; // Devolver el error de autenticación
            }

            // 2. Generar QR con el token obtenido
            log_message('debug', 'Iniciando generación de QR con token: ' . substr($authToken->token, 0, 20) . '...');
            $qrResponse = $this->generateLigoQR($data, $authToken->token, $organization);
            
            // Guardar la respuesta completa en la base de datos o en un archivo de log
            log_message('info', 'Respuesta completa de generación de QR: ' . json_encode($qrResponse));
            
            // Guardar la respuesta en una tabla de la base de datos para referencia futura
            try {
                $db = \Config\Database::connect();
                $db->table('ligo_responses')->insert([
                    'organization_id' => $organization['id'],
                    'request_type' => 'create_qr',
                    'request_data' => json_encode($data),
                    'response_data' => json_encode($qrResponse),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                log_message('info', 'Respuesta de QR guardada en la base de datos');
            } catch (\Exception $e) {
                log_message('error', 'Error al guardar respuesta de QR en la base de datos: ' . $e->getMessage());
                // Continuar a pesar del error
            }

            if (isset($qrResponse->error)) {
                log_message('error', 'Error al generar QR en Ligo: ' . $qrResponse->error);
                return $qrResponse;
            }

            // 3. Obtener el QR generado por su ID
            $qrId = $qrResponse->data->id ?? null;

            if (!$qrId) {
                log_message('error', 'No se recibió ID de QR en la respuesta de Ligo');
                return (object)['error' => 'No QR ID in response'];
            }

            $qrDetails = $this->getQRDetailsById($qrId, $authToken->token, $organization);

            if (isset($qrDetails->error)) {
                log_message('error', 'Error al obtener detalles del QR: ' . $qrDetails->error);
                return $qrDetails;
            }

            // 4. Preparar respuesta con los datos del QR
            log_message('info', 'Respuesta completa de getCreateQRByID: ' . json_encode($qrDetails));
            
            // Intentar extraer el hash de diferentes ubicaciones en la respuesta
            $qrHash = null;
            
            // Verificar primero en data.hash
            if (isset($qrDetails->data->hash)) {
                $qrHash = $qrDetails->data->hash;
                log_message('info', 'Hash encontrado en data.hash: ' . $qrHash);
            }
            // Verificar en data.qr o data.qrString
            else if (isset($qrDetails->data->qr)) {
                $qrHash = $qrDetails->data->qr;
                log_message('info', 'Hash encontrado en data.qr: ' . $qrHash);
            }
            else if (isset($qrDetails->data->qrString)) {
                $qrHash = $qrDetails->data->qrString;
                log_message('info', 'Hash encontrado en data.qrString: ' . $qrHash);
            }
            // Verificar directamente en el nivel superior
            else if (isset($qrDetails->hash)) {
                $qrHash = $qrDetails->hash;
                log_message('info', 'Hash encontrado en hash: ' . $qrHash);
            }
            
            // Si no hay hash pero la respuesta es exitosa (status=1), usar el ID como hash temporal
            if (!$qrHash && isset($qrDetails->status) && $qrDetails->status == 1) {
                log_message('warning', 'No se recibió hash de QR pero la respuesta es exitosa. Usando ID como hash temporal.');
                $qrHash = $qrId;
            } else if (!$qrHash) {
                log_message('error', 'No se recibió hash de QR en la respuesta de Ligo. Estructura completa: ' . json_encode($qrDetails));
                return (object)['error' => 'No QR hash in response'];
            }

            // Crear un texto para el QR que incluya la información relevante para el pago
            $qrText = json_encode([
                'id' => $qrId,
                'amount' => $data['amount'],
                'currency' => $data['currency'],
                'description' => $data['description'],
                'merchant' => $organization['name'],
                'timestamp' => time(),
                'hash' => $qrHash
            ]);
            
            // Generar URL de imagen QR usando una librería o servicio
            // Usamos un servicio externo para generar el QR con la información relevante
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrText);

            // Construir respuesta
            $response = (object)[
                'qr_data' => $qrText,
                'qr_image_url' => $qrImageUrl,
                'order_id' => $qrId,
                'expiration' => date('Y-m-d H:i:s', strtotime('+1 hour')) // Ajustar según la configuración de Ligo
            ];

            log_message('info', 'QR generado exitosamente con ID: ' . $qrId);
            return $response;
        } catch (Exception $e) {
            log_message('error', 'Error en el proceso de generación de QR: ' . $e->getMessage());
            return (object)['error' => 'QR generation error: ' . $e->getMessage()];
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
        log_message('debug', 'Obteniendo token de autenticación de Ligo para organización ID: ' . $organization['id']);

        // 🚀 CACHE MEJORADO: Verificar si hay un token almacenado y si aún es válido
        if (!empty($organization['ligo_token']) && !empty($organization['ligo_token_expiry'])) {
            $expiryDate = strtotime($organization['ligo_token_expiry']);
            $now = time();
            $marginMinutes = 10; // Aumentado de 5 a 10 minutos para mayor seguridad
            
            // Si el token aún es válido (con margen ampliado), usarlo
            if ($expiryDate > ($now + ($marginMinutes * 60))) {
                $remainingMinutes = round(($expiryDate - $now) / 60, 1);
                log_message('info', '🚀 TOKEN CACHE HIT: Usando token almacenado válido (queda ' . $remainingMinutes . ' min) - org_id=' . $organization['id']);
                
                // Extraer el company ID del token JWT
                $companyId = $credentials['company_id'];
                
                // Verificar si podemos extraer el company ID del token
                $tokenParts = explode('.', $organization['ligo_token']);
                if (count($tokenParts) >= 2) {
                    try {
                        $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', $tokenParts[1]))), true);
                        if (isset($payload['companyId'])) {
                            $companyId = $payload['companyId'];
                        }
                    } catch (\Exception $e) {
                        log_message('error', 'Error al decodificar token JWT: ' . $e->getMessage());
                    }
                }
                
                return (object)[
                    'token' => $organization['ligo_token'],
                    'userId' => 'stored-user',
                    'companyId' => $companyId,
                    'is_cached' => true,
                    'remaining_minutes' => $remainingMinutes
                ];
            } else {
                $expiredMinutes = round(($now - $expiryDate) / 60, 1);
                log_message('info', '❌ TOKEN CACHE MISS: Token expirado hace ' . $expiredMinutes . ' min, obteniendo nuevo token - org_id=' . $organization['id']);
            }
        } else {
            log_message('info', '❌ TOKEN CACHE MISS: No hay token almacenado - org_id=' . $organization['id']);
        }
        
        // Si no hay token válido almacenado, intentar obtener uno nuevo
        // Get credentials if not already obtained
        if (!isset($credentials)) {
            $credentials = $this->getLigoCredentials($organization);
        }
        
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['company_id'])) {
            log_message('error', 'Credenciales de Ligo incompletas para organización ID: ' . $organization['id']);
            return (object)['error' => 'Incomplete Ligo credentials'];
        }
        
        // Obtener configuración dinámica
        $config = $this->getLigoConfig($organization);
        
        // URL específica para autenticación
        $authUrl = $config['auth_url'] . '/v1/auth/sign-in?companyId=' . $credentials['company_id'];
        log_message('info', "🌍 ENTORNO: {$config['environment']} - Auth URL: {$authUrl}");
        log_message('debug', 'URL de autenticación completa: ' . $authUrl);
        
        // Intentar generar el token JWT usando la clave privada
        try {
            // Verificar que la clave privada exista
            if (empty($organization['ligo_private_key'])) {
                log_message('error', 'Clave privada de Ligo no configurada para la organización ID: ' . $organization['id']);
                return (object)['error' => 'Ligo private key not configured'];
            }
            
            // Cargar la clase JwtGenerator
            $privateKey = $organization['ligo_private_key'];
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
            
            log_message('info', 'Token JWT generado correctamente');
            log_message('debug', 'Token JWT: ' . substr($authorizationToken, 0, 30) . '...');
        } catch (\Exception $e) {
            log_message('error', 'Error al generar token JWT: ' . $e->getMessage());
            return (object)['error' => 'Error al generar token JWT: ' . $e->getMessage()];
        }
        
        $companyId = $credentials['company_id'];
        // Usar la URL específica para autenticación
        $url = $authUrl;
        
        $curl = curl_init();
        
        // Datos de autenticación para la solicitud POST
        $authData = [
            'username' => $credentials['username'],
            'password' => $credentials['password']
        ];
        $requestBody = json_encode($authData);
        
        log_message('debug', 'Enviando solicitud de autenticación a: ' . $url);
        log_message('debug', 'Datos de autenticación: ' . json_encode(['username' => $authData['username'], 'password' => '********']));
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $requestBody,  // Agregar datos de autenticación
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $authorizationToken,
                'Content-Type: application/json',
                'Content-Length: ' . strlen($requestBody),  // Agregar Content-Length
                'Accept: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => $config['ssl_verify_host'],
            CURLOPT_SSL_VERIFYPEER => $config['ssl_verify']
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Error en solicitud de autenticación: ' . $err);
            return (object)['error' => 'Error en solicitud de autenticación: ' . $err];
        }
        
        if ($info['http_code'] != 200) {
            log_message('error', 'Error en autenticación. HTTP Code: ' . $info['http_code'] . ' - Respuesta: ' . $response);
            return (object)['error' => 'Error en autenticación. HTTP Code: ' . $info['http_code']];
        }
        
        $decoded = json_decode($response);
        
        // Verificar si hay token en la respuesta
        if (!$decoded || !isset($decoded->data) || !isset($decoded->data->access_token)) {
            log_message('error', 'No se recibió token en la respuesta: ' . json_encode($decoded));
            
            // Extraer mensaje de error
            $errorMsg = 'No token in auth response';
            if (isset($decoded->message)) {
                $errorMsg .= ': ' . $decoded->message;
            } elseif (isset($decoded->errors)) {
                $errorMsg .= ': ' . (is_string($decoded->errors) ? $decoded->errors : json_encode($decoded->errors));
            } elseif (isset($decoded->error)) {
                $errorMsg .= ': ' . (is_string($decoded->error) ? $decoded->error : json_encode($decoded->error));
            }
            
            return (object)['error' => $errorMsg];
        }
        
        log_message('info', 'Autenticación con Ligo exitosa, token obtenido');
        log_message('debug', 'Token de acceso recibido: ' . substr($decoded->data->access_token, 0, 30) . '...');
        
        // Guardar el token en la base de datos para futuros usos
        try {
            $organizationModel = new \App\Models\OrganizationModel();
            
            // Calcular fecha de expiración
            $expiryDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $organizationModel->update($organization['id'], [
                'ligo_token' => $decoded->data->access_token,
                'ligo_token_expiry' => $expiryDate,
                'ligo_auth_error' => null,
                'ligo_enabled' => 1 // Habilitar Ligo automáticamente si la autenticación es exitosa
            ]);
            
            log_message('info', 'Token guardado en la base de datos con expiración: ' . $expiryDate);
        } catch (\Exception $e) {
            log_message('error', 'Error al guardar token en la base de datos: ' . $e->getMessage());
            // Continuar aunque no se pueda guardar el token
        }
        
        return (object)[
            'token' => $decoded->data->access_token,
            'userId' => $decoded->data->userId ?? null,
            'companyId' => $decoded->data->companyId ?? $companyId
        ];
    }
    
    /**
     * Generate QR in Ligo API
     *
     * @param array $data Order data
     * @param string $token Authentication token
     * @param array $organization Organization data
     * @return object Response from Ligo API
     */
    private function generateLigoQR($data, $token, $organization)
    {
        log_message('debug', 'Generando QR en Ligo para factura: ' . $data['orderId']);

        try {
            $curl = curl_init();

            // Asegurar que tenemos valores válidos para los campos requeridos
            $idCuenta = !empty($organization['ligo_account_id']) ? $organization['ligo_account_id'] : '92100178794744781044';
            $codigoComerciante = !empty($organization['ligo_merchant_code']) ? $organization['ligo_merchant_code'] : '4829';
            
            // Registrar los valores para depuración
            log_message('debug', 'Valores para generación de QR - idCuenta: ' . $idCuenta . ', codigoComerciante: ' . $codigoComerciante);
            
            // Determinar el tipo de QR a generar (estático o dinámico)
            $qrTipo = isset($data['qr_type']) && $data['qr_type'] === 'static' ? '11' : '12';
            log_message('debug', 'Tipo de QR a generar: ' . ($qrTipo === '11' ? 'Estático' : 'Dinámico'));
            
            // Preparar datos para la generación de QR según la documentación
            $qrData = [
                'header' => [
                    'sisOrigen' => '0921' // Valor del archivo de Postman: debtorParticipantCode
                ],
                'data' => [
                    'qrTipo' => $qrTipo, // 11 = Estático, 12 = Dinámico con monto
                    'idCuenta' => $idCuenta, // Aseguramos que no esté vacío
                    'moneda' => $data['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                    'codigoComerciante' => $codigoComerciante, // Aseguramos que no esté vacío
                    'nombreComerciante' => $organization['name'],
                    'ciudadComerciante' => $organization['city'] ?? 'Lima'
                ],
                'type' => 'TEXT'
            ];
            
            // Agregar campos adicionales para QR dinámico
            if ($qrTipo === '12') {
                $qrData['data']['importe'] = (int)($data['amount'] * 100); // Convertir a centavos
                $qrData['data']['cantidadPagos'] = 1; // Cantidad de pagos permitidos por QR
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
                $qrData['data']['cantidadPagos'] = 1; // Cantidad de pagos permitidos por QR (siempre 1)
                $qrData['data']['glosa'] = null;
                $qrData['data']['info'] = null;
            }

            // Obtener configuración dinámica y URL para generar QR
            $config = $this->getLigoConfig($organization);
            $url = $config['api_url'] . '/v1/createQr';

            log_message('debug', 'URL para generar QR: ' . $url);
            log_message('debug', 'Datos para generar QR: ' . json_encode($qrData));

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
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ],
                CURLOPT_SSL_VERIFYHOST => $config['ssl_verify_host'],
                CURLOPT_SSL_VERIFYPEER => $config['ssl_verify']
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);
            
            // Registrar información detallada de la solicitud y respuesta
            log_message('debug', 'Solicitud a Ligo - URL: ' . $url);
            log_message('debug', 'Solicitud a Ligo - Datos: ' . json_encode($qrData));
            log_message('debug', 'Solicitud a Ligo - Headers: Authorization Bearer ' . substr($token, 0, 20) . '...');
            log_message('debug', 'Respuesta de Ligo - HTTP Code: ' . $info['http_code']);
            log_message('debug', 'Respuesta de Ligo - Content Type: ' . $info['content_type']);
            log_message('debug', 'Respuesta de Ligo - Total Time: ' . $info['total_time'] . ' segundos');

            curl_close($curl);

            if ($err) {
                log_message('error', 'Error al generar QR en Ligo: ' . $err);
                return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
            }

            log_message('debug', 'Respuesta de generación de QR: ' . $response);
            
            // Guardar la solicitud y respuesta completas en un archivo de log
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'organization_id' => $organization['id'],
                'request_url' => $url,
                'request_data' => $qrData,
                'response_code' => $info['http_code'],
                'response_data' => $response
            ];
            
            // Guardar en un archivo de log
            $logFile = WRITEPATH . 'logs/ligo_qr_' . date('Y-m-d') . '.log';
            file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);
            log_message('info', 'Detalles de QR guardados en: ' . $logFile);

            $decoded = json_decode($response);

            log_message('error', 'LIGO QR CREATE DEBUG - Raw response: ' . $response);
            log_message('error', 'LIGO QR CREATE DEBUG - Decoded: ' . json_encode($decoded));
            log_message('error', 'LIGO QR CREATE DEBUG - HTTP Code: ' . $info['http_code']);

            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta de generación de QR: ' . json_last_error_msg());
                return (object)['error' => 'Invalid JSON in QR generation response: ' . json_last_error_msg()];
            }

            // Verificar si hay errores en la respuesta - La API devuelve status=1 para éxito
            if (!isset($decoded->status) || $decoded->status != 1 || !isset($decoded->data) || !isset($decoded->data->id)) {
                log_message('error', 'Error en la respuesta de generación de QR: ' . json_encode($decoded));
                log_message('error', 'LIGO QR CREATE ERROR - Response structure: status=' . (isset($decoded->status) ? $decoded->status : 'missing') . ', data=' . (isset($decoded->data) ? 'exists' : 'missing') . ', id=' . (isset($decoded->data->id) ? 'exists' : 'missing'));
                return (object)['error' => 'Invalid response from Ligo API: ' . json_encode($decoded)];
            }

            return $decoded;
        } catch (Exception $e) {
            log_message('error', 'Error en el proceso de generación de QR: ' . $e->getMessage());
            return (object)['error' => 'QR generation error: ' . $e->getMessage()];
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
    private function getQRDetailsById($qrId, $token, $organization, $maxRetries = 3, $currentAttempt = 1)
    {
        log_message('debug', "Obteniendo detalles de QR con ID: {$qrId} (Intento {$currentAttempt}/{$maxRetries})");

        try {
            $curl = curl_init();
            
            // Obtener configuración dinámica y URL para obtener detalles del QR
            $config = $this->getLigoConfig($organization);
            $url = $config['api_url'] . '/v1/getCreateQRById/' . $qrId;
            
            log_message('debug', 'URL para obtener detalles del QR: ' . $url);
            
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
                CURLOPT_SSL_VERIFYHOST => $config['ssl_verify_host'],
                CURLOPT_SSL_VERIFYPEER => $config['ssl_verify']
            ]);
            
            $response = curl_exec($curl);
            $info = curl_getinfo($curl);
            $err = curl_error($curl);
            
            curl_close($curl);
            
            if ($err) {
                log_message('error', 'Error de cURL al obtener detalles del QR: ' . $err);
                return (object)['error' => 'cURL Error: ' . $err];
            }
            
            // Registrar la respuesta para depuración
            $logData = [
                'timestamp' => date('Y-m-d H:i:s'),
                'qr_id' => $qrId,
                'attempt' => $currentAttempt,
                'response_code' => $info['http_code'],
                'response_data' => $response
            ];
            
            // Guardar en un archivo de log
            $logFile = WRITEPATH . 'logs/ligo_qr_details_' . date('Y-m-d') . '.log';
            file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);
            log_message('info', 'Detalles de QR guardados en: ' . $logFile);
            
            $decoded = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta de detalles de QR: ' . json_last_error_msg());
                return (object)['error' => 'Invalid JSON in QR details response: ' . json_last_error_msg()];
            }
            
            // Verificar si hay errores en la respuesta
            if (!isset($decoded->data)) {
                log_message('error', 'Error en la respuesta de detalles de QR: ' . json_encode($decoded));
                return (object)['error' => 'Error in QR details response: ' . json_encode($decoded)];
            }
            
            // Verificar si los datos están vacíos y si podemos reintentar
            if (empty($decoded->data) || (is_object($decoded->data) && empty((array)$decoded->data))) {
                log_message('warning', "La respuesta de detalles de QR no contiene datos (intento {$currentAttempt}/{$maxRetries}): " . json_encode($decoded));
                
                // Si no hemos alcanzado el máximo de reintentos, esperar y reintentar
                if ($currentAttempt < $maxRetries) {
                    $waitTime = $currentAttempt * 2; // Incrementar el tiempo de espera: 2s, 4s, 6s
                    log_message('info', "Esperando {$waitTime} segundos antes del siguiente intento...");
                    sleep($waitTime);
                    
                    // Llamada recursiva con el siguiente intento
                    return $this->getQRDetailsById($qrId, $token, $organization, $maxRetries, $currentAttempt + 1);
                }
                
                // Si agotamos todos los intentos, crear datos mínimos para que el flujo continúe
                log_message('warning', "Agotados todos los intentos para obtener detalles de QR. Usando datos de respaldo.");
                $decoded->data = (object)[
                    'hash' => 'LIGO-PENDING-' . $qrId . '-' . time(),
                    'idQr' => $qrId,
                    'status' => 'pending_details',
                    'note' => 'QR created successfully but details are still being processed by Ligo'
                ];
            } else {
                // Datos obtenidos exitosamente
                log_message('info', "Detalles de QR obtenidos exitosamente en el intento {$currentAttempt}");
                
                // Verificar si tenemos el hash real
                if (isset($decoded->data->hash) && !empty($decoded->data->hash)) {
                    log_message('info', 'Hash del QR obtenido: ' . substr($decoded->data->hash, 0, 50) . '...');
                }
            }
            
            return $decoded;
        } catch (\Exception $e) {
            log_message('error', 'Error al obtener detalles del QR: ' . $e->getMessage());
            return (object)['error' => 'QR details error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Clean up old QR cache entries to prevent database bloat
     * Removes QR cache entries older than 24 hours
     */
    private function cleanupOldQRCache()
    {
        try {
            $hashModel = new \App\Models\LigoQRHashModel();
            $cleanupTime = date('Y-m-d H:i:s', strtotime('-24 hours'));
            
            // Get count before cleanup for logging
            $oldCount = $hashModel->where('created_at <', $cleanupTime)->countAllResults(false);
            
            if ($oldCount > 0) {
                // Delete old cache entries
                $hashModel->where('created_at <', $cleanupTime)->delete();
                
                log_message('info', '🧹 CACHE CLEANUP: Eliminadas ' . $oldCount . ' entradas de cache QR antiguas (>24h)');
            } else {
                log_message('debug', '🧹 CACHE CLEANUP: No hay entradas de cache QR antiguas para eliminar');
            }
        } catch (\Exception $e) {
            log_message('error', 'Error durante limpieza de cache QR: ' . $e->getMessage());
        }
    }
}

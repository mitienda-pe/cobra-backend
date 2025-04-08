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
     * Generate QR code and return JSON data for AJAX requests
     *
     * @param string $invoiceIdentifier UUID o ID de la factura
     * @param int|null $instalmentId ID de la cuota (opcional)
     * @return mixed
     */
    public function ajaxQR($invoiceIdentifier, $instalmentId = null)
    {
        // Registrar información de depuración
        log_message('debug', 'ajaxQR llamado con identificador: ' . $invoiceIdentifier . ', instalmentId: ' . ($instalmentId ?? 'null'));

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

        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            // Si Ligo no está habilitado, usar un QR de demostración temporal
            log_message('info', 'Ligo no está habilitado para la organización ID: ' . $organization['id'] . '. Usando QR de demostración.');

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
        if (!empty($organization['ligo_username']) && !empty($organization['ligo_password']) && !empty($organization['ligo_company_id'])) {
            // Preparar datos para la orden
            $orderData = [
                'amount' => $paymentAmount,
                'currency' => $invoice['currency'] ?? 'PEN',
                'orderId' => $invoice['id'],
                'description' => $paymentDescription
            ];

            // Log para depuración
            log_message('debug', 'Intentando crear orden en Ligo con datos: ' . json_encode($orderData));

            // Crear orden en Ligo
            $response = $this->createLigoOrder($orderData, $organization);

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
        if (!empty($organization['ligo_username']) && !empty($organization['ligo_password']) && !empty($organization['ligo_company_id'])) {
            // Preparar datos para la orden
            $orderData = [
                'amount' => $paymentAmount,
                'currency' => $invoice['currency'] ?? 'PEN',
                'orderId' => $invoice['id'],
                'description' => $paymentDescription
            ];

            // Log para depuración
            log_message('debug', 'Intentando crear orden en Ligo con datos: ' . json_encode($orderData));
            log_message('debug', 'Organización: ' . $organization['id'] . ' - Username: ' . $organization['ligo_username']);

            // Crear orden en Ligo
            $response = $this->createLigoOrder($orderData, $organization);

            // Log de respuesta
            log_message('debug', 'Respuesta de Ligo: ' . json_encode($response));

            if (!isset($response->error)) {
                $data['qr_data'] = $response->qr_data ?? null;
                $data['qr_image_url'] = $response->qr_image_url ?? null;
                $data['order_id'] = $response->order_id ?? null;
                $data['expiration'] = $response->expiration ?? null;

                // Log de éxito
                log_message('info', 'QR generado exitosamente para factura #' . ($invoice['number'] ?? $invoice['invoice_number'] ?? 'N/A'));
            } else {
                log_message('error', 'Error generando QR Ligo: ' . json_encode($response));

                // Si hay un error, mostrar un mensaje en la vista
                $data['error_message'] = 'No se pudo generar el código QR. Error: ' . (is_string($response->error) ? $response->error : json_encode($response->error));
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
            $qrResponse = $this->generateLigoQR($data, $authToken->token, $organization);

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
            $qrHash = $qrDetails->data->hash ?? null;

            if (!$qrHash) {
                log_message('error', 'No se recibió hash de QR en la respuesta de Ligo');
                return (object)['error' => 'No QR hash in response'];
            }

            // Generar URL de imagen QR usando una librería o servicio
            $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrHash);

            // Construir respuesta
            $response = (object)[
                'qr_data' => $qrHash,
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

        // Verificar si hay un token almacenado y si aún es válido
        if (!empty($organization['ligo_token']) && !empty($organization['ligo_token_expiry'])) {
            $expiryDate = strtotime($organization['ligo_token_expiry']);
            $now = time();
            
            // Si el token aún es válido (con 5 minutos de margen), usarlo
            if ($expiryDate > ($now + 300)) {
                log_message('info', 'Usando token almacenado válido hasta: ' . $organization['ligo_token_expiry']);
                
                // Extraer el company ID del token JWT
                $companyId = $organization['ligo_company_id'];
                
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
                    'companyId' => $companyId
                ];
            } else {
                log_message('info', 'Token almacenado expirado, obteniendo nuevo token');
            }
        }
        
        // Si no hay token válido almacenado, intentar obtener uno nuevo
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            log_message('error', 'Credenciales de Ligo incompletas para organización ID: ' . $organization['id']);
            return (object)['error' => 'Incomplete Ligo credentials'];
        }
        
        try {
            // Eliminar espacios en blanco de las credenciales
            $username = trim($organization['ligo_username']);
            $password = trim($organization['ligo_password']);
            $companyId = trim($organization['ligo_company_id']);
            
            $curl = curl_init();
            
            // Datos de autenticación
            $authData = [
                'username' => $username,
                'password' => $password
            ];
            
            // URL de autenticación
            $prefix = 'dev'; // Cambiar a 'prod' para entorno de producción
            $url = 'https://cce-auth-' . $prefix . '.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
            
            log_message('debug', 'URL de autenticación Ligo: ' . $url);
            
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
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if ($err) {
                log_message('error', 'Error al conectar con Ligo: ' . $err);
                return (object)['error' => 'Failed to connect to Ligo Auth API: ' . $err];
            }
            
            // Log de respuesta
            log_message('debug', 'Código de respuesta HTTP: ' . $info['http_code']);
            
            // Verificar si la respuesta es HTML
            if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
                log_message('error', 'Ligo Auth API devolvió HTML en lugar de JSON');
                return (object)['error' => 'Auth API returned HTML response'];
            }
            
            $decoded = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta JSON: ' . json_last_error_msg());
                return (object)['error' => 'Invalid JSON in auth response: ' . json_last_error_msg()];
            }
            
            // Verificar si hay token en la respuesta
            if (!isset($decoded->data) || !isset($decoded->data->access_token)) {
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
            
            // Guardar el token en la base de datos para futuros usos
            try {
                $organizationModel = new \App\Models\OrganizationModel();
                
                // Calcular fecha de expiración
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $organizationModel->update($organization['id'], [
                    'ligo_token' => $decoded->data->access_token,
                    'ligo_token_expiry' => $expiryDate,
                    'ligo_auth_error' => null
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
            
        } catch (\Exception $e) {
            log_message('error', 'Excepción en autenticación Ligo: ' . $e->getMessage());
            return (object)['error' => 'Authentication error: ' . $e->getMessage()];
        }
        
        // Si todo falla y hay un token de respaldo, usarlo como último recurso
        if (!empty($GLOBALS['ligo_fallback_token'])) {
            log_message('warning', 'Usando token de respaldo como último recurso');
            return (object)[
                'token' => $GLOBALS['ligo_fallback_token'],
                'userId' => 'fallback-user',
                'companyId' => $organization['ligo_company_id'] ?? 'e8b4a36d-6f1d-4a2a-bf3a-ce9371dde4ab'
            ];
        }
        
        return (object)['error' => 'Failed to get authentication token'];
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

            // Preparar datos para la generación de QR según la documentación
            $qrData = [
                'header' => [
                    'sisOrigen' => '0921' // Valor del archivo de Postman: debtorParticipantCode
                ],
                'data' => [
                    'qrTipo' => '12', // QR dinámico con monto
                    'idCuenta' => $organization['ligo_account_id'] ?? '92100178794744781044', // Valor del archivo de Postman: idCuentaQr
                    'moneda' => $data['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                    'importe' => (int)($data['amount'] * 100), // Convertir a centavos
                    'fechaVencimiento' => null,
                    'cantidadPagos' => null,
                    'glosa' => $data['description'],
                    'codigoComerciante' => $organization['ligo_merchant_code'] ?? '4829', // Valor del archivo de Postman: merchantCode
                    'nombreComerciante' => $organization['name'],
                    'ciudadComerciante' => $organization['city'] ?? 'Lima',
                    'info' => json_encode(['invoice_id' => $data['orderId']])
                ],
                'type' => 'TEXT'
            ];

            // URL para generar QR según la documentación
            $prefix = 'dev'; // Cambiar a 'dev' para entorno de desarrollo
            $url = 'https://cce-api-gateway-' . $prefix . '.ligocloud.tech/v1/createQr';

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
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);

            curl_close($curl);

            if ($err) {
                log_message('error', 'Error al generar QR en Ligo: ' . $err);
                return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
            }

            log_message('debug', 'Respuesta de generación de QR: ' . $response);

            $decoded = json_decode($response);

            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta de generación de QR: ' . json_last_error_msg());
                return (object)['error' => 'Invalid JSON in QR generation response: ' . json_last_error_msg()];
            }

            // Verificar si hay errores en la respuesta
            if (!isset($decoded->data) || !isset($decoded->data->id)) {
                log_message('error', 'Error en la respuesta de generación de QR: ' . json_encode($decoded));
                return (object)['error' => 'Error in QR generation response: ' . json_encode($decoded)];
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
    private function getQRDetailsById($qrId, $token, $organization)
    {
        log_message('debug', 'Obteniendo detalles de QR con ID: ' . $qrId);

        try {
            $curl = curl_init();

            // URL para obtener detalles del QR según la documentación
            $prefix = 'dev'; // Cambiar a 'prod' para entorno de producción
            $url = 'https://cce-api-gateway-' . $prefix . '.ligocloud.tech/v1/getCreateQRById/' . $qrId;

            log_message('debug', 'URL para obtener detalles de QR: ' . $url);

            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                log_message('error', 'Error al obtener detalles de QR: ' . $err);
                return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
            }

            log_message('debug', 'Respuesta de detalles de QR: ' . $response);

            $decoded = json_decode($response);

            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta de detalles de QR: ' . json_last_error_msg());
                return (object)['error' => 'Invalid JSON in QR details response: ' . json_last_error_msg()];
            }

            // Verificar si hay errores en la respuesta
            if (!isset($decoded->data) || !isset($decoded->data->hash)) {
                log_message('error', 'Error en la respuesta de detalles de QR: ' . json_encode($decoded));
                return (object)['error' => 'Error in QR details response: ' . json_encode($decoded)];
            }

            return $decoded;
        } catch (Exception $e) {
            log_message('error', 'Error al obtener detalles de QR: ' . $e->getMessage());
            return (object)['error' => 'QR details error: ' . $e->getMessage()];
        }
    }
}

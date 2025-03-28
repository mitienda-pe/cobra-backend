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
     * Display QR code page for invoice payment
     *
     * @param string $invoiceId
     * @return mixed
     */
    public function index($invoiceId)
    {
        // Get invoice details
        $invoice = $this->invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            return redirect()->to('/invoices')->with('error', 'Organización no encontrada');
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
                    'amount' => $invoice['amount'],
                    'currency' => $invoice['currency'] ?? 'PEN',
                    'description' => "Pago factura #{$invoice['invoice_number']}",
                    'demo' => true
                ]),
                'qr_image_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode("DEMO QR - Factura #{$invoice['invoice_number']}"),
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
                'amount' => $invoice['amount'],
                'currency' => $invoice['currency'] ?? 'PEN',
                'orderId' => $invoice['id'],
                'description' => "Pago factura #{$invoice['invoice_number']}"
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
                log_message('info', 'QR generado exitosamente para factura #' . $invoice['invoice_number']);
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
        
        // Verificar credenciales
        if (empty($organization['ligo_username']) || empty($organization['ligo_password']) || empty($organization['ligo_company_id'])) {
            log_message('error', 'Credenciales de Ligo incompletas para organización ID: ' . $organization['id']);
            return (object)['error' => 'Incomplete Ligo credentials'];
        }
        
        try {
            $curl = curl_init();
            
            // Datos de autenticación según la documentación
            $authData = [
                'username' => $organization['ligo_username'],
                'password' => $organization['ligo_password']
            ];
            
            // URL de autenticación según la documentación
            $prefix = 'prod'; // Cambiar a 'dev' para entorno de desarrollo
            $url = 'https://cce-auth-' . $prefix . '.ligocloud.tech/v1/auth/sign-in?companyId=' . $organization['ligo_company_id'];
            
            log_message('debug', 'URL de autenticación Ligo: ' . $url);
            log_message('debug', 'Datos de autenticación: ' . json_encode($authData));
            
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
            
            // Log detallado de la información de la solicitud
            log_message('debug', 'Ligo Auth API Info: ' . json_encode($info));
            
            if ($err) {
                log_message('error', 'Error al obtener token de Ligo: ' . $err);
                return (object)['error' => 'Failed to connect to Ligo Auth API: ' . $err];
            }
            
            // Log de respuesta
            log_message('debug', 'Respuesta de autenticación Ligo (primeros 500 caracteres): ' . substr($response, 0, 500));
            
            // Verificar si la respuesta es HTML
            if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
                log_message('error', 'Ligo Auth API devolvió HTML en lugar de JSON');
                
                // Guardar la respuesta HTML para diagnóstico
                $htmlFile = WRITEPATH . 'logs/ligo_auth_response_' . date('Y-m-d_H-i-s') . '.html';
                file_put_contents($htmlFile, $response);
                log_message('error', 'Respuesta HTML guardada en: ' . $htmlFile);
                
                // Intentar extraer mensajes de error del HTML
                preg_match('/<title>(.*?)<\/title>/i', $response, $titleMatches);
                $errorTitle = isset($titleMatches[1]) ? $titleMatches[1] : 'Unknown error';
                
                return (object)['error' => 'Auth API returned HTML: ' . $errorTitle];
            }
            
            $decoded = json_decode($response);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta de autenticación: ' . json_last_error_msg());
                log_message('error', 'Respuesta cruda: ' . $response);
                return (object)['error' => 'Invalid JSON in auth response: ' . json_last_error_msg()];
            }
            
            // Verificar si hay errores en la respuesta
            if (!isset($decoded->data) || !isset($decoded->data->access_token)) {
                log_message('error', 'No se recibió token en la respuesta de autenticación: ' . json_encode($decoded));
                return (object)['error' => 'No token in auth response'];
            }
            
            log_message('info', 'Token de autenticación Ligo obtenido correctamente');
            
            // Devolver un objeto con el token en el formato esperado por el resto del código
            return (object)[
                'token' => $decoded->data->access_token,
                'userId' => $decoded->data->userId,
                'companyId' => $decoded->data->companyId
            ];
            
        } catch (Exception $e) {
            log_message('error', 'Error en el proceso de autenticación: ' . $e->getMessage());
            return (object)['error' => 'Authentication error: ' . $e->getMessage()];
        }
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
                    'sisOrigen' => '0921' // Este valor puede variar según la configuración de Ligo
                ],
                'data' => [
                    'qrTipo' => '12', // QR dinámico con monto
                    'idCuenta' => $organization['ligo_account_id'] ?? '92100144571260631044', // Debe configurarse en la organización
                    'moneda' => $data['currency'] == 'PEN' ? '604' : '840', // 604 = Soles, 840 = Dólares
                    'importe' => (int)($data['amount'] * 100), // Convertir a centavos
                    'fechaVencimiento' => null,
                    'cantidadPagos' => null,
                    'glosa' => $data['description'],
                    'codigoComerciante' => $organization['ligo_merchant_code'] ?? '4829', // Debe configurarse en la organización
                    'nombreComerciante' => $organization['name'],
                    'ciudadComerciante' => $organization['city'] ?? 'Lima',
                    'info' => json_encode(['invoice_id' => $data['orderId']])
                ],
                'type' => 'TEXT'
            ];
            
            // URL para generar QR según la documentación
            $prefix = 'prod'; // Cambiar a 'dev' para entorno de desarrollo
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
            $prefix = 'prod'; // Cambiar a 'dev' para entorno de desarrollo
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

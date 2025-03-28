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
            return redirect()->to('/invoices')->with('error', 'Invoice not found');
        }
        
        // Get organization details
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            return redirect()->to('/invoices')->with('error', 'Organization not found');
        }
        
        // Check if Ligo is enabled for this organization
        if (!isset($organization['ligo_enabled']) || !$organization['ligo_enabled']) {
            return redirect()->to('/invoices')->with('error', 'Ligo payments not enabled for this organization');
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
            }
        } else {
            log_message('error', 'Credenciales de Ligo no configuradas para la organización ID: ' . $organization['id']);
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
        log_message('debug', 'Iniciando llamada a Ligo API con organización ID: ' . $organization['id']);
        
        // Asegurarse de que las credenciales estén presentes
        if (empty($organization['ligo_api_key'])) {
            log_message('error', 'API Key de Ligo no configurada para organización ID: ' . $organization['id']);
            return (object)['error' => 'Ligo API Key not configured'];
        }
        
        // Intentar autenticarse primero para obtener un token
        $authToken = $this->getLigoAuthToken($organization);
        
        if (isset($authToken->error)) {
            log_message('error', 'Error al obtener token de autenticación de Ligo: ' . $authToken->error);
            return $authToken; // Devolver el error de autenticación
        }
        
        // Usar el token para la solicitud de creación de orden
        $curl = curl_init();
        
        $headers = [
            'Authorization: Bearer ' . $authToken->token,
            'Content-Type: application/json'
        ];
        
        log_message('debug', 'Headers para Ligo API: ' . json_encode($headers));
        log_message('debug', 'Datos para Ligo API: ' . json_encode($data));
        
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.ligo.pe/v1/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYHOST => 0, // Deshabilitar verificación de host SSL
            CURLOPT_SSL_VERIFYPEER => false, // Deshabilitar verificación de certificado SSL
            CURLOPT_VERBOSE => true
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        $info = curl_getinfo($curl);
        
        // Log de información de la solicitud
        log_message('debug', 'Ligo API Info: ' . json_encode($info));
        
        curl_close($curl);
        
        if ($err) {
            log_message('error', 'Ligo API Error: ' . $err);
            return (object)['error' => 'Failed to connect to Ligo API: ' . $err];
        }
        
        // Log de la respuesta completa
        log_message('debug', 'Ligo API Response (raw): ' . $response);
        
        // Verificar si la respuesta es HTML en lugar de JSON
        if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
            log_message('error', 'Ligo API devolvió HTML en lugar de JSON. Posible error de autenticación o redirección.');
            return (object)['error' => 'API returned HTML instead of JSON. Check credentials.'];
        }
        
        // Verificar si la respuesta está vacía
        if (empty(trim($response))) {
            log_message('error', 'Ligo API devolvió una respuesta vacía.');
            return (object)['error' => 'Empty response from API'];
        }
        
        $decoded = json_decode($response);
        
        // Verificar si la respuesta se pudo decodificar
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_message('error', 'Error decodificando respuesta de Ligo: ' . json_last_error_msg());
            log_message('error', 'Respuesta cruda: ' . $response);
            return (object)['error' => 'Invalid JSON response: ' . json_last_error_msg()];
        }
        
        // Verificar si hay errores en la respuesta de la API
        if (isset($decoded->error) || isset($decoded->message) || $info['http_code'] >= 400) {
            $errorMsg = isset($decoded->error) ? $decoded->error : 
                       (isset($decoded->message) ? $decoded->message : 'HTTP Error: ' . $info['http_code']);
            log_message('error', 'Ligo API Error Response: ' . $errorMsg);
            return (object)['error' => $errorMsg];
        }
        
        return $decoded;
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
        
        // Verificar si tenemos la llave privada para firmar el token
        if (empty($organization['ligo_private_key'])) {
            log_message('error', 'Llave privada de Ligo no configurada para organización ID: ' . $organization['id']);
            return (object)['error' => 'Ligo private key not configured'];
        }
        
        // Según la documentación, primero debemos crear un token firmado con la llave privada
        // Este token se usará en el encabezado Authorization
        try {
            // Crear un token firmado con la llave privada (esto es un ejemplo, ajustar según la documentación específica)
            $privateKey = $organization['ligo_private_key'];
            $signedToken = $this->createSignedToken($privateKey);
            
            $curl = curl_init();
            
            // Datos de autenticación según la documentación
            $authData = [
                'username' => $organization['ligo_username'],
                'password' => $organization['ligo_password']
            ];
            
            // URL de autenticación según la documentación
            $url = 'https://cce-auth-prod.ligocloud.tech/v1/auth/sign-in?companyId=' . $organization['ligo_company_id'];
            
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
                    'Accept: application/json',
                    'Authorization: ' . $signedToken
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
     * Create a signed token using the private key
     * 
     * @param string $privateKey Private key in PEM format
     * @return string Signed token
     */
    private function createSignedToken($privateKey)
    {
        // Implementación básica de ejemplo - esto debe ajustarse según la documentación específica de Ligo
        // sobre cómo crear el token firmado
        
        // En un escenario real, aquí se usaría la llave privada para firmar un payload
        // Por ahora, devolvemos un placeholder para pruebas
        return 'SignedToken_Placeholder';
    }
}

<?php

namespace App\Controllers;

use CodeIgniter\Controller;

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
        
        // Prepare data for view - sin generar QR por ahora
        $data = [
            'title' => 'Pago con QR - Ligo',
            'invoice' => $invoice,
            'qr_data' => null,
            'qr_image_url' => null,
            'order_id' => null,
            'expiration' => null
        ];
        
        // Intentar generar QR solo si las credenciales están configuradas
        if (!empty($organization['ligo_api_key']) && !empty($organization['ligo_api_secret'])) {
            // Preparar datos para la orden
            $orderData = [
                'amount' => $invoice['amount'],
                'currency' => $invoice['currency'] ?? 'PEN',
                'orderId' => $invoice['id'],
                'description' => "Pago factura #{$invoice['invoice_number']}"
            ];
            
            // Crear orden en Ligo
            $response = $this->createLigoOrder($orderData, $organization);
            
            if (!isset($response->error)) {
                $data['qr_data'] = $response->qr_data ?? null;
                $data['qr_image_url'] = $response->qr_image_url ?? null;
                $data['order_id'] = $response->order_id ?? null;
                $data['expiration'] = $response->expiration ?? null;
            } else {
                log_message('error', 'Error generando QR Ligo: ' . $response->error);
            }
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
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.ligo.pe/v1/orders',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $organization['ligo_api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYHOST => 0, // Deshabilitar verificación de host SSL
            CURLOPT_SSL_VERIFYPEER => false, // Deshabilitar verificación de certificado SSL
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

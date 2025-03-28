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
        if (empty($organization['ligo_api_key']) || empty($organization['ligo_api_secret'])) {
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
    
    /**
     * Check if user can access an invoice
     */
    private function canAccessInvoice($invoice)
    {
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can access any invoice in their organization
            return $invoice['organization_id'] == $this->user['organization_id'];
        } else {
            // For regular users, check if they have access to the client through portfolios
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
    }
}

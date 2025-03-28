<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class LigoWebhookController extends ResourceController
{
    use ResponseTrait;
    
    protected $invoiceModel;
    protected $organizationModel;
    protected $paymentModel;

    public function __construct()
    {
        $this->invoiceModel = new \App\Models\InvoiceModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        $this->paymentModel = new \App\Models\PaymentModel();
    }

    /**
     * Handle payment notification from Ligo
     *
     * @return mixed
     */
    public function handlePaymentNotification()
    {
        // Get request payload
        $payload = $this->request->getJSON();
        
        // Validate payload
        if (!$payload || !isset($payload->type) || !isset($payload->data) || !isset($payload->data->order_id)) {
            log_message('error', 'Invalid Ligo webhook payload: ' . json_encode($payload));
            return $this->fail('Invalid webhook payload', 400);
        }
        
        // Get invoice from order_id
        $invoiceId = $payload->data->order_id;
        $invoice = $this->invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            log_message('error', 'Ligo webhook: Invoice not found for order_id: ' . $invoiceId);
            return $this->fail('Invoice not found', 404);
        }
        
        // Get organization
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            log_message('error', 'Ligo webhook: Organization not found for invoice: ' . $invoiceId);
            return $this->fail('Organization not found', 404);
        }
        
        // Verify webhook signature
        $signature = $this->request->getHeaderLine('Ligo-Signature');
        
        if (!$this->verifySignature($signature, $this->request->getBody(), $organization['ligo_webhook_secret'])) {
            log_message('error', 'Ligo webhook: Invalid signature for invoice: ' . $invoiceId);
            return $this->fail('Invalid signature', 401);
        }
        
        // Process payment notification
        if ($payload->type === 'payment.succeeded') {
            // Check if payment already processed
            $existingPayment = $this->paymentModel->where('invoice_id', $invoiceId)
                                                ->where('payment_method', 'ligo_qr')
                                                ->where('external_id', $payload->data->payment_id ?? '')
                                                ->first();
            
            if ($existingPayment) {
                log_message('info', 'Ligo webhook: Payment already processed for invoice: ' . $invoiceId);
                return $this->respond(['message' => 'Payment already processed']);
            }
            
            // Create payment record
            $paymentData = [
                'invoice_id' => $invoiceId,
                'amount' => $payload->data->amount,
                'payment_method' => 'ligo_qr',
                'reference_code' => $payload->data->payment_id ?? '',
                'external_id' => $payload->data->payment_id ?? '',
                'payment_date' => date('Y-m-d H:i:s'),
                'status' => 'completed',
                'notes' => 'Pago procesado por Ligo QR'
            ];
            
            $this->paymentModel->insert($paymentData);
            
            log_message('info', 'Ligo webhook: Payment processed for invoice: ' . $invoiceId);
            return $this->respond(['message' => 'Payment processed successfully']);
        }
        
        // Handle other event types if needed
        log_message('info', 'Ligo webhook: Unhandled event type: ' . $payload->type . ' for invoice: ' . $invoiceId);
        return $this->respond(['message' => 'Event received but not processed']);
    }
    
    /**
     * Verify webhook signature
     *
     * @param string $signature Signature from header
     * @param string $payload Request body
     * @param string $secret Webhook secret
     * @return bool
     */
    private function verifySignature($signature, $payload, $secret)
    {
        if (empty($signature) || empty($secret)) {
            return false;
        }
        
        $computedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($signature, $computedSignature);
    }
}

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
        if (!$payload || !isset($payload->type) || !isset($payload->data)) {
            log_message('error', 'Invalid Ligo webhook payload: ' . json_encode($payload));
            return $this->fail('Invalid webhook payload', 400);
        }
        
        // Get invoice from instructionId or order_id
        $invoiceId = $payload->data->instructionId ?? $payload->data->order_id;
        $invoice = $this->invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            log_message('error', 'Ligo webhook: Invoice not found for instructionId/order_id: ' . $invoiceId);
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
        
        // LOG detallado del payload recibido
        log_message('info', '[LigoWebhook] Payload recibido: ' . json_encode($payload));
        
        // Process payment notification
        if ($payload->type === 'payment.succeeded') {
            // Check if payment already processed
            $existingPayment = $this->paymentModel->where('invoice_id', $invoiceId)
                                                ->where('payment_method', 'ligo_qr')
                                                ->where('external_id', $payload->data->payment_id ?? '')
                                                ->first();
            if ($existingPayment) {
                log_message('info', '[LigoWebhook] Payment already processed for invoice: ' . $invoiceId . ' (payment_id: ' . ($payload->data->payment_id ?? 'N/A') . ')');
                return $this->respond(['message' => 'Payment already processed']);
            }
            // Crear registro de pago
            $paymentData = [
                'invoice_id' => $invoiceId,
                'amount' => $payload->data->transferDetails->amount ?? $payload->data->amount,
                'currency' => $payload->data->transferDetails->currency ?? $payload->data->currency,
                'payment_method' => 'ligo_qr',
                'reference_code' => $payload->data->instructionId ?? $payload->data->payment_id ?? '',
                'external_id' => $payload->data->payment_id ?? '',
                'payment_date' => date('Y-m-d H:i:s'),
                'status' => 'completed',
                'notes' => json_encode([
                    'payer_info' => $payload->data->originDetails ?? [],
                    'transfer_details' => $payload->data->transferDetails ?? [],
                    'destination_details' => $payload->data->destinationDetails ?? [],
                ])
            ];
            $this->paymentModel->insert($paymentData);
            log_message('info', '[LigoWebhook] Payment inserted for invoice: ' . $invoiceId . ' (payment_id: ' . ($payload->data->payment_id ?? 'N/A') . ')');
            // Actualizar estado de la factura si corresponde
            if (in_array($invoice['status'], ['pending', 'partially_paid'])) {
                $invoiceTotal = floatval($invoice['total_amount'] ?? $invoice['amount']);
                $paidSum = 0;
                $payments = $this->paymentModel->where('invoice_id', $invoiceId)->where('status', 'completed')->findAll();
                foreach ($payments as $p) {
                    $paidSum += floatval($p['amount']);
                }
                if ($paidSum >= $invoiceTotal) {
                    $this->invoiceModel->update($invoiceId, ['status' => 'paid']);
                    log_message('info', '[LigoWebhook] Invoice ' . $invoiceId . ' marked as PAID (paidSum: ' . $paidSum . ', total: ' . $invoiceTotal . ')');
                } else {
                    $this->invoiceModel->update($invoiceId, ['status' => 'partially_paid']);
                    log_message('info', '[LigoWebhook] Invoice ' . $invoiceId . ' marked as PARTIALLY PAID (paidSum: ' . $paidSum . ', total: ' . $invoiceTotal . ')');
                }
            }
            return $this->respond(['message' => 'Payment processed successfully', 'invoice_id' => $invoiceId, 'payment_id' => $payload->data->payment_id ?? null]);
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

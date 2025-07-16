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
    protected $webhookModel;
    protected $webhookLogModel;

    public function __construct()
    {
        $this->invoiceModel = new \App\Models\InvoiceModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        $this->paymentModel = new \App\Models\PaymentModel();
        $this->webhookModel = new \App\Models\WebhookModel();
        $this->webhookLogModel = new \App\Models\WebhookLogModel();
    }

    /**
     * Handle payment notification from Ligo
     *
     * @return mixed
     */
    public function handlePaymentNotification()
    {
        // ===== LOGS TEMPORALES PARA DEBUGGING =====
        log_message('info', '[LIGO_WEBHOOK_DEBUG] ===== WEBHOOK RECIBIDO =====');
        log_message('info', '[LIGO_WEBHOOK_DEBUG] Method: ' . $this->request->getMethod());
        log_message('info', '[LIGO_WEBHOOK_DEBUG] URI: ' . $this->request->getUri());
        log_message('info', '[LIGO_WEBHOOK_DEBUG] Headers: ' . json_encode($this->request->getHeaders()));
        log_message('info', '[LIGO_WEBHOOK_DEBUG] Raw Body: ' . $this->request->getBody());
        log_message('info', '[LIGO_WEBHOOK_DEBUG] Content-Type: ' . $this->request->getHeaderLine('Content-Type'));
        log_message('info', '[LIGO_WEBHOOK_DEBUG] User-Agent: ' . $this->request->getHeaderLine('User-Agent'));
        log_message('info', '[LIGO_WEBHOOK_DEBUG] Ligo-Signature: ' . $this->request->getHeaderLine('Ligo-Signature'));
        log_message('info', '[LIGO_WEBHOOK_DEBUG] ================================');
        
        // Get request payload
        $payload = $this->request->getJSON();
        $rawPayload = $this->request->getBody();
        
        // Log the incoming webhook (we'll get or create webhook record later)
        $webhookId = null;
        $success = false;
        $responseCode = 200;
        $responseMessage = 'OK';
        
        // Validate payload
        if (!$payload || !isset($payload->type) || !isset($payload->data)) {
            log_message('error', 'Invalid Ligo webhook payload: ' . json_encode($payload));
            $responseCode = 400;
            $responseMessage = 'Invalid webhook payload';
            $this->logWebhookAttempt($webhookId, $payload->type ?? 'unknown', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('Invalid webhook payload', 400);
        }
        
        // Get invoice from instructionId or order_id
        $invoiceId = $payload->data->instructionId ?? $payload->data->order_id;
        $invoice = $this->invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            log_message('error', 'Ligo webhook: Invoice not found for instructionId/order_id: ' . $invoiceId);
            $responseCode = 404;
            $responseMessage = 'Invoice not found';
            $this->logWebhookAttempt($webhookId, $payload->type, $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('Invoice not found', 404);
        }
        
        // Get organization
        $organization = $this->organizationModel->find($invoice['organization_id']);
        
        if (!$organization) {
            log_message('error', 'Ligo webhook: Organization not found for invoice: ' . $invoiceId);
            $responseCode = 404;
            $responseMessage = 'Organization not found';
            $this->logWebhookAttempt($webhookId, $payload->type, $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('Organization not found', 404);
        }
        
        // Get or create webhook record for this organization
        $webhookId = $this->getOrCreateLigoWebhook($organization['id']);
        
        // Validar IP de origen (Ligo usa whitelist de IPs, no firma)
        $clientIp = $this->request->getIPAddress();
        log_message('info', '[LIGO_WEBHOOK_DEBUG] IP de origen: ' . $clientIp);
        
        // Whitelist de IPs de Ligo (actualizar según proporcione Ligo)
        $allowedIPs = [
            // IPs de Ligo - actualizar cuando los proporcionen
            // '192.168.1.100',
            // '10.0.0.0/8',
        ];
        
        // Validar IP si hay whitelist configurada
        if (!empty($allowedIPs) && !$this->isIPAllowed($clientIp, $allowedIPs)) {
            log_message('error', '[LIGO_WEBHOOK] IP no autorizada: ' . $clientIp);
            $responseCode = 403;
            $responseMessage = 'IP not authorized';
            $this->logWebhookAttempt($webhookId, $payload->type ?? 'unknown', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('IP not authorized', 403);
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
                'currency' => $payload->data->transferDetails->currency ?? $payload->data->currency ?? 'PEN',
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
            
            // Log successful webhook processing
            $success = true;
            $responseMessage = 'Payment processed successfully';
            $this->logWebhookAttempt($webhookId, $payload->type, $rawPayload, $responseCode, $responseMessage, $success);
            
            return $this->respond(['message' => 'Payment processed successfully', 'invoice_id' => $invoiceId, 'payment_id' => $payload->data->payment_id ?? null]);
        }
        
        // Handle other event types if needed
        log_message('info', 'Ligo webhook: Unhandled event type: ' . $payload->type . ' for invoice: ' . $invoiceId);
        
        // Log unhandled event type
        $success = true; // Still successful response even if we don't process the event
        $responseMessage = 'Event received but not processed';
        $this->logWebhookAttempt($webhookId, $payload->type, $rawPayload, $responseCode, $responseMessage, $success);
        
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
    
    /**
     * Get or create webhook record for Ligo incoming webhooks
     *
     * @param int $organizationId
     * @return int|null
     */
    private function getOrCreateLigoWebhook($organizationId)
    {
        // Look for existing Ligo webhook record for this organization
        $webhook = $this->webhookModel->where('organization_id', $organizationId)
                                    ->where('name', 'Ligo Incoming Webhooks')
                                    ->first();
        
        if ($webhook) {
            return $webhook['id'];
        }
        
        // Create new webhook record for logging purposes
        $webhookData = [
            'organization_id' => $organizationId,
            'name' => 'Ligo Incoming Webhooks',
            'url' => 'https://api.ligo.com/webhooks (incoming)',
            'secret' => null, // No secret needed for incoming webhooks
            'events' => 'payment.succeeded,payment.failed,payment.cancelled',
            'is_active' => true,
        ];
        
        $webhookId = $this->webhookModel->insert($webhookData);
        return $webhookId;
    }
    
    /**
     * Log webhook attempt
     *
     * @param int|null $webhookId
     * @param string $event
     * @param string $payload
     * @param int $responseCode
     * @param string $responseMessage
     * @param bool $success
     */
    private function logWebhookAttempt($webhookId, $event, $payload, $responseCode, $responseMessage, $success)
    {
        // If we don't have a webhook ID, we can't log (this shouldn't happen in normal flow)
        if (!$webhookId) {
            return;
        }
        
        $logData = [
            'webhook_id' => $webhookId,
            'event' => $event,
            'payload' => $payload,
            'response_code' => $responseCode,
            'response_body' => $responseMessage,
            'attempts' => 1,
            'success' => $success,
        ];
        
        $this->webhookLogModel->insert($logData);
    }
    
    /**
     * Verificar si la IP está en la whitelist
     *
     * @param string $ip
     * @param array $allowedIPs
     * @return bool
     */
    private function isIPAllowed($ip, $allowedIPs)
    {
        foreach ($allowedIPs as $allowedIP) {
            // Verificar IP exacta
            if ($ip === $allowedIP) {
                return true;
            }
            
            // Verificar rango CIDR (ej: 192.168.1.0/24)
            if (strpos($allowedIP, '/') !== false) {
                if ($this->ipInRange($ip, $allowedIP)) {
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * Verificar si IP está en rango CIDR
     *
     * @param string $ip
     * @param string $cidr
     * @return bool
     */
    private function ipInRange($ip, $cidr)
    {
        list($range, $netmask) = explode('/', $cidr, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        
        return (($ip_decimal & $netmask_decimal) == ($range_decimal & $netmask_decimal));
    }
}

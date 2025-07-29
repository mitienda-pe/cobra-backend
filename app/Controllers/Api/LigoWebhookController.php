<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;

class LigoWebhookController extends ResourceController
{
    use ResponseTrait;
    
    protected $invoiceModel;
    protected $instalmentModel;
    protected $organizationModel;
    protected $paymentModel;
    protected $webhookModel;
    protected $webhookLogModel;

    public function __construct()
    {
        $this->invoiceModel = new \App\Models\InvoiceModel();
        $this->instalmentModel = new \App\Models\InstalmentModel();
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
        
        // Validate payload - Ligo sends direct payload structure (no type/data wrapper)
        if (!$payload || !isset($payload->unstructuredInformation)) {
            log_message('error', 'Invalid Ligo webhook payload: ' . json_encode($payload));
            $responseCode = 400;
            $responseMessage = 'Invalid webhook payload - missing unstructuredInformation';
            $this->logWebhookAttempt($webhookId, 'unknown', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('Invalid webhook payload', 400);
        }
        
        // Get invoice from unstructuredInformation (idQr) using ligo_qr_hashes mapping table
        $idQr = $payload->unstructuredInformation ?? null;
        $instructionId = $payload->instructionId ?? null; // Keep for logging
        $ligoQRHashModel = new \App\Models\LigoQRHashModel();
        $qrHash = $ligoQRHashModel->where('id_qr', $idQr)->first();
        
        if (!$qrHash) {
            log_message('error', 'Ligo webhook: QR Hash not found for idQr: ' . $idQr . ' (instructionId: ' . $instructionId . ')');
            $responseCode = 404;
            $responseMessage = 'QR Hash not found';
            $this->logWebhookAttempt($webhookId, 'payment.notification', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('QR Hash not found', 404);
        }
        
        $invoiceId = $qrHash['invoice_id'];
        $instalmentId = $qrHash['instalment_id'] ?? null;
        
        // Get instalment if this is an instalment payment
        $instalment = null;
        if ($instalmentId) {
            $instalment = $this->instalmentModel->find($instalmentId);
            if (!$instalment) {
                log_message('error', 'Ligo webhook: Instalment not found for instalment_id: ' . $instalmentId . ' (idQr: ' . $idQr . ', instructionId: ' . $instructionId . ')');
                $responseCode = 404;
                $responseMessage = 'Instalment not found';
                $this->logWebhookAttempt($webhookId, 'payment.notification', $rawPayload, $responseCode, $responseMessage, $success);
                return $this->fail('Instalment not found', 404);
            }
        }
        
        // Get invoice
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            log_message('error', 'Ligo webhook: Invoice not found for invoice_id: ' . $invoiceId . ' (idQr: ' . $idQr . ', instructionId: ' . $instructionId . ')');
            $responseCode = 404;
            $responseMessage = 'Invoice not found';
            return $this->fail('Invoice not found', 404);
        }
        
        // Get organization
        $organization = $this->organizationModel->find($invoice['organization_id'] ?? null);
        
        if (!$organization) {
            log_message('error', 'Ligo webhook: Organization not found for invoice: ' . $invoiceId);
            $responseCode = 404;
            $responseMessage = 'Organization not found';
            $this->logWebhookAttempt($webhookId, 'payment.notification', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('Organization not found', 404);
        }
        
        // Get or create webhook record for this organization
        $webhookId = $this->getOrCreateLigoWebhook($organization['id'] ?? null);
        
        // Validar IP de origen (Ligo usa whitelist de IPs, no firma)
        $clientIp = $this->request->getIPAddress();
        log_message('info', '[LIGO_WEBHOOK_DEBUG] IP de origen: ' . $clientIp);
        
        // Whitelist de IPs de Ligo
        $allowedIPs = [
            '35.221.25.179',   // Ligo Producción
            '34.150.173.107',  // Ligo Desarrollo
        ];
        
        // Validar IP si hay whitelist configurada
        if (!empty($allowedIPs) && !$this->isIPAllowed($clientIp, $allowedIPs)) {
            log_message('error', '[LIGO_WEBHOOK] IP no autorizada: ' . $clientIp);
            $responseCode = 403;
            $responseMessage = 'IP not authorized';
            $this->logWebhookAttempt($webhookId, 'payment.notification', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('IP not authorized', 403);
        }
        
        // LOG detallado del payload recibido
        log_message('info', '[LigoWebhook] Payload recibido: ' . json_encode($payload));
        
        // Process payment notification - Ligo webhooks are always payment confirmations
        // Check if payment already processed
        $query = $this->paymentModel->where('invoice_id', $invoiceId)
                                   ->where('payment_method', 'ligo_qr')
                                   ->where('reference_code', $instructionId);
        
        if ($instalmentId) {
            $query->where('instalment_id', $instalmentId);
        }
        
        $existingPayment = $query->first();
        if ($existingPayment) {
            $logMsg = $instalmentId ? 
                '[LigoWebhook] Payment already processed for instalment: ' . $instalmentId . ' (idQr: ' . $idQr . ', instructionId: ' . $instructionId . ')' :
                '[LigoWebhook] Payment already processed for invoice: ' . $invoiceId . ' (idQr: ' . $idQr . ', instructionId: ' . $instructionId . ')';
            log_message('info', $logMsg);
            $success = true;
            $responseMessage = 'Payment already processed';
            $this->logWebhookAttempt($webhookId, 'payment.succeeded', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->respond(['message' => 'Payment already processed']);
        }
        
        // Crear registro de pago
        $paymentData = [
            'invoice_id' => $invoiceId,
            'instalment_id' => $instalmentId, // Include instalment_id if it exists
            'amount' => $payload->transferDetails->amount ?? 0,
            'currency' => $payload->transferDetails->currency ?? 'PEN',
            'payment_method' => 'ligo_qr',
            'reference_code' => $instructionId,
            'external_id' => $instructionId,
            'payment_date' => $payload->transferDetails->transferDate ?? date('Y-m-d H:i:s'),
            'status' => 'completed',
            'notes' => json_encode([
                'instruction_id' => $instructionId,
                'id_qr' => $idQr,
                'unstructured_information' => $payload->unstructuredInformation ?? '',
                'payer_info' => $payload->originDetails ?? [],
                'transfer_details' => $payload->transferDetails ?? [],
                'destination_details' => $payload->destinationDetails ?? [],
                'channel' => $payload->channel ?? '',
                'recharge_date' => $payload->rechargeDate ?? '',
                'recharge_time' => $payload->rechargeTime ?? '',
            ])
        ];
        $this->paymentModel->insert($paymentData);
        
        $logMsg = $instalmentId ? 
            '[LigoWebhook] Payment inserted for instalment: ' . $instalmentId . ' (idQr: ' . $idQr . ', instructionId: ' . $instructionId . ')' :
            '[LigoWebhook] Payment inserted for invoice: ' . $invoiceId . ' (idQr: ' . $idQr . ', instructionId: ' . $instructionId . ')';
        log_message('info', $logMsg);
        
        // Update instalment status if this is an instalment payment
        if ($instalment && $instalment['status'] === 'pending') {
            $this->instalmentModel->update($instalmentId, ['status' => 'paid']);
            log_message('info', '[LigoWebhook] Instalment ' . $instalmentId . ' marked as PAID');
        }
        
        // Update invoice status based on all instalments
        if (in_array($invoice['status'], ['pending', 'partially_paid'])) {
            // Check if all instalments of this invoice are paid
            $allInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->findAll();
            $paidInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->where('status', 'paid')->findAll();
            
            if (count($allInstalments) === count($paidInstalments)) {
                // All instalments are paid
                $this->invoiceModel->update($invoiceId, ['status' => 'paid']);
                log_message('info', '[LigoWebhook] Invoice ' . $invoiceId . ' marked as PAID (all instalments paid)');
            } else {
                // Some instalments are paid
                $this->invoiceModel->update($invoiceId, ['status' => 'partially_paid']);
                log_message('info', '[LigoWebhook] Invoice ' . $invoiceId . ' marked as PARTIALLY PAID (' . count($paidInstalments) . '/' . count($allInstalments) . ' instalments paid)');
            }
        }
        
        // Log successful webhook processing
        $success = true;
        $responseMessage = 'Payment processed successfully';
        $this->logWebhookAttempt($webhookId, 'payment.succeeded', $rawPayload, $responseCode, $responseMessage, $success);
        
        return $this->respond([
            'message' => 'Payment processed successfully', 
            'invoice_id' => $invoiceId, 
            'instalment_id' => $instalmentId,
            'instruction_id' => $instructionId,
            'id_qr' => $idQr
        ]);
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

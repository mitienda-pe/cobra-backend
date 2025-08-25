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
     * @param string $environment Environment (dev/prod) from URL
     * @return mixed
     */
    public function handlePaymentNotification($environment = 'dev')
    {
        // Validar entorno solicitado
        if (!in_array($environment, ['dev', 'prod'])) {
            $environment = 'dev'; // default fallback
        }
        
        // ===== LOGS TEMPORALES PARA DEBUGGING =====
        log_message('info', '[LIGO_WEBHOOK_DEBUG] ===== WEBHOOK RECIBIDO =====');
        log_message('info', '[LIGO_WEBHOOK_DEBUG] Environment: ' . $environment);
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
        
        // Webhook Logging  
        log_message('info', '🔔 [WEBHOOK] Ligo webhook recibido - Environment: ' . $environment);
        $idQr = $payload->unstructuredInformation ?? null;
        $instructionId = $payload->instructionId ?? null;
        $amount = $payload->transferDetails->amount ?? 0;
        
        log_message('info', '📱 [WEBHOOK] instructionId: ' . $instructionId . ', idQr: ' . json_encode($idQr) . ', amount: ' . $amount);

        // Validate payload - Ligo sends direct payload structure (no type/data wrapper)
        if (!$payload || !isset($payload->unstructuredInformation)) {
            log_message('error', '❌ [WEBHOOK] Invalid payload - missing unstructuredInformation: ' . json_encode($payload));
            $responseCode = 400;
            $responseMessage = 'Invalid webhook payload - missing unstructuredInformation';
            $this->logWebhookAttempt($webhookId, 'unknown', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('Invalid webhook payload', 400);
        }
        
        $ligoQRHashModel = new \App\Models\LigoQRHashModel();
        $qrHash = $ligoQRHashModel->where('id_qr', $idQr)->first();
        
        if (!$qrHash) {
            log_message('error', '❌ [WEBHOOK] QR Hash NOT FOUND para idQr: ' . $idQr);
            
            // Buscar por instructionId como fallback
            $fallbackHash = $ligoQRHashModel->like('hash', $instructionId)->orLike('real_hash', $instructionId)->first();
            if ($fallbackHash) {
                log_message('info', '⚠️ [WEBHOOK] FALLBACK: Found hash by instructionId');
                $qrHash = $fallbackHash;
            } else {
                $responseCode = 404;
                $responseMessage = 'QR Hash not found';
                $this->logWebhookAttempt($webhookId, 'payment.notification', $rawPayload, $responseCode, $responseMessage, $success);
                return $this->fail('QR Hash not found', 404);
            }
        }
        
        log_message('info', '✅ [WEBHOOK] QR Hash encontrado - Instalment: ' . $qrHash['instalment_id']);
        
        $invoiceId = $qrHash['invoice_id'];
        $instalmentId = $qrHash['instalment_id'] ?? null;
        
        log_message('info', '[LigoWebhook] SUCCESS: QR Hash found - Invoice ID: ' . $invoiceId . ', Instalment ID: ' . ($instalmentId ?? 'NULL'));
        log_message('info', '[LigoWebhook] QR Hash record: id=' . $qrHash['id'] . ', hash=' . substr($qrHash['hash'], 0, 20) . '..., amount=' . ($qrHash['amount'] ?? 'NULL'));
        
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
        $webhookId = $this->getOrCreateLigoWebhook($organization['id'] ?? null, $environment);
        
        // Validar IP de origen (Ligo usa whitelist de IPs, no firma)
        $clientIp = $this->request->getIPAddress();
        log_message('info', '[LIGO_WEBHOOK_DEBUG] IP de origen: ' . $clientIp);
        
        // Whitelist de IPs de Ligo específicas por entorno
        $allowedIPs = [];
        if ($environment === 'prod') {
            $allowedIPs = [
                '35.221.25.179',   // Ligo Producción
            ];
        } else { // dev
            $allowedIPs = [
                '34.150.173.107',  // Ligo Desarrollo
                '190.237.15.74',   // TEMPORAL: IP para testing - REMOVER DESPUÉS
            ];
        }
        
        // Validar IP si hay whitelist configurada
        if (!empty($allowedIPs) && !$this->isIPAllowed($clientIp, $allowedIPs)) {
            log_message('error', '[LIGO_WEBHOOK] IP no autorizada: ' . $clientIp);
            $responseCode = 403;
            $responseMessage = 'IP not authorized';
            $this->logWebhookAttempt($webhookId, 'payment.notification', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->fail('IP not authorized', 403);
        }
        
        // LOG detallado del payload recibido
        log_message('info', '[LigoWebhook] ===== WEBHOOK PROCESSING START =====');
        log_message('info', '[LigoWebhook] Payload recibido: ' . json_encode($payload));
        log_message('info', '[LigoWebhook] idQr extraído: ' . $idQr);
        log_message('info', '[LigoWebhook] instructionId: ' . $instructionId);
        
        // Process payment notification - Ligo webhooks are always payment confirmations
        // Check if payment already processed
        $query = $this->paymentModel->where('invoice_id', $invoiceId)
                                   ->where('payment_method', 'qr')
                                   ->where('reference_code', $instructionId);
        
        if ($instalmentId) {
            $query->where('instalment_id', $instalmentId);
        }
        
        $existingPayment = $query->first();
        if ($existingPayment) {
            $logMsg = $instalmentId ? 
                '[LigoWebhook] DUPLICATE: Payment already processed for instalment: ' . $instalmentId . ' (cuota #' . ($instalment['number'] ?? '?') . ') - idQr: ' . $idQr . ', instructionId: ' . $instructionId :
                '[LigoWebhook] DUPLICATE: Payment already processed for invoice: ' . $invoiceId . ' - idQr: ' . $idQr . ', instructionId: ' . $instructionId;
            log_message('info', $logMsg);
            log_message('info', '[LigoWebhook] Existing payment ID: ' . $existingPayment['id'] . ', amount: ' . $existingPayment['amount'] . ', date: ' . $existingPayment['payment_date']);
            $success = true;
            $responseMessage = 'Payment already processed';
            $this->logWebhookAttempt($webhookId, 'payment.succeeded', $rawPayload, $responseCode, $responseMessage, $success);
            return $this->respond(['message' => 'Payment already processed']);
        }
        
        // Crear registro de pago
        $paymentAmount = $payload->transferDetails->amount ?? 0;
        // Convertir centavos a soles si el monto es >= 100 centavos
        if ($paymentAmount >= 100) {
            $paymentAmount = $paymentAmount / 100;
        }
        
        $paymentData = [
            'invoice_id' => $invoiceId,
            'instalment_id' => $instalmentId, // Include instalment_id if it exists
            'amount' => $paymentAmount,
            'currency' => $payload->transferDetails->currency ?? 'PEN',
            'payment_method' => 'qr',
            'reference_code' => $instructionId,
            'external_id' => $instructionId,
            'payment_date' => $payload->transferDetails->transferDate ?? date('Y-m-d H:i:s'),
            'status' => 'completed',
            'ligo_environment' => $environment, // Save which Ligo credentials environment was used
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
        
        $paymentId = $this->paymentModel->insert($paymentData);
        
        $logMsg = $instalmentId ? 
            '[LigoWebhook] ✅ PAYMENT CREATED: Payment ID ' . $paymentId . ' for instalment ' . $instalmentId . ' (cuota #' . ($instalment['number'] ?? '?') . ') - Amount: ' . $paymentData['amount'] . ' ' . $paymentData['currency'] :
            '[LigoWebhook] ✅ PAYMENT CREATED: Payment ID ' . $paymentId . ' for invoice ' . $invoiceId . ' - Amount: ' . $paymentData['amount'] . ' ' . $paymentData['currency'];
        log_message('info', $logMsg);
        log_message('info', '[LigoWebhook] Payment details - idQr: ' . $idQr . ', instructionId: ' . $instructionId . ', reference: ' . $paymentData['reference_code']);
        
        // Update instalment status if this is an instalment payment
        if ($instalment && $instalment['status'] === 'pending') {
            $this->instalmentModel->update($instalmentId, [
                'status' => 'paid'
            ]);
            log_message('info', '[LigoWebhook] ✅ INSTALMENT UPDATED: Instalment ' . $instalmentId . ' (cuota #' . $instalment['number'] . ') status changed from "' . $instalment['status'] . '" to "paid"');
        } else {
            if ($instalment) {
                log_message('info', '[LigoWebhook] ℹ️ INSTALMENT ALREADY PAID: Instalment ' . $instalmentId . ' (cuota #' . $instalment['number'] . ') was already marked as "' . $instalment['status'] . '"');
            }
        }
        
        // Update invoice status based on all instalments
        if (in_array($invoice['status'], ['pending', 'partially_paid'])) {
            // Check if all instalments of this invoice are paid
            $allInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->findAll();
            $paidInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->where('status', 'paid')->findAll();
            
            if (count($allInstalments) === count($paidInstalments)) {
                // All instalments are paid
                $this->invoiceModel->update($invoiceId, ['status' => 'paid']);
                log_message('info', '[LigoWebhook] ✅ INVOICE COMPLETED: Invoice ' . $invoiceId . ' (' . $invoice['invoice_number'] . ') marked as PAID (all ' . count($allInstalments) . ' instalments paid)');
            } else {
                // Some instalments are paid
                $this->invoiceModel->update($invoiceId, ['status' => 'partially_paid']);
                log_message('info', '[LigoWebhook] ⏳ INVOICE PARTIAL: Invoice ' . $invoiceId . ' (' . $invoice['invoice_number'] . ') marked as PARTIALLY PAID (' . count($paidInstalments) . '/' . count($allInstalments) . ' instalments paid)');
            }
        } else {
            log_message('info', '[LigoWebhook] ℹ️ INVOICE STATUS: Invoice ' . $invoiceId . ' (' . $invoice['invoice_number'] . ') status remains "' . $invoice['status'] . '"');
        }
        
        // Store payment event in cache for SSE notification
        $cache = \Config\Services::cache();
        $paymentEvent = [
            'qr_id' => $idQr,
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'instalment_id' => $instalmentId,
            'instalment_number' => $instalment['number'] ?? null,
            'status' => 'completed',
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency'],
            'payment_date' => $paymentData['payment_date'],
            'instruction_id' => $instructionId,
            'message' => 'Payment completed successfully!'
        ];
        
        // Cache event for 5 minutes for SSE to detect
        $cache->save("payment_event_$idQr", $paymentEvent, 300);
        log_message('info', '📡 [SSE] Payment event stored in cache for QR: ' . $idQr);
        
        // Log successful webhook processing
        $success = true;
        $responseMessage = 'Payment processed successfully';
        $this->logWebhookAttempt($webhookId, 'payment.succeeded', $rawPayload, $responseCode, $responseMessage, $success);
        
        log_message('info', '[LigoWebhook] ===== WEBHOOK PROCESSING COMPLETED SUCCESSFULLY =====');
        log_message('info', '[LigoWebhook] 🎉 SUMMARY: Payment ID ' . $paymentId . ' created for ' . ($instalmentId ? 'instalment ' . $instalmentId . ' (cuota #' . ($instalment['number'] ?? '?') . ')' : 'invoice ' . $invoiceId));
        
        return $this->respond([
            'message' => 'Payment processed successfully', 
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId, 
            'instalment_id' => $instalmentId,
            'instalment_number' => $instalment['number'] ?? null,
            'instruction_id' => $instructionId,
            'id_qr' => $idQr,
            'amount' => $paymentData['amount'],
            'currency' => $paymentData['currency']
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
     * @param string $environment
     * @return int|null
     */
    private function getOrCreateLigoWebhook($organizationId, $environment = 'dev')
    {
        // Look for existing Ligo webhook record for this organization and environment
        $webhookName = 'Ligo Incoming Webhooks (' . strtoupper($environment) . ')';
        $webhook = $this->webhookModel->where('organization_id', $organizationId)
                                    ->where('name', $webhookName)
                                    ->first();
        
        if ($webhook) {
            return $webhook['id'];
        }
        
        // Create new webhook record for logging purposes
        $webhookUrl = $environment === 'prod' 
            ? 'https://tu-dominio.com/webhooks/ligo/prod (incoming)' 
            : 'https://tu-dominio.com/webhooks/ligo/dev (incoming)';
            
        $webhookData = [
            'organization_id' => $organizationId,
            'name' => $webhookName,
            'url' => $webhookUrl,
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

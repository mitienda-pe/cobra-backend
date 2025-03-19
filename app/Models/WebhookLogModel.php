<?php

namespace App\Models;

use CodeIgniter\Model;

class WebhookLogModel extends Model
{
    protected $table            = 'webhook_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'webhook_id', 'event', 'payload', 'response_code', 
        'response_body', 'attempts', 'success'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [
        'webhook_id' => 'required|is_natural_no_zero',
        'event'      => 'required|max_length[50]',
        'payload'    => 'required',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    
    /**
     * Get logs by webhook
     */
    public function getByWebhook($webhookId, $limit = 50, $offset = 0)
    {
        return $this->where('webhook_id', $webhookId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll($limit, $offset);
    }
    
    /**
     * Get logs by event
     */
    public function getByEvent($event, $limit = 50, $offset = 0)
    {
        return $this->where('event', $event)
                    ->orderBy('created_at', 'DESC')
                    ->findAll($limit, $offset);
    }
    
    /**
     * Get failed logs for retry
     */
    public function getFailedForRetry($maxAttempts = 3, $limit = 50)
    {
        return $this->where('success', false)
                    ->where('attempts <', $maxAttempts)
                    ->orderBy('created_at', 'ASC')
                    ->findAll($limit);
    }
    
    /**
     * Retry failed webhook
     */
    public function retry($logId)
    {
        $log = $this->find($logId);
        
        if (!$log) {
            return false;
        }
        
        $webhookModel = new WebhookModel();
        $webhook = $webhookModel->find($log['webhook_id']);
        
        if (!$webhook || !$webhook['is_active']) {
            return false;
        }
        
        $payload = json_decode($log['payload'], true);
        
        // Use curl to send the notification
        $ch = curl_init($webhook['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $log['payload']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . hash_hmac('sha256', $log['payload'], $webhook['secret'] ?? '')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $success = ($statusCode >= 200 && $statusCode < 300);
        
        // Update log
        $this->update($logId, [
            'response_code' => $statusCode,
            'response_body' => $response ?: $error,
            'success'       => $success,
            'attempts'      => $log['attempts'] + 1
        ]);
        
        // If payment notification was successful, update the payment
        if ($success && $log['event'] === 'payment.created') {
            $payload = json_decode($log['payload'], true);
            if (isset($payload['payment']['id'])) {
                $paymentModel = new PaymentModel();
                $paymentModel->update($payload['payment']['id'], ['is_notified' => true]);
            }
        }
        
        return $success;
    }
}
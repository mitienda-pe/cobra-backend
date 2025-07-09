<?php

namespace App\Models;

use CodeIgniter\Model;

class WebhookModel extends Model
{
    protected $table            = 'webhooks';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'organization_id', 'name', 'url', 'secret', 'events', 'is_active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'organization_id' => 'required|is_natural_no_zero',
        'name'            => 'required|min_length[3]|max_length[100]',
        'url'             => 'required|valid_url',
        'events'          => 'required',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    
    /**
     * Generate a new secret key
     */
    public function generateSecret()
    {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Get webhooks by organization
     */
    public function getByOrganization($organizationId)
    {
        return $this->where('organization_id', $organizationId)->findAll();
    }
    
    /**
     * Get webhooks by event
     */
    public function getByEvent($event, $organizationId = null)
    {
        $builder = $this->like('events', $event)->where('is_active', true);
        
        if ($organizationId) {
            $builder = $builder->where('organization_id', $organizationId);
        }
        
        return $builder->findAll();
    }
    
    /**
     * Test webhook by sending a ping event
     */
    public function testWebhook($webhookId, $webhookHash = null)
    {
        $webhookHash = $webhookHash ?: substr(md5(uniqid()), 0, 8);
        log_message('info', "[{$webhookHash}] === WEBHOOK MODEL TEST START ===");
        log_message('info', "[{$webhookHash}] Testing webhook ID: {$webhookId}");
        
        $webhook = $this->find($webhookId);
        
        if (!$webhook) {
            log_message('error', "[{$webhookHash}] Webhook not found with ID: {$webhookId}");
            return [
                'success' => false,
                'message' => 'Webhook not found'
            ];
        }
        
        log_message('info', "[{$webhookHash}] Webhook found: " . json_encode($webhook));
        
        $payload = [
            'event'     => 'ping',
            'timestamp' => time(),
            'data'      => [
                'message' => 'This is a test ping from the webhook system',
                'test_hash' => $webhookHash
            ]
        ];
        
        log_message('info', "[{$webhookHash}] Payload to send: " . json_encode($payload));
        
        // Use curl to send the notification
        $ch = curl_init($webhook['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Webhook-Signature: ' . hash_hmac('sha256', json_encode($payload), $webhook['secret'] ?? '')
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        log_message('info', "[{$webhookHash}] Sending POST request to: " . $webhook['url']);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        log_message('info', "[{$webhookHash}] Response code: {$statusCode}");
        log_message('info', "[{$webhookHash}] Response body: " . ($response ?: 'No response'));
        log_message('info', "[{$webhookHash}] Curl error: " . ($error ?: 'No error'));
        
        $success = ($statusCode >= 200 && $statusCode < 300);
        
        // Log test webhook
        $webhookLogModel = new WebhookLogModel();
        $logData = [
            'webhook_id'    => $webhook['id'],
            'event'         => 'ping',
            'payload'       => json_encode($payload),
            'response_code' => $statusCode,
            'response_body' => $response ?: $error,
            'success'       => $success,
            'attempts'      => 1
        ];
        
        log_message('info', "[{$webhookHash}] Logging webhook test: " . json_encode($logData));
        $webhookLogModel->insert($logData);
        
        $result = [
            'success'      => $success,
            'status_code'  => $statusCode,
            'response'     => $response,
            'error'        => $error,
            'message'      => $success ? 'Webhook test successful' : 'Webhook test failed',
            'test_hash'    => $webhookHash
        ];
        
        log_message('info', "[{$webhookHash}] Webhook test result: " . json_encode($result));
        
        return $result;
    }
}
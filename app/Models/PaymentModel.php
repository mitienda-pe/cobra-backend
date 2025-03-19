<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table            = 'payments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'invoice_id', 'user_id', 'uuid', 'external_id', 'amount', 'payment_method', 
        'reference_code', 'payment_date', 'status', 'notes', 'latitude', 
        'longitude', 'is_notified'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'invoice_id'     => 'required|is_natural_no_zero',
        'amount'         => 'required|numeric',
        'payment_method' => 'required|max_length[50]',
        'payment_date'   => 'required',
        'status'         => 'required|in_list[completed,pending,rejected,cancelled]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    
    // Callbacks
    protected $beforeInsert = ['generateExternalId', 'generateUuid', 'updateInvoiceStatus'];
    protected $afterInsert  = ['notifyWebhook'];

    /**
     * Generate external UUID if not provided
     */
    protected function generateExternalId(array $data)
    {
        if (!isset($data['data']['external_id']) || empty($data['data']['external_id'])) {
            $data['data']['external_id'] = bin2hex(random_bytes(16));
        }
        
        return $data;
    }
    
    /**
     * Generate a UUID for new payments
     */
    protected function generateUuid(array $data)
    {
        if (! isset($data['data']['uuid']) || empty($data['data']['uuid'])) {
            $data['data']['uuid'] = generate_unique_uuid('payments', 'uuid');
        }
        
        return $data;
    }
    
    /**
     * Update invoice status when payment is recorded
     * For partial payments, checks total amount paid vs invoice amount
     */
    protected function updateInvoiceStatus(array $data)
    {
        if (isset($data['data']['invoice_id']) && isset($data['data']['status']) && $data['data']['status'] === 'completed') {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($data['data']['invoice_id']);
            
            if (!$invoice) {
                return $data;
            }
            
            // Calculate total paid amount including this new payment
            $totalPaid = $data['data']['amount'];
            
            // Get existing payments for this invoice
            $existingPayments = $this->where('invoice_id', $data['data']['invoice_id'])
                                    ->where('status', 'completed')
                                    ->where('deleted_at IS NULL')
                                    ->findAll();
                                    
            foreach ($existingPayments as $payment) {
                $totalPaid += $payment['amount'];
            }
            
            // If total paid is greater than or equal to invoice amount, mark as paid
            if ($totalPaid >= $invoice['amount']) {
                $invoiceModel->update($data['data']['invoice_id'], ['status' => 'paid']);
                
                // Log full payment
                log_message('info', 'Invoice #' . $invoice['invoice_number'] . ' (ID: ' . $invoice['id'] . ') marked as paid. ' .
                           'Total paid: ' . $totalPaid . ', Invoice amount: ' . $invoice['amount']);
            } else {
                // Keep invoice status as pending for partial payments
                log_message('info', 'Partial payment recorded for Invoice #' . $invoice['invoice_number'] . ' (ID: ' . $invoice['id'] . '). ' .
                          'Amount paid: ' . $data['data']['amount'] . ', Total paid so far: ' . $totalPaid . 
                          ', Invoice amount: ' . $invoice['amount'] . ', Remaining: ' . ($invoice['amount'] - $totalPaid));
            }
        }
        
        return $data;
    }
    
    /**
     * Send webhook notification after payment
     */
    protected function notifyWebhook(array $data)
    {
        if (!isset($data['id']) || !isset($data['data']['invoice_id'])) {
            return $data;
        }
        
        // Get invoice and organization details
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($data['data']['invoice_id']);
        
        if (!$invoice) {
            return $data;
        }
        
        // Get webhook configurations for this organization
        $webhookModel = new WebhookModel();
        $webhooks = $webhookModel->where('organization_id', $invoice['organization_id'])
                                ->where('is_active', true)
                                ->like('events', 'payment.created')
                                ->findAll();
        
        if (empty($webhooks)) {
            return $data;
        }
        
        // Get payment details
        $payment = $this->find($data['id']);
        
        if (!$payment) {
            return $data;
        }
        
        // Get client details
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Prepare webhook payload
        $payload = [
            'event'     => 'payment.created',
            'timestamp' => time(),
            'payment'   => $payment,
            'invoice'   => $invoice,
            'client'    => $client
        ];
        
        // Send webhook notifications
        foreach ($webhooks as $webhook) {
            $webhookLogModel = new WebhookLogModel();
            
            $logData = [
                'webhook_id'   => $webhook['id'],
                'event'        => 'payment.created',
                'payload'      => json_encode($payload),
                'attempts'     => 1,
                'success'      => false
            ];
            
            $logId = $webhookLogModel->insert($logData);
            
            // Use curl to send the notification
            $ch = curl_init($webhook['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'X-Webhook-Signature: ' . hash_hmac('sha256', json_encode($payload), $webhook['secret'] ?? '')
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            
            $response = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $success = ($statusCode >= 200 && $statusCode < 300);
            
            // Update webhook log
            $webhookLogModel->update($logId, [
                'response_code' => $statusCode,
                'response_body' => $response ?: $error,
                'success'       => $success
            ]);
            
            // Update payment notification status if at least one webhook was successful
            if ($success) {
                $this->update($data['id'], ['is_notified' => true]);
            }
        }
        
        return $data;
    }
    
    /**
     * Get payments by organization
     */
    public function getByOrganization($organizationId, $dateStart = null, $dateEnd = null)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('payments p');
        $builder->select('p.*, i.invoice_number, i.concept, c.business_name, c.document_number, u.name as collector_name');
        $builder->join('invoices i', 'p.invoice_id = i.id');
        $builder->join('clients c', 'i.client_id = c.id');
        $builder->join('users u', 'p.user_id = u.id', 'left');
        $builder->where('i.organization_id', $organizationId);
        $builder->where('p.deleted_at IS NULL');
        
        if ($dateStart) {
            $builder->where('p.payment_date >=', $dateStart);
        }
        
        if ($dateEnd) {
            $builder->where('p.payment_date <=', $dateEnd);
        }
        
        return $builder->get()->getResultArray();
    }
    
    /**
     * Get payments by invoice
     */
    public function getByInvoice($invoiceId)
    {
        return $this->where('invoice_id', $invoiceId)->findAll();
    }
    
    /**
     * Get payments by user (collector)
     */
    public function getByUser($userId, $dateStart = null, $dateEnd = null)
    {
        $builder = $this->where('user_id', $userId);
        
        if ($dateStart) {
            $builder = $builder->where('payment_date >=', $dateStart);
        }
        
        if ($dateEnd) {
            $builder = $builder->where('payment_date <=', $dateEnd);
        }
        
        return $builder->findAll();
    }
    
    /**
     * Get payment by external ID
     */
    public function getByExternalId($externalId)
    {
        return $this->where('external_id', $externalId)->first();
    }
    
    /**
     * Get payments needing webhook retry
     */
    public function getUnnotified($limit = 50)
    {
        return $this->where('is_notified', false)
                   ->where('status', 'completed')
                   ->findAll($limit);
    }
}
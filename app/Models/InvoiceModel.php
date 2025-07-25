<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table            = 'invoices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'organization_id', 'client_id', 'client_uuid', 'uuid', 'external_id',
        'invoice_number', 'concept', 'total_amount', 'currency', 'due_date', 'issue_date',
        'status', 'notes', 'document_type', 'series', 'client_document_type',
        'client_document_number', 'client_name', 'client_address', 'paid_amount'
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
        'client_id'      => 'required|is_natural_no_zero',
        'invoice_number' => 'required|max_length[50]',
        'concept'        => 'required|max_length[255]',
        'total_amount'   => 'required|numeric',
        'issue_date'     => 'permit_empty|valid_date',
        'due_date'       => 'required|valid_date',
        'currency'       => 'required|in_list[PEN,USD]',
        'status'         => 'required|in_list[pending,paid,cancelled,rejected,expired]',
        'external_id'    => 'permit_empty|max_length[36]',
        'notes'          => 'permit_empty'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $beforeInsert   = ['generateExternalId', 'generateUuid'];
    protected $beforeUpdate   = [];
    protected $afterFind      = ['checkExpiredStatus'];

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
     * Generate a UUID for new invoices
     */
    protected function generateUuid(array $data)
    {
        if (! isset($data['data']['uuid']) || empty($data['data']['uuid'])) {
            $data['data']['uuid'] = generate_unique_uuid('invoices', 'uuid');
        }
        
        return $data;
    }
    
    /**
     * Check and update expired status for invoices
     */
    protected function checkExpiredStatus(array $data)
    {
        if (isset($data['data'])) {
            $isMultiple = true;
            $invoices = $data['data'];
        } else {
            $isMultiple = false;
            $invoices = [$data];
        }
        
        $today = date('Y-m-d');
        $updates = [];
        
        foreach ($invoices as $key => $invoice) {
            if (isset($invoice['status']) && $invoice['status'] === 'pending' && 
                isset($invoice['due_date']) && $invoice['due_date'] < $today) {
                
                if ($isMultiple) {
                    $data['data'][$key]['status'] = 'expired';
                } else {
                    $data['status'] = 'expired';
                }
                
                $updates[] = [
                    'id' => $invoice['id'],
                    'status' => 'expired'
                ];
            }
        }
        
        // Update expired invoices in the database
        if (!empty($updates)) {
            $this->updateBatch($updates, 'id');
        }
        
        return $data;
    }
    
    /**
     * Get invoices by organization
     */
    public function getByOrganization($organizationId, $status = null, $dueDateStart = null, $dueDateEnd = null)
    {
        $builder = $this->where('organization_id', $organizationId);
        
        if ($status) {
            $builder = $builder->where('status', $status);
        }
        
        if ($dueDateStart) {
            $builder = $builder->where('due_date >=', $dueDateStart);
        }
        
        if ($dueDateEnd) {
            $builder = $builder->where('due_date <=', $dueDateEnd);
        }
        
        return $builder->findAll();
    }
    
    /**
     * Get invoices by client
     */
    public function getByClient($clientId, $status = null)
    {
        $builder = $this->where('client_id', $clientId);
        
        if ($status) {
            $builder = $builder->where('status', $status);
        }
        
        return $builder->findAll();
    }
    
    /**
     * Get invoices by portfolio
     *
     * @param int $portfolioId Portfolio ID to filter invoices
     * @param string|null $status Filter by invoice status (optional)
     * @param int|null $clientId Filter by specific client ID (optional)
     * @return array
     */
    public function getByPortfolio($portfolioId, $status = null, $clientId = null)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('invoices i');
        $builder->select('i.*');
        $builder->join('clients c', 'i.client_id = c.id');
        $builder->join('client_portfolio cp', 'c.id = cp.client_id');
        $builder->where('cp.portfolio_id', $portfolioId);
        $builder->where('i.deleted_at IS NULL');
        
        // Add specific client filter if provided
        if ($clientId) {
            $builder->where('i.client_id', $clientId);
        }
        
        // Add status filter if provided
        if ($status) {
            $builder->where('i.status', $status);
        }
        
        // Add order by to get most recent invoices first
        $builder->orderBy('i.due_date', 'DESC');
        
        return $builder->get()->getResultArray();
    }
    
    /**
     * Get invoices for a user (based on assigned portfolios)
     */
    public function getByUser($userId, $status = null)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('invoices i');
        $builder->select('i.*, c.business_name, c.legal_name, c.document_number');
        $builder->join('clients c', 'i.client_id = c.id');
        $builder->join('client_portfolio cp', 'c.id = cp.client_id');
        $builder->join('portfolio_user pu', 'cp.portfolio_id = pu.portfolio_id');
        $builder->where('pu.user_id', $userId);
        $builder->where('i.deleted_at IS NULL');
        
        if ($status) {
            $builder->where('i.status', $status);
        }
        
        // Join client information
        $builder->groupBy('i.id');
        
        return $builder->get()->getResultArray();
    }
    
    /**
     * Get invoice by external ID
     */
    public function getByExternalId($externalId, $organizationId = null)
    {
        $query = $this->where('external_id', $externalId);
        
        if ($organizationId) {
            $query = $query->where('organization_id', $organizationId);
        }
        
        return $query->first();
    }
    
    /**
     * Check if invoice number already exists in the organization
     */
    public function isInvoiceNumberDuplicate($invoiceNumber, $organizationId, $excludeId = null)
    {
        $builder = $this->where('invoice_number', $invoiceNumber)
                       ->where('organization_id', $organizationId);
        
        // Exclude current invoice when editing
        if ($excludeId) {
            $builder->where('id !=', $excludeId);
        }
        
        // Count rather than get the full records
        $count = $builder->countAllResults();
        return $count > 0;
    }
    
    /**
     * Mark invoice as paid
     */
    public function markAsPaid($invoiceId)
    {
        return $this->update($invoiceId, ['status' => 'paid']);
    }
    
    /**
     * Calculate the remaining amount for an invoice
     */
    public function calculateRemainingAmount($invoiceId)
    {
        $invoice = $this->find($invoiceId);
        if (!$invoice) {
            return null;
        }
        
        // Get total paid for this invoice
        $paymentModel = new \App\Models\PaymentModel();
        $payments = $paymentModel->where('invoice_id', $invoiceId)
                                ->where('status', 'completed')
                                ->where('deleted_at IS NULL')
                                ->findAll();
        
        $totalPaid = 0;
        foreach ($payments as $payment) {
            $totalPaid += $payment['amount'];
        }
        
        return [
            'invoice_amount' => $invoice['total_amount'] ?? $invoice['amount'] ?? 0,
            'total_paid' => $totalPaid,
            'remaining' => max(0, ($invoice['total_amount'] ?? $invoice['amount'] ?? 0) - $totalPaid),
            'is_fully_paid' => $totalPaid >= ($invoice['total_amount'] ?? $invoice['amount'] ?? 0),
            'payments' => $payments
        ];
    }
    
    /**
     * Import invoices from CSV
     */
    public function importFromCsv($filePath, $organizationId)
    {
        $file = fopen($filePath, 'r');
        
        // Skip header row
        $header = fgetcsv($file);
        
        $clientModel = new ClientModel();
        $success = 0;
        $errors = [];
        $row = 1;
        
        $db = \Config\Database::connect();
        $db->transStart();
        
        while (($data = fgetcsv($file)) !== FALSE) {
            $row++;
            
            if (count($data) < 7) {
                $errors[] = "Row {$row}: Not enough columns";
                continue;
            }
            
            // Map CSV columns to fields
            // Format: [business_name, document_number, invoice_number, concept, amount, due_date, external_id]
            $businessName = $data[0];
            $documentNumber = $data[1];
            $invoiceNumber = $data[2];
            $concept = $data[3];
            $amount = $data[4];
            $dueDate = $data[5];
            $externalId = isset($data[6]) && !empty($data[6]) ? $data[6] : null;
            
            // Find or create client
            $client = $clientModel->where('document_number', $documentNumber)
                                 ->where('organization_id', $organizationId)
                                 ->first();
            
            if (!$client) {
                $clientData = [
                    'organization_id' => $organizationId,
                    'business_name'   => $businessName,
                    'legal_name'      => $businessName, // Assuming same as business name
                    'document_number' => $documentNumber,
                    'status'          => 'active'
                ];
                
                $clientId = $clientModel->insert($clientData);
                
                if (!$clientId) {
                    $errors[] = "Row {$row}: Failed to create client - " . implode(', ', $clientModel->errors());
                    continue;
                }
            } else {
                $clientId = $client['id'];
            }
            
            // Check if invoice number is duplicate
            if ($this->isInvoiceNumberDuplicate($invoiceNumber, $organizationId)) {
                $errors[] = "Row {$row}: Duplicate invoice number '{$invoiceNumber}' in organization";
                continue;
            }
            
            // Create invoice
            $invoiceData = [
                'organization_id' => $organizationId,
                'client_id'       => $clientId,
                'external_id'     => $externalId,
                'invoice_number'  => $invoiceNumber,
                'concept'         => $concept,
                'total_amount'    => $amount,
                'due_date'        => $dueDate,
                'status'          => 'pending'
            ];
            
            $invoiceId = $this->insert($invoiceData);
            
            if (!$invoiceId) {
                $errors[] = "Row {$row}: Failed to create invoice - " . implode(', ', $this->errors());
            } else {
                $success++;
            }
        }
        
        fclose($file);
        
        $db->transComplete();
        
        return [
            'success' => $success,
            'errors'  => $errors
        ];
    }
}
<?php

namespace App\Models;

use CodeIgniter\Model;

class InstalmentModel extends Model
{
    protected $table            = 'instalments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'uuid', 'invoice_id', 'number', 'amount', 'due_date', 'status', 'notes'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'invoice_id' => 'required|is_natural_no_zero',
        'number'     => 'required|is_natural_no_zero',
        'amount'     => 'required|numeric',
        'due_date'   => 'required|valid_date',
        'status'     => 'required|in_list[pending,paid,cancelled]',
    ];
    
    // Callbacks
    protected $beforeInsert = ['generateUuid'];
    
    /**
     * Generate UUID before insert
     */
    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            helper('uuid');
            $data['data']['uuid'] = generate_unique_uuid('instalments', 'uuid');
        }
        return $data;
    }
    
    /**
     * Get instalments by invoice
     */
    public function getByInvoice($invoiceId)
    {
        return $this->where('invoice_id', $invoiceId)
                    ->orderBy('number', 'ASC')
                    ->findAll();
    }
    
    /**
     * Update instalment status based on payments
     */
    public function updateStatus($instalmentId)
    {
        $db = \Config\Database::connect();
        
        // Get instalment
        $instalment = $this->find($instalmentId);
        if (!$instalment) {
            return false;
        }
        
        // Calculate total paid for this instalment
        $totalPaid = $db->table('payments')
                        ->selectSum('amount')
                        ->where('instalment_id', $instalmentId)
                        ->where('status', 'completed')
                        ->get()
                        ->getRow()
                        ->amount ?? 0;
        
        // Update status based on total paid
        if ($totalPaid >= $instalment['amount']) {
            return $this->update($instalmentId, ['status' => 'paid']);
        } else if ($totalPaid > 0) {
            return $this->update($instalmentId, ['status' => 'pending']);
        }
        
        return true;
    }
    
    /**
     * Check if all instalments for an invoice are paid
     */
    public function areAllPaid($invoiceId)
    {
        $pendingCount = $this->where('invoice_id', $invoiceId)
                             ->whereIn('status', ['pending'])
                             ->countAllResults();
        
        return $pendingCount === 0;
    }
}

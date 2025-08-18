<?php

namespace App\Models;

use CodeIgniter\Model;

class OrganizationBalanceModel extends Model
{
    protected $table            = 'organization_balances';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'organization_id', 'total_collected', 'total_ligo_payments', 'total_cash_payments', 
        'total_other_payments', 'total_pending', 'currency', 'last_payment_date', 
        'last_calculated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'organization_id' => 'required|is_natural_no_zero',
        'currency' => 'required|in_list[PEN,USD]',
    ];

    protected $paymentModel;
    protected $invoiceModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->paymentModel = new PaymentModel();
        $this->invoiceModel = new InvoiceModel();
    }

    /**
     * Calculate and update balance for an organization
     */
    public function calculateBalance($organizationId, $currency = 'PEN')
    {
        $db = \Config\Database::connect();
        
        // Get ONLY Ligo production payments for this organization (exclude development/test payments)
        $builder = $db->table('payments p');
        $builder->select('
            SUM(CASE WHEN p.status = "completed" AND p.payment_method = "ligo_qr" 
                     AND p.external_id NOT LIKE "test%" 
                     AND p.external_id NOT LIKE "TEST%" 
                     AND p.external_id NOT LIKE "INST%" 
                     THEN p.amount ELSE 0 END) as total_collected,
            SUM(CASE WHEN p.status = "completed" AND p.payment_method = "ligo_qr" 
                     AND p.external_id NOT LIKE "test%" 
                     AND p.external_id NOT LIKE "TEST%" 
                     AND p.external_id NOT LIKE "INST%" 
                     THEN p.amount ELSE 0 END) as total_ligo_payments,
            0 as total_cash_payments,
            0 as total_other_payments,
            MAX(CASE WHEN p.status = "completed" AND p.payment_method = "ligo_qr" 
                     AND p.external_id NOT LIKE "test%" 
                     AND p.external_id NOT LIKE "TEST%" 
                     AND p.external_id NOT LIKE "INST%" 
                     THEN p.payment_date END) as last_payment_date
        ');
        $builder->join('invoices i', 'p.invoice_id = i.id');
        $builder->where('i.organization_id', $organizationId);
        $builder->where('i.currency', $currency);
        $builder->where('p.deleted_at IS NULL');
        $builder->where('i.deleted_at IS NULL');
        
        // DEBUG: Log the actual SQL query
        log_message('info', 'Balance Query SQL: ' . $builder->getCompiledSelect());
        $result = $builder->get()->getRowArray();
        log_message('info', 'Balance Query Result: ' . json_encode($result));
        
        // Get total pending amount (calculate based on invoice amount vs payments)
        $pendingBuilder = $db->table('invoices i');
        $pendingBuilder->select('
            SUM(CASE 
                WHEN i.status IN ("pending", "partially_paid") THEN 
                    COALESCE(
                        CASE WHEN i.total_amount IS NOT NULL AND i.total_amount > 0 
                             THEN i.total_amount 
                             ELSE i.amount 
                        END, 0
                    )
                ELSE 0 
            END) as total_pending
        ');
        $pendingBuilder->where('i.organization_id', $organizationId);
        $pendingBuilder->where('i.currency', $currency);
        $pendingBuilder->where('i.deleted_at IS NULL');
        
        $pendingResult = $pendingBuilder->get()->getRowArray();
        
        $balanceData = [
            'organization_id' => $organizationId,
            'total_collected' => $result['total_collected'] ?? 0,
            'total_ligo_payments' => $result['total_ligo_payments'] ?? 0,
            'total_cash_payments' => $result['total_cash_payments'] ?? 0,
            'total_other_payments' => $result['total_other_payments'] ?? 0,
            'total_pending' => $pendingResult['total_pending'] ?? 0,
            'currency' => $currency,
            'last_payment_date' => $result['last_payment_date'] ?? null,
            'last_calculated_at' => date('Y-m-d H:i:s')
        ];
        
        // Update or insert balance record
        $existingBalance = $this->where('organization_id', $organizationId)
                               ->where('currency', $currency)
                               ->first();
        
        if ($existingBalance) {
            $this->update($existingBalance['id'], $balanceData);
            return $existingBalance['id'];
        } else {
            return $this->insert($balanceData);
        }
    }

    /**
     * Get balance for an organization
     */
    public function getBalance($organizationId, $currency = 'PEN', $recalculate = false)
    {
        if ($recalculate) {
            $this->calculateBalance($organizationId, $currency);
        }
        
        return $this->where('organization_id', $organizationId)
                   ->where('currency', $currency)
                   ->first();
    }

    /**
     * Get detailed movements for an organization
     */
    public function getMovements($organizationId, $dateStart = null, $dateEnd = null, $paymentMethod = null, $currency = 'PEN')
    {
        $db = \Config\Database::connect();
        $builder = $db->table('payments p');
        
        $builder->select('
            p.id,
            p.amount,
            p.payment_method,
            p.payment_date,
            p.reference_code,
            p.status,
            i.invoice_number,
            i.concept as invoice_concept,
            i.currency,
            c.business_name as client_name,
            c.document_number as client_document,
            inst.number as instalment_number,
            u.name as collector_name
        ');
        
        $builder->join('invoices i', 'p.invoice_id = i.id');
        $builder->join('clients c', 'i.client_id = c.id');
        $builder->join('instalments inst', 'p.instalment_id = inst.id', 'left');
        $builder->join('users u', 'p.user_id = u.id', 'left');
        
        $builder->where('i.organization_id', $organizationId);
        $builder->where('i.currency', $currency);
        $builder->where('p.payment_method', 'ligo_qr'); // Only Ligo payments
        // Exclude all test payments (multiple patterns)
        $builder->where('p.external_id NOT LIKE', 'test%');
        $builder->where('p.external_id NOT LIKE', 'TEST%');
        $builder->where('p.external_id NOT LIKE', 'INST%');
        $builder->where('p.deleted_at IS NULL');
        $builder->where('i.deleted_at IS NULL');
        
        if ($dateStart) {
            $builder->where('p.payment_date >=', $dateStart);
        }
        
        if ($dateEnd) {
            $builder->where('p.payment_date <=', $dateEnd . ' 23:59:59');
        }
        
        if ($paymentMethod) {
            $builder->where('p.payment_method', $paymentMethod);
        }
        
        $builder->orderBy('p.payment_date', 'DESC');
        
        // DEBUG: Log movements query
        log_message('info', 'Movements Query SQL: ' . $builder->getCompiledSelect());
        $result = $builder->get()->getResultArray();
        log_message('info', 'Movements Query Result Count: ' . count($result));
        log_message('info', 'Movements Query Sample: ' . json_encode(array_slice($result, 0, 3)));
        
        return $result;
    }

    /**
     * Get Ligo payments summary for an organization
     */
    public function getLigoPaymentsSummary($organizationId, $dateStart = null, $dateEnd = null, $currency = 'PEN')
    {
        $db = \Config\Database::connect();
        $builder = $db->table('payments p');
        
        $builder->select('
            COUNT(*) as total_transactions,
            SUM(p.amount) as total_amount,
            MIN(p.payment_date) as first_payment,
            MAX(p.payment_date) as last_payment,
            AVG(p.amount) as average_amount
        ');
        
        $builder->join('invoices i', 'p.invoice_id = i.id');
        $builder->where('i.organization_id', $organizationId);
        $builder->where('i.currency', $currency);
        $builder->where('p.payment_method', 'ligo_qr');
        // Exclude all test payments (multiple patterns)
        $builder->where('p.external_id NOT LIKE', 'test%');
        $builder->where('p.external_id NOT LIKE', 'TEST%');
        $builder->where('p.external_id NOT LIKE', 'INST%');
        $builder->where('p.status', 'completed');
        $builder->where('p.deleted_at IS NULL');
        $builder->where('i.deleted_at IS NULL');
        
        if ($dateStart) {
            $builder->where('p.payment_date >=', $dateStart);
        }
        
        if ($dateEnd) {
            $builder->where('p.payment_date <=', $dateEnd . ' 23:59:59');
        }
        
        return $builder->get()->getRowArray();
    }

    /**
     * Get monthly Ligo payments breakdown
     */
    public function getMonthlyBreakdown($organizationId, $year = null, $currency = 'PEN')
    {
        if (!$year) {
            $year = date('Y');
        }
        
        $db = \Config\Database::connect();
        $builder = $db->table('payments p');
        
        // SQLite-compatible date functions
        $builder->select('
            CAST(strftime("%m", p.payment_date) AS INTEGER) as month,
            CASE CAST(strftime("%m", p.payment_date) AS INTEGER)
                WHEN 1 THEN "January"
                WHEN 2 THEN "February"
                WHEN 3 THEN "March"
                WHEN 4 THEN "April"
                WHEN 5 THEN "May"
                WHEN 6 THEN "June"
                WHEN 7 THEN "July"
                WHEN 8 THEN "August"
                WHEN 9 THEN "September"
                WHEN 10 THEN "October"
                WHEN 11 THEN "November"
                WHEN 12 THEN "December"
            END as month_name,
            COUNT(*) as transaction_count,
            SUM(p.amount) as total_amount
        ');
        
        $builder->join('invoices i', 'p.invoice_id = i.id');
        $builder->where('i.organization_id', $organizationId);
        $builder->where('i.currency', $currency);
        $builder->where('p.payment_method', 'ligo_qr');
        // Exclude all test payments (multiple patterns)
        $builder->where('p.external_id NOT LIKE', 'test%');
        $builder->where('p.external_id NOT LIKE', 'TEST%');
        $builder->where('p.external_id NOT LIKE', 'INST%');
        $builder->where('p.status', 'completed');
        $builder->where('strftime("%Y", p.payment_date)', $year); // SQLite year function
        $builder->where('p.deleted_at IS NULL');
        $builder->where('i.deleted_at IS NULL');
        
        $builder->groupBy('strftime("%m", p.payment_date)'); // SQLite month grouping
        $builder->orderBy('month', 'ASC'); // Order by the selected alias
        
        return $builder->get()->getResultArray();
    }

    /**
     * Recalculate balances for all organizations
     */
    public function recalculateAllBalances()
    {
        $organizationModel = new OrganizationModel();
        $organizations = $organizationModel->findAll();
        
        $updated = 0;
        foreach ($organizations as $org) {
            // Calculate for both currencies
            $this->calculateBalance($org['id'], 'PEN');
            $this->calculateBalance($org['id'], 'USD');
            $updated++;
        }
        
        return $updated;
    }
}
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
    protected $ligoModel;
    
    public function __construct()
    {
        parent::__construct();
        $this->paymentModel = new PaymentModel();
        $this->invoiceModel = new InvoiceModel();
        $this->ligoModel = new LigoModel();
    }

    /**
     * Calculate and update balance for an organization using Ligo API
     */
    public function calculateBalance($organizationId, $currency = 'PEN')
    {
        log_message('info', 'OrganizationBalanceModel: Calculating balance using Ligo API for org: ' . $organizationId . ', currency: ' . $currency);
        
        // Get Ligo recharges data for this organization
        $ligoSummary = $this->getLigoRechargesSummary($organizationId, null, null, $currency);
        
        // Get total pending amount from invoices (still needed)
        $db = \Config\Database::connect();
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
            'total_collected' => $ligoSummary['total_amount'] ?? 0,
            'total_ligo_payments' => $ligoSummary['total_amount'] ?? 0,
            'total_cash_payments' => 0,
            'total_other_payments' => 0,
            'total_pending' => $pendingResult['total_pending'] ?? 0,
            'currency' => $currency,
            'last_payment_date' => $ligoSummary['last_payment'] ?? null,
            'last_calculated_at' => date('Y-m-d H:i:s')
        ];
        
        log_message('info', 'OrganizationBalanceModel: Balance data from Ligo API: ' . json_encode($balanceData));
        
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
     * Get detailed movements for an organization using Ligo API
     */
    public function getMovements($organizationId, $dateStart = null, $dateEnd = null, $paymentMethod = null, $currency = 'PEN')
    {
        log_message('info', 'OrganizationBalanceModel: Getting movements using Ligo API for org: ' . $organizationId);
        
        // Get Ligo recharges for the specified date range
        $ligoMovements = $this->getLigoRechargesMovements($organizationId, $dateStart, $dateEnd, $currency);
        
        log_message('info', 'OrganizationBalanceModel: Ligo movements count: ' . count($ligoMovements));
        log_message('info', 'OrganizationBalanceModel: Sample movements: ' . json_encode(array_slice($ligoMovements, 0, 3)));
        
        return $ligoMovements;
    }

    /**
     * Get Ligo payments summary for an organization using Ligo API
     */
    public function getLigoPaymentsSummary($organizationId, $dateStart = null, $dateEnd = null, $currency = 'PEN')
    {
        // Use the new Ligo API method
        return $this->getLigoRechargesSummary($organizationId, $dateStart, $dateEnd, $currency);
    }

    /**
     * Get monthly Ligo payments breakdown using Ligo API
     */
    public function getMonthlyBreakdown($organizationId, $year = null, $currency = 'PEN')
    {
        if (!$year) {
            $year = date('Y');
        }
        
        log_message('info', 'OrganizationBalanceModel: Getting monthly breakdown using Ligo API for year: ' . $year);
        
        // Set organization context in session for LigoModel
        $session = session();
        $originalOrgId = $session->get('selected_organization_id');
        $session->set('selected_organization_id', $organizationId);
        
        try {
            // Get data for the entire year
            $dateStart = $year . '-01-01';
            $dateEnd = $year . '-12-31';
            
            // Get recharges from Ligo API
            $response = $this->ligoModel->listRecharges([
                'startDate' => $dateStart,
                'endDate' => $dateEnd,
                'page' => 1
            ]);
            
            if (isset($response['error'])) {
                log_message('error', 'OrganizationBalanceModel: Ligo API error for monthly breakdown: ' . $response['error']);
                return [];
            }
            
            $recharges = $response['data']['records'] ?? [];
            
            // Initialize monthly data
            $monthlyData = [];
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];
            
            // Process recharges
            foreach ($recharges as $recharge) {
                // Only include successful recharges (responseCode = '00')
                if (($recharge['responseCode'] ?? '') === '00') {
                    // Filter by currency
                    $rechargeCurrency = ($recharge['currency'] ?? '604') === '604' ? 'PEN' : 'USD';
                    if ($rechargeCurrency === $currency) {
                        // Only include recharges associated with this organization
                        if (isset($recharge['instalment']) && !empty($recharge['instalment'])) {
                            $createdAt = $recharge['createdAt'] ?? null;
                            if ($createdAt) {
                                $month = (int)date('n', strtotime($createdAt));
                                $amount = floatval($recharge['amount'] ?? 0);
                                
                                if (!isset($monthlyData[$month])) {
                                    $monthlyData[$month] = [
                                        'month' => $month,
                                        'month_name' => $monthNames[$month],
                                        'transaction_count' => 0,
                                        'total_amount' => 0
                                    ];
                                }
                                
                                $monthlyData[$month]['transaction_count']++;
                                $monthlyData[$month]['total_amount'] += $amount;
                            }
                        }
                    }
                }
            }
            
            // Sort by month
            ksort($monthlyData);
            
            log_message('info', 'OrganizationBalanceModel: Monthly breakdown data: ' . json_encode(array_values($monthlyData)));
            
            return array_values($monthlyData);
            
        } finally {
            // Restore original organization context
            if ($originalOrgId) {
                $session->set('selected_organization_id', $originalOrgId);
            } else {
                $session->remove('selected_organization_id');
            }
        }
    }

    /**
     * Get Ligo recharges summary for an organization using API
     */
    private function getLigoRechargesSummary($organizationId, $dateStart = null, $dateEnd = null, $currency = 'PEN')
    {
        // Set organization context in session for LigoModel
        $session = session();
        $originalOrgId = $session->get('selected_organization_id');
        $session->set('selected_organization_id', $organizationId);
        
        try {
            // Use a wide date range if not specified (last 2 years)
            if (!$dateStart) {
                $dateStart = date('Y-m-d', strtotime('-2 years'));
            }
            if (!$dateEnd) {
                $dateEnd = date('Y-m-d');
            }
            
            log_message('info', 'OrganizationBalanceModel: Fetching Ligo recharges from ' . $dateStart . ' to ' . $dateEnd);
            
            // Get recharges from Ligo API
            $response = $this->ligoModel->listRecharges([
                'startDate' => $dateStart,
                'endDate' => $dateEnd,
                'page' => 1
            ]);
            
            if (isset($response['error'])) {
                log_message('error', 'OrganizationBalanceModel: Ligo API error: ' . $response['error']);
                return [
                    'total_transactions' => 0,
                    'total_amount' => 0,
                    'first_payment' => null,
                    'last_payment' => null,
                    'average_amount' => 0
                ];
            }
            
            $recharges = $response['data']['records'] ?? [];
            
            // Filter for this organization and successful transactions only
            $filteredRecharges = [];
            foreach ($recharges as $recharge) {
                // Only include successful recharges (responseCode = '00')
                if (($recharge['responseCode'] ?? '') === '00') {
                    // Filter by currency if it matches
                    $rechargeCurrency = ($recharge['currency'] ?? '604') === '604' ? 'PEN' : 'USD';
                    if ($rechargeCurrency === $currency) {
                        // Check if recharge is associated with this organization
                        if (isset($recharge['instalment']) && !empty($recharge['instalment'])) {
                            $filteredRecharges[] = $recharge;
                        }
                    }
                }
            }
            
            log_message('info', 'OrganizationBalanceModel: Filtered recharges count: ' . count($filteredRecharges));
            
            // Calculate summary
            $totalAmount = 0;
            $dates = [];
            
            foreach ($filteredRecharges as $recharge) {
                $amount = floatval($recharge['amount'] ?? 0);
                $totalAmount += $amount;
                
                if (!empty($recharge['createdAt'])) {
                    $dates[] = $recharge['createdAt'];
                }
            }
            
            $summary = [
                'total_transactions' => count($filteredRecharges),
                'total_amount' => $totalAmount,
                'first_payment' => !empty($dates) ? min($dates) : null,
                'last_payment' => !empty($dates) ? max($dates) : null,
                'average_amount' => count($filteredRecharges) > 0 ? ($totalAmount / count($filteredRecharges)) : 0
            ];
            
            log_message('info', 'OrganizationBalanceModel: Ligo summary: ' . json_encode($summary));
            
            return $summary;
            
        } finally {
            // Restore original organization context
            if ($originalOrgId) {
                $session->set('selected_organization_id', $originalOrgId);
            } else {
                $session->remove('selected_organization_id');
            }
        }
    }
    
    /**
     * Get Ligo recharges movements for an organization using API
     */
    private function getLigoRechargesMovements($organizationId, $dateStart = null, $dateEnd = null, $currency = 'PEN')
    {
        // Set organization context in session for LigoModel
        $session = session();
        $originalOrgId = $session->get('selected_organization_id');
        $session->set('selected_organization_id', $organizationId);
        
        try {
            // Use reasonable defaults if dates not specified
            if (!$dateStart) {
                $dateStart = date('Y-m-d', strtotime('-1 year'));
            }
            if (!$dateEnd) {
                $dateEnd = date('Y-m-d');
            }
            
            log_message('info', 'OrganizationBalanceModel: Fetching Ligo movements from ' . $dateStart . ' to ' . $dateEnd);
            
            // Get recharges from Ligo API
            $response = $this->ligoModel->listRecharges([
                'startDate' => $dateStart,
                'endDate' => $dateEnd,
                'page' => 1
            ]);
            
            if (isset($response['error'])) {
                log_message('error', 'OrganizationBalanceModel: Ligo API error for movements: ' . $response['error']);
                return [];
            }
            
            $recharges = $response['data']['records'] ?? [];
            $movements = [];
            
            // Convert Ligo recharges to movements format
            foreach ($recharges as $recharge) {
                // Only include successful recharges (responseCode = '00')
                if (($recharge['responseCode'] ?? '') === '00') {
                    // Filter by currency
                    $rechargeCurrency = ($recharge['currency'] ?? '604') === '604' ? 'PEN' : 'USD';
                    if ($rechargeCurrency === $currency) {
                        // Only include recharges associated with this organization
                        if (isset($recharge['instalment']) && !empty($recharge['instalment'])) {
                            $instalment = $recharge['instalment'];
                            
                            $movement = [
                                'id' => $recharge['transferId'] ?? $recharge['instructionId'] ?? uniqid(),
                                'amount' => floatval($recharge['amount'] ?? 0),
                                'payment_method' => 'ligo_qr',
                                'payment_date' => $recharge['createdAt'] ?? null,
                                'reference_code' => $recharge['transferId'] ?? $recharge['instructionId'] ?? '',
                                'status' => 'completed',
                                'invoice_number' => $instalment['invoice_number'] ?? 'N/A',
                                'invoice_concept' => $instalment['invoice_description'] ?? 'Pago de cuota',
                                'currency' => $currency,
                                'client_name' => $instalment['client_name'] ?? $recharge['debtorName'] ?? 'Cliente',
                                'client_document' => '',
                                'instalment_number' => $instalment['number'] ?? null,
                                'collector_name' => 'Ligo QR'
                            ];
                            
                            $movements[] = $movement;
                        }
                    }
                }
            }
            
            // Sort by payment date descending
            usort($movements, function($a, $b) {
                return strtotime($b['payment_date'] ?? '1970-01-01') - strtotime($a['payment_date'] ?? '1970-01-01');
            });
            
            log_message('info', 'OrganizationBalanceModel: Ligo movements count: ' . count($movements));
            
            return $movements;
            
        } finally {
            // Restore original organization context
            if ($originalOrgId) {
                $session->set('selected_organization_id', $originalOrgId);
            } else {
                $session->remove('selected_organization_id');
            }
        }
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
<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\OrganizationModel;
use App\Models\OrganizationBalanceModel;
use App\Models\PaymentModel;
use App\Models\TransferModel;
use App\Models\LigoModel;

class OrganizationAccountController extends BaseController
{
    protected $organizationModel;
    protected $balanceModel;
    protected $paymentModel;
    protected $transferModel;
    protected $ligoModel;

    public function __construct()
    {
        $this->organizationModel = new OrganizationModel();
        $this->balanceModel = new OrganizationBalanceModel();
        $this->paymentModel = new PaymentModel();
        $this->transferModel = new TransferModel();
        $this->ligoModel = new LigoModel();
    }

    /**
     * Display account statement for an organization
     */
    public function index($organizationUuid = null)
    {
        // Get organization by UUID or user context
        if ($organizationUuid) {
            // Find organization by UUID for security
            $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
            if (!$organization) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Organización no encontrada');
            }
            $organizationId = $organization['id'];
        } else {
            // For superadmin, use selected_organization_id, for regular users use organization_id
            if (session('role') === 'superadmin') {
                $organizationId = session('selected_organization_id');
            } else {
                $organizationId = session('organization_id');
            }
            $organization = $this->organizationModel->find($organizationId);
        }

        // Verify access to organization
        if (!$this->hasOrganizationAccess($organizationId)) {
            return redirect()->back()->with('error', 'No tienes acceso a esta organización');
        }

        // Get date range from request
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $currency = $this->request->getGet('currency') ?: 'PEN';

        // Default to last 30 days if no dates provided
        if (!$dateStart || !$dateEnd) {
            $dateEnd = date('Y-m-d');
            $dateStart = date('Y-m-d', strtotime('-30 days'));
        }

        // Get organization balance (recalculate if requested)
        $recalculate = $this->request->getGet('recalculate') === '1';
        $balance = $this->balanceModel->getBalance($organizationId, $currency, $recalculate);

        // Get Ligo payments summary
        $ligoSummary = $this->balanceModel->getLigoPaymentsSummary($organizationId, $dateStart, $dateEnd, $currency);

        // Get monthly breakdown for current year
        $monthlyBreakdown = $this->balanceModel->getMonthlyBreakdown($organizationId, null, $currency);

        // Get recent transfers for this organization
        $transfers = $this->transferModel->getTransfersByOrganization($organizationId, 20);
        
        // Get transfer balance summary
        $transferBalance = $this->transferModel->calculateOrganizationBalance($organizationId);

        // Get current active Ligo configuration to determine production vs dev
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        $activeConfig = $superadminLigoConfigModel->where('enabled', 1)->where('is_active', 1)->first();
        $isProduction = $activeConfig && $activeConfig['environment'] === 'prod';
        
        // Get individual Ligo payments (completed only, filtered by current environment)
        $db = \Config\Database::connect();
        $query = $db->table('payments p')
                   ->select('p.id, p.amount, p.payment_date, p.status, p.payment_method, p.created_at, p.invoice_id, p.instalment_id, p.external_id, p.ligo_environment')
                   ->join('invoices i', 'p.invoice_id = i.id')
                   ->where('i.organization_id', $organizationId)
                   ->where('p.payment_method', 'ligo_qr')
                   ->where('p.status', 'completed');
        
        // Filter based on current environment preference using ligo_environment field
        if ($isProduction) {
            // Show only production payments
            $query->where('p.ligo_environment', 'prod');
        } else {
            // Show only development/test payments  
            $query->where('p.ligo_environment', 'dev');
        }
        
        $ligoPayments = $query->orderBy('p.created_at', 'DESC')
                            ->limit(50)
                            ->get()
                            ->getResultArray();

        return view('organizations/account_statement', [
            'organization' => $organization,
            'balance' => $balance,
            'ligoSummary' => $ligoSummary,
            'monthlyBreakdown' => $monthlyBreakdown,
            'transfers' => $transfers,
            'transferBalance' => $transferBalance,
            'ligoPayments' => $ligoPayments,
            'activeConfig' => $activeConfig,
            'isProduction' => $isProduction,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'currency' => $currency,
            'title' => 'Estado de Cuenta - ' . $organization['name']
        ]);
    }

    /**
     * Display detailed movements for an organization
     */
    public function movements($organizationUuid = null)
    {
        // Get organization by UUID or user context
        if ($organizationUuid) {
            // Find organization by UUID for security
            $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
            if (!$organization) {
                throw new \CodeIgniter\Exceptions\PageNotFoundException('Organización no encontrada');
            }
            $organizationId = $organization['id'];
        } else {
            // For superadmin, use selected_organization_id, for regular users use organization_id
            if (session('role') === 'superadmin') {
                $organizationId = session('selected_organization_id');
            } else {
                $organizationId = session('organization_id');
            }
            $organization = $this->organizationModel->find($organizationId);
        }

        // Verify access to organization
        if (!$this->hasOrganizationAccess($organizationId)) {
            return redirect()->back()->with('error', 'No tienes acceso a esta organización');
        }

        // Get filters from request
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $paymentMethod = $this->request->getGet('payment_method');
        $page = (int)$this->request->getGet('page') ?: 1;
        $perPage = 50;

        // Default to last 30 days if no dates provided
        if (!$dateStart || !$dateEnd) {
            $dateEnd = date('Y-m-d');
            $dateStart = date('Y-m-d', strtotime('-30 days'));
        }

        // Get movements (default to PEN currency for now - can be enhanced later)
        $currency = 'PEN';
        $movements = $this->balanceModel->getMovements($organizationId, $dateStart, $dateEnd, $paymentMethod, $currency);

        // Paginate results
        $totalMovements = count($movements);
        $offset = ($page - 1) * $perPage;
        $paginatedMovements = array_slice($movements, $offset, $perPage);

        // Calculate pagination info
        $totalPages = ceil($totalMovements / $perPage);

        return view('organizations/movements', [
            'organization' => $organization,
            'movements' => $paginatedMovements,
            'totalMovements' => $totalMovements,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'paymentMethod' => $paymentMethod,
            'title' => 'Movimientos - ' . $organization['name']
        ]);
    }

    /**
     * Export movements to CSV
     */
    public function exportMovements($organizationUuid = null)
    {
        // Get organization by UUID
        if ($organizationUuid) {
            $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
            if (!$organization) {
                return $this->response->setJSON(['error' => 'Organización no encontrada'])->setStatusCode(404);
            }
            $organizationId = $organization['id'];
        } else {
            if (session('role') === 'superadmin') {
                $organizationId = session('selected_organization_id');
            } else {
                $organizationId = session('organization_id');
            }
            $organization = $this->organizationModel->find($organizationId);
        }

        // Verify access to organization
        if (!$this->hasOrganizationAccess($organizationId)) {
            return $this->response->setJSON(['error' => 'No tienes acceso a esta organización'])->setStatusCode(403);
        }

        // Get filters from request
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $paymentMethod = $this->request->getGet('payment_method');

        // Get movements (default to PEN currency)
        $currency = 'PEN';
        $movements = $this->balanceModel->getMovements($organizationId, $dateStart, $dateEnd, $paymentMethod, $currency);

        // Prepare CSV content
        $csv = "Fecha,Método de Pago,Monto,Estado,Factura,Concepto,Cliente,Documento,Cuota,Cobrador,Referencia\n";
        
        foreach ($movements as $movement) {
            $csv .= sprintf(
                "%s,%s,%.2f,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $movement['payment_date'],
                $movement['payment_method'],
                $movement['amount'],
                $movement['status'],
                $movement['invoice_number'] ?: '-',
                '"' . str_replace('"', '""', $movement['invoice_concept'] ?: '-') . '"',
                '"' . str_replace('"', '""', $movement['client_name'] ?: '-') . '"',
                $movement['client_document'] ?: '-',
                $movement['instalment_number'] ? 'Cuota ' . $movement['instalment_number'] : '-',
                '"' . str_replace('"', '""', $movement['collector_name'] ?: '-') . '"',
                $movement['reference_code'] ?: '-'
            );
        }

        // Set headers for CSV download
        $filename = 'movimientos_' . $organization['name'] . '_' . date('Y-m-d') . '.csv';
        $filename = preg_replace('/[^A-Za-z0-9_\-.]/', '_', $filename);

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=utf-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setBody("\xEF\xBB\xBF" . $csv); // Add BOM for proper UTF-8 encoding
    }

    /**
     * Recalculate organization balance
     */
    public function recalculateBalance($organizationUuid = null)
    {
        // Get organization by UUID
        if ($organizationUuid) {
            $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
            if (!$organization) {
                return $this->response->setJSON(['error' => 'Organización no encontrada'])->setStatusCode(404);
            }
            $organizationId = $organization['id'];
        } else {
            if (session('role') === 'superadmin') {
                $organizationId = session('selected_organization_id');
            } else {
                $organizationId = session('organization_id');
            }
        }

        // Verify access to organization
        if (!$this->hasOrganizationAccess($organizationId)) {
            return $this->response->setJSON(['error' => 'No tienes acceso a esta organización'])->setStatusCode(403);
        }

        try {
            $currency = $this->request->getPost('currency') ?: 'PEN';
            $this->balanceModel->calculateBalance($organizationId, $currency);
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Balance recalculado exitosamente'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error recalculating balance: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => 'Error al recalcular el balance: ' . $e->getMessage()
            ])->setStatusCode(500);
        }
    }

    /**
     * API endpoint to get organization balance
     */
    public function getBalance($organizationUuid = null)
    {
        // Get organization by UUID
        if ($organizationUuid) {
            $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
            if (!$organization) {
                return $this->response->setJSON(['error' => 'Organización no encontrada'])->setStatusCode(404);
            }
            $organizationId = $organization['id'];
        } else {
            if (session('role') === 'superadmin') {
                $organizationId = session('selected_organization_id');
            } else {
                $organizationId = session('organization_id');
            }
        }

        // Verify access to organization
        if (!$this->hasOrganizationAccess($organizationId)) {
            return $this->response->setJSON(['error' => 'No tienes acceso a esta organización'])->setStatusCode(403);
        }

        $currency = $this->request->getGet('currency') ?: 'PEN';
        $recalculate = $this->request->getGet('recalculate') === '1';

        $balance = $this->balanceModel->getBalance($organizationId, $currency, $recalculate);

        return $this->response->setJSON([
            'organization_id' => $organizationId,
            'balance' => $balance ?: [
                'total_collected' => 0,
                'total_ligo_payments' => 0,
                'total_cash_payments' => 0,
                'total_other_payments' => 0,
                'total_pending' => 0,
                'currency' => $currency,
                'last_payment_date' => null,
                'last_calculated_at' => null
            ]
        ]);
    }

    /**
     * Check if current user has access to organization
     */
    private function hasOrganizationAccess($organizationId)
    {
        // Try multiple ways to get user role (different session structures)
        $user = session('user');
        $roleFromSession = session('role');
        $roleFromUser = isset($user) && isset($user['role']) ? $user['role'] : null;
        
        // Superadmin has access to all organizations
        if ($roleFromSession === 'superadmin' || $roleFromUser === 'superadmin') {
            return true;
        }

        // Regular users can only access their own organization
        $userOrgId = session('organization_id');
        return $userOrgId && $userOrgId == $organizationId;
    }

    /**
     * Get Ligo transactions for organization (AJAX endpoint)
     */
    public function getLigoTransactions($organizationUuid = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error' => 'Invalid request'])->setStatusCode(400);
        }

        // Get organization by UUID
        if ($organizationUuid) {
            $organization = $this->organizationModel->where('uuid', $organizationUuid)->first();
            if (!$organization) {
                return $this->response->setJSON(['error' => 'Organización no encontrada'])->setStatusCode(404);
            }
            $organizationId = $organization['id'];
        } else {
            if (session('role') === 'superadmin') {
                $organizationId = session('selected_organization_id');
            } else {
                $organizationId = session('organization_id');
            }
        }

        // Verify access
        if (!$this->hasOrganizationAccess($organizationId)) {
            return $this->response->setJSON(['error' => 'No tienes acceso a esta organización'])->setStatusCode(403);
        }

        // Get filters from request
        $startDate = $this->request->getPost('startDate');
        $endDate = $this->request->getPost('endDate');
        $page = (int)$this->request->getPost('page') ?: 1;

        $requestData = [
            'page' => $page,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        // Call Ligo API
        $response = $this->ligoModel->listTransactionsForOrganization($requestData);

        if (isset($response['error'])) {
            return $this->response->setJSON(['error' => $response['error']])->setStatusCode(400);
        }

        return $this->response->setJSON($response);
    }
}
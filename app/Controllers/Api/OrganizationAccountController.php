<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\OrganizationModel;
use App\Models\OrganizationBalanceModel;

class OrganizationAccountController extends ResourceController
{
    use ResponseTrait;

    protected $organizationModel;
    protected $balanceModel;

    public function __construct()
    {
        $this->organizationModel = new OrganizationModel();
        $this->balanceModel = new OrganizationBalanceModel();
    }

    /**
     * Get organization balance
     * GET /api/organizations/{id}/balance
     */
    public function getBalance($organizationId = null)
    {
        try {
            // Verify access to organization
            if (!$this->hasOrganizationAccess($organizationId)) {
                return $this->failForbidden('No tienes acceso a esta organización');
            }

            $organization = $this->organizationModel->find($organizationId);
            if (!$organization) {
                return $this->failNotFound('Organización no encontrada');
            }

            $currency = $this->request->getGet('currency') ?: 'PEN';
            $recalculate = $this->request->getGet('recalculate') === '1';

            $balance = $this->balanceModel->getBalance($organizationId, $currency, $recalculate);

            // Get Ligo payments summary for current month if requested
            $includeSummary = $this->request->getGet('include_summary') === '1';
            $summary = null;

            if ($includeSummary) {
                $dateStart = date('Y-m-01'); // First day of current month
                $dateEnd = date('Y-m-t');    // Last day of current month
                $summary = $this->balanceModel->getLigoPaymentsSummary($organizationId, $dateStart, $dateEnd);
            }

            return $this->respond([
                'organization_id' => $organizationId,
                'organization_name' => $organization['name'],
                'currency' => $currency,
                'balance' => $balance ?: [
                    'total_collected' => 0,
                    'total_ligo_payments' => 0,
                    'total_cash_payments' => 0,
                    'total_other_payments' => 0,
                    'total_pending' => 0,
                    'currency' => $currency,
                    'last_payment_date' => null,
                    'last_calculated_at' => null
                ],
                'current_month_summary' => $summary
            ]);

        } catch (\Exception $e) {
            log_message('error', 'API Error getting organization balance: ' . $e->getMessage());
            return $this->failServerError('Error interno del servidor');
        }
    }

    /**
     * Get organization movements
     * GET /api/organizations/{id}/movements
     */
    public function movements($organizationId = null)
    {
        try {
            // Verify access to organization
            if (!$this->hasOrganizationAccess($organizationId)) {
                return $this->failForbidden('No tienes acceso a esta organización');
            }

            $organization = $this->organizationModel->find($organizationId);
            if (!$organization) {
                return $this->failNotFound('Organización no encontrada');
            }

            // Get filters from request
            $dateStart = $this->request->getGet('date_start');
            $dateEnd = $this->request->getGet('date_end');
            $paymentMethod = $this->request->getGet('payment_method');
            $page = max(1, (int)$this->request->getGet('page'));
            $perPage = min(100, max(10, (int)$this->request->getGet('per_page') ?: 50));

            // Default to last 30 days if no dates provided
            if (!$dateStart || !$dateEnd) {
                $dateEnd = date('Y-m-d');
                $dateStart = date('Y-m-d', strtotime('-30 days'));
            }

            // Validate date format
            if (!$this->isValidDate($dateStart) || !$this->isValidDate($dateEnd)) {
                return $this->failValidationError('Formato de fecha inválido. Use YYYY-MM-DD');
            }

            // Get movements
            $movements = $this->balanceModel->getMovements($organizationId, $dateStart, $dateEnd, $paymentMethod);

            // Calculate pagination
            $totalMovements = count($movements);
            $totalPages = ceil($totalMovements / $perPage);
            $offset = ($page - 1) * $perPage;
            $paginatedMovements = array_slice($movements, $offset, $perPage);

            // Format movements for API response
            $formattedMovements = array_map(function($movement) {
                return [
                    'id' => $movement['id'],
                    'payment_date' => $movement['payment_date'],
                    'amount' => (float)$movement['amount'],
                    'payment_method' => $movement['payment_method'],
                    'status' => $movement['status'],
                    'reference_code' => $movement['reference_code'],
                    'invoice' => [
                        'number' => $movement['invoice_number'],
                        'concept' => $movement['invoice_concept']
                    ],
                    'client' => [
                        'name' => $movement['client_name'],
                        'document' => $movement['client_document']
                    ],
                    'instalment_number' => $movement['instalment_number'] ? (int)$movement['instalment_number'] : null,
                    'collector_name' => $movement['collector_name']
                ];
            }, $paginatedMovements);

            return $this->respond([
                'organization_id' => $organizationId,
                'organization_name' => $organization['name'],
                'filters' => [
                    'date_start' => $dateStart,
                    'date_end' => $dateEnd,
                    'payment_method' => $paymentMethod
                ],
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalMovements,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_previous' => $page > 1
                ],
                'movements' => $formattedMovements
            ]);

        } catch (\Exception $e) {
            log_message('error', 'API Error getting organization movements: ' . $e->getMessage());
            return $this->failServerError('Error interno del servidor');
        }
    }

    /**
     * Recalculate organization balance
     * POST /api/organizations/{id}/recalculate-balance
     */
    public function recalculateBalance($organizationId = null)
    {
        try {
            // Verify access to organization
            if (!$this->hasOrganizationAccess($organizationId)) {
                return $this->failForbidden('No tienes acceso a esta organización');
            }

            $organization = $this->organizationModel->find($organizationId);
            if (!$organization) {
                return $this->failNotFound('Organización no encontrada');
            }

            $input = $this->getRequestInput();
            $currency = $input['currency'] ?? 'PEN';

            if (!in_array($currency, ['PEN', 'USD'])) {
                return $this->failValidationError('Moneda inválida. Use PEN o USD');
            }

            $balanceId = $this->balanceModel->calculateBalance($organizationId, $currency);
            $updatedBalance = $this->balanceModel->getBalance($organizationId, $currency);

            return $this->respond([
                'success' => true,
                'message' => 'Balance recalculado exitosamente',
                'organization_id' => $organizationId,
                'organization_name' => $organization['name'],
                'currency' => $currency,
                'balance' => $updatedBalance,
                'recalculated_at' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            log_message('error', 'API Error recalculating balance: ' . $e->getMessage());
            return $this->failServerError('Error al recalcular el balance: ' . $e->getMessage());
        }
    }

    /**
     * Get organization monthly breakdown
     * GET /api/organizations/{id}/monthly-breakdown
     */
    public function monthlyBreakdown($organizationId = null)
    {
        try {
            // Verify access to organization
            if (!$this->hasOrganizationAccess($organizationId)) {
                return $this->failForbidden('No tienes acceso a esta organización');
            }

            $organization = $this->organizationModel->find($organizationId);
            if (!$organization) {
                return $this->failNotFound('Organización no encontrada');
            }

            $year = $this->request->getGet('year') ?: date('Y');

            if (!is_numeric($year) || $year < 2020 || $year > date('Y') + 1) {
                return $this->failValidationError('Año inválido');
            }

            $breakdown = $this->balanceModel->getMonthlyBreakdown($organizationId, $year);

            return $this->respond([
                'organization_id' => $organizationId,
                'organization_name' => $organization['name'],
                'year' => (int)$year,
                'monthly_breakdown' => array_map(function($item) {
                    return [
                        'month' => (int)$item['month'],
                        'month_name' => $item['month_name'],
                        'transaction_count' => (int)$item['transaction_count'],
                        'total_amount' => (float)$item['total_amount']
                    ];
                }, $breakdown)
            ]);

        } catch (\Exception $e) {
            log_message('error', 'API Error getting monthly breakdown: ' . $e->getMessage());
            return $this->failServerError('Error interno del servidor');
        }
    }

    /**
     * Check if current user has access to organization
     */
    private function hasOrganizationAccess($organizationId)
    {
        // Get user info from JWT token or session
        $userOrganizationId = null;
        $userRole = null;

        // Try to get from JWT first (API request)
        $jwt = service('request')->getHeaderLine('Authorization');
        if ($jwt && strpos($jwt, 'Bearer ') === 0) {
            $token = substr($jwt, 7);
            // Here you would decode the JWT and get user info
            // For now, we'll use session fallback
        }

        // Fallback to session (web request)
        $userOrganizationId = session('organization_id');
        $userRole = session('role');

        // Superadmin has access to all organizations
        if ($userRole === 'superadmin') {
            return true;
        }

        // Regular users can only access their own organization
        return $userOrganizationId && $userOrganizationId == $organizationId;
    }

    /**
     * Validate date format
     */
    private function isValidDate($date, $format = 'Y-m-d')
    {
        $dateTime = \DateTime::createFromFormat($format, $date);
        return $dateTime && $dateTime->format($format) === $date;
    }

    /**
     * Get request input data
     */
    private function getRequestInput()
    {
        $input = $this->request->getJSON(true);
        if (empty($input)) {
            $input = $this->request->getRawInput();
        }
        return $input ?: [];
    }
}
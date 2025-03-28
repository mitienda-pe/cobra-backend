<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\PortfolioModel;

class PortfolioController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * Get the authenticated user
     * This method ensures we have a user object even if session data is missing
     */
    protected function getAuthUser()
    {
        if ($this->user) {
            return $this->user;
        }
        
        // Try to get user from request object (set by ApiAuthFilter)
        if (isset($this->request) && isset($this->request->user)) {
            $this->user = $this->request->user;
            return $this->user;
        }
        
        // If still no user, return a default user with role 'guest'
        return [
            'id' => 0,
            'role' => 'guest',
            'organization_id' => 0
        ];
    }
    
    /**
     * List portfolios based on user role
     */
    public function index()
    {
        $portfolioModel = new PortfolioModel();
        
        $user = $this->getAuthUser();
        
        if ($user['role'] === 'superadmin' || $user['role'] === 'admin') {
            // Admins and superadmins can see all portfolios in their organization
            $portfolios = $portfolioModel->getByOrganization($user['organization_id']);
        } else {
            // Regular users can only see their assigned portfolios
            $portfolios = $portfolioModel->getByUser($user['id']);
        }
        
        return $this->respond(['portfolios' => $portfolios]);
    }
    
    /**
     * Get a single portfolio
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Portfolio ID is required');
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->find($id);
        
        if (!$portfolio) {
            return $this->failNotFound('Portfolio not found');
        }
        
        // Check if user has access to this portfolio
        $user = $this->getAuthUser();
        if (!$this->canAccessPortfolio($portfolio, $user)) {
            return $this->failForbidden('You do not have access to this portfolio');
        }
        
        // Get clients and users assigned to this portfolio
        $clients = $portfolioModel->getAssignedClients($id);
        $users = $portfolioModel->getAssignedUsers($id);
        
        // Include assignments in the response
        $portfolio['clients'] = $clients;
        $portfolio['users'] = $users;
        
        return $this->respond(['portfolio' => $portfolio]);
    }
    
    /**
     * Get portfolios assigned to current user
     */
    public function myPortfolios()
    {
        $portfolioModel = new PortfolioModel();
        $user = $this->getAuthUser();
        $portfolios = $portfolioModel->getByUser($user['id']);
        
        return $this->respond(['portfolios' => $portfolios]);
    }
    
    /**
     * Get invoices from the collector's portfolio
     */
    public function myInvoices()
    {
        $portfolioModel = new PortfolioModel();
        $invoiceModel = new \App\Models\InvoiceModel();
        $clientModel = new \App\Models\ClientModel();
        
        // Get user's portfolio (one-to-one relationship)
        $user = $this->getAuthUser();
        
        // Debug information
        log_message('debug', 'User in myInvoices: ' . json_encode($user));
        
        // Try to find portfolio by collector_id
        $portfolio = $portfolioModel->where('collector_id', $user['id'])->first();
        
        // If not found, try to find by user_id
        if (!$portfolio) {
            log_message('debug', 'No portfolio found by collector_id, trying user_id');
            $portfolio = $portfolioModel->where('user_id', $user['id'])->first();
        }
        
        // If still not found, try to get portfolios assigned to user
        if (!$portfolio) {
            log_message('debug', 'No portfolio found by user_id, trying to get assigned portfolios');
            $portfolios = $portfolioModel->getByUser($user['uuid'] ?? $user['id']);
            if (!empty($portfolios)) {
                $portfolio = $portfolios[0]; // Use the first assigned portfolio
                log_message('debug', 'Found portfolio by user assignment: ' . json_encode($portfolio));
            }
        }
        
        if (!$portfolio) {
            log_message('error', 'No portfolio assigned to user with ID: ' . $user['id']);
            return $this->failForbidden('No portfolio assigned to this collector');
        }
        
        log_message('debug', 'Portfolio found: ' . json_encode($portfolio));
        
        // Get clients assigned to this portfolio
        $portfolioId = $portfolio['id'] ?? null;
        $portfolioUuid = $portfolio['uuid'] ?? null;
        
        // Try with ID first, then with UUID
        if ($portfolioId) {
            $clients = $portfolioModel->getAssignedClients($portfolioId);
            if (empty($clients) && $portfolioUuid) {
                $clients = $portfolioModel->getAssignedClients($portfolioUuid);
            }
        } elseif ($portfolioUuid) {
            $clients = $portfolioModel->getAssignedClients($portfolioUuid);
        } else {
            $clients = [];
        }
        
        log_message('debug', 'Clients found: ' . json_encode($clients));
        
        if (empty($clients)) {
            log_message('error', 'No clients assigned to portfolio with ID: ' . ($portfolioId ?? $portfolioUuid));
            return $this->respond(['invoices' => []]);
        }
        
        // Get client IDs
        $clientIds = array_column($clients, 'id');
        
        log_message('debug', 'Client IDs: ' . json_encode($clientIds));
        
        // Get invoices for these clients
        $status = $this->request->getGet('status');
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        
        $invoices = $invoiceModel->where('organization_id', $user['organization_id'])
                               ->whereIn('client_id', $clientIds);
        
        if ($status) {
            $invoices->where('status', $status);
        }
        
        if ($dateStart) {
            $invoices->where('due_date >=', $dateStart);
        }
        
        if ($dateEnd) {
            $invoices->where('due_date <=', $dateEnd);
        }
        
        $invoices = $invoices->findAll();
        
        log_message('debug', 'Invoices found: ' . count($invoices));
        
        // Add client information to each invoice
        foreach ($invoices as &$invoice) {
            $client = $clientModel->find($invoice['client_id']);
            $invoice['client_name'] = $client['name'] ?? 'Unknown';
            $invoice['client_phone'] = $client['phone'] ?? '';
            $invoice['client_email'] = $client['email'] ?? '';
            $invoice['client_address'] = $client['address'] ?? '';
        }
        
        return $this->respond(['invoices' => $invoices]);
    }
    
    /**
     * Check if user can access a portfolio
     */
    private function canAccessPortfolio($portfolio, $user)
    {
        if ($user['role'] === 'superadmin' || $user['role'] === 'admin') {
            // Admins and superadmins can access any portfolio in their organization
            return $portfolio['organization_id'] == $user['organization_id'];
        } else {
            // For regular users, check if they are assigned to the portfolio
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($user['id']);
            
            foreach ($portfolios as $userPortfolio) {
                if ($userPortfolio['id'] == $portfolio['id']) {
                    return true;
                }
            }
            
            return false;
        }
    }
}
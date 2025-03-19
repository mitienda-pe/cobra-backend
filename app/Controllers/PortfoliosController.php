<?php

namespace App\Controllers;

use App\Models\PortfolioModel;
use App\Models\ClientModel;
use App\Models\UserModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PortfoliosController extends BaseController
{
    use OrganizationTrait;
    
    protected $auth;
    protected $session;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        log_message('debug', '====== PORTFOLIOS INDEX ======');
        
        // Refresh organization context from session
        $currentOrgId = $this->refreshOrganizationContext();
        
        $portfolioModel = new PortfolioModel();
        $auth = $this->auth;
        
        // Filter portfolios based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all portfolios or filter by organization
            if ($currentOrgId) {
                // Use the trait method to apply organization filter
                $this->applyOrganizationFilter($portfolioModel, $currentOrgId);
                $portfolios = $portfolioModel->findAll();
                
                // Verify filtering is working
                log_message('debug', 'SQL Query: ' . $portfolioModel->getLastQuery()->getQuery());
                log_message('debug', 'Superadmin fetched ' . count($portfolios) . ' portfolios for organization ' . $currentOrgId);
            } else {
                $portfolios = $portfolioModel->findAll();
                log_message('debug', 'Superadmin fetched all ' . count($portfolios) . ' portfolios (no org filter)');
            }
        } else if ($auth->hasRole('admin')) {
            // Admin can see all portfolios from their organization
            $adminOrgId = $auth->user()['organization_id']; // Always use admin's fixed organization
            $portfolios = $portfolioModel->where('organization_id', $adminOrgId)->findAll();
            log_message('debug', 'Admin fetched ' . count($portfolios) . ' portfolios for organization ' . $adminOrgId);
        } else {
            // Regular users can only see their assigned portfolios
            $portfolios = $portfolioModel->getByUser($auth->user()['id']);
            log_message('debug', 'User has ' . count($portfolios) . ' portfolios');
        }
        
        // If no portfolios found with role-based filtering, log this fact
        if (empty($portfolios)) {
            $allPortfolios = $portfolioModel->findAll();
            log_message('debug', 'No portfolios found with filtering. Total portfolios in database: ' . count($allPortfolios));
            
            // For debugging, log all available organizations
            $db = \Config\Database::connect();
            $orgs = $db->table('organizations')->get()->getResultArray();
            log_message('debug', 'Available organizations: ' . json_encode(array_column($orgs, 'id')));
        }
        
        // Get organization names for portfolios
        $organizationModel = new \App\Models\OrganizationModel();
        $organizationsById = [];
        foreach ($organizationModel->findAll() as $org) {
            $organizationsById[$org['id']] = $org;
        }
        
        // Initialize view data
        $data = [
            'portfolios' => $portfolios,
            'organizations' => $organizationsById,
        ];
        
        // Use the trait to prepare organization-related data for the view
        $data = $this->prepareOrganizationData($data);
        
        return view('portfolios/index', $data);
    }
    
    public function create()
    {
        // Only admins and superadmins can create portfolios
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear carteras.');
        }
        
        log_message('debug', 'CSRF token value: ' . csrf_hash());
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organizations for superadmin
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name'        => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status'      => 'required|in_list[active,inactive]',
            ];
            
            // Add organization_id rule for superadmins
            if ($this->auth->hasRole('superadmin')) {
                $rules['organization_id'] = 'required|is_natural_no_zero';
            }
            
            if ($this->validate($rules)) {
                $portfolioModel = new PortfolioModel();
                
                // Get organization_id based on role
                $organizationId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $this->auth->organizationId();
                
                // Prepare data
                $data = [
                    'organization_id' => $organizationId,
                    'name'            => $this->request->getPost('name'),
                    'description'     => $this->request->getPost('description'),
                    'status'          => $this->request->getPost('status'),
                ];
                
                $portfolioId = $portfolioModel->insert($data);
                
                if ($portfolioId) {
                    // Assign users if specified
                    $userIds = $this->request->getPost('user_ids') ?: [];
                    
                    if (!empty($userIds)) {
                        $portfolioModel->assignUsers($portfolioId, $userIds);
                    }
                    
                    // Assign clients if specified
                    $clientIds = $this->request->getPost('client_ids') ?: [];
                    
                    if (!empty($clientIds)) {
                        $portfolioModel->assignClients($portfolioId, $clientIds);
                    }
                    
                    return redirect()->to('/portfolios')->with('message', 'Cartera creada exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al crear la cartera.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // In create form, we don't prefill users and clients
        // They will be loaded dynamically when user selects an organization
        $organizationId = $this->auth->organizationId();
        log_message('info', 'Create portfolio - Current user organization ID: ' . $organizationId);
        
        // For non-superadmin users, load their organization's users and clients
        if (!$this->auth->hasRole('superadmin')) {
            $userModel = new UserModel();
            $users = $userModel->where('organization_id', $organizationId)
                             ->where('role !=', 'superadmin')
                             ->findAll();
            
            $clientModel = new ClientModel();
            $clients = $clientModel->where('organization_id', $organizationId)
                                 ->where('status', 'active')
                                 ->findAll();
            
            $data['users'] = $users;
            $data['clients'] = $clients;
            
            log_message('info', 'Non-superadmin: Found ' . count($users) . ' users and ' . count($clients) . ' clients');
        }
        
        return view('portfolios/create', $data);
    }
    
    public function edit($id = null)
    {
        // Only admins and superadmins can edit portfolios
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar carteras.');
        }
        
        if (!$id) {
            return redirect()->to('/portfolios')->with('error', 'ID de cartera no proporcionado.');
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->find($id);
        
        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }
        
        // Check if user has access to this portfolio
        if (!$this->hasAccessToPortfolio($portfolio)) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para editar esta cartera.');
        }
        
        $data = [
            'portfolio' => $portfolio,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name'        => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status'      => 'required|in_list[active,inactive]',
            ];
            
            if ($this->validate($rules)) {
                // Prepare data
                $data = [
                    'name'        => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status'      => $this->request->getPost('status'),
                ];
                
                $updated = $portfolioModel->update($id, $data);
                
                if ($updated) {
                    // Update user assignments
                    $userIds = $this->request->getPost('user_ids') ?: [];
                    $portfolioModel->assignUsers($id, $userIds);
                    
                    // Update client assignments
                    $clientIds = $this->request->getPost('client_ids') ?: [];
                    $portfolioModel->assignClients($id, $clientIds);
                    
                    return redirect()->to('/portfolios')->with('message', 'Cartera actualizada exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al actualizar la cartera.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // Get users for the dropdown
        $userModel = new UserModel();
        $organizationId = $this->auth->organizationId();
        log_message('info', 'Edit portfolio - Current user organization ID: ' . $organizationId);
        
        // Only get users from the same organization as the portfolio
        $users = $userModel->where('organization_id', $portfolio['organization_id'])
                          ->where('role !=', 'superadmin')
                          ->findAll();
        
        log_message('info', 'Edit portfolio - Found ' . count($users) . ' users for organization ' . $portfolio['organization_id']);
        
        // Get clients for the dropdown - only from the same organization as the portfolio
        $clientModel = new ClientModel();
        $clients = $clientModel->where('organization_id', $portfolio['organization_id'])
                              ->where('status', 'active')
                              ->findAll();
        
        log_message('info', 'Edit portfolio - Found ' . count($clients) . ' clients for organization ' . $portfolio['organization_id']);
        
        // Get assigned users and clients
        $assignedUsers = $portfolioModel->getAssignedUsers($id);
        $assignedClients = $portfolioModel->getAssignedClients($id);
        
        log_message('debug', 'Edit portfolio - Found ' . count($assignedUsers) . ' assigned users and ' . count($assignedClients) . ' assigned clients');
        
        $data['users'] = $users;
        $data['clients'] = $clients;
        $data['assignedUserIds'] = array_column($assignedUsers, 'id');
        $data['assignedClientIds'] = array_column($assignedClients, 'id');
        
        return view('portfolios/edit', $data);
    }
    
    public function delete($id = null)
    {
        // Only admins and superadmins can delete portfolios
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar carteras.');
        }
        
        if (!$id) {
            return redirect()->to('/portfolios')->with('error', 'ID de cartera no proporcionado.');
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->find($id);
        
        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }
        
        // Check if user has access to this portfolio
        if (!$this->hasAccessToPortfolio($portfolio)) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para eliminar esta cartera.');
        }
        
        // Delete related assignments first
        $db = \Config\Database::connect();
        $db->table('portfolio_user')->where('portfolio_id', $id)->delete();
        $db->table('client_portfolio')->where('portfolio_id', $id)->delete();
        
        $deleted = $portfolioModel->delete($id);
        
        if ($deleted) {
            return redirect()->to('/portfolios')->with('message', 'Cartera eliminada exitosamente.');
        } else {
            return redirect()->to('/portfolios')->with('error', 'Error al eliminar la cartera.');
        }
    }
    
    public function view($id = null)
    {
        if (!$id) {
            return redirect()->to('/portfolios')->with('error', 'ID de cartera no proporcionado.');
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->find($id);
        
        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }
        
        // Check if user has access to this portfolio
        if (!$this->hasAccessToPortfolio($portfolio)) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para ver esta cartera.');
        }
        
        // Get assigned users
        $assignedUsers = $portfolioModel->getAssignedUsers($id);
        
        // Get assigned clients
        $assignedClients = $portfolioModel->getAssignedClients($id);
        
        // Get client_id parameter if available
        $clientId = $this->request->getGet('client_id');
        
        // Log for debugging
        log_message('debug', 'PortfoliosController::view - Portfolio ID: ' . $id . ', Client ID filter: ' . ($clientId ?: 'none'));
        
        // Get invoices for this portfolio, filtered by client if specified
        $invoiceModel = new \App\Models\InvoiceModel();
        $invoices = $invoiceModel->getByPortfolio($id, null, $clientId);
        
        // Get client information for display
        $clientModel = new ClientModel();
        foreach ($invoices as &$invoice) {
            $client = $clientModel->find($invoice['client_id']);
            if ($client) {
                $invoice['client_name'] = $client['business_name'];
                $invoice['document_number'] = $client['document_number'];
            } else {
                $invoice['client_name'] = 'Cliente no encontrado';
                $invoice['document_number'] = '';
            }
        }
        
        $data = [
            'portfolio'      => $portfolio,
            'assignedUsers'  => $assignedUsers,
            'assignedClients' => $assignedClients,
            'invoices'       => $invoices,
            'auth'           => $this->auth,
        ];
        
        return view('portfolios/view', $data);
    }
    
    /**
     * Check if user has access to a portfolio
     */
    private function hasAccessToPortfolio($portfolio)
    {
        log_message('debug', 'hasAccessToPortfolio check - User role: ' . $this->auth->user()['role'] . ', Portfolio org: ' . $portfolio['organization_id'] . ', User org: ' . $this->auth->organizationId());
        
        // Superadmin can access any portfolio
        if ($this->auth->hasRole('superadmin')) {
            return true;
        }
        
        // Admin can access any portfolio in their organization
        if ($this->auth->hasRole('admin')) {
            return $portfolio['organization_id'] == $this->auth->organizationId();
        }
        
        // For regular users, check if they are assigned to the portfolio
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($this->auth->user()['id']);
        
        log_message('debug', 'User ' . $this->auth->user()['id'] . ' has ' . count($portfolios) . ' portfolios assigned');
        
        foreach ($portfolios as $userPortfolio) {
            if ($userPortfolio['id'] == $portfolio['id']) {
                return true;
            }
        }
        
        return false;
    }
}
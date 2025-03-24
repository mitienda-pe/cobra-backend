<?php

namespace App\Controllers;

use App\Models\PortfolioModel;
use App\Models\ClientModel;
use App\Models\UserModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PortfolioController extends BaseController
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
        
        // Check if admin has access to this portfolio's organization
        if ($this->auth->hasRole('admin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para editar esta cartera.');
        }
        
        $data = [
            'portfolio' => $portfolio,
            'auth' => $this->auth,
        ];
        
        // Get organizations for superadmin
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        // Get users and clients for the portfolio's organization
        $userModel = new UserModel();
        $users = $userModel->where('organization_id', $portfolio['organization_id'])
                         ->where('role !=', 'superadmin')
                         ->findAll();
        
        $clientModel = new ClientModel();
        $clients = $clientModel->where('organization_id', $portfolio['organization_id'])
                             ->where('status', 'active')
                             ->findAll();
        
        // Get currently assigned users and clients
        $assignedUsers = $portfolioModel->getAssignedUsers($id);
        $assignedClients = $portfolioModel->getAssignedClients($id);
        
        $data['users'] = $users;
        $data['clients'] = $clients;
        $data['assigned_user_ids'] = array_column($assignedUsers, 'id');
        $data['assigned_client_ids'] = array_column($assignedClients, 'id');
        
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
                // Get organization_id based on role
                $organizationId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $portfolio['organization_id']; // Non-superadmins can't change organization
                
                // Prepare data
                $updateData = [
                    'organization_id' => $organizationId,
                    'name'            => $this->request->getPost('name'),
                    'description'     => $this->request->getPost('description'),
                    'status'          => $this->request->getPost('status'),
                ];
                
                $updated = $portfolioModel->update($id, $updateData);
                
                if ($updated) {
                    // Update user assignments
                    $userIds = $this->request->getPost('user_ids') ?: [];
                    $portfolioModel->updateUsers($id, $userIds);
                    
                    // Update client assignments
                    $clientIds = $this->request->getPost('client_ids') ?: [];
                    $portfolioModel->updateClients($id, $clientIds);
                    
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
        
        // Check if admin has access to this portfolio's organization
        if ($this->auth->hasRole('admin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para eliminar esta cartera.');
        }
        
        // Delete portfolio and its relationships
        try {
            $portfolioModel->deletePortfolio($id);
            return redirect()->to('/portfolios')->with('message', 'Cartera eliminada exitosamente.');
        } catch (\Exception $e) {
            return redirect()->to('/portfolios')->with('error', 'Error al eliminar la cartera: ' . $e->getMessage());
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
        
        // Check access based on role
        if (!$this->hasAccessToPortfolio($portfolio)) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para ver esta cartera.');
        }
        
        // Get assigned users and clients
        $assignedUsers = $portfolioModel->getAssignedUsers($id);
        $assignedClients = $portfolioModel->getAssignedClients($id);
        
        // Get organization information
        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($portfolio['organization_id']);
        
        $data = [
            'portfolio' => $portfolio,
            'organization' => $organization,
            'users' => $assignedUsers,
            'assignedClients' => $assignedClients,
            'auth' => $this->auth,
            'request' => $this->request
        ];
        
        return view('portfolios/view', $data);
    }
    
    private function hasAccessToPortfolio($portfolio)
    {
        $auth = $this->auth;
        
        // Superadmin can access all portfolios
        if ($auth->hasRole('superadmin')) {
            return true;
        }
        
        // Admin can access portfolios from their organization
        if ($auth->hasRole('admin')) {
            return $portfolio['organization_id'] === $auth->organizationId();
        }
        
        // Regular users can only access their assigned portfolios
        $portfolioModel = new PortfolioModel();
        $userPortfolios = $portfolioModel->getByUser($auth->user()['id']);
        
        foreach ($userPortfolios as $userPortfolio) {
            if ($userPortfolio['id'] === $portfolio['id']) {
                return true;
            }
        }
        
        return false;
    }
}

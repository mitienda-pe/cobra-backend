<?php

namespace App\Controllers;

use App\Models\PortfolioModel;
use App\Models\ClientModel;
use App\Models\UserModel;
use App\Models\OrganizationModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PortfolioController extends BaseController
{
    use OrganizationTrait;
    
    protected $auth;
    protected $session;
    protected $db;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        $this->db = \Config\Database::connect();
        helper(['form', 'url', 'uuid']);
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
            $orgs = $this->db->table('organizations')->get()->getResultArray();
            log_message('debug', 'Available organizations: ' . json_encode(array_column($orgs, 'id')));
        }
        
        // Get organization names for portfolios
        $organizationModel = new OrganizationModel();
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
        $data = [
            'auth' => $this->auth
        ];
        $portfolioModel = new PortfolioModel();

        // If superadmin and no organization context, load organizations
        if ($this->auth->hasRole('superadmin') && !$this->auth->organizationId()) {
            $organizationModel = new OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }

        // If organization is already selected (either by context or POST)
        $organizationId = $this->auth->organizationId() ?? $this->request->getPost('organization_id');
        if ($organizationId) {
            // Get available users for this organization
            $data['users'] = $portfolioModel->getAvailableUsers($organizationId);
            $data['clients'] = $portfolioModel->getAvailableClients($organizationId);
        }

        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'status' => 'required|in_list[active,inactive]',
                'user_id' => 'required|is_not_unique[users.uuid]',
                'organization_id' => 'required|is_not_unique[organizations.id]'
            ];

            if ($this->validate($rules)) {
                helper('uuid');
                $uuid = generate_uuid();
                
                $portfolioData = [
                    'uuid' => $uuid,
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                    'organization_id' => $this->request->getPost('organization_id')
                ];

                if ($portfolioModel->insert($portfolioData)) {
                    // Insert user assignment
                    $user_id = $this->request->getPost('user_id');
                    if ($user_id) {
                        $this->db->table('portfolio_user')->insert([
                            'portfolio_uuid' => $uuid,
                            'user_uuid' => $user_id
                        ]);
                    }

                    // Insert client assignments
                    $client_ids = $this->request->getPost('client_ids') ?? [];
                    foreach ($client_ids as $client_id) {
                        $this->db->table('client_portfolio')->insert([
                            'portfolio_uuid' => $uuid,
                            'client_uuid' => $client_id
                        ]);
                    }

                    return redirect()->to('/portfolios')->with('success', 'Cartera creada exitosamente');
                }

                return redirect()->back()->with('error', 'Error al crear la cartera');
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        return view('portfolios/create', $data);
    }

    public function view($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/portfolios')->with('error', 'UUID de cartera no proporcionado.');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }

        // Verificar permisos de organización
        if (!$this->auth->hasRole('superadmin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para ver esta cartera.');
        }

        // Obtener usuarios y clientes asignados
        $assignedUsers = $portfolioModel->getAssignedUsers($portfolio['uuid']);
        $assignedClients = $portfolioModel->getAssignedClients($portfolio['uuid']);

        $data = [
            'portfolio' => $portfolio,
            'assignedUsers' => $assignedUsers,
            'assignedClients' => $assignedClients,
            'auth' => $this->auth,
            'request' => $this->request
        ];

        return view('portfolios/view', $data);
    }
    
    public function edit($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/portfolios');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada');
        }

        // Get assigned users
        $assigned_users = $this->db->table('portfolio_user pu')
            ->select('u.*')
            ->join('users u', 'u.uuid = pu.user_uuid')
            ->where('pu.portfolio_uuid', $uuid)
            ->where('u.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        // Get assigned clients
        $assigned_clients = $this->db->table('client_portfolio cp')
            ->select('c.uuid, c.business_name, c.document_number')
            ->join('clients c', 'c.uuid = cp.client_uuid')
            ->where('cp.portfolio_uuid', $uuid)
            ->where('c.deleted_at IS NULL')
            ->get()
            ->getResultArray();

        // Get all available users for this organization plus currently assigned users
        $users = $portfolioModel->getAvailableUsers($portfolio['organization_id']);
        foreach ($assigned_users as $user) {
            if (!in_array($user, $users)) {
                $users[] = $user;
            }
        }

        // Get all available clients for this organization plus currently assigned clients
        $clients = $portfolioModel->getAvailableClients($portfolio['organization_id']);
        foreach ($assigned_clients as $client) {
            if (!in_array($client, $clients)) {
                $clients[] = $client;
            }
        }

        // Get assigned IDs for easy checking in the view
        $assigned_user_ids = array_column($assigned_users, 'uuid');
        $assigned_client_ids = array_column($assigned_clients, 'uuid');

        $data = [
            'auth' => $this->auth,
            'portfolio' => $portfolio,
            'users' => $users,
            'clients' => $clients,
            'assigned_user_ids' => $assigned_user_ids,
            'assigned_client_ids' => $assigned_client_ids
        ];

        if ($this->request->getMethod() === 'post') {
            // Handle form submission...
            $updateData = [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'status' => $this->request->getPost('status')
            ];

            if ($portfolioModel->update($portfolio['id'], $updateData)) {
                // Update user assignment (single user)
                $user_id = $this->request->getPost('user_id');
                $this->db->table('portfolio_user')->where('portfolio_uuid', $uuid)->delete();
                if ($user_id) {
                    $this->db->table('portfolio_user')->insert([
                        'portfolio_uuid' => $uuid,
                        'user_uuid' => $user_id
                    ]);
                }

                // Update client assignments (multiple clients)
                $client_ids = $this->request->getPost('client_ids') ?? [];
                $this->db->table('client_portfolio')->where('portfolio_uuid', $uuid)->delete();
                foreach ($client_ids as $client_id) {
                    $this->db->table('client_portfolio')->insert([
                        'portfolio_uuid' => $uuid,
                        'client_uuid' => $client_id
                    ]);
                }

                return redirect()->to('/portfolios')->with('success', 'Cartera actualizada exitosamente');
            }

            return redirect()->back()->with('error', 'Error al actualizar la cartera');
        }

        return view('portfolios/edit', $data);
    }

    public function delete($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/portfolios')->with('error', 'UUID de cartera no proporcionado.');
        }

        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para eliminar carteras.');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }

        // Verificar permisos de organización
        if (!$this->auth->hasRole('superadmin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para eliminar esta cartera.');
        }

        if ($portfolioModel->delete($portfolio['id'])) {
            return redirect()->to('/portfolios')->with('message', 'Cartera eliminada exitosamente.');
        }

        return redirect()->to('/portfolios')->with('error', 'Error al eliminar la cartera.');
    }
    
    public function getOrganizationUsers($uuid = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        if (!$uuid) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Se requiere el UUID de la organización'
            ]);
        }

        // Obtener la organización por UUID
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->where('uuid', $uuid)->first();
        if (!$organization) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Organización no encontrada'
            ]);
        }

        $portfolioModel = new PortfolioModel();
        $users = $portfolioModel->getAvailableUsers($organization['id']);

        return $this->response->setJSON(['users' => $users]);
    }

    public function getOrganizationClients($uuid = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        if (!$uuid) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Se requiere el UUID de la organización'
            ]);
        }

        // Obtener la organización por UUID
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->where('uuid', $uuid)->first();
        if (!$organization) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Organización no encontrada'
            ]);
        }

        $portfolioModel = new PortfolioModel();
        $clients = $portfolioModel->getAvailableClients($organization['id']);

        return $this->response->setJSON(['clients' => $clients]);
    }
}

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
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear carteras.');
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status' => 'required|in_list[active,inactive]',
            ];
            
            if ($this->auth->hasRole('superadmin')) {
                $rules['organization_id'] = 'required|is_natural_no_zero';
            }
            
            if ($this->validate($rules)) {
                $portfolioModel = new PortfolioModel();
                
                $organizationId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $this->auth->organizationId();
                
                helper('uuid');
                $uuid = substr(generate_uuid(), 0, 8);
                
                $data = [
                    'uuid' => $uuid,
                    'organization_id' => $organizationId,
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                ];
                
                $portfolioId = $portfolioModel->insert($data);
                
                if ($portfolioId) {
                    $userIds = $this->request->getPost('user_ids') ?: [];
                    if (!empty($userIds)) {
                        $portfolioModel->assignUsers($portfolioId, $userIds);
                    }
                    
                    $clientIds = $this->request->getPost('client_ids') ?: [];
                    if (!empty($clientIds)) {
                        $portfolioModel->assignClients($portfolioId, $clientIds);
                    }
                    
                    return redirect()->to('/portfolios')->with('message', 'Cartera creada exitosamente.');
                }
                
                return redirect()->back()->withInput()->with('error', 'Error al crear la cartera.');
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
            return redirect()->to('/portfolios')->with('error', 'UUID de cartera no proporcionado.');
        }

        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para editar carteras.');
        }

        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->where('uuid', $uuid)->first();

        if (!$portfolio) {
            return redirect()->to('/portfolios')->with('error', 'Cartera no encontrada.');
        }

        // Verificar permisos de organización
        if (!$this->auth->hasRole('superadmin') && $portfolio['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/portfolios')->with('error', 'No tiene permisos para editar esta cartera.');
        }

        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status' => 'required|in_list[active,inactive]'
            ];

            if ($this->validate($rules)) {
                $data = [
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                if ($portfolioModel->update($portfolio['id'], $data)) {
                    // Actualizar usuarios asignados
                    $userUuids = $this->request->getPost('user_ids') ?: [];
                    $portfolioModel->assignUsers($uuid, $userUuids);

                    // Actualizar clientes asignados
                    $clientUuids = $this->request->getPost('client_ids') ?: [];
                    $portfolioModel->assignClients($uuid, $clientUuids);

                    return redirect()->to('/portfolios')->with('message', 'Cartera actualizada exitosamente.');
                }

                return redirect()->back()->withInput()->with('error', 'Error al actualizar la cartera.');
            }

            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Obtener usuarios y clientes asignados
        $assignedUsers = $portfolioModel->getAssignedUsers($uuid);
        $assignedUserIds = array_column($assignedUsers, 'uuid');

        $assignedClients = $portfolioModel->getAssignedClients($uuid);
        $assignedClientIds = array_column($assignedClients, 'uuid');

        // Obtener todos los usuarios y clientes disponibles
        $userModel = new UserModel();
        $clientModel = new ClientModel();

        if ($this->auth->hasRole('superadmin')) {
            $users = $userModel->findAll();
            $clients = $clientModel->findAll();
        } else {
            $users = $userModel->where('organization_id', $this->auth->organizationId())->findAll();
            $clients = $clientModel->where('organization_id', $this->auth->organizationId())->findAll();
        }

        $data = [
            'portfolio' => $portfolio,
            'users' => $users,
            'clients' => $clients,
            'assigned_user_ids' => $assignedUserIds,
            'assigned_client_ids' => $assignedClientIds,
            'auth' => $this->auth
        ];

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
    
    /**
     * Get users by organization
     */
    public function getUsersByOrganization($uuid)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->where('uuid', $uuid)->first();
        
        if (!$organization) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Organization not found']);
        }

        $userModel = new UserModel();
        $users = $userModel->where('organization_id', $organization['id'])
                          ->where('role !=', 'superadmin')
                          ->where('status', 'active')
                          ->findAll();

        return $this->response->setJSON(['users' => $users]);
    }

    /**
     * Get clients by organization
     */
    public function getClientsByOrganization($uuid)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->where('uuid', $uuid)->first();
        
        if (!$organization) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Organization not found']);
        }

        $clientModel = new ClientModel();
        $clients = $clientModel->where('organization_id', $organization['id'])
                             ->where('status', 'active')
                             ->findAll();

        return $this->response->setJSON(['clients' => $clients]);
    }
}

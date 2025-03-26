<?php

namespace App\Controllers;

use App\Models\OrganizationModel;
use App\Models\UserModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;

class OrganizationController extends BaseController
{
    protected $organizationModel;
    protected $userModel;
    protected $clientModel;
    protected $portfolioModel;
    protected $auth;
    protected $session;
    
    public function __construct()
    {
        $this->organizationModel = new OrganizationModel();
        $this->userModel = new UserModel();
        $this->clientModel = new ClientModel();
        $this->portfolioModel = new PortfolioModel();
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        // Only superadmins can view all organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta sección.');
        }
        
        return view('organizations/index', [
            'title' => 'Organizations',
            'organizations' => $this->organizationModel->findAll(),
            'auth' => $this->auth,
        ]);
    }
    
    public function create()
    {
        // Only superadmins can create organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear organizaciones.');
        }
        
        return view('organizations/create', [
            'title' => 'Create Organization',
            'auth' => $this->auth,
        ]);
    }
    
    public function store()
    {
        // Only superadmins can create organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear organizaciones.');
        }
        
        $postData = $this->request->getPost();
        log_message('debug', 'Method: ' . $this->request->getMethod());
        log_message('debug', 'POST data received in store: ' . json_encode($postData));
        
        if (!$this->validate([
            'name' => 'required|min_length[3]',
            'code' => 'required|min_length[2]|is_unique[organizations.code]',
            'status' => 'required|in_list[active,inactive]'
        ])) {
            log_message('debug', 'Validation errors: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->organizationModel->insert([
            'name' => $this->request->getPost('name'),
            'code' => $this->request->getPost('code'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ]);
        
        log_message('debug', 'Insert result: ' . json_encode($result));

        if (!$result) {
            log_message('error', 'Database error: ' . json_encode($this->organizationModel->errors()));
            return redirect()->back()->withInput()->with('error', 'Error al crear la organización');
        }

        return redirect()->to('/organizations')->with('message', 'Organization created successfully');
    }
    
    public function edit($uuid)
    {
        $organization = $this->organizationModel->where('uuid', $uuid)->first();
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        // Only superadmin can edit organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/organizations')->with('error', 'No tiene permisos para editar organizaciones.');
        }
        
        $data = [
            'organization' => $organization,
            'auth' => $this->auth
        ];
        
        return view('organizations/edit', $data);
    }
    
    public function update($uuid)
    {
        // Only superadmin can update organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/organizations')->with('error', 'No tiene permisos para actualizar organizaciones.');
        }
        
        // Log the UUID we're trying to update
        log_message('debug', 'Attempting to update organization with UUID: ' . $uuid);
        
        // Get current POST data for logging
        $postData = $this->request->getPost();
        log_message('debug', 'Update request for organization with data: ' . json_encode($postData));
        
        // Check if organization exists
        $organization = $this->organizationModel->where('uuid', $uuid)->first();
        
        if (!$organization) {
            log_message('error', 'Organization not found with UUID: ' . $uuid);
            log_message('error', 'Last query: ' . $this->organizationModel->getLastQuery());
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        log_message('debug', 'Found organization: ' . json_encode($organization));
        
        $rules = [
            'name' => 'required|min_length[3]',
            'status' => 'required|in_list[active,inactive]'
        ];
        
        if (!$this->validate($rules)) {
            log_message('error', 'Validation errors: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        
        $data = [
            'name' => $postData['name'],
            'description' => $postData['description'] ?? null,
            'status' => $postData['status']
        ];
        
        try {
            $builder = $this->organizationModel->builder();
            $updated = $builder->where('uuid', $uuid)
                             ->update($data);
            
            if ($updated === false) {
                log_message('error', 'Update failed for organization UUID: ' . $uuid . '. Errors: ' . json_encode($this->organizationModel->errors()));
                log_message('error', 'Last query: ' . $this->organizationModel->getLastQuery());
                return redirect()->back()->withInput()->with('error', 'Error al actualizar la organización: ' . implode(', ', $this->organizationModel->errors()));
            }
            
            log_message('debug', 'Organization updated successfully');
            return redirect()->to('/organizations/' . $uuid)->with('message', 'Organización actualizada exitosamente');
            
        } catch (\Exception $e) {
            log_message('error', 'Exception updating organization: ' . $e->getMessage());
            log_message('error', 'Stack trace: ' . $e->getTraceAsString());
            return redirect()->back()->withInput()->with('error', 'Error al actualizar la organización: ' . $e->getMessage());
        }
    }
    
    public function delete($uuid)
    {
        // Only superadmin can delete organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/organizations')->with('error', 'No tiene permisos para eliminar organizaciones.');
        }
        
        $organization = $this->organizationModel->where('uuid', $uuid)->first();
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        try {
            $result = $this->organizationModel->delete($organization['id']);
            
            if ($result === false) {
                return redirect()->back()->with('error', 'Error al eliminar la organización: ' . implode(', ', $this->organizationModel->errors()));
            }
            
            return redirect()->to('/organizations')->with('message', 'Organización eliminada exitosamente');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar la organización: ' . $e->getMessage());
        }
    }
    
    public function view($uuid)
    {
        $organization = $this->organizationModel->where('uuid', $uuid)->first();
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        // Only superadmin can view any organization
        // Admins can only view their own organization
        if (!$this->auth->hasRole('superadmin') && $organization['id'] != $this->auth->organizationId()) {
            return redirect()->to('/organizations')->with('error', 'No tiene permisos para ver esta organización.');
        }
        
        // Get users from this organization
        $users = $this->userModel->where('organization_id', $organization['id'])->findAll();
        
        // Get clients from this organization
        $clients = $this->clientModel->where('organization_id', $organization['id'])->findAll();
        
        // Get portfolios from this organization
        $portfolios = $this->portfolioModel->where('organization_id', $organization['id'])->findAll();
        
        $data = [
            'organization' => $organization,
            'users' => $users,
            'clients' => $clients,
            'portfolios' => $portfolios,
            'auth' => $this->auth
        ];
        
        return view('organizations/view', $data);
    }
}

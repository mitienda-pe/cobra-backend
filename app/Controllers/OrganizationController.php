<?php

namespace App\Controllers;

use App\Models\OrganizationModel;
use App\Libraries\Auth;

class OrganizationController extends BaseController
{
    protected $organizationModel;
    protected $auth;
    protected $session;
    
    public function __construct()
    {
        $this->organizationModel = new OrganizationModel();
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
    
    public function edit($id)
    {
        // Only superadmins can edit organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar organizaciones.');
        }
        
        $organization = $this->organizationModel->select($this->organizationModel->selectedFields)->find($id);
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organization not found');
        }

        log_message('debug', 'Organization data for edit: ' . json_encode($organization));

        return view('organizations/edit', [
            'title' => 'Edit Organization',
            'organization' => $organization,
            'auth' => $this->auth,
        ]);
    }
    
    public function update($id)
    {
        // Only superadmins can edit organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar organizaciones.');
        }
        
        $postData = $this->request->getPost();
        log_message('debug', 'Method: ' . $this->request->getMethod());
        log_message('debug', 'POST/PUT data received in update: ' . json_encode($postData));
        
        $organization = $this->organizationModel->find($id);
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organization not found');
        }

        if (!$this->validate([
            'name' => 'required|min_length[3]',
            'status' => 'required|in_list[active,inactive]'
        ])) {
            log_message('debug', 'Validation errors in update: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $result = $this->organizationModel->update($id, [
            'name' => $this->request->getPost('name'),
            'status' => $this->request->getPost('status'),
            'description' => $this->request->getPost('description')
        ]);
        
        log_message('debug', 'Update result: ' . json_encode($result));

        if (!$result) {
            log_message('error', 'Database error in update: ' . json_encode($this->organizationModel->errors()));
            return redirect()->back()->withInput()->with('error', 'Error al actualizar la organización');
        }

        return redirect()->to('/organizations')->with('message', 'Organization updated successfully');
    }
    
    public function delete($id)
    {
        // Only superadmins can delete organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar organizaciones.');
        }
        
        $organization = $this->organizationModel->find($id);
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organization not found');
        }

        // Check if organization has any related data
        // This is a simplified check, you might want to add more tables as needed
        $db = \Config\Database::connect();
        $clientCount = $db->table('clients')->where('organization_id', $id)->countAllResults();
        $userCount = $db->table('users')->where('organization_id', $id)->countAllResults();
        $portfolioCount = $db->table('portfolios')->where('organization_id', $id)->countAllResults();
        
        if ($clientCount > 0 || $userCount > 0 || $portfolioCount > 0) {
            return redirect()->to('/organizations')->with('error', 'No se puede eliminar esta organización porque tiene datos asociados.');
        }
        
        $this->organizationModel->delete($id);

        return redirect()->to('/organizations')->with('message', 'Organization deleted successfully');
    }
    
    public function view($id)
    {
        // Only superadmins can view organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para ver organizaciones.');
        }
        
        $organization = $this->organizationModel->find($id);
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        return view('organizations/view', [
            'title' => 'Ver Organización',
            'organization' => $organization,
            'auth' => $this->auth,
        ]);
    }
}

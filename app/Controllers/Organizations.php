<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\OrganizationModel;
use App\Models\UserModel;

class Organizations extends BaseController
{
    protected $auth;
    
    public function __construct()
    {
        $this->auth = new Auth();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        $organizationModel = new OrganizationModel();
        
        // Only superadmin can see all organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        $data = [
            'organizations' => $organizationModel->findAll(),
        ];
        
        return view('organizations/index', $data);
    }
    
    public function create()
    {
        // Only superadmin can create organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status' => 'required|in_list[active,inactive]',
            ];
            
            if ($this->validate($rules)) {
                $organizationModel = new OrganizationModel();
                
                $data = [
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                ];
                
                $organizationModel->insert($data);
                
                return redirect()->to('/organizations')->with('message', 'Organización creada exitosamente.');
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('organizations/create');
    }
    
    public function edit($id = null)
    {
        // Only superadmin can edit organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        if (!$id) {
            return redirect()->to('/organizations')->with('error', 'ID de organización no proporcionado.');
        }
        
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->find($id);
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        $data = [
            'organization' => $organization,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'description' => 'permit_empty',
                'status' => 'required|in_list[active,inactive]',
            ];
            
            if ($this->validate($rules)) {
                $data = [
                    'name' => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status' => $this->request->getPost('status'),
                ];
                
                $organizationModel->update($id, $data);
                
                return redirect()->to('/organizations')->with('message', 'Organización actualizada exitosamente.');
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('organizations/edit', $data);
    }
    
    public function delete($id = null)
    {
        // Only superadmin can delete organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        if (!$id) {
            return redirect()->to('/organizations')->with('error', 'ID de organización no proporcionado.');
        }
        
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->find($id);
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        // Check if there are users associated with this organization
        $userModel = new UserModel();
        $usersCount = $userModel->where('organization_id', $id)->countAllResults();
        
        if ($usersCount > 0) {
            return redirect()->to('/organizations')->with('error', 'No se puede eliminar la organización porque tiene usuarios asociados.');
        }
        
        $organizationModel->delete($id);
        
        return redirect()->to('/organizations')->with('message', 'Organización eliminada exitosamente.');
    }
    
    public function view($id = null)
    {
        // Only superadmin can view organization details
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        if (!$id) {
            return redirect()->to('/organizations')->with('error', 'ID de organización no proporcionado.');
        }
        
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->find($id);
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        // Get users associated with this organization
        $userModel = new UserModel();
        $users = $userModel->where('organization_id', $id)->findAll();
        
        $data = [
            'organization' => $organization,
            'users' => $users,
        ];
        
        return view('organizations/view', $data);
    }
}
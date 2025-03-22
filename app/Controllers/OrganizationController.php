<?php

namespace App\Controllers;

use App\Models\OrganizationModel;
use App\Libraries\Auth;

class OrganizationController extends BaseController
{
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
        // Only superadmins can view all organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta sección.');
        }
        
        $organizationModel = new OrganizationModel();
        $data = [
            'organizations' => $organizationModel->findAll(),
            'auth' => $this->auth,
        ];
        
        return view('organizations/index', $data);
    }
    
    public function create()
    {
        // Only superadmins can create organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear organizaciones.');
        }
        
        $data = [
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
                $organizationModel = new OrganizationModel();
                
                // Prepare data
                $data = [
                    'name'        => $this->request->getPost('name'),
                    'description' => $this->request->getPost('description'),
                    'status'      => $this->request->getPost('status'),
                ];
                
                $organizationId = $organizationModel->insert($data);
                
                if ($organizationId) {
                    return redirect()->to('/organizations')->with('message', 'Organización creada exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al crear la organización.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('organizations/create', $data);
    }
    
    public function edit($id = null)
    {
        // Only superadmins can edit organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar organizaciones.');
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
                
                $updated = $organizationModel->update($id, $data);
                
                if ($updated) {
                    return redirect()->to('/organizations')->with('message', 'Organización actualizada exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al actualizar la organización.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('organizations/edit', $data);
    }
    
    public function delete($id = null)
    {
        // Only superadmins can delete organizations
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar organizaciones.');
        }
        
        if (!$id) {
            return redirect()->to('/organizations')->with('error', 'ID de organización no proporcionado.');
        }
        
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->find($id);
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
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
        
        $deleted = $organizationModel->delete($id);
        
        if ($deleted) {
            return redirect()->to('/organizations')->with('message', 'Organización eliminada exitosamente.');
        } else {
            return redirect()->to('/organizations')->with('error', 'Error al eliminar la organización.');
        }
    }
    
    public function view($id = null)
    {
        // Only superadmins can view organization details
        if (!$this->auth->hasRole('superadmin')) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para ver detalles de organizaciones.');
        }
        
        if (!$id) {
            return redirect()->to('/organizations')->with('error', 'ID de organización no proporcionado.');
        }
        
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->find($id);
        
        if (!$organization) {
            return redirect()->to('/organizations')->with('error', 'Organización no encontrada.');
        }
        
        // Get stats about the organization
        $db = \Config\Database::connect();
        $clientCount = $db->table('clients')->where('organization_id', $id)->countAllResults();
        $userCount = $db->table('users')->where('organization_id', $id)->countAllResults();
        $portfolioCount = $db->table('portfolios')->where('organization_id', $id)->countAllResults();
        $invoiceCount = $db->table('invoices')
                          ->join('clients', 'clients.id = invoices.client_id')
                          ->where('clients.organization_id', $id)
                          ->countAllResults();
        
        $data = [
            'organization' => $organization,
            'stats' => [
                'clients' => $clientCount,
                'users' => $userCount,
                'portfolios' => $portfolioCount,
                'invoices' => $invoiceCount,
            ],
            'auth' => $this->auth,
        ];
        
        return view('organizations/view', $data);
    }
}

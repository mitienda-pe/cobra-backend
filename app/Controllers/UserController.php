<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\UserModel;
use App\Models\OrganizationModel;

class UserController extends BaseController
{
    protected $auth;
    protected $session;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        helper(['form', 'url']);
        
        // Debug logs
        log_message('debug', 'UserController initialized');
    }
    
    public function index()
    {
        $userModel = new UserModel();
        $organizationModel = new OrganizationModel();
        $auth = $this->auth;
        $db = \Config\Database::connect();
        
        // Get sorting parameters
        $sort = $this->request->getGet('sort') ?? 'id';
        $order = $this->request->getGet('order') ?? 'asc';
        $filterOrgId = $this->request->getGet('organization_id') ?? '';
        
        // Filter users based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all users with organization name
            $builder = $db->table('users');
            $builder->select('users.*, organizations.name as organization_name');
            $builder->join('organizations', 'organizations.id = users.organization_id', 'left');
            $builder->where('users.deleted_at IS NULL');
            
            // Apply organization filter if selected
            if (!empty($filterOrgId)) {
                $builder->where('users.organization_id', $filterOrgId);
            }
            
            // Apply sorting
            if ($sort == 'organization') {
                $builder->orderBy('organizations.name', $order);
            } else {
                $builder->orderBy("users.$sort", $order);
            }
            
            $users = $builder->get()->getResultArray();
            
            // Get all organizations for the filter dropdown
            $organizations = $organizationModel->findAll();
        } elseif ($auth->hasRole('admin')) {
            // Admin can only see users from their organization
            $organizationId = $auth->organizationId();
            
            $builder = $db->table('users');
            $builder->select('users.*, organizations.name as organization_name');
            $builder->join('organizations', 'organizations.id = users.organization_id', 'left');
            $builder->where('users.deleted_at IS NULL');
            $builder->where('users.organization_id', $organizationId);
            $builder->orderBy("users.$sort", $order);
            
            $users = $builder->get()->getResultArray();
            
            $organizations = []; // Admin doesn't need the organization filter
        } else {
            // Regular users shouldn't access this page, but just in case
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        $data = [
            'users' => $users,
            'auth' => $this->auth,
            'organizations' => $organizations,
            'currentSort' => $sort,
            'currentOrder' => $order,
            'filterOrgId' => $filterOrgId,
        ];
        
        return view('users/index', $data);
    }
    
    public function create()
    {
        $organizationModel = new OrganizationModel();
        
        return view('users/create', [
            'title' => 'Create User',
            'organizations' => $organizationModel->findAll(),
            'auth' => $this->auth,
        ]);
    }

    public function store()
    {
        $userModel = new UserModel();
        
        // Validate form
        if (!$this->validate([
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'phone' => 'required|min_length[10]',
            'role' => 'required|in_list[superadmin,admin,user]',
            'organization_id' => 'permit_empty|numeric',
            'password' => 'required|min_length[6]',
            'password_confirm' => 'required|matches[password]'
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        // Hash password
        $password = password_hash($this->request->getPost('password'), PASSWORD_DEFAULT);

        // Create user
        $result = $userModel->insert([
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'phone' => $this->request->getPost('phone'),
            'role' => $this->request->getPost('role'),
            'organization_id' => $this->request->getPost('organization_id') ?: null,
            'password' => $password,
            'status' => 'active'
        ]);

        if (!$result) {
            return redirect()->back()->withInput()->with('error', 'Error creating user');
        }

        return redirect()->to('/users')->with('message', 'User created successfully');
    }
    
    public function edit($uuid)
    {
        $userModel = new UserModel();
        $user = $userModel->where('uuid', $uuid)->first();
        
        if (!$user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && $user['organization_id'] != $this->auth->user()['organization_id']) {
            return redirect()->to('/users')->with('error', 'No tiene permisos para editar este usuario');
        }
        
        // Get organizations for dropdown (only for superadmin)
        $organizationModel = new OrganizationModel();
        $organizations = [];
        
        if ($this->auth->hasRole('superadmin')) {
            $organizations = $organizationModel->findAll();
        } else {
            $organizations = [$organizationModel->find($this->auth->organizationId())];
        }
        
        $data = [
            'user' => $user,
            'organizations' => $organizations,
            'auth' => $this->auth
        ];
        
        return view('users/edit', $data);
    }
    
    public function update($uuid)
    {
        $userModel = new UserModel();
        $user = $userModel->where('uuid', $uuid)->first();
        
        if (!$user) {
            log_message('error', 'User not found with UUID: ' . $uuid);
            return redirect()->to('/users')->with('error', 'Usuario no encontrado');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && $user['organization_id'] != $this->auth->user()['organization_id']) {
            return redirect()->to('/users')->with('error', 'No tiene permisos para actualizar este usuario');
        }
        
        // Get current POST data for logging
        $postData = $this->request->getPost();
        log_message('debug', 'Update request for user ' . $uuid . ' with data: ' . json_encode($postData));
        
        // Get validation rules for update
        $rules = $userModel->getValidationRulesForUpdate($user['id']);
        
        if (!$this->validate($rules)) {
            log_message('error', 'Validation errors: ' . json_encode($this->validator->getErrors()));
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        
        $data = [
            'name' => $postData['name'],
            'email' => $postData['email'],
            'phone' => $postData['phone'] ?? null,
            'role' => $postData['role'],
            'status' => $postData['status']
        ];
        
        // Only update organization_id if user is superadmin
        if ($this->auth->hasRole('superadmin') && isset($postData['organization_id'])) {
            $data['organization_id'] = $postData['organization_id'];
        }
        
        // Only update password if provided
        if (!empty($postData['password'])) {
            $data['password'] = password_hash($postData['password'], PASSWORD_DEFAULT);
        }
        
        try {
            $builder = $userModel->builder();
            $updated = $builder->where('uuid', $uuid)
                             ->update($data);
            
            if ($updated === false) {
                log_message('error', 'Update failed for user UUID: ' . $uuid . '. Errors: ' . json_encode($userModel->errors()));
                return redirect()->back()->withInput()->with('error', 'Error al actualizar el usuario: ' . implode(', ', $userModel->errors()));
            }
            
            return redirect()->to('/users/' . $uuid)->with('message', 'Usuario actualizado exitosamente');
            
        } catch (\Exception $e) {
            log_message('error', 'Exception updating user: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Error al actualizar el usuario: ' . $e->getMessage());
        }
    }
    
    public function view($uuid)
    {
        $userModel = new UserModel();
        $user = $userModel->where('uuid', $uuid)->first();
        
        if (!$user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && $user['organization_id'] != $this->auth->user()['organization_id']) {
            return redirect()->to('/users')->with('error', 'No tiene permisos para ver este usuario.');
        }
        
        // Get organization info
        $organizationModel = new OrganizationModel();
        $organization = $organizationModel->find($user['organization_id']);
        
        // Get portfolios assigned to this user
        $portfolioModel = new \App\Models\PortfolioModel();
        $portfolios = $portfolioModel->getByUser($user['id']);
        
        $data = [
            'user' => $user,
            'organization' => $organization,
            'portfolios' => $portfolios,
            'auth' => $this->auth
        ];
        
        return view('users/view', $data);
    }
    
    public function delete($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/users')->with('error', 'UUID de usuario no proporcionado.');
        }
        
        $userModel = new UserModel();
        $user = $userModel->where('uuid', $uuid)->first();
        
        if (!$user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && 
            ($user['organization_id'] != $this->auth->organizationId() || $user['role'] === 'superadmin')) {
            return redirect()->to('/users')->with('error', 'No tiene permisos para eliminar este usuario.');
        }
        
        // Don't allow users to delete themselves
        if ($user['id'] === $this->auth->user()['id']) {
            return redirect()->to('/users')->with('error', 'No puede eliminar su propio usuario.');
        }
        
        $userModel->delete($user['id']);
        
        return redirect()->to('/users')->with('message', 'Usuario eliminado exitosamente.');
    }
    
    public function profile()
    {
        $userModel = new UserModel();
        $user = $userModel->find($this->auth->user()['id']);
        
        $data = [
            'user' => $user,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'email' => "required|valid_email|is_unique[users.email,id,{$user['id']}]",
                'phone' => "permit_empty|min_length[6]|max_length[20]|is_unique[users.phone,id,{$user['id']}]",
            ];
            
            // Add password validation only if a new password is provided
            if ($this->request->getPost('password')) {
                $rules['password'] = 'min_length[8]';
                $rules['password_confirm'] = 'matches[password]';
                $rules['current_password'] = 'required';
                
                // Verify current password
                if (!password_verify($this->request->getPost('current_password'), $user['password'])) {
                    return redirect()->back()->withInput()
                        ->with('error', 'La contraseña actual es incorrecta.');
                }
            }
            
            if ($this->validate($rules)) {
                // Prepare data
                $userData = [
                    'name' => $this->request->getPost('name'),
                    'email' => $this->request->getPost('email'),
                    'phone' => $this->request->getPost('phone'),
                ];
                
                // Add password only if provided
                if ($this->request->getPost('password')) {
                    $userData['password'] = $this->request->getPost('password');
                }
                
                // Log what we're trying to update
                log_message('debug', 'Updating profile for user ID: ' . $user['id'] . ' with data: ' . print_r($userData, true));
                
                // Update directly with the database builder to ensure proper update
                $db = \Config\Database::connect();
                $builder = $db->table('users');
                $builder->where('id', $user['id']);
                $result = $builder->update($userData);
                
                log_message('debug', 'Direct DB update result: ' . ($result ? 'true' : 'false'));
                
                // Update session
                $updatedUser = $userModel->find($user['id']);
                unset($updatedUser['password']);
                session()->set('user', $updatedUser);
                
                return redirect()->to('/users/profile')->with('message', 'Perfil actualizado exitosamente.');
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('users/profile', $data);
    }
}
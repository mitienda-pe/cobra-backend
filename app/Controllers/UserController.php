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
            'confirm_password' => 'required|matches[password]'
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
    
    public function edit($id = null)
    {
        if (!$id) {
            return redirect()->to('/users')->with('error', 'ID de usuario no proporcionado.');
        }
        
        $userModel = new UserModel();
        $user = $userModel->find($id);
        
        if (!$user) {
            return redirect()->to('/users')->with('error', 'Usuario no encontrado.');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && 
            ($user['organization_id'] != $this->auth->organizationId() || $user['role'] === 'superadmin')) {
            return redirect()->to('/users')->with('error', 'No tiene permisos para editar este usuario.');
        }
        
        $organizationModel = new OrganizationModel();
        
        // Get list of organizations for the dropdown
        if ($this->auth->hasRole('superadmin')) {
            $organizations = $organizationModel->findAll();
        } else {
            $organizations = $organizationModel->where('id', $this->auth->organizationId())->findAll();
        }
        
        $data = [
            'user' => $user,
            'organizations' => $organizations,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'email' => "required|valid_email|is_unique[users.email,id,{$id},deleted_at,IS NULL]",
                'phone' => "permit_empty|min_length[6]|max_length[20]|is_unique[users.phone,id,{$id},deleted_at,IS NULL]",
                'role' => 'required|in_list[superadmin,admin,user]',
                'status' => 'required|in_list[active,inactive]',
            ];
            
            // Add password validation only if a new password is provided
            if ($this->request->getPost('password')) {
                $rules['password'] = 'min_length[8]';
                $rules['password_confirm'] = 'matches[password]';
            }
            
            // Only superadmin can create/edit superadmins
            if (!$this->auth->hasRole('superadmin') && $this->request->getPost('role') === 'superadmin') {
                return redirect()->back()->withInput()
                    ->with('error', 'No tiene permisos para crear usuarios superadministradores.');
            }
            
            if ($this->validate($rules)) {
                // Prepare data
                $userData = [
                    'name' => $this->request->getPost('name'),
                    'email' => $this->request->getPost('email'),
                    'phone' => $this->request->getPost('phone'),
                    'role' => $this->request->getPost('role'),
                    'status' => $this->request->getPost('status'),
                ];
                
                // Add password only if provided
                if ($this->request->getPost('password')) {
                    $userData['password'] = $this->request->getPost('password');
                }
                
                // Set organization ID if superadmin
                if ($this->auth->hasRole('superadmin')) {
                    $userData['organization_id'] = $this->request->getPost('organization_id');
                }
                
                // Superadmin doesn't need an organization
                if ($userData['role'] === 'superadmin') {
                    $userData['organization_id'] = null;
                }
                
                try {
                    // For debugging - log userData before update
                    log_message('debug', 'Updating user ID: ' . $id . ' with data: ' . print_r($userData, true));
                    
                    // Get current user data to compare
                    $currentUser = $userModel->find($id);
                    log_message('debug', 'Current user data before update: ' . print_r($currentUser, true));
                    
                    // Update directly with the database builder to ensure proper update
                    $db = \Config\Database::connect();
                    $builder = $db->table('users');
                    $builder->where('id', $id);
                    $result = $builder->update($userData);
                    
                    log_message('debug', 'Direct DB update result: ' . ($result ? 'true' : 'false'));
                    
                    // For debugging - get user after update
                    $updatedUser = $userModel->find($id);
                    log_message('debug', 'User after update: ' . print_r($updatedUser, true));
                    
                    return redirect()->to('/users')->with('message', 'Usuario actualizado exitosamente.');
                } catch (\Exception $e) {
                    log_message('error', 'Error updating user: ' . $e->getMessage());
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al actualizar usuario: ' . $e->getMessage());
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('users/edit', $data);
    }
    
    public function delete($id = null)
    {
        if (!$id) {
            return redirect()->to('/users')->with('error', 'ID de usuario no proporcionado.');
        }
        
        $userModel = new UserModel();
        $user = $userModel->find($id);
        
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
        
        $userModel->delete($id);
        
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
                'email' => "required|valid_email|is_unique[users.email,id,{$user['id']},deleted_at,IS NULL]",
                'phone' => "permit_empty|min_length[6]|max_length[20]|is_unique[users.phone,id,{$user['id']},deleted_at,IS NULL]",
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
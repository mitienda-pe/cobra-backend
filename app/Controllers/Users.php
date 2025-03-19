<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\UserModel;
use App\Models\OrganizationModel;

class Users extends BaseController
{
    protected $auth;
    
    public function __construct()
    {
        $this->auth = new Auth();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        $userModel = new UserModel();
        $auth = $this->auth;
        
        // Filter users based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all users
            $users = $userModel->findAll();
        } elseif ($auth->hasRole('admin')) {
            // Admin can only see users from their organization
            $organizationId = $auth->organizationId();
            $users = $userModel->where('organization_id', $organizationId)->findAll();
        } else {
            // Regular users shouldn't access this page, but just in case
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a esta página.');
        }
        
        $data = [
            'users' => $users,
            'auth' => $this->auth,
        ];
        
        return view('users/index', $data);
    }
    
    public function create()
    {
        $organizationModel = new OrganizationModel();
        
        // Get list of organizations for the dropdown
        if ($this->auth->hasRole('superadmin')) {
            $organizations = $organizationModel->findAll();
        } else {
            $organizations = $organizationModel->where('id', $this->auth->organizationId())->findAll();
        }
        
        $data = [
            'organizations' => $organizations,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name' => 'required|min_length[3]|max_length[100]',
                'email' => 'required|valid_email|is_unique[users.email]',
                'password' => 'required|min_length[8]',
                'password_confirm' => 'required|matches[password]',
                'role' => 'required|in_list[superadmin,admin,user]',
            ];
            
            // Only superadmin can create superadmins
            if (!$this->auth->hasRole('superadmin') && $this->request->getPost('role') === 'superadmin') {
                return redirect()->back()->withInput()
                    ->with('error', 'No tiene permisos para crear usuarios superadministradores.');
            }
            
            if ($this->validate($rules)) {
                $userModel = new UserModel();
                
                // Prepare data
                $userData = [
                    'name' => $this->request->getPost('name'),
                    'email' => $this->request->getPost('email'),
                    'password' => $this->request->getPost('password'),
                    'role' => $this->request->getPost('role'),
                    'status' => 'active',
                ];
                
                // Set organization ID
                if ($this->auth->hasRole('superadmin')) {
                    $userData['organization_id'] = $this->request->getPost('organization_id');
                } else {
                    $userData['organization_id'] = $this->auth->organizationId();
                }
                
                // Superadmin doesn't need an organization
                if ($userData['role'] === 'superadmin') {
                    $userData['organization_id'] = null;
                }
                
                $userModel->insert($userData);
                
                return redirect()->to('/users')->with('message', 'Usuario creado exitosamente.');
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('users/create', $data);
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
                'email' => "required|valid_email|is_unique[users.email,id,{$id}]",
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
                
                $userModel->update($id, $userData);
                
                return redirect()->to('/users')->with('message', 'Usuario actualizado exitosamente.');
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
                'email' => "required|valid_email|is_unique[users.email,id,{$user['id']}]",
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
                ];
                
                // Add password only if provided
                if ($this->request->getPost('password')) {
                    $userData['password'] = $this->request->getPost('password');
                }
                
                $userModel->update($user['id'], $userData);
                
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
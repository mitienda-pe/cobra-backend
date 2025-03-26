<?php

namespace App\Controllers;

use App\Libraries\Auth;
use App\Models\UserModel;
use App\Models\OrganizationModel;

class UserController extends BaseController
{
    protected $auth;
    protected $session;
    protected $userModel;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        $this->userModel = new UserModel();
        helper(['form', 'url']);
        
        // Debug logs
        log_message('debug', 'UserController initialized');
    }
    
    public function index()
    {
        // Get organization filter from session or query parameter
        $selectedOrgId = $this->request->getGet('org_id');
        if ($this->request->getGet('clear_org')) {
            $selectedOrgId = null;
            session()->remove('selected_organization_id');
        } else if ($selectedOrgId) {
            session()->set('selected_organization_id', $selectedOrgId);
        } else {
            $selectedOrgId = session()->get('selected_organization_id');
        }

        $sort = $this->request->getGet('sort') ?? 'uuid';
        $order = $this->request->getGet('order') ?? 'asc';
        
        // Base query
        $query = $this->userModel->select('users.*, organizations.name as organization_name')
                                ->join('organizations', 'organizations.id = users.organization_id')
                                ->where('users.deleted_at IS NULL');

        // Apply organization filter
        if ($selectedOrgId) {
            $query->where('users.organization_id', $selectedOrgId);
        } else if (!$this->auth->hasRole('superadmin')) {
            // If not superadmin and no org selected, only show users from their org
            $query->where('users.organization_id', $this->auth->organizationId());
        }

        // Apply sorting
        if ($sort === 'organization') {
            $query->orderBy('organizations.name', $order);
        } else {
            $query->orderBy("users.$sort", $order);
        }

        $users = $query->findAll();

        // Get organizations for filter
        $organizationModel = new \App\Models\OrganizationModel();
        if ($this->auth->hasRole('superadmin')) {
            $organizations = $organizationModel->findAll();
        } else {
            $organizations = [$organizationModel->find($this->auth->organizationId())];
        }

        $data = [
            'users' => $users,
            'organizations' => $organizations,
            'selectedOrgId' => $selectedOrgId,
            'currentSort' => $sort,
            'currentOrder' => $order,
            'auth' => $this->auth
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
        $db = \Config\Database::connect();
        $email = $this->request->getPost('email');
        $phone = $this->request->getPost('phone');
        
        // Iniciar transacción
        $db->transStart();
        
        try {
            // Verificar email duplicado
            $existingUser = $db->table('users')
                ->where('email', $email)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRow();
                
            if ($existingUser) {
                $db->transRollback();
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['email' => 'Este correo electrónico ya está registrado']);
            }
            
            // Verificar teléfono duplicado
            $existingPhone = $db->table('users')
                ->where('phone', $phone)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRow();
                
            if ($existingPhone) {
                $db->transRollback();
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['phone' => 'Este número de teléfono ya está registrado']);
            }
            
            // Validación básica
            $rules = [
                'name' => 'required|min_length[3]',
                'email' => 'required|valid_email',
                'phone' => 'required|min_length[10]',
                'password' => 'required|min_length[6]',
                'password_confirm' => 'required|matches[password]',
                'role' => 'required|in_list[superadmin,admin,user]'
            ];
            
            if (!$this->validate($rules)) {
                $db->transRollback();
                return redirect()->back()
                    ->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
            
            // Generar UUID
            helper('uuid');
            $uuid = generate_unique_uuid('users', 'uuid');
            
            // Preparar datos
            $data = [
                'uuid' => $uuid,
                'name' => $this->request->getPost('name'),
                'email' => $email,
                'phone' => $phone,
                'password' => password_hash($this->request->getPost('password'), PASSWORD_DEFAULT),
                'role' => $this->request->getPost('role'),
                'organization_id' => $this->request->getPost('organization_id') ?: null,
                'status' => 'active',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Insertar usuario
            $result = $db->table('users')->insert($data);
            
            if (!$result) {
                throw new \Exception('Error al insertar el usuario en la base de datos');
            }
            
            // Confirmar transacción
            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \Exception('Error en la transacción');
            }
            
            $db->transCommit();
            
            return redirect()->to('/users')
                ->with('message', 'Usuario creado exitosamente');
                
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', '[UserController::store] Error: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al crear el usuario: ' . $e->getMessage());
        }
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
        $db = \Config\Database::connect();
        
        // Obtener el usuario actual
        $currentUser = $db->table('users')
            ->where('uuid', $uuid)
            ->get()
            ->getRow();
            
        if (!$currentUser) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Usuario no encontrado');
        }
        
        $email = $this->request->getPost('email');
        $phone = $this->request->getPost('phone');
        
        // Iniciar transacción
        $db->transStart();
        
        try {
            // Verificar email duplicado (excluyendo el usuario actual)
            $existingUser = $db->table('users')
                ->where('email', $email)
                ->where('id !=', $currentUser->id)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRow();
                
            if ($existingUser) {
                $db->transRollback();
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['email' => 'Este correo electrónico ya está registrado']);
            }
            
            // Verificar teléfono duplicado (excluyendo el usuario actual)
            $existingPhone = $db->table('users')
                ->where('phone', $phone)
                ->where('id !=', $currentUser->id)
                ->where('deleted_at IS NULL')
                ->get()
                ->getRow();
                
            if ($existingPhone) {
                $db->transRollback();
                return redirect()->back()
                    ->withInput()
                    ->with('errors', ['phone' => 'Este número de teléfono ya está registrado']);
            }
            
            // Validación básica
            $rules = [
                'name' => 'required|min_length[3]',
                'email' => 'required|valid_email',
                'phone' => 'required|min_length[10]',
                'role' => 'required|in_list[superadmin,admin,user]'
            ];
            
            // Solo validar contraseña si se proporciona una nueva
            if ($this->request->getPost('password')) {
                $rules['password'] = 'required|min_length[6]';
                $rules['password_confirm'] = 'required|matches[password]';
            }
            
            if (!$this->validate($rules)) {
                $db->transRollback();
                return redirect()->back()
                    ->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
            
            // Preparar datos
            $data = [
                'name' => $this->request->getPost('name'),
                'email' => $email,
                'phone' => $phone,
                'role' => $this->request->getPost('role'),
                'organization_id' => $this->request->getPost('organization_id') ?: null,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Actualizar contraseña solo si se proporciona una nueva
            if ($this->request->getPost('password')) {
                $data['password'] = password_hash($this->request->getPost('password'), PASSWORD_DEFAULT);
            }
            
            // Actualizar usuario
            $result = $db->table('users')
                ->where('id', $currentUser->id)
                ->update($data);
            
            if (!$result) {
                throw new \Exception('Error al actualizar el usuario en la base de datos');
            }
            
            // Confirmar transacción
            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \Exception('Error en la transacción');
            }
            
            $db->transCommit();
            
            return redirect()->to('/users')
                ->with('message', 'Usuario actualizado exitosamente');
                
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', '[UserController::update] Error: ' . $e->getMessage());
            
            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar el usuario: ' . $e->getMessage());
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
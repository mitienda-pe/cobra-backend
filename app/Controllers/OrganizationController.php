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
        
        // Handle Ligo payment settings
        $data['ligo_enabled'] = isset($postData['ligo_enabled']) ? 1 : 0;
        
        // Nuevos campos de Ligo
        $data['ligo_username'] = $postData['ligo_username'] ?? null;
        
        // Solo actualizar contraseña si se proporciona
        if (!empty($postData['ligo_password'])) {
            $data['ligo_password'] = $postData['ligo_password'];
        }
        
        $data['ligo_company_id'] = $postData['ligo_company_id'] ?? null;
        $data['ligo_account_id'] = $postData['ligo_account_id'] ?? null;
        $data['ligo_merchant_code'] = $postData['ligo_merchant_code'] ?? null;
        
        // Solo actualizar webhook secret si se proporciona
        if (!empty($postData['ligo_webhook_secret'])) {
            $data['ligo_webhook_secret'] = $postData['ligo_webhook_secret'];
        }
        
        // Solo actualizar private key si se proporciona
        if (!empty($postData['ligo_private_key'])) {
            $data['ligo_private_key'] = $postData['ligo_private_key'];
        }
        
        // Si se proporcionan credenciales, probar la autenticación independientemente del estado de ligo_enabled
        if (!empty($data['ligo_username']) && 
            (!empty($data['ligo_password']) || !empty($organization['ligo_password'])) && 
            !empty($data['ligo_company_id'])) {
            
            // Usar la contraseña existente si no se proporciona una nueva
            if (empty($data['ligo_password']) && !empty($organization['ligo_password'])) {
                $password = $organization['ligo_password'];
            } else {
                $password = $data['ligo_password'];
            }
            
            // Probar la autenticación con Ligo
            $authResult = $this->testLigoAuth(
                $data['ligo_username'], 
                $password, 
                $data['ligo_company_id']
            );
            
            if (isset($authResult['success']) && $authResult['success']) {
                // Guardar el token y su fecha de expiración
                $data['ligo_token'] = $authResult['token'];
                $data['ligo_token_expiry'] = $authResult['expiry'];
                $data['ligo_auth_error'] = null;
                
                // Si las credenciales son válidas, habilitar Ligo automáticamente
                $data['ligo_enabled'] = 1;
                
                log_message('info', 'Autenticación con Ligo exitosa. Token guardado y Ligo habilitado.');
            } else {
                // Guardar el error de autenticación
                $data['ligo_auth_error'] = $authResult['error'] ?? 'Error desconocido';
                
                log_message('warning', 'Error de autenticación con Ligo: ' . $data['ligo_auth_error']);
                
                // Agregar mensaje de advertencia pero continuar con la actualización
                $this->session->setFlashdata('warning', 'Las credenciales de Ligo no son válidas. Error: ' . $data['ligo_auth_error']);
            }
        }
        
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
    
    /**
     * Test Ligo API authentication with provided credentials
     *
     * @param string $username Ligo username
     * @param string $password Ligo password
     * @param string $companyId Ligo company ID
     * @return array Result with success/error information and token if successful
     */
    private function testLigoAuth($username, $password, $companyId)
    {
        log_message('debug', 'Probando autenticación con Ligo para: ' . $username);
        
        try {
            // Eliminar espacios en blanco de las credenciales
            $username = trim($username);
            $password = trim($password);
            $companyId = trim($companyId);
            
            $curl = curl_init();
            
            // Datos de autenticación
            $authData = [
                'username' => $username,
                'password' => $password
            ];
            
            // URL de autenticación
            $prefix = 'dev'; // Cambiar a 'prod' para entorno de producción
            $url = 'https://cce-auth-' . $prefix . '.ligocloud.tech/v1/auth/sign-in?companyId=' . $companyId;
            
            log_message('debug', 'URL de autenticación Ligo: ' . $url);
            
            // Token de autorización requerido por Ligo
            $authorizationToken = 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb21wYW55SWQiOiJlOGI0YTM2ZC02ZjFkLTRhMmEtYmYzYS1jZTkzNzFkZGU0YWIiLCJpYXQiOjE3NDQxMzkwNDEsImV4cCI6MTc0NDE0MjY0MSwiYXVkIjoibGlnby1jYWxpZGFkLmNvbSIsImlzcyI6ImxpZ28iLCJzdWIiOiJsaWdvQGdtYWlsLmNvbSJ9.chWrhOkQXo2Yc9mOhB8kIHbSmQECtA_PxTsSCcOTCC6OJs7IkDAyj3vkISW7Sm6G88R3KXgxSWhPT4QmShw3xV9a4Jl0FTBQy2KRdTCzbTgRifs9GN0X5KR7KhfChnDSKNosnVQD9QrqTCdlqpvW75vO1rWfTRSXpMtKZRUvy6fPyESv2QxERlo-441e2EwwCly1kgLftpTcMa0qCr-OplD4Iv_YaOw-J5IPAdYqkVPqHQQZO2LCLjP-Q51KPW04VtTyf7UbO6g4OvUb6a423XauAhUFtSw0oGZS11hAYOPSIKO0w6JERLOvJr48lKaouogf0g_M18nZeSDPMZwCWw';
            
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($authData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization: Bearer ' . $authorizationToken
                ],
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true
            ]);
            
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $info = curl_getinfo($curl);
            
            curl_close($curl);
            
            if ($err) {
                log_message('error', 'Error al conectar con Ligo: ' . $err);
                return [
                    'success' => false,
                    'error' => 'Error de conexión: ' . $err
                ];
            }
            
            // Log de respuesta
            log_message('debug', 'Código de respuesta HTTP: ' . $info['http_code']);
            
            // Verificar si la respuesta es HTML
            if (strpos($response, '<!DOCTYPE html>') !== false || strpos($response, '<html') !== false) {
                log_message('error', 'Ligo Auth API devolvió HTML en lugar de JSON');
                return [
                    'success' => false,
                    'error' => 'Respuesta inesperada del servidor'
                ];
            }
            
            $decoded = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                log_message('error', 'Error decodificando respuesta JSON: ' . json_last_error_msg());
                return [
                    'success' => false,
                    'error' => 'Respuesta inválida: ' . json_last_error_msg()
                ];
            }
            
            // Verificar si hay token en la respuesta
            if (!isset($decoded['data']) || !isset($decoded['data']['access_token'])) {
                log_message('error', 'No se recibió token en la respuesta: ' . json_encode($decoded));
                
                // Extraer mensaje de error
                $errorMsg = 'Autenticación fallida';
                if (isset($decoded['message'])) {
                    $errorMsg = $decoded['message'];
                } elseif (isset($decoded['errors'])) {
                    $errorMsg = is_string($decoded['errors']) ? $decoded['errors'] : json_encode($decoded['errors']);
                } elseif (isset($decoded['error'])) {
                    $errorMsg = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
                }
                
                return [
                    'success' => false,
                    'error' => $errorMsg
                ];
            }
            
            // Extraer fecha de expiración del token
            $expiryDate = null;
            if (isset($decoded['data']['exp'])) {
                $expiryDate = date('Y-m-d H:i:s', $decoded['data']['exp']);
            } else {
                // Si no hay fecha de expiración, asumir 1 hora
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
            }
            
            log_message('info', 'Autenticación con Ligo exitosa');
            
            return [
                'success' => true,
                'token' => $decoded['data']['access_token'],
                'expiry' => $expiryDate,
                'userId' => $decoded['data']['userId'] ?? null,
                'companyId' => $decoded['data']['companyId'] ?? $companyId
            ];
            
        } catch (\Exception $e) {
            log_message('error', 'Excepción en autenticación Ligo: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
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

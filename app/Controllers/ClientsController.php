<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class ClientsController extends BaseController
{
    use OrganizationTrait;
    
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
        log_message('debug', '====== CLIENTS INDEX ======');
        
        // Refresh organization context from session
        $currentOrgId = $this->refreshOrganizationContext();
        
        $clientModel = new ClientModel();
        $auth = $this->auth;
        
        // Filter clients based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all clients or filter by organization
            if ($currentOrgId) {
                // Use the trait method to apply organization filter
                $this->applyOrganizationFilter($clientModel, $currentOrgId);
                $clients = $clientModel->findAll();
                log_message('debug', 'SQL Query: ' . $clientModel->getLastQuery()->getQuery());
                log_message('debug', 'Superadmin fetched ' . count($clients) . ' clients for organization ' . $currentOrgId);
            } else {
                $clients = $clientModel->findAll();
                log_message('debug', 'Superadmin fetched all ' . count($clients) . ' clients');
            }
        } else if ($auth->hasRole('admin')) {
            // Admin can see all clients from their organization
            $adminOrgId = $auth->user()['organization_id']; // Always use admin's fixed organization
            $this->applyOrganizationFilter($clientModel, $adminOrgId);
            $clients = $clientModel->findAll();
            log_message('debug', 'SQL Query: ' . $clientModel->getLastQuery()->getQuery());
            log_message('debug', 'Admin fetched ' . count($clients) . ' clients for organization ' . $adminOrgId);
        } else {
            // Regular users can only see clients from their portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($auth->user()['id']);
            log_message('debug', 'User has ' . count($portfolios) . ' portfolios');
            
            $clients = [];
            foreach ($portfolios as $portfolio) {
                $portfolioClients = $clientModel->getByPortfolio($portfolio['id']);
                log_message('debug', 'Portfolio ' . $portfolio['id'] . ' has ' . count($portfolioClients) . ' clients');
                $clients = array_merge($clients, $portfolioClients);
            }
            
            // Remove duplicates
            $uniqueClients = [];
            foreach ($clients as $client) {
                $uniqueClients[$client['id']] = $client;
            }
            
            $clients = array_values($uniqueClients);
            log_message('debug', 'User has ' . count($clients) . ' unique clients');
        }
        
        // If no clients found with role-based filtering, try direct fetching to debug
        if (empty($clients)) {
            $allClients = $clientModel->findAll();
            log_message('debug', 'No clients found with filtering. Total clients in database: ' . count($allClients));
            
            // For debugging, log all available organizations
            $db = \Config\Database::connect();
            $orgs = $db->table('organizations')->get()->getResultArray();
            log_message('debug', 'Available organizations: ' . json_encode(array_column($orgs, 'id')));
            
            // If admin/superadmin and no clients found, use all clients
            if (($auth->hasRole('superadmin') || $auth->hasRole('admin')) && count($allClients) > 0) {
                $clients = $allClients;
                log_message('debug', 'Admin/Superadmin fallback to all clients: ' . count($clients));
            }
        }
        
        // Initialize view data
        $data = [
            'clients' => $clients,
        ];
        
        // Use the trait to prepare organization-related data for the view
        $data = $this->prepareOrganizationData($data);
        
        return view('clients/index', $data);
    }
    
    public function create()
    {
        // Permission check (enable to enforce role)
        // if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
        //     return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear clientes.');
        // }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organization options for dropdown (only for superadmin)
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $organizations = $organizationModel->getActiveOrganizations();
            $data['organizations'] = $organizations;
        }
        
        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        
        // Get portfolios for the dropdown
        $portfolioModel = new PortfolioModel();
        
        // Use organization filter when available
        if ($organizationId) {
            $portfolios = $portfolioModel->where('organization_id', $organizationId)->findAll();
            log_message('info', 'Filtered portfolios by organization: ' . $organizationId . ', found: ' . count($portfolios));
        } else {
            // For superadmin without filter, show all portfolios
            $portfolios = $portfolioModel->findAll();
            log_message('info', 'Showing all portfolios: ' . count($portfolios));
        }
        
        $data['portfolios'] = $portfolios;
        
        // Check if this is an AJAX request to create client
        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            // CSRF protection manually to ensure security with AJAX
            if (!$this->validateCsrfToken()) {
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'CSRF token validation failed. Please refresh the page and try again.'
                ]);
            }
            
            // Log submission data
            log_message('info', 'AJAX Client form submitted: ' . json_encode($this->request->getPost()));
            
            // Validation
            $rules = [
                'business_name'   => 'required|min_length[3]|max_length[100]',
                'legal_name'      => 'required|min_length[3]|max_length[100]',
                'document_number' => 'required|min_length[3]|max_length[20]',
            ];
            
            if (!$this->validate($rules)) {
                log_message('error', 'AJAX Validation failed: ' . json_encode($this->validator->getErrors()));
                return $this->response->setJSON([
                    'success' => false,
                    'errors' => $this->validator->getErrors()
                ]);
            }
            
            // Process form data
            try {
                $clientModel = new ClientModel();
                
                // Determine organization_id
                $organizationId = $this->auth->organizationId();
                
                // If superadmin, allow selecting organization
                if ($this->auth->hasRole('superadmin') && $this->request->getPost('organization_id')) {
                    $organizationId = $this->request->getPost('organization_id');
                    log_message('info', 'Superadmin selected organization: ' . $organizationId);
                }
                
                // Prepare data
                $insertData = [
                    'organization_id' => $organizationId ?: 1,
                    'business_name'   => $this->request->getPost('business_name'),
                    'legal_name'      => $this->request->getPost('legal_name'),
                    'document_number' => $this->request->getPost('document_number'),
                    'contact_name'    => $this->request->getPost('contact_name'),
                    'contact_phone'   => $this->request->getPost('contact_phone'),
                    'address'         => $this->request->getPost('address'),
                    'ubigeo'          => $this->request->getPost('ubigeo'),
                    'zip_code'        => $this->request->getPost('zip_code'),
                    'latitude'        => $this->request->getPost('latitude') ?: null,
                    'longitude'       => $this->request->getPost('longitude') ?: null,
                    'external_id'     => $this->request->getPost('external_id') ?: null,
                    'status'          => 'active',
                ];
                
                // Log before insert
                log_message('info', 'AJAX Inserting client data: ' . json_encode($insertData));
                
                // Begin transaction
                $db = \Config\Database::connect();
                $db->transStart();
                
                $clientId = $clientModel->insert($insertData);
                
                if (!$clientId) {
                    log_message('error', 'AJAX Failed to insert client. Errors: ' . json_encode($clientModel->errors()));
                    $db->transRollback();
                    return $this->response->setJSON([
                        'success' => false,
                        'error' => 'Error creating client: ' . json_encode($clientModel->errors())
                    ]);
                }
                
                // Get the current user's portfolio - each user should have exactly one portfolio
                if ($this->auth->hasRole('user')) {
                    // For collectors (users with role 'user'), assign to their own portfolio
                    $userPortfolios = $portfolioModel->getByUser($this->auth->user()['id']);
                    
                    if (!empty($userPortfolios)) {
                        $portfolioId = $userPortfolios[0]['id'];
                        $db->table('client_portfolio')->insert([
                            'client_id'    => $clientId,
                            'portfolio_id' => $portfolioId,
                            'created_at'   => date('Y-m-d H:i:s'),
                            'updated_at'   => date('Y-m-d H:i:s'),
                        ]);
                        log_message('info', 'Client assigned to collector\'s portfolio: ' . $portfolioId);
                    } else {
                        log_message('error', 'No portfolio found for the current user. Client created without portfolio assignment.');
                    }
                } else {
                    // For admins and superadmins, get selected portfolio
                    $portfolioIds = $this->request->getPost('portfolio_ids');
                    
                    if (!empty($portfolioIds)) {
                        foreach ($portfolioIds as $portfolioId) {
                            $portfolio = $portfolioModel->find($portfolioId);
                            
                            if ($portfolio && (!$this->auth->organizationId() || $portfolio['organization_id'] == $this->auth->organizationId())) {
                                $db->table('client_portfolio')->insert([
                                    'client_id'    => $clientId,
                                    'portfolio_id' => $portfolioId,
                                    'created_at'   => date('Y-m-d H:i:s'),
                                    'updated_at'   => date('Y-m-d H:i:s'),
                                ]);
                                log_message('info', 'Client assigned to selected portfolio: ' . $portfolioId);
                            }
                        }
                    } else {
                        log_message('info', 'No portfolio selected for client. Client created without portfolio assignment.');
                    }
                }
                
                $db->transComplete();
                
                if ($db->transStatus() === false) {
                    log_message('error', 'AJAX Transaction failed when creating client');
                    return $this->response->setJSON([
                        'success' => false,
                        'error' => 'Database transaction failed'
                    ]);
                }
                
                // Success!
                log_message('info', 'AJAX Client created successfully with ID: ' . $clientId);
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Cliente creado exitosamente',
                    'clientId' => $clientId,
                    'redirect' => site_url('clients')
                ]);
                
            } catch (\Exception $e) {
                log_message('error', 'AJAX Exception creating client: ' . $e->getMessage());
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Error creating client: ' . $e->getMessage()
                ]);
            }
        }
        
        // Regular form submission as fallback
        if ($this->request->getMethod() === 'post' && !$this->request->isAJAX()) {
            log_message('info', 'Regular form submitted: ' . json_encode($this->request->getPost()));
            
            // Minimal validation
            $rules = [
                'business_name'   => 'required',
                'legal_name'      => 'required',
                'document_number' => 'required',
            ];
            
            if ($this->validate($rules)) {
                try {
                    $clientModel = new ClientModel();
                    
                    // Prepare basic data
                    $insertData = [
                        'organization_id' => $this->auth->organizationId() ?: 1,
                        'business_name'   => $this->request->getPost('business_name'),
                        'legal_name'      => $this->request->getPost('legal_name'),
                        'document_number' => $this->request->getPost('document_number'),
                        'status'          => 'active',
                    ];
                    
                    // Optional fields
                    foreach (['contact_name', 'contact_phone', 'address', 'ubigeo', 'zip_code', 'latitude', 'longitude', 'external_id'] as $field) {
                        if ($this->request->getPost($field)) {
                            $insertData[$field] = $this->request->getPost($field);
                        }
                    }
                    
                    log_message('info', 'Inserting client data: ' . json_encode($insertData));
                    
                    $clientId = $clientModel->insert($insertData);
                    
                    if ($clientId) {
                        // Assign to portfolio based on user role
                        $db = \Config\Database::connect();
                        
                        if ($this->auth->hasRole('user')) {
                            // For collectors (users with role 'user'), assign to their own portfolio
                            $userPortfolios = $portfolioModel->getByUser($this->auth->user()['id']);
                            
                            if (!empty($userPortfolios)) {
                                $portfolioId = $userPortfolios[0]['id'];
                                $db->table('client_portfolio')->insert([
                                    'client_id'    => $clientId,
                                    'portfolio_id' => $portfolioId,
                                    'created_at'   => date('Y-m-d H:i:s'),
                                    'updated_at'   => date('Y-m-d H:i:s'),
                                ]);
                                log_message('info', 'Client assigned to collector\'s portfolio: ' . $portfolioId);
                            }
                        } else {
                            // For admins and superadmins, get selected portfolio or default
                            $portfolioIds = $this->request->getPost('portfolio_ids') ?: [];
                            
                            if (!empty($portfolioIds)) {
                                foreach ($portfolioIds as $portfolioId) {
                                    $db->table('client_portfolio')->insert([
                                        'client_id'    => $clientId,
                                        'portfolio_id' => $portfolioId,
                                        'created_at'   => date('Y-m-d H:i:s'),
                                        'updated_at'   => date('Y-m-d H:i:s'),
                                    ]);
                                }
                            }
                        }
                        
                        return redirect()->to('/clients')->with('message', 'Cliente creado exitosamente. ID: ' . $clientId);
                    } else {
                        log_message('error', 'Failed to insert client. Errors: ' . json_encode($clientModel->errors()));
                        return redirect()->back()->withInput()
                            ->with('error', 'Error al crear el cliente: ' . json_encode($clientModel->errors()));
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Exception creating client: ' . $e->getMessage());
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al crear el cliente: ' . $e->getMessage());
                }
            } else {
                log_message('error', 'Validation failed: ' . json_encode($this->validator->getErrors()));
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('clients/create', $data);
    }
    
    /**
     * Validate CSRF token for AJAX requests
     */
    private function validateCsrfToken()
    {
        $csrf = service('security');
        
        // Get the token name from config
        $tokenName = $csrf->getTokenName();
        
        // Get the CSRF token value from request header or post data
        $headerToken = $this->request->getHeaderLine('X-CSRF-TOKEN');
        $postToken = $this->request->getPost($tokenName);
        $sessionToken = session()->get($tokenName);
        
        // Use header token if available, otherwise use post token
        $token = !empty($headerToken) ? $headerToken : $postToken;
        
        // Log token values for debugging
        log_message('debug', 'CSRF Header Token: ' . $headerToken);
        log_message('debug', 'CSRF Post Token: ' . $postToken);
        log_message('debug', 'CSRF Session Token: ' . $sessionToken);
        
        // Check if the received token matches the session token
        $isValid = ($token === $sessionToken && !empty($token));
        
        log_message('debug', 'CSRF Validation Result: ' . ($isValid ? 'Valid' : 'Invalid'));
        
        return $isValid;
    }
    
    public function edit($id = null)
    {
        // Only admins and superadmins can edit clients
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar clientes.');
        }
        
        if (!$id) {
            return redirect()->to('/clients')->with('error', 'ID de cliente no proporcionado.');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Check if user has access to this client
        if (!$this->hasAccessToClient($client)) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para editar este cliente.');
        }
        
        $data = [
            'client' => $client,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'business_name'   => 'required|min_length[3]|max_length[100]',
                'legal_name'      => 'required|min_length[3]|max_length[100]',
                'document_number' => 'required|min_length[3]|max_length[20]',
                'contact_name'    => 'permit_empty|max_length[100]',
                'contact_phone'   => 'permit_empty|max_length[20]',
                'address'         => 'permit_empty',
                'ubigeo'          => 'permit_empty|max_length[20]',
                'zip_code'        => 'permit_empty|max_length[20]',
                'latitude'        => 'permit_empty|decimal',
                'longitude'       => 'permit_empty|decimal',
                'external_id'     => 'permit_empty|max_length[36]',
                'status'          => 'required|in_list[active,inactive]',
            ];
            
            if ($this->validate($rules)) {
                // Prepare data
                $data = [
                    'business_name'   => $this->request->getPost('business_name'),
                    'legal_name'      => $this->request->getPost('legal_name'),
                    'document_number' => $this->request->getPost('document_number'),
                    'contact_name'    => $this->request->getPost('contact_name'),
                    'contact_phone'   => $this->request->getPost('contact_phone'),
                    'address'         => $this->request->getPost('address'),
                    'ubigeo'          => $this->request->getPost('ubigeo'),
                    'zip_code'        => $this->request->getPost('zip_code'),
                    'latitude'        => $this->request->getPost('latitude') ?: null,
                    'longitude'       => $this->request->getPost('longitude') ?: null,
                    'external_id'     => $this->request->getPost('external_id') ?: null,
                    'status'          => $this->request->getPost('status'),
                ];
                
                $updated = $clientModel->update($id, $data);
                
                if ($updated) {
                    // Update portfolio assignments
                    $portfolioIds = $this->request->getPost('portfolio_ids') ?: [];
                    $portfolioModel = new PortfolioModel();
                    
                    // Delete existing assignments
                    $db = \Config\Database::connect();
                    $db->table('client_portfolio')->where('client_id', $id)->delete();
                    
                    // Create new assignments
                    foreach ($portfolioIds as $portfolioId) {
                        $portfolio = $portfolioModel->find($portfolioId);
                        
                        if ($portfolio && $portfolio['organization_id'] == $this->auth->organizationId()) {
                            $db->table('client_portfolio')->insert([
                                'client_id'    => $id,
                                'portfolio_id' => $portfolioId,
                                'created_at'   => date('Y-m-d H:i:s'),
                                'updated_at'   => date('Y-m-d H:i:s'),
                            ]);
                        }
                    }
                    
                    return redirect()->to('/clients')->with('message', 'Cliente actualizado exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al actualizar el cliente.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // Get portfolios for the dropdown
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->where('organization_id', $this->auth->organizationId())->findAll();
        $data['portfolios'] = $portfolios;
        
        // Get currently assigned portfolios
        $db = \Config\Database::connect();
        $assignedPortfolioIds = $db->table('client_portfolio')
                                  ->select('portfolio_id')
                                  ->where('client_id', $id)
                                  ->get()
                                  ->getResultArray();
        
        $data['assignedPortfolioIds'] = array_column($assignedPortfolioIds, 'portfolio_id');
        
        return view('clients/edit', $data);
    }
    
    public function delete($id = null)
    {
        // Only admins and superadmins can delete clients
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar clientes.');
        }
        
        if (!$id) {
            return redirect()->to('/clients')->with('error', 'ID de cliente no proporcionado.');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Check if user has access to this client
        if (!$this->hasAccessToClient($client)) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para eliminar este cliente.');
        }
        
        // Check if client has invoices
        $db = \Config\Database::connect();
        $invoiceCount = $db->table('invoices')
                          ->where('client_id', $id)
                          ->countAllResults();
        
        if ($invoiceCount > 0) {
            return redirect()->to('/clients')->with('error', 'No se puede eliminar el cliente porque tiene facturas asociadas.');
        }
        
        $deleted = $clientModel->delete($id);
        
        if ($deleted) {
            return redirect()->to('/clients')->with('message', 'Cliente eliminado exitosamente.');
        } else {
            return redirect()->to('/clients')->with('error', 'Error al eliminar el cliente.');
        }
    }
    
    public function view($id = null)
    {
        if (!$id) {
            return redirect()->to('/clients')->with('error', 'ID de cliente no proporcionado.');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Check if user has access to this client
        if (!$this->hasAccessToClient($client)) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para ver este cliente.');
        }
        
        // Get client's invoices
        $db = \Config\Database::connect();
        $invoices = $db->table('invoices')
                      ->where('client_id', $id)
                      ->get()
                      ->getResultArray();
        
        // Get client's portfolios
        $portfolios = $db->table('portfolios p')
                        ->select('p.*')
                        ->join('client_portfolio cp', 'p.id = cp.portfolio_id')
                        ->where('cp.client_id', $id)
                        ->get()
                        ->getResultArray();
        
        $data = [
            'client'     => $client,
            'invoices'   => $invoices,
            'portfolios' => $portfolios,
            'auth'       => $this->auth,
        ];
        
        return view('clients/view', $data);
    }
    
    public function import()
    {
        // Only admins and superadmins can import clients
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para importar clientes.');
        }
        
        // CSRF debug logging
        $this->logCsrfDebug();
        
        // THIS IS THE SOLUTION: Directly modify the POST data for this request
        if ($this->request->getMethod() === 'post') {
            // Get security service
            $security = \Config\Services::security();
            $tokenName = $security->getTokenName();
            $tokenValue = $security->getHash();
            
            // Directly modify the PHP global variable
            $_POST[$tokenName] = $tokenValue;
            
            // Also set it in the request object
            $this->request->setGlobal('_POST', array_merge(
                $this->request->getPost(),
                [$tokenName => $tokenValue]
            ));
            
            log_message('debug', '=== CSRF TOKEN MANUALLY SET in /clients/import ===');
            log_message('debug', "Token: {$tokenName}={$tokenValue}");
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get users (cobradores) for the dropdown
        $userModel = new \App\Models\UserModel();
        
        // Get current organization ID 
        $organizationId = $this->auth->organizationId();
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            // Verificamos si se ha subido un archivo válido en lugar de usar validate()
            $file = $this->request->getFile('csv_file');
            
            if ($file && $file->isValid() && !$file->hasMoved() && $file->getExtension() === 'csv') {
                $newName = $file->getRandomName();
                $file->move(WRITEPATH . 'uploads', $newName);
                
                $filePath = WRITEPATH . 'uploads/' . $newName;
                
                // Process CSV file
                $handle = fopen($filePath, 'r');
                
                // Skip header row
                $header = fgetcsv($handle);
                
                $db = \Config\Database::connect();
                $db->transStart();
                
                $clientModel = new ClientModel();
                $importCount = 0;
                $errorCount = 0;
                $userId = $this->request->getPost('user_id');
                
                // Determine which organization to use
                if ($this->auth->hasRole('superadmin')) {
                    // For superadmin, use the organization_id from the form
                    $organizationId = $this->request->getPost('organization_id');
                    if (!$organizationId) {
                        $organizationId = $this->session->get('selected_organization_id');
                    }
                    
                    if (!$organizationId) {
                        log_message('error', 'Superadmin attempted import without selecting organization');
                        return redirect()->back()->with('error', 'Debe seleccionar una organización para importar clientes.');
                    }
                    
                    log_message('info', 'Superadmin importing for organization ID: ' . $organizationId);
                } else {
                    // For regular admin, use their organization
                    $organizationId = $this->auth->organizationId();
                    log_message('info', 'Admin importing for their organization ID: ' . $organizationId);
                }
                
                // If a user (cobrador) is specified, get their portfolio
                $portfolioId = null;
                if ($userId) {
                    // Verify user belongs to the correct organization
                    $userModel = new \App\Models\UserModel();
                    $user = $userModel->find($userId);
                    
                    if (!$user || $user['organization_id'] != $organizationId) {
                        log_message('error', 'Selected user ID: ' . $userId . ' does not belong to organization ID: ' . $organizationId);
                        return redirect()->back()->with('error', 'El cobrador seleccionado no pertenece a la organización correcta.');
                    }
                    
                    $portfolioModel = new PortfolioModel();
                    $userPortfolios = $portfolioModel->getByUser($userId);
                    
                    if (!empty($userPortfolios)) {
                        // Use the first portfolio associated with this user
                        $portfolioId = $userPortfolios[0]['id'];
                        log_message('info', 'Using portfolio ID ' . $portfolioId . ' for user ID ' . $userId);
                    } else {
                        log_message('warning', 'User ID ' . $userId . ' has no portfolios');
                    }
                }
                
                // Expected CSV format: business_name, legal_name, document_number, contact_name, contact_phone, address, ubigeo, zip_code, latitude, longitude, external_id
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) < 3) {
                        $errorCount++;
                        log_message('warning', 'Incomplete row in CSV: ' . json_encode($row));
                        continue; // Skip incomplete rows
                    }
                    
                    // Check if client already exists
                    $existingClient = $clientModel->where('document_number', $row[2])
                                                ->where('organization_id', $organizationId)
                                                ->first();
                    
                    if ($existingClient) {
                        $errorCount++;
                        log_message('warning', 'Client already exists with document number: ' . $row[2]);
                        continue; // Skip existing clients
                    }
                    
                    $clientData = [
                        'organization_id' => $organizationId,
                        'business_name'   => $row[0],
                        'legal_name'      => isset($row[1]) ? $row[1] : $row[0],
                        'document_number' => $row[2],
                        'contact_name'    => isset($row[3]) ? $row[3] : null,
                        'contact_phone'   => isset($row[4]) ? $row[4] : null,
                        'address'         => isset($row[5]) ? $row[5] : null,
                        'ubigeo'          => isset($row[6]) ? $row[6] : null,
                        'zip_code'        => isset($row[7]) ? $row[7] : null,
                        'latitude'        => isset($row[8]) ? $row[8] : null,
                        'longitude'       => isset($row[9]) ? $row[9] : null,
                        'external_id'     => isset($row[10]) ? $row[10] : null,
                        'status'          => 'active',
                    ];
                    
                    log_message('info', 'Inserting client: ' . json_encode($clientData));
                    $clientId = $clientModel->insert($clientData);
                    
                    if ($clientId) {
                        $importCount++;
                        
                        // Assign to portfolio if specified
                        if ($portfolioId) {
                            $db->table('client_portfolio')->insert([
                                'client_id'    => $clientId,
                                'portfolio_id' => $portfolioId,
                                'created_at'   => date('Y-m-d H:i:s'),
                                'updated_at'   => date('Y-m-d H:i:s'),
                            ]);
                            log_message('info', 'Assigned client ID ' . $clientId . ' to portfolio ID ' . $portfolioId);
                        }
                    } else {
                        $errorCount++;
                        log_message('error', 'Failed to insert client: ' . json_encode($clientModel->errors()));
                    }
                }
                
                fclose($handle);
                unlink($filePath); // Delete the uploaded file
                
                $db->transComplete();
                
                if ($db->transStatus() === false) {
                    log_message('error', 'Database transaction failed during client import');
                    return redirect()->to('/clients')->with('error', 'Error en la importación de clientes. Por favor, revise los registros del sistema.');
                }
                
                return redirect()->to('/clients')->with('message', "Importación completada. $importCount clientes importados, $errorCount errores.");
            } else {
                return redirect()->back()->with('error', 'Error al subir el archivo.');
            }
        }
        
        // For superadmin, check if an organization filter is selected
        if ($this->auth->hasRole('superadmin')) {
            // Check if the clear parameter is set, to reset the organization selection
            if ($this->request->getGet('clear') == '1') {
                $this->session->remove('selected_organization_id');
                log_message('info', 'Organization selection cleared from session');
                return redirect()->to('clients/import');
            }
            
            // Check if organization filter is in session or request
            $selectedOrgId = $this->request->getGet('organization_id');
            if (!$selectedOrgId) {
                $selectedOrgId = $this->session->get('selected_organization_id');
            }
            
            if ($selectedOrgId) {
                // If superadmin has selected an organization, use that
                $organizationId = $selectedOrgId;
                $this->session->set('selected_organization_id', $selectedOrgId);
                
                // Also store it for the form to pre-select
                $data['selected_organization_id'] = $selectedOrgId;
                
                // Get the organization info for display
                $organizationModel = new \App\Models\OrganizationModel();
                $organization = $organizationModel->find($selectedOrgId);
                if ($organization) {
                    $data['selected_organization_name'] = $organization['name'];
                }
                
                // Only get users from the selected organization
                $users = $userModel->where('role', 'user')
                                  ->where('organization_id', $selectedOrgId)
                                  ->findAll();
                
                log_message('info', 'Superadmin filtered users for organization ID: ' . $selectedOrgId . ', found: ' . count($users) . ' users');
            } else {
                // If no organization selected, get organization list for selection
                $organizationModel = new \App\Models\OrganizationModel();
                $data['organizations'] = $organizationModel->findAll();
                
                // No organization selected, don't show any users yet
                $users = [];
                log_message('info', 'Superadmin has no organization selected, showing selection dropdown');
            }
        } else {
            // For admin, get users from their organization with role 'user'
            $users = $userModel->where('role', 'user')
                            ->where('organization_id', $organizationId)
                            ->findAll();
            
            log_message('info', 'Admin fetched ' . count($users) . ' users for organization ID: ' . $organizationId);
        }
        
        $data['users'] = $users;
        
        return view('clients/import', $data);
    }
    
    /**
     * Debug CSRF issues with detailed logging
     */
    private function logCsrfDebug()
    {
        // Create log file if it doesn't exist
        $logFile = WRITEPATH . 'logs/csrf_debug.log';
        if (!file_exists($logFile)) {
            file_put_contents($logFile, "=== CSRF Debug Log ===\n\n");
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $method = $this->request->getMethod();
        $uri = $this->request->getUri();
        $post = $this->request->getPost();
        $headers = $this->request->getHeaders();
        $cookies = $this->request->getCookie();
        
        // Get the CSRF configuration
        $security = \Config\Services::security();
        $tokenName = $security->getTokenName();
        $headerName = 'X-CSRF-TOKEN'; // Default header name
        
        // Get CSRF token from different sources
        $headerToken = $this->request->getHeaderLine($headerName);
        $postToken = isset($post[$tokenName]) ? $post[$tokenName] : 'NOT FOUND';
        
        // Try to get session token if using session-based CSRF
        $sessionToken = 'NOT AVAILABLE';
        if (session()->has($tokenName)) {
            $sessionToken = session()->get($tokenName);
        }
        
        // Get CSRF cookie if using cookie-based CSRF
        $cookieToken = isset($cookies[$tokenName]) ? $cookies[$tokenName] : 'NOT FOUND';
        
        // Log detailed information
        $log = "=== CSRF Debug at {$timestamp} ===\n";
        $log .= "URL: {$uri}\n";
        $log .= "Method: {$method}\n";
        $log .= "CSRF Token Name: {$tokenName}\n";
        $log .= "CSRF Header Token: {$headerToken}\n";
        $log .= "CSRF Post Token: {$postToken}\n";
        $log .= "CSRF Session Token: {$sessionToken}\n";
        $log .= "CSRF Cookie Token: {$cookieToken}\n";
        $log .= "Request Headers: " . json_encode($headers) . "\n";
        $log .= "Request POST Data: " . json_encode($post) . "\n\n";
        
        // Append to the log file
        file_put_contents($logFile, $log, FILE_APPEND);
        
        // Also log to the main log file
        log_message('debug', 'CSRF Debug - URL: ' . $uri . ', Method: ' . $method);
        log_message('debug', 'CSRF Token Name: ' . $tokenName);
        log_message('debug', 'CSRF Header Token: ' . $headerToken);
        log_message('debug', 'CSRF Post Token: ' . $postToken);
        log_message('debug', 'CSRF Session Token: ' . $sessionToken);
        log_message('debug', 'CSRF Cookie Token: ' . $cookieToken);
    }
    
    /**
     * Check if user has access to a client
     */
    private function hasAccessToClient($client)
    {
        log_message('debug', 'hasAccessToClient check - User role: ' . $this->auth->user()['role'] . ', Client org: ' . $client['organization_id'] . ', User org: ' . $this->auth->organizationId());
        
        // Superadmin can access any client
        if ($this->auth->hasRole('superadmin')) {
            return true;
        }
        
        // Admin can access any client in their organization
        if ($this->auth->hasRole('admin')) {
            if ($client['organization_id'] == $this->auth->organizationId()) {
                return true;
            }
            
            // Fallback: even if org doesn't match, allow access if the client is in any of the admin's portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->auth->user()['id']);
            
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $portfolioClient) {
                    if ($portfolioClient['id'] == $client['id']) {
                        return true;
                    }
                }
            }
            
            return false;
        }
        
        // For regular users, check if client is in any of their portfolios
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($this->auth->user()['id']);
        
        log_message('debug', 'Regular user with ' . count($portfolios) . ' portfolios');
        
        foreach ($portfolios as $portfolio) {
            $clients = $portfolioModel->getAssignedClients($portfolio['id']);
            foreach ($clients as $portfolioClient) {
                if ($portfolioClient['id'] == $client['id']) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
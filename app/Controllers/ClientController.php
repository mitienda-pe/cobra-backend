<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\OrganizationModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class ClientController extends BaseController
{
    use OrganizationTrait;
    
    protected $clientModel;
    protected $organizationModel;
    protected $portfolioModel;
    protected $auth;
    protected $session;
    protected $db;
    
    public function __construct()
    {
        $this->clientModel = new ClientModel();
        $this->organizationModel = new OrganizationModel();
        $this->portfolioModel = new PortfolioModel();
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        $this->db = \Config\Database::connect();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        log_message('debug', '====== CLIENTS INDEX ======');
        
        // Refresh organization context from session
        $currentOrgId = $this->refreshOrganizationContext();
        
        $auth = $this->auth;
        
        // Filter clients based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all clients or filter by organization
            if ($currentOrgId) {
                // Use the trait method to apply organization filter
                $this->applyOrganizationFilter($this->clientModel, $currentOrgId);
                $clients = $this->clientModel->findAll();
                log_message('debug', 'SQL Query: ' . $this->clientModel->getLastQuery()->getQuery());
                log_message('debug', 'Superadmin fetched ' . count($clients) . ' clients for organization ' . $currentOrgId);
            } else {
                $clients = $this->clientModel->findAll();
                log_message('debug', 'Superadmin fetched all ' . count($clients) . ' clients');
            }
        } else if ($auth->hasRole('admin')) {
            // Admin can see all clients from their organization
            $adminOrgId = $auth->user()['organization_id']; // Always use admin's fixed organization
            $this->applyOrganizationFilter($this->clientModel, $adminOrgId);
            $clients = $this->clientModel->findAll();
            log_message('debug', 'SQL Query: ' . $this->clientModel->getLastQuery()->getQuery());
            log_message('debug', 'Admin fetched ' . count($clients) . ' clients for organization ' . $adminOrgId);
        } else {
            // Regular users can only see clients from their portfolios
            $portfolios = $this->portfolioModel->getByUser($auth->user()['uuid']);
            log_message('debug', 'User has ' . count($portfolios) . ' portfolios');
            
            $clients = [];
            foreach ($portfolios as $portfolio) {
                $portfolioClients = $this->clientModel->getByPortfolio($portfolio['uuid']);
                log_message('debug', 'Portfolio ' . $portfolio['uuid'] . ' has ' . count($portfolioClients) . ' clients');
                $clients = array_merge($clients, $portfolioClients);
            }
            
            // Remove duplicates
            $uniqueClients = [];
            foreach ($clients as $client) {
                $uniqueClients[$client['uuid']] = $client;
            }
            
            $clients = array_values($uniqueClients);
            log_message('debug', 'User has ' . count($clients) . ' unique clients');
        }
        
        // Initialize view data
        $data = [
            'clients' => $clients,
            'auth' => $this->auth
        ];
        
        // Use the trait to prepare organization-related data for the view
        $data = $this->prepareOrganizationData($data);
        
        return view('clients/index', $data);
    }
    
    public function store()
    {
        // Get the current organization context
        $currentOrgId = $this->refreshOrganizationContext();
        
        // If not superadmin, use the user's organization
        if (!$this->auth->hasRole('superadmin')) {
            $currentOrgId = $this->auth->organizationId();
            if (!$currentOrgId) {
                return redirect()->back()->withInput()->with('error', 'No tiene una organización asignada.');
            }
        }
        
        // Validate organization_id if provided
        $organizationId = $this->request->getPost('organization_id');
        if ($organizationId && $this->auth->hasRole('superadmin')) {
            $currentOrgId = $organizationId;
        }
        
        if (!$currentOrgId) {
            return redirect()->back()->withInput()->with('error', 'Debe seleccionar una organización.');
        }
        
        $rules = [
            'business_name' => 'required|min_length[3]|max_length[100]',
            'legal_name' => 'required|min_length[3]|max_length[100]',
            'document_number' => 'required|min_length[8]|is_unique[clients.document_number]',
            'status' => 'required|in_list[active,inactive]'
        ];
        
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        
        $data = [
            'organization_id' => $currentOrgId,
            'business_name' => $this->request->getPost('business_name'),
            'legal_name' => $this->request->getPost('legal_name'),
            'document_number' => $this->request->getPost('document_number'),
            'external_id' => $this->request->getPost('external_id'),
            'contact_name' => $this->request->getPost('contact_name'),
            'contact_phone' => $this->request->getPost('contact_phone'),
            'address' => $this->request->getPost('address'),
            'ubigeo' => $this->request->getPost('ubigeo'),
            'zip_code' => $this->request->getPost('zip_code'),
            'latitude' => $this->request->getPost('latitude'),
            'longitude' => $this->request->getPost('longitude'),
            'status' => $this->request->getPost('status', 'active')
        ];
        
        try {
            $db = \Config\Database::connect();
            $db->transStart();
            
            // Insert client
            $client = $this->clientModel->insert($data);
            
            if ($client === false) {
                $db->transRollback();
                log_message('error', 'Error al crear cliente: ' . print_r($this->clientModel->errors(), true));
                return redirect()->back()->withInput()->with('error', 'Error de validación: ' . implode(', ', $this->clientModel->errors()));
            }
            
            // Get the newly created client with UUID
            $newClient = $this->clientModel->find($client);
            if (!$newClient) {
                $db->transRollback();
                log_message('error', 'No se pudo encontrar el cliente recién creado con ID: ' . $client);
                return redirect()->back()->withInput()->with('error', 'Error al recuperar el cliente creado');
            }
            
            // Handle portfolio assignments
            $portfolioUuids = $this->request->getPost('portfolio_ids');
            if ($portfolioUuids) {
                foreach ($portfolioUuids as $portfolioUuid) {
                    try {
                        $result = $db->table('client_portfolio')->insert([
                            'portfolio_uuid' => $portfolioUuid,
                            'client_uuid' => $newClient['uuid'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        if (!$result) {
                            throw new \Exception('Error al insertar en client_portfolio');
                        }
                    } catch (\Exception $e) {
                        log_message('error', 'Error al asignar cartera: ' . $e->getMessage());
                        $db->transRollback();
                        return redirect()->back()->withInput()->with('error', 'Error al asignar cartera: ' . $e->getMessage());
                    }
                }
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                log_message('error', 'Error en la transacción al crear cliente');
                return redirect()->back()->withInput()->with('error', 'Error en la transacción al crear el cliente');
            }
            
            return redirect()->to('/clients')->with('message', 'Cliente creado exitosamente');
            
        } catch (\Exception $e) {
            log_message('error', 'Excepción al crear cliente: ' . $e->getMessage());
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Error al crear el cliente: ' . $e->getMessage());
        }
    }
    
    public function create()
    {
        error_log("=== INICIO create() ===");

        // Permission check (enable to enforce role)
        // if (!$this->auth->hasRole(['superadmin', 'admin'])) {
        //     return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear clientes.');
        // }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organization options for dropdown (only for superadmin)
        if ($this->auth->hasRole('superadmin')) {
            $organizations = $this->organizationModel->getActiveOrganizations();
            $data['organizations'] = $organizations;
        }
        
        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        error_log("Organization ID: " . $organizationId);
        
        // Get portfolios for the dropdown
        $portfolios = $this->portfolioModel->where('organization_id', $organizationId)->findAll();
        $data['portfolios'] = $portfolios;
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            error_log("POST request detected");
            error_log("POST data: " . print_r($this->request->getPost(), true));
            
            // Validation
            $rules = [
                'business_name'   => 'required|min_length[3]|max_length[100]',
                'legal_name'      => 'required|min_length[3]|max_length[100]',
                'document_number' => 'required|min_length[3]|max_length[20]|is_unique[clients.document_number]',
            ];
            
            if (!$this->validate($rules)) {
                error_log("Validation failed: " . print_r($this->validator->getErrors(), true));
                return redirect()->back()
                               ->withInput()
                               ->with('errors', $this->validator->getErrors())
                               ->with('error', 'Por favor corrija los errores en el formulario.');
            }
            
            // Process form data
            try {
                error_log('=== INICIO CREACIÓN DE CLIENTE ===');
                
                // Determine organization_id
                $organizationId = $this->auth->organizationId();
                error_log('Organization ID from auth: ' . $organizationId);
                
                // If superadmin, allow selecting organization
                if ($this->auth->hasRole('superadmin') && $this->request->getPost('organization_id')) {
                    $organizationId = $this->request->getPost('organization_id');
                    error_log('Superadmin selected organization: ' . $organizationId);
                }
                
                // Prepare data
                $insertData = [
                    'organization_id' => $organizationId,
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
                
                error_log('Data to insert: ' . print_r($insertData, true));
                
                // Insert the client
                $client = $this->clientModel->insert($insertData);
                error_log('Insert result: ' . print_r($client, true));
                
                if ($client === false) {
                    $errors = $this->clientModel->errors();
                    error_log('Error al crear cliente: ' . print_r($errors, true));
                    throw new \Exception('Error de validación: ' . implode(', ', $errors));
                }
                
                // Get the newly created client with UUID
                $newClient = $this->clientModel->find($client);
                error_log('New client data: ' . print_r($newClient, true));
                
                if (!$newClient) {
                    throw new \Exception('No se pudo encontrar el cliente recién creado con ID: ' . $client);
                }
                
                // Handle portfolio assignments
                $portfolioIds = $this->request->getPost('portfolios');
                if ($portfolioIds) {
                    error_log('Portfolio IDs to assign: ' . print_r($portfolioIds, true));
                    
                    $db = \Config\Database::connect();
                    foreach ($portfolioIds as $portfolioId) {
                        try {
                            $result = $db->table('client_portfolio')->insert([
                                'portfolio_uuid' => $portfolioId,
                                'client_uuid' => $newClient['uuid'],
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                            error_log('Portfolio assignment result: ' . print_r($result, true));
                            
                            if (!$result) {
                                throw new \Exception('Error al insertar en client_portfolio');
                            }
                        } catch (\Exception $e) {
                            log_message('error', 'Error al asignar cartera: ' . $e->getMessage());
                            $db->transRollback();
                            return redirect()->back()->withInput()->with('error', 'Error al asignar cartera: ' . $e->getMessage());
                        }
                    }
                }
                
                error_log('=== FIN CREACIÓN DE CLIENTE ===');
                return redirect()->to('/clients')->with('message', 'Cliente creado exitosamente');
                
            } catch (\Exception $e) {
                error_log('Excepción al crear cliente: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'Error al crear el cliente: ' . $e->getMessage());
            }
        }
        
        error_log("=== FIN create() ===");
        return view('clients/create', $data);
    }
    
    public function edit($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/clients')->with('error', 'UUID de cliente no proporcionado.');
        }

        // Get client
        $client = $this->clientModel->where('uuid', $uuid)->first();
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }

        // Check organization permissions
        if (!$this->auth->hasRole('superadmin') && $client['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para editar este cliente.');
        }

        if ($this->request->getMethod() === 'post') {
            $rules = [
                'business_name' => 'required|min_length[3]|max_length[100]',
                'legal_name' => 'required|min_length[3]|max_length[100]',
                'document_number' => 'required|min_length[8]',
                'status' => 'required|in_list[active,inactive]'
            ];

            if (!$this->validate($rules)) {
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }

            $data = [
                'business_name' => $this->request->getPost('business_name'),
                'legal_name' => $this->request->getPost('legal_name'),
                'document_number' => $this->request->getPost('document_number'),
                'external_id' => $this->request->getPost('external_id'),
                'contact_name' => $this->request->getPost('contact_name'),
                'contact_phone' => $this->request->getPost('contact_phone'),
                'address' => $this->request->getPost('address'),
                'ubigeo' => $this->request->getPost('ubigeo'),
                'zip_code' => $this->request->getPost('zip_code'),
                'latitude' => $this->request->getPost('latitude'),
                'longitude' => $this->request->getPost('longitude'),
                'status' => $this->request->getPost('status')
            ];

            try {
                if (!$this->clientModel->update($client['uuid'], $data)) {
                    return redirect()->back()->withInput()->with('error', 'Error al actualizar el cliente: ' . implode(', ', $this->clientModel->errors()));
                }

                return redirect()->to('/clients')->with('message', 'Cliente actualizado exitosamente.');

            } catch (\Exception $e) {
                return redirect()->back()->withInput()->with('error', 'Error al actualizar el cliente: ' . $e->getMessage());
            }
        }

        // Get assigned portfolios for display only
        $assignedPortfolios = $this->clientModel->getPortfolios($client['uuid']);

        $data = [
            'client' => $client,
            'assignedPortfolios' => $assignedPortfolios,
            'auth' => $this->auth
        ];

        return view('clients/edit', $data);
    }
    
    public function update($uuid)
    {
        $client = $this->clientModel->where('uuid', $uuid)->first();
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && $client['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para actualizar este cliente.');
        }
        
        $rules = [
            'business_name' => 'required|min_length[3]',
            'legal_name' => 'required|min_length[3]',
            'document_number' => 'required|min_length[8]',
            'status' => 'required|in_list[active,inactive]'
        ];
        
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        
        $data = [
            'business_name' => $this->request->getPost('business_name'),
            'legal_name' => $this->request->getPost('legal_name'),
            'document_number' => $this->request->getPost('document_number'),
            'external_id' => $this->request->getPost('external_id'),
            'contact_name' => $this->request->getPost('contact_name'),
            'contact_phone' => $this->request->getPost('contact_phone'),
            'address' => $this->request->getPost('address'),
            'ubigeo' => $this->request->getPost('ubigeo'),
            'zip_code' => $this->request->getPost('zip_code'),
            'latitude' => $this->request->getPost('latitude'),
            'longitude' => $this->request->getPost('longitude'),
            'status' => $this->request->getPost('status')
        ];
        
        // Only superadmin can change organization
        if ($this->auth->hasRole('superadmin')) {
            $data['organization_id'] = $this->request->getPost('organization_id');
        }
        
        try {
            $db = \Config\Database::connect();
            $db->transStart();
            
            // Update client data
            $result = $this->clientModel->update($client['id'], $data);
            
            if ($result === false) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', 'Error al actualizar el cliente: ' . implode(', ', $this->clientModel->errors()));
            }
            
            // Update portfolio assignments
            $portfolioIds = $this->request->getPost('portfolio_ids');
            if ($portfolioIds !== null) {
                // Delete existing assignments
                $db->table('client_portfolio')->where('client_uuid', $client['uuid'])->delete();
                
                // Insert new assignments
                foreach ($portfolioIds as $portfolioId) {
                    try {
                        $result = $db->table('client_portfolio')->insert([
                            'portfolio_uuid' => $portfolioId,
                            'client_uuid' => $client['uuid'],
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s')
                        ]);
                        if (!$result) {
                            throw new \Exception('Error al insertar en client_portfolio');
                        }
                    } catch (\Exception $e) {
                        log_message('error', 'Error al asignar cartera: ' . $e->getMessage());
                        $db->transRollback();
                        return redirect()->back()->withInput()->with('error', 'Error al asignar cartera: ' . $e->getMessage());
                    }
                }
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', 'Error al actualizar las carteras del cliente.');
            }
            
            return redirect()->to('/clients/' . $uuid)->with('message', 'Cliente actualizado exitosamente');
            
        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Error al actualizar el cliente: ' . $e->getMessage());
        }
    }
    
    public function delete($uuid)
    {
        $client = $this->clientModel->where('uuid', $uuid)->first();
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && $client['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para eliminar este cliente.');
        }
        
        try {
            $result = $this->clientModel->delete($client['id']);
            
            if ($result === false) {
                return redirect()->back()->with('error', 'Error al eliminar el cliente: ' . implode(', ', $this->clientModel->errors()));
            }
            
            return redirect()->to('/clients')->with('message', 'Cliente eliminado exitosamente');
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error al eliminar el cliente: ' . $e->getMessage());
        }
    }
    
    public function view($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/clients')->with('error', 'UUID de cliente no proporcionado.');
        }

        // Get client
        $client = $this->clientModel->where('uuid', $uuid)->first();
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }

        // Check organization permissions
        if (!$this->auth->hasRole('superadmin') && $client['organization_id'] !== $this->auth->organizationId()) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para ver este cliente.');
        }

        // Get assigned portfolios
        $assignedPortfolios = $this->clientModel->getPortfolios($client['uuid']);

        // Get organization info
        $organizationModel = new \App\Models\OrganizationModel();
        $organization = $organizationModel->find($client['organization_id']);

        $data = [
            'client' => $client,
            'organization' => $organization,
            'assignedPortfolios' => $assignedPortfolios,
            'auth' => $this->auth
        ];

        return view('clients/view', $data);
    }
    
    public function import()
    {
        // GET request shows the import form
        if ($this->request->getMethod() === 'get') {
            $data = [
                'auth' => $this->auth
            ];

            // If superadmin and no organization context, load organizations
            if ($this->auth->hasRole('superadmin') && !$this->auth->organizationId()) {
                $data['organizations'] = $this->organizationModel->findAll();
            }

            // Load available users (collectors) based on role and organization
            if ($this->auth->hasRole('superadmin')) {
                if ($this->auth->organizationId()) {
                    $data['users'] = $this->getUsersByOrganization($this->auth->organizationId());
                }
            } else {
                $data['users'] = $this->getUsersByOrganization($this->auth->user()['organization_id']);
            }

            return view('clients/import', $data);
        }

        // POST request processes the import
        if ($this->request->getMethod() === 'post') {
            // Get the uploaded file
            $file = $this->request->getFile('csv_file');
            
            // Check if a file was uploaded
            if (!$file || !$file->isValid()) {
                return redirect()->back()->with('error', 'No se ha subido ningún archivo o el archivo no es válido.');
            }
            
            // Check file extension
            if ($file->getClientExtension() !== 'csv') {
                return redirect()->back()->with('error', 'El archivo debe ser CSV.');
            }

            try {
                // Move file to writable directory
                $file->move(WRITEPATH . 'uploads');
                $fileName = $file->getName();
                
                // Get organization ID based on role
                $organizationId = $this->auth->hasRole('superadmin') 
                    ? ($this->request->getPost('organization_id') ?? $this->auth->organizationId())
                    : $this->auth->user()['organization_id'];

                if (!$organizationId) {
                    throw new \Exception('No se ha seleccionado una organización.');
                }

                // Get selected collector (user_id) if any
                $userId = $this->request->getPost('user_id');
                
                // Process CSV file
                $importedCount = 0;
                $errors = [];
                
                if (($handle = fopen(WRITEPATH . 'uploads/' . $fileName, "r")) !== FALSE) {
                    $header = fgetcsv($handle); // Get header row
                    
                    // Normalize header names
                    $header = array_map(function($field) {
                        return trim(strtolower($field));
                    }, $header);
                    
                    // Required fields check
                    $requiredFields = ['nombre_comercial', 'razon_social', 'documento'];
                    $missingFields = array_diff($requiredFields, $header);
                    
                    if (!empty($missingFields)) {
                        throw new \Exception('Faltan campos requeridos: ' . implode(', ', $missingFields));
                    }
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        // Skip empty rows
                        if (empty(array_filter($data))) {
                            continue;
                        }

                        // Map CSV data to client fields
                        $rowData = array_combine($header, $data);
                        
                        try {
                            // Prepare client data
                            $clientData = [
                                'organization_id' => $organizationId,
                                'business_name'   => $rowData['nombre_comercial'] ?? '',
                                'legal_name'      => $rowData['razon_social'] ?? '',
                                'document_number' => $rowData['documento'] ?? '',
                                'contact_name'    => $rowData['contacto'] ?? '',
                                'contact_phone'   => $rowData['telefono'] ?? '',
                                'address'         => $rowData['direccion'] ?? '',
                                'ubigeo'          => $rowData['ubigeo'] ?? '',
                                'zip_code'        => $rowData['codigo_postal'] ?? '',
                                'latitude'        => $rowData['latitud'] ?? null,
                                'longitude'       => $rowData['longitud'] ?? null,
                                'external_id'     => $rowData['id_externo'] ?? '',
                                'status'          => 'active'
                            ];

                            // Check if client already exists
                            $existingClient = $this->clientModel
                                ->where('organization_id', $organizationId)
                                ->where('document_number', $clientData['document_number'])
                                ->first();

                            if ($existingClient) {
                                $errors[] = "Cliente con documento {$clientData['document_number']} ya existe.";
                                continue;
                            }

                            // Insert new client
                            $this->clientModel->insert($clientData);
                            $clientUuid = $this->clientModel->getInsertUUID();

                            // If a collector was selected, assign client to their portfolio
                            if ($userId) {
                                $portfolio = $this->portfolioModel->where('user_id', $userId)->first();
                                if ($portfolio) {
                                    $this->db->table('client_portfolio')->insert([
                                        'client_uuid' => $clientUuid,
                                        'portfolio_uuid' => $portfolio['uuid']
                                    ]);
                                }
                            }

                            $importedCount++;
                        } catch (\Exception $e) {
                            $errors[] = "Error en fila {$importedCount}: " . $e->getMessage();
                        }
                    }
                    fclose($handle);
                }
                
                // Delete the uploaded file
                unlink(WRITEPATH . 'uploads/' . $fileName);
                
                // Prepare response message
                $message = "Se importaron {$importedCount} clientes exitosamente.";
                if (!empty($errors)) {
                    $message .= " Hubo " . count($errors) . " errores.";
                    return redirect()->back()
                        ->with('warning', $message)
                        ->with('import_errors', $errors);
                }
                
                return redirect()->to('clients')->with('success', $message);
                
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
            }
        }
    }
    
    private function insertImportedClient($data)
    {
        // Required fields validation
        if (empty($data['business_name']) || empty($data['document_number'])) {
            throw new \Exception('Faltan campos requeridos (Razón Social o RUC)');
        }
        
        // Check if client already exists by document number
        $existingClient = $this->clientModel->where('document_number', $data['document_number'])->first();
        
        if ($existingClient) {
            throw new \Exception("Cliente con RUC {$data['document_number']} ya existe");
        }
        
        // Prepare client data
        $clientData = [
            'organization_id' => $this->auth->organizationId() ?: 1, // Default to org 1 if none set
            'business_name' => $data['business_name'],
            'legal_name' => $data['legal_name'] ?? $data['business_name'],
            'document_number' => $data['document_number'],
            'contact_name' => $data['contact_name'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'address' => $data['address'] ?? null,
            'ubigeo' => $data['ubigeo'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'status' => 'active'
        ];
        
        // Insert client
        $clientId = $this->clientModel->insert($clientData);
        
        if (!$clientId) {
            throw new \Exception('Error al insertar cliente en la base de datos');
        }
        
        return $clientId;
    }
    
    private function validateCsrfToken()
    {
        $csrfName = csrf_token();
        $csrfHash = csrf_hash();
        $postedHash = $this->request->getPost($csrfName);
        
        log_message('debug', 'CSRF Validation - Token: ' . $csrfName . ', Expected: ' . $csrfHash . ', Got: ' . $postedHash);
        
        return $postedHash === $csrfHash;
    }
    
    private function getUsersByOrganization($organizationId)
    {
        return $this->db->table('users')
            ->where('organization_id', $organizationId)
            ->where('role', 'collector')
            ->where('status', 'active')
            ->get()
            ->getResultArray();
    }
}

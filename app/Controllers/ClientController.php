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
    
    public function __construct()
    {
        $this->clientModel = new ClientModel();
        $this->organizationModel = new OrganizationModel();
        $this->portfolioModel = new PortfolioModel();
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
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
            $portfolios = $this->portfolioModel->getByUser($auth->user()['id']);
            log_message('debug', 'User has ' . count($portfolios) . ' portfolios');
            
            $clients = [];
            foreach ($portfolios as $portfolio) {
                $portfolioClients = $this->clientModel->getByPortfolio($portfolio['id']);
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
            $allClients = $this->clientModel->findAll();
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
            $clientId = $this->clientModel->insert($data);
            
            if ($clientId === false) {
                $db->transRollback();
                return redirect()->back()->withInput()->with('error', 'Error al crear el cliente: ' . implode(', ', $this->clientModel->errors()));
            }
            
            // Handle portfolio assignments
            $portfolioIds = $this->request->getPost('portfolio_ids');
            if ($portfolioIds) {
                foreach ($portfolioIds as $portfolioId) {
                    $db->table('client_portfolio')->insert([
                        'portfolio_id' => $portfolioId,
                        'client_id' => $clientId
                    ]);
                }
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                return redirect()->back()->withInput()->with('error', 'Error al asignar las carteras al cliente.');
            }
            
            return redirect()->to('/clients')->with('message', 'Cliente creado exitosamente');
            
        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Error al crear el cliente: ' . $e->getMessage());
        }
    }
    
    public function create()
    {
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
        
        // Get portfolios for the dropdown
        $portfolios = $this->portfolioModel->where('organization_id', $organizationId)->findAll();
        
        $data['portfolios'] = $portfolios;
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            // Log submission data
            log_message('info', 'Client form submitted: ' . json_encode($this->request->getPost()));
            
            // Validation
            $rules = [
                'business_name'   => 'required|min_length[3]|max_length[100]',
                'legal_name'      => 'required|min_length[3]|max_length[100]',
                'document_number' => 'required|min_length[3]|max_length[20]',
            ];
            
            if (!$this->validate($rules)) {
                log_message('error', 'Validation failed: ' . json_encode($this->validator->getErrors()));
                
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON([
                        'success' => false,
                        'errors' => $this->validator->getErrors()
                    ]);
                }
                
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            }
            
            // Process form data
            try {
                // Determine organization_id
                $organizationId = $this->auth->organizationId();
                
                // If superadmin, allow selecting organization
                if ($this->auth->hasRole('superadmin') && $this->request->getPost('organization_id')) {
                    $organizationId = $this->request->getPost('organization_id');
                    log_message('info', 'Superadmin selected organization: ' . $organizationId);
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
                
                // Log before insert
                log_message('info', 'Inserting client data: ' . json_encode($insertData));
                
                // Insert the client
                $clientId = $this->clientModel->insert($insertData);
                
                if ($clientId) {
                    // Handle portfolio assignments
                    $portfolioIds = $this->request->getPost('portfolios');
                    if ($portfolioIds) {
                        $db = \Config\Database::connect();
                        foreach ($portfolioIds as $portfolioId) {
                            $db->table('client_portfolio')->insert([
                                'portfolio_id' => $portfolioId,
                                'client_id' => $clientId
                            ]);
                        }
                        log_message('info', 'Assigned client to portfolios: ' . json_encode($portfolioIds));
                    }
                    
                    if ($this->request->isAJAX()) {
                        return $this->response->setJSON([
                            'success' => true,
                            'message' => 'Cliente creado exitosamente',
                            'redirect' => site_url('clients'),
                            'client_id' => $clientId
                        ]);
                    }
                    
                    return redirect()->to('clients')->with('message', 'Cliente creado exitosamente');
                } else {
                    log_message('error', 'Failed to insert client');
                    
                    if ($this->request->isAJAX()) {
                        return $this->response->setJSON([
                            'success' => false,
                            'error' => 'Error al crear el cliente'
                        ]);
                    }
                    
                    return redirect()->back()->withInput()->with('error', 'Error al crear el cliente');
                }
            } catch (\Exception $e) {
                log_message('error', 'Exception creating client: ' . $e->getMessage());
                
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON([
                        'success' => false,
                        'error' => 'Error al crear el cliente: ' . $e->getMessage()
                    ]);
                }
                
                return redirect()->back()->withInput()->with('error', 'Error al crear el cliente: ' . $e->getMessage());
            }
        }
        
        return view('clients/create', $data);
    }
    
    public function edit($uuid)
    {
        $client = $this->clientModel->where('uuid', $uuid)->first();
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Check permissions
        if (!$this->auth->hasRole('superadmin') && $client['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/clients')->with('error', 'No tiene permisos para editar este cliente.');
        }
        
        // Get organizations for dropdown
        $organizations = [];
        
        if ($this->auth->hasRole('superadmin')) {
            $organizations = $this->organizationModel->findAll();
        } else {
            $organizations = [$this->organizationModel->find($this->auth->organizationId())];
        }
        
        // Get portfolios for dropdown
        $portfolios = $this->portfolioModel->where('organization_id', $client['organization_id'])->findAll();
        
        // Get assigned portfolios
        $db = \Config\Database::connect();
        $assignedPortfolios = $db->table('client_portfolio')
                                ->where('client_id', $client['id'])
                                ->get()
                                ->getResultArray();
        $assignedPortfolioIds = array_column($assignedPortfolios, 'portfolio_id');
        
        $data = [
            'client' => $client,
            'organizations' => $organizations,
            'portfolios' => $portfolios,
            'assignedPortfolioIds' => $assignedPortfolioIds,
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
                $db->table('client_portfolio')->where('client_id', $client['id'])->delete();
                
                // Insert new assignments
                foreach ($portfolioIds as $portfolioId) {
                    $db->table('client_portfolio')->insert([
                        'portfolio_id' => $portfolioId,
                        'client_id' => $client['id']
                    ]);
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
    
    public function view($uuid)
    {
        $client = $this->clientModel->where('uuid', $uuid)->first();
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado.');
        }
        
        // Get organization info
        $organization = $this->organizationModel->find($client['organization_id']);
        if (!$organization) {
            return redirect()->to('/clients')->with('error', 'Organización del cliente no encontrada.');
        }
        
        // Get portfolios for this client
        $portfolios = $this->portfolioModel->getByClient($client['id']);
        
        $data = [
            'client' => $client,
            'organization' => $organization,
            'portfolios' => $portfolios,
            'auth' => $this->auth
        ];
        
        return view('clients/view', $data);
    }
    
    public function import()
    {
        // Only allow POST for actual import
        if ($this->request->getMethod() === 'post') {
            // Get the uploaded file
            $file = $this->request->getFile('file');
            
            // Check if a file was uploaded
            if (!$file || !$file->isValid()) {
                return redirect()->back()->with('error', 'No se ha subido ningún archivo o el archivo no es válido.');
            }
            
            // Check file extension
            $ext = $file->getClientExtension();
            if ($ext !== 'csv' && $ext !== 'xlsx') {
                return redirect()->back()->with('error', 'El archivo debe ser CSV o Excel (XLSX).');
            }
            
            try {
                // Move file to writable directory
                $file->move(WRITEPATH . 'uploads');
                
                // Get the new file name (might be different from original)
                $fileName = $file->getName();
                
                // Process the file based on its type
                $importedCount = 0;
                $errors = [];
                
                if ($ext === 'csv') {
                    // Process CSV file
                    if (($handle = fopen(WRITEPATH . 'uploads/' . $fileName, "r")) !== FALSE) {
                        $header = fgetcsv($handle); // Get header row
                        
                        while (($data = fgetcsv($handle)) !== FALSE) {
                            // Map CSV data to client fields
                            $clientData = array_combine($header, $data);
                            
                            // Insert client
                            try {
                                $this->insertImportedClient($clientData);
                                $importedCount++;
                            } catch (\Exception $e) {
                                $errors[] = "Error en fila {$importedCount}: " . $e->getMessage();
                            }
                        }
                        fclose($handle);
                    }
                } else {
                    // Process Excel file using PhpSpreadsheet
                    $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                    $spreadsheet = $reader->load(WRITEPATH . 'uploads/' . $fileName);
                    $worksheet = $spreadsheet->getActiveSheet();
                    
                    $header = [];
                    foreach ($worksheet->getRowIterator() as $row) {
                        $cellIterator = $row->getCellIterator();
                        $cellIterator->setIterateOnlyExistingCells(FALSE);
                        
                        // Get header row
                        if ($row->getRowIndex() === 1) {
                            foreach ($cellIterator as $cell) {
                                $header[] = $cell->getValue();
                            }
                            continue;
                        }
                        
                        // Process data rows
                        $rowData = [];
                        foreach ($cellIterator as $cell) {
                            $rowData[] = $cell->getValue();
                        }
                        
                        // Skip empty rows
                        if (empty(array_filter($rowData))) {
                            continue;
                        }
                        
                        // Map Excel data to client fields
                        $clientData = array_combine($header, $rowData);
                        
                        // Insert client
                        try {
                            $this->insertImportedClient($clientData);
                            $importedCount++;
                        } catch (\Exception $e) {
                            $errors[] = "Error en fila {$importedCount}: " . $e->getMessage();
                        }
                    }
                }
                
                // Delete the uploaded file
                unlink(WRITEPATH . 'uploads/' . $fileName);
                
                // Prepare response message
                $message = "Se importaron {$importedCount} clientes exitosamente.";
                if (!empty($errors)) {
                    $message .= " Hubo " . count($errors) . " errores.";
                }
                
                if (!empty($errors)) {
                    return redirect()->back()->with('warning', $message)->with('import_errors', $errors);
                } else {
                    return redirect()->back()->with('success', $message);
                }
                
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
            }
        }
        
        // Show import form for GET requests
        return view('clients/import');
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
}

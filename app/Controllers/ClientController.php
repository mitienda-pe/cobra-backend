<?php

namespace App\Controllers;

use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class ClientController extends BaseController
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
                
                // Insert the client
                $clientId = $clientModel->insert($insertData);
                
                if ($clientId) {
                    // Handle portfolio assignments
                    $portfolioIds = $this->request->getPost('portfolios');
                    if ($portfolioIds) {
                        $db = \Config\Database::connect();
                        foreach ($portfolioIds as $portfolioId) {
                            $db->table('portfolio_clients')->insert([
                                'portfolio_id' => $portfolioId,
                                'client_id' => $clientId
                            ]);
                        }
                        log_message('info', 'Assigned client to portfolios: ' . json_encode($portfolioIds));
                    }
                    
                    return $this->response->setJSON([
                        'success' => true,
                        'message' => 'Cliente creado exitosamente',
                        'client_id' => $clientId
                    ]);
                } else {
                    log_message('error', 'Failed to insert client');
                    return $this->response->setJSON([
                        'success' => false,
                        'error' => 'Error al crear el cliente'
                    ]);
                }
            } catch (\Exception $e) {
                log_message('error', 'Exception creating client: ' . $e->getMessage());
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Error al crear el cliente: ' . $e->getMessage()
                ]);
            }
        }
        
        return view('clients/create', $data);
    }
    
    public function edit($id = null)
    {
        if (!$id) {
            return redirect()->to('/clients')->with('error', 'ID de cliente no especificado');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado');
        }
        
        // Get organization options for dropdown (only for superadmin)
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $organizations = $organizationModel->getActiveOrganizations();
            $data['organizations'] = $organizations;
        }
        
        // Get portfolios for the dropdown
        $portfolioModel = new PortfolioModel();
        $organizationId = $client['organization_id'];
        
        if ($organizationId) {
            $portfolios = $portfolioModel->where('organization_id', $organizationId)->findAll();
        } else {
            $portfolios = $portfolioModel->findAll();
        }
        
        // Get current portfolio assignments
        $db = \Config\Database::connect();
        $currentPortfolios = $db->table('portfolio_clients')
            ->where('client_id', $id)
            ->get()
            ->getResultArray();
        
        $selectedPortfolios = array_column($currentPortfolios, 'portfolio_id');
        
        $data = [
            'client' => $client,
            'portfolios' => $portfolios,
            'selectedPortfolios' => $selectedPortfolios,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            // Validation
            $rules = [
                'business_name'   => 'required|min_length[3]|max_length[100]',
                'legal_name'      => 'required|min_length[3]|max_length[100]',
                'document_number' => 'required|min_length[3]|max_length[20]',
            ];
            
            if (!$this->validate($rules)) {
                return view('clients/edit', array_merge($data, ['validation' => $this->validator]));
            }
            
            try {
                // Determine organization_id
                $organizationId = $client['organization_id']; // Keep existing organization by default
                
                // If superadmin, allow changing organization
                if ($this->auth->hasRole('superadmin') && $this->request->getPost('organization_id')) {
                    $organizationId = $this->request->getPost('organization_id');
                }
                
                // Update data
                $updateData = [
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
                ];
                
                if ($clientModel->update($id, $updateData)) {
                    // Update portfolio assignments
                    $newPortfolios = $this->request->getPost('portfolios') ?: [];
                    
                    // Remove existing assignments
                    $db->table('portfolio_clients')->where('client_id', $id)->delete();
                    
                    // Add new assignments
                    foreach ($newPortfolios as $portfolioId) {
                        $db->table('portfolio_clients')->insert([
                            'portfolio_id' => $portfolioId,
                            'client_id' => $id
                        ]);
                    }
                    
                    return redirect()->to('/clients')->with('success', 'Cliente actualizado exitosamente');
                } else {
                    return redirect()->back()->withInput()->with('error', 'Error al actualizar el cliente');
                }
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->with('error', 'Error al actualizar el cliente: ' . $e->getMessage());
            }
        }
        
        return view('clients/edit', $data);
    }
    
    public function delete($id = null)
    {
        if (!$id) {
            return redirect()->to('/clients')->with('error', 'ID de cliente no especificado');
        }
        
        $clientModel = new ClientModel();
        
        try {
            if ($clientModel->delete($id)) {
                return redirect()->to('/clients')->with('success', 'Cliente eliminado exitosamente');
            } else {
                return redirect()->to('/clients')->with('error', 'Error al eliminar el cliente');
            }
        } catch (\Exception $e) {
            return redirect()->to('/clients')->with('error', 'Error al eliminar el cliente: ' . $e->getMessage());
        }
    }
    
    public function view($id = null)
    {
        if (!$id) {
            return redirect()->to('/clients')->with('error', 'ID de cliente no especificado');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return redirect()->to('/clients')->with('error', 'Cliente no encontrado');
        }
        
        // Get portfolios for this client
        $db = \Config\Database::connect();
        $portfolios = $db->table('portfolios')
            ->select('portfolios.*, portfolio_clients.created_at as assigned_date')
            ->join('portfolio_clients', 'portfolios.id = portfolio_clients.portfolio_id')
            ->where('portfolio_clients.client_id', $id)
            ->get()
            ->getResultArray();
        
        $data = [
            'client' => $client,
            'portfolios' => $portfolios
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
        $clientModel = new ClientModel();
        
        // Required fields validation
        if (empty($data['business_name']) || empty($data['document_number'])) {
            throw new \Exception('Faltan campos requeridos (Razón Social o RUC)');
        }
        
        // Check if client already exists by document number
        $existingClient = $clientModel->where('document_number', $data['document_number'])->first();
        
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
        $clientId = $clientModel->insert($clientData);
        
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

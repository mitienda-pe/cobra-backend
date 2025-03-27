<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
use App\Models\ClientModel;
use App\Models\OrganizationModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;
use CodeIgniter\Controller;

class InvoiceController extends Controller
{
    use OrganizationTrait;
    
    protected $auth;
    protected $session;
    protected $invoiceModel;
    protected $clientModel;
    protected $organizationModel;
    protected $portfolioModel;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        $this->invoiceModel = new InvoiceModel();
        $this->clientModel = new ClientModel();
        $this->organizationModel = new OrganizationModel();
        $this->portfolioModel = new PortfolioModel();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        log_message('debug', '====== INVOICES INDEX ======');
        
        // Refresh organization context from session
        $currentOrgId = $this->refreshOrganizationContext();
        
        $invoiceModel = new InvoiceModel();
        $auth = $this->auth;
        
        // Filter invoices based on role
        if ($auth->hasRole('superadmin')) {
            // Superadmin can see all invoices or filter by organization
            if ($currentOrgId) {
                // Use the trait method to apply organization filter
                $this->applyOrganizationFilter($invoiceModel, $currentOrgId);
                $invoices = $invoiceModel->findAll();
                log_message('debug', 'SQL Query: ' . $invoiceModel->getLastQuery()->getQuery());
                log_message('debug', 'Superadmin fetched ' . count($invoices) . ' invoices for organization ' . $currentOrgId);
            } else {
                $invoices = $invoiceModel->findAll();
                log_message('debug', 'Superadmin fetched all ' . count($invoices) . ' invoices');
            }
        } else if ($auth->hasRole('admin')) {
            // Admin can see all invoices from their organization
            $adminOrgId = $auth->user()['organization_id']; // Always use admin's fixed organization
            $invoices = $invoiceModel->getByOrganization($adminOrgId);
            log_message('debug', 'Admin fetched ' . count($invoices) . ' invoices for organization ' . $adminOrgId);
        } else {
            // Regular users can only see invoices from their portfolios
            $invoices = $invoiceModel->getByUser($auth->user()['id']);
            log_message('debug', 'User fetched ' . count($invoices) . ' invoices from portfolios');
        }
        
        // Get statuses for filtering
        $status = $this->request->getGet('status');
        if ($status) {
            $filteredInvoices = array_filter($invoices, function($invoice) use ($status) {
                return $invoice['status'] === $status;
            });
            $invoices = array_values($filteredInvoices);
            log_message('debug', 'Filtered to ' . count($invoices) . ' invoices with status: ' . $status);
        }
        
        // Get client information and payment information for display
        $clientModel = new ClientModel();
        foreach ($invoices as &$invoice) {
            // Add client info
            $client = $clientModel->find($invoice['client_id']);
            if ($client) {
                $invoice['client_name'] = $client['business_name'];
                $invoice['document_number'] = $client['document_number'];
                $invoice['client_organization_id'] = $client['organization_id'];
            } else {
                $invoice['client_name'] = 'Cliente no encontrado';
                $invoice['document_number'] = '';
                $invoice['client_organization_id'] = null;
            }
            
            // Add payment info for each invoice
            if ($invoice['status'] === 'pending') {
                $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                $invoice['total_paid'] = $paymentInfo['total_paid'];
                $invoice['remaining_amount'] = $paymentInfo['remaining'];
                $invoice['payment_percentage'] = ($paymentInfo['total_paid'] / $invoice['amount']) * 100;
                $invoice['has_partial_payment'] = ($paymentInfo['total_paid'] > 0);
            }
        }
        
        // If no invoices found with role-based filtering, log this info
        if (empty($invoices)) {
            $allInvoices = $invoiceModel->findAll();
            log_message('debug', 'No invoices found with filtering. Total invoices in database: ' . count($allInvoices));
            
            // For debugging, log all available organizations
            $db = \Config\Database::connect();
            $orgs = $db->table('organizations')->get()->getResultArray();
            log_message('debug', 'Available organizations: ' . json_encode(array_column($orgs, 'id')));
        }
        
        // Initialize view data
        $data = [
            'invoices' => $invoices,
            'status' => $status,
        ];
        
        // Use the trait to prepare organization-related data for the view
        $data = $this->prepareOrganizationData($data);
        
        return view('invoices/index', $data);
    }
    
    public function create()
    {
        // Only admins and superadmins can create invoices
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear facturas.');
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organizations for superadmin dropdown
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        
        // Get clients for the selected organization
        $clientModel = new ClientModel();
        if (!empty($organizationId)) {
            // Hard-code the organization_id to the SQL query to make sure it's filtering correctly
            $db = \Config\Database::connect();
            $builder = $db->table('clients');
            $builder->where('organization_id', $organizationId);
            $builder->where('status', 'active');
            $builder->where('deleted_at IS NULL');
            $clients = $builder->get()->getResultArray();
            
            // Log the SQL query and results
            log_message('info', 'SQL Query: ' . $db->getLastQuery());
            log_message('info', 'Found ' . count($clients) . ' clients for organization ' . $organizationId);
            
            // Verify each client has the correct organization_id
            foreach ($clients as $index => $client) {
                // Double-check the client belongs to the selected organization
                if ($client['organization_id'] != $organizationId) {
                    log_message('error', 'Mismatched client found: Client ID ' . $client['id'] . 
                                ' has organization_id ' . $client['organization_id'] . 
                                ' but should have ' . $organizationId);
                    // Remove it from the results
                    unset($clients[$index]);
                } else {
                    log_message('info', 'Valid client: ID ' . $client['id'] . ', Name: ' . 
                               $client['business_name'] . ', Org ID: ' . $client['organization_id']);
                }
            }
            
            // Reset array keys and assign to data
            $data['clients'] = array_values($clients);
        } else {
            $data['clients'] = [];
            log_message('info', 'No organization ID provided, clients list is empty');
        }
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'client_id'      => 'required|is_natural_no_zero',
                'invoice_number' => 'required|max_length[50]',
                'concept'        => 'required|max_length[255]',
                'amount'         => 'required|numeric',
                'due_date'       => 'required|valid_date',
                'external_id'    => 'permit_empty|max_length[36]',
                'notes'          => 'permit_empty',
            ];
            
            // Add organization_id rule for superadmins
            if ($this->auth->hasRole('superadmin')) {
                $rules['organization_id'] = 'required|is_natural_no_zero';
            }
            
            if ($this->validate($rules)) {
                $invoiceModel = new InvoiceModel();
                
                // Get organization_id based on role
                $invoiceOrgId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $this->auth->organizationId();
                
                $invoiceNumber = $this->request->getPost('invoice_number');
                
                // Check if invoice number already exists in this organization
                $existingInvoice = $invoiceModel->where('organization_id', $invoiceOrgId)
                    ->where('invoice_number', $invoiceNumber)
                    ->first();
                
                if ($existingInvoice) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Ya existe una factura con este número en la organización.');
                }
                
                // Prepare invoice data
                $invoiceData = [
                    'organization_id' => $invoiceOrgId,
                    'client_id'      => $this->request->getPost('client_id'),
                    'invoice_number' => $invoiceNumber,
                    'concept'        => $this->request->getPost('concept'),
                    'amount'         => $this->request->getPost('amount'),
                    'due_date'       => $this->request->getPost('due_date'),
                    'external_id'    => $this->request->getPost('external_id') ?: null,
                    'notes'          => $this->request->getPost('notes') ?: null,
                    'status'         => 'pending',
                ];
                
                try {
                    if ($invoiceModel->insert($invoiceData)) {
                        return redirect()->to('/invoices')
                            ->with('success', 'Factura creada exitosamente.');
                    } else {
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Error al crear la factura.');
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Error creating invoice: ' . $e->getMessage());
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Error al crear la factura: ' . $e->getMessage());
                }
            } else {
                return view('invoices/create', array_merge($data, ['validation' => $this->validator]));
            }
        }
        
        return view('invoices/create', $data);
    }
    
    public function edit($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'UUID de factura no especificado');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->where('uuid', $uuid)->first();
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Get organizations for superadmin dropdown
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        // Get clients for the dropdown
        $clientModel = new ClientModel();
        $organizationId = $invoice['organization_id'];
        
        if ($organizationId) {
            $clients = $clientModel->where('organization_id', $organizationId)
                ->where('status', 'active')
                ->findAll();
        } else {
            $clients = [];
        }
        
        $data = [
            'invoice' => $invoice,
            'clients' => $clients,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'client_id'      => 'required|is_natural_no_zero',
                'invoice_number' => 'required|max_length[50]',
                'concept'        => 'required|max_length[255]',
                'amount'         => 'required|numeric',
                'due_date'       => 'required|valid_date',
                'external_id'    => 'permit_empty|max_length[36]',
                'notes'          => 'permit_empty',
            ];
            
            // Add organization_id rule for superadmins
            if ($this->auth->hasRole('superadmin')) {
                $rules['organization_id'] = 'required|is_natural_no_zero';
            }
            
            if ($this->validate($rules)) {
                // Get organization_id based on role
                $invoiceOrgId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $invoice['organization_id'];
                
                $invoiceNumber = $this->request->getPost('invoice_number');
                
                // Check if invoice number already exists in this organization (excluding current invoice)
                $existingInvoice = $invoiceModel->where('organization_id', $invoiceOrgId)
                    ->where('invoice_number', $invoiceNumber)
                    ->where('uuid !=', $uuid)
                    ->first();
                
                if ($existingInvoice) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Ya existe una factura con este número en la organización.');
                }
                
                // Prepare invoice data
                $invoiceData = [
                    'organization_id' => $invoiceOrgId,
                    'client_id'      => $this->request->getPost('client_id'),
                    'invoice_number' => $invoiceNumber,
                    'concept'        => $this->request->getPost('concept'),
                    'amount'         => $this->request->getPost('amount'),
                    'due_date'       => $this->request->getPost('due_date'),
                    'external_id'    => $this->request->getPost('external_id') ?: null,
                    'notes'          => $this->request->getPost('notes') ?: null,
                ];
                
                try {
                    if ($invoiceModel->update($uuid, $invoiceData)) {
                        return redirect()->to('/invoices')
                            ->with('success', 'Factura actualizada exitosamente.');
                    } else {
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Error al actualizar la factura.');
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Error updating invoice: ' . $e->getMessage());
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Error al actualizar la factura: ' . $e->getMessage());
                }
            } else {
                return view('invoices/edit', array_merge($data, ['validation' => $this->validator]));
            }
        }
        
        return view('invoices/edit', $data);
    }
    
    public function delete($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'UUID de factura no especificado');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->where('uuid', $uuid)->first();
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        try {
            if ($invoiceModel->delete($uuid)) {
                return redirect()->to('/invoices')->with('success', 'Factura eliminada exitosamente');
            } else {
                return redirect()->to('/invoices')->with('error', 'Error al eliminar la factura');
            }
        } catch (\Exception $e) {
            return redirect()->to('/invoices')->with('error', 'Error al eliminar la factura: ' . $e->getMessage());
        }
    }
    
    public function view($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'UUID de factura no especificado');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->where('uuid', $uuid)->first();
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Get payment information
        $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
        
        $data = [
            'invoice' => $invoice,
            'client' => $client,
            'payment_info' => $paymentInfo
        ];
        
        return view('invoices/view', $data);
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
                            // Map CSV data to invoice fields
                            $invoiceData = array_combine($header, $data);
                            
                            // Insert invoice
                            try {
                                $this->insertImportedInvoice($invoiceData);
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
                        
                        // Map Excel data to invoice fields
                        $invoiceData = array_combine($header, $rowData);
                        
                        // Insert invoice
                        try {
                            $this->insertImportedInvoice($invoiceData);
                            $importedCount++;
                        } catch (\Exception $e) {
                            $errors[] = "Error en fila {$importedCount}: " . $e->getMessage();
                        }
                    }
                }
                
                // Delete the uploaded file
                unlink(WRITEPATH . 'uploads/' . $fileName);
                
                // Prepare response message
                $message = "Se importaron {$importedCount} facturas exitosamente.";
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
        return view('invoices/import');
    }
    
    private function insertImportedInvoice($data)
    {
        $invoiceModel = new InvoiceModel();
        $clientModel = new ClientModel();
        
        // Required fields validation
        if (empty($data['invoice_number']) || empty($data['client_document']) || empty($data['amount'])) {
            throw new \Exception('Faltan campos requeridos (Número de factura, RUC del cliente o monto)');
        }
        
        // Find client by document number
        $client = $clientModel->where('document_number', $data['client_document'])->first();
        
        if (!$client) {
            throw new \Exception("Cliente con RUC {$data['client_document']} no encontrado");
        }
        
        // Check if invoice number already exists for this organization
        $existingInvoice = $invoiceModel->where('organization_id', $client['organization_id'])
            ->where('invoice_number', $data['invoice_number'])
            ->first();
        
        if ($existingInvoice) {
            throw new \Exception("Factura {$data['invoice_number']} ya existe en esta organización");
        }
        
        // Prepare invoice data
        $invoiceData = [
            'organization_id' => $client['organization_id'],
            'client_id'      => $client['id'],
            'invoice_number' => $data['invoice_number'],
            'concept'        => $data['concept'] ?? 'Importado',
            'amount'         => $data['amount'],
            'due_date'       => $data['due_date'] ?? date('Y-m-d'),
            'external_id'    => $data['external_id'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'status'         => 'pending'
        ];
        
        // Insert invoice
        $invoiceId = $invoiceModel->insert($invoiceData);
        
        if (!$invoiceId) {
            throw new \Exception('Error al insertar factura en la base de datos');
        }
        
        return $invoiceId;
    }
    
    /**
     * Get clients by organization
     */
    public function getClientsByOrganization($uuid = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['error' => 'Invalid request']);
        }

        if (!$uuid) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Se requiere el UUID de la organización'
            ]);
        }

        // Obtener la organización por UUID
        $organization = $this->organizationModel->where('uuid', $uuid)->first();
        if (!$organization) {
            return $this->response->setStatusCode(404)->setJSON([
                'status' => 'error',
                'message' => 'Organización no encontrada'
            ]);
        }

        // Verificar acceso a la organización
        $user = session()->get('user');
        if (!$user) {
            return $this->response->setStatusCode(401)->setJSON([
                'status' => 'error',
                'message' => 'Usuario no autenticado'
            ]);
        }

        // Solo superadmin puede ver clientes de cualquier organización
        // Los demás usuarios solo pueden ver clientes de su organización
        if ($user['role'] !== 'superadmin' && $user['organization_id'] != $organization['id']) {
            return $this->response->setStatusCode(403)->setJSON([
                'status' => 'error',
                'message' => 'No tiene acceso a los clientes de esta organización'
            ]);
        }

        $clients = $this->clientModel->where('organization_id', $organization['id'])
                                   ->where('status', 'active')
                                   ->orderBy('business_name', 'ASC')
                                   ->findAll();

        if (empty($clients)) {
            return $this->response->setJSON([
                'status' => 'error',
                'message' => 'No hay clientes disponibles para la organización seleccionada.',
                'clients' => []
            ]);
        }

        return $this->response->setJSON([
            'status' => 'success',
            'message' => 'Clientes encontrados',
            'clients' => $clients
        ]);
    }
}

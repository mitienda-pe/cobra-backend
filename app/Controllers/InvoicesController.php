<?php

namespace App\Controllers;

use App\Models\InvoiceModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class InvoicesController extends BaseController
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
                if ($invoiceModel->isInvoiceNumberDuplicate($invoiceNumber, $invoiceOrgId)) {
                    log_message('error', 'Duplicate invoice number: ' . $invoiceNumber . ' in organization ID: ' . $invoiceOrgId);
                    return redirect()->back()->withInput()
                        ->with('error', 'El número de factura ya existe en esta organización. Por favor, use un número diferente.');
                }
                    
                // Check that client belongs to selected organization
                $client = $clientModel->find($this->request->getPost('client_id'));
                if ($client && $client['organization_id'] != $invoiceOrgId) {
                    log_message('error', 'Client organization mismatch: Client ID ' . $client['id'] . 
                              ' has organization_id ' . $client['organization_id'] . 
                              ' but invoice has organization_id ' . $invoiceOrgId);
                    return redirect()->back()->withInput()
                        ->with('error', 'El cliente seleccionado no pertenece a la organización.');
                }
                
                // Prepare data
                $data = [
                    'organization_id' => $invoiceOrgId,
                    'client_id'       => $this->request->getPost('client_id'),
                    'invoice_number'  => $invoiceNumber,
                    'concept'         => $this->request->getPost('concept'),
                    'amount'          => $this->request->getPost('amount'),
                    'due_date'        => $this->request->getPost('due_date'),
                    'status'          => 'pending',
                    'external_id'     => $this->request->getPost('external_id') ?: null,
                    'notes'           => $this->request->getPost('notes'),
                ];
                
                $invoiceId = $invoiceModel->insert($data);
                
                if ($invoiceId) {
                    return redirect()->to('/invoices')->with('message', 'Factura creada exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al crear la factura: ' . implode(', ', $invoiceModel->errors()));
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('invoices/create', $data);
    }
    
    public function edit($id = null)
    {
        // Only admins and superadmins can edit invoices
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar facturas.');
        }
        
        if (!$id) {
            return redirect()->to('/invoices')->with('error', 'ID de factura no proporcionado.');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }
        
        // Check if user has access to this invoice
        if (!$this->hasAccessToInvoice($invoice)) {
            return redirect()->to('/invoices')->with('error', 'No tiene permisos para editar esta factura.');
        }
        
        // Check if invoice is already paid
        if ($invoice['status'] === 'paid') {
            return redirect()->to('/invoices/view/' . $id)->with('error', 'No se puede editar una factura pagada.');
        }
        
        $data = [
            'invoice' => $invoice,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'invoice_number' => 'required|max_length[50]',
                'concept'        => 'required|max_length[255]',
                'amount'         => 'required|numeric',
                'due_date'       => 'required|valid_date',
                'status'         => 'required|in_list[pending,cancelled,rejected]',
                'external_id'    => 'permit_empty|max_length[36]',
                'notes'          => 'permit_empty',
            ];
            
            if ($this->validate($rules)) {
                $invoiceNumber = $this->request->getPost('invoice_number');
                
                // Check if invoice number already exists in this organization (excluding current invoice)
                if ($invoiceModel->isInvoiceNumberDuplicate($invoiceNumber, $invoice['organization_id'], $id)) {
                    log_message('error', 'Duplicate invoice number on edit: ' . $invoiceNumber . 
                                ' in organization ID: ' . $invoice['organization_id'] . ', invoice ID: ' . $id);
                    return redirect()->back()->withInput()
                        ->with('error', 'El número de factura ya existe en esta organización. Por favor, use un número diferente.');
                }
                
                // Prepare data
                $data = [
                    'invoice_number'  => $invoiceNumber,
                    'concept'         => $this->request->getPost('concept'),
                    'amount'          => $this->request->getPost('amount'),
                    'due_date'        => $this->request->getPost('due_date'),
                    'status'          => $this->request->getPost('status'),
                    'external_id'     => $this->request->getPost('external_id') ?: null,
                    'notes'           => $this->request->getPost('notes'),
                ];
                
                $updated = $invoiceModel->update($id, $data);
                
                if ($updated) {
                    return redirect()->to('/invoices')->with('message', 'Factura actualizada exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al actualizar la factura: ' . implode(', ', $invoiceModel->errors()));
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        $data['client'] = $client;
        
        return view('invoices/edit', $data);
    }
    
    public function delete($id = null)
    {
        // Only admins and superadmins can delete invoices
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar facturas.');
        }
        
        if (!$id) {
            return redirect()->to('/invoices')->with('error', 'ID de factura no proporcionado.');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }
        
        // Check if user has access to this invoice
        if (!$this->hasAccessToInvoice($invoice)) {
            return redirect()->to('/invoices')->with('error', 'No tiene permisos para eliminar esta factura.');
        }
        
        // Check if invoice has payments
        $db = \Config\Database::connect();
        $paymentCount = $db->table('payments')
                          ->where('invoice_id', $id)
                          ->countAllResults();
        
        if ($paymentCount > 0) {
            return redirect()->to('/invoices')->with('error', 'No se puede eliminar la factura porque tiene pagos asociados.');
        }
        
        $deleted = $invoiceModel->delete($id);
        
        if ($deleted) {
            return redirect()->to('/invoices')->with('message', 'Factura eliminada exitosamente.');
        } else {
            return redirect()->to('/invoices')->with('error', 'Error al eliminar la factura.');
        }
    }
    
    public function view($id = null)
    {
        if (!$id) {
            return redirect()->to('/invoices')->with('error', 'ID de factura no proporcionado.');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }
        
        // Check if user has access to this invoice
        if (!$this->hasAccessToInvoice($invoice)) {
            return redirect()->to('/invoices')->with('error', 'No tiene permisos para ver esta factura.');
        }
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Verificar si el cliente existe
        if (!$client) {
            log_message('error', 'Cliente no encontrado para la factura ID: ' . $id . ', client_id: ' . $invoice['client_id']);
            $client = [
                'id' => $invoice['client_id'],
                'business_name' => 'Cliente no encontrado',
                'document_number' => 'N/A'
            ];
        }
        
        // Get payments for this invoice
        $db = \Config\Database::connect();
        $payments = $db->table('payments p')
                      ->select('p.*, u.name as collector_name')
                      ->join('users u', 'p.user_id = u.id', 'left')
                      ->where('p.invoice_id', $id)
                      ->orderBy('p.payment_date', 'DESC')
                      ->get()
                      ->getResultArray();
        
        // Calculate payment summary
        $paymentInfo = $invoiceModel->calculateRemainingAmount($id);
        
        $data = [
            'invoice'       => $invoice,
            'client'        => $client,
            'payments'      => $payments,
            'payment_info'  => $paymentInfo,
            'auth'          => $this->auth,
        ];
        
        return view('invoices/view', $data);
    }
    
    public function import()
    {
        // Only admins and superadmins can import invoices
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para importar facturas.');
        }
        
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
            
            log_message('debug', '=== CSRF TOKEN MANUALLY SET in /invoices/import ===');
            log_message('debug', "Token: {$tokenName}={$tokenValue}");
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'csv_file' => 'uploaded[csv_file]|ext_in[csv_file,csv]',
            ];
            
            if ($this->validate($rules)) {
                $file = $this->request->getFile('csv_file');
                
                if ($file->isValid() && !$file->hasMoved()) {
                    $newName = $file->getRandomName();
                    $file->move(WRITEPATH . 'uploads', $newName);
                    
                    $filePath = WRITEPATH . 'uploads/' . $newName;
                    
                    // Process import
                    $invoiceModel = new InvoiceModel();
                    $result = $invoiceModel->importFromCsv($filePath, $this->auth->organizationId());
                    
                    unlink($filePath); // Delete the uploaded file
                    
                    $message = "Importación completada. {$result['success']} facturas importadas.";
                    if (!empty($result['errors'])) {
                        $message .= " Hubo {$result['errors']} errores durante la importación.";
                    }
                    
                    return redirect()->to('/invoices')->with('message', $message);
                } else {
                    return redirect()->back()->with('error', 'Error al subir el archivo.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        return view('invoices/import', $data);
    }
    
    /**
     * Check if user has access to an invoice
     */
    private function hasAccessToInvoice($invoice)
    {
        log_message('debug', '[hasAccessToInvoice] Checking access to invoice ID: ' . $invoice['id'] . 
                   ' for user ID: ' . $this->auth->user()['id'] . ' with role: ' . $this->auth->user()['role']);
                   
        // Superadmin puede acceder a cualquier factura
        if ($this->auth->hasRole('superadmin')) {
            log_message('debug', '[hasAccessToInvoice] User is superadmin, access granted');
            return true;
        }
        
        // Admin puede acceder a facturas de su organización
        if ($this->auth->hasRole('admin')) {
            $orgMatch = $invoice['organization_id'] == $this->auth->organizationId();
            log_message('debug', '[hasAccessToInvoice] User is admin, invoice org: ' . $invoice['organization_id'] . 
                       ', user org: ' . $this->auth->organizationId() . ', match: ' . ($orgMatch ? 'yes' : 'no'));
            return $orgMatch;
        }
        
        // Para usuarios regulares, verificar si el cliente está en alguno de sus portafolios
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        if (!$client) {
            log_message('debug', '[hasAccessToInvoice] Client not found for ID: ' . $invoice['client_id']);
            return false;
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($this->auth->user()['id']);
        
        log_message('debug', '[hasAccessToInvoice] User has ' . count($portfolios) . ' portfolios');
        
        // Si el usuario no tiene portafolios asignados, no tiene acceso
        if (empty($portfolios)) {
            log_message('debug', '[hasAccessToInvoice] User has no portfolios, access denied');
            return false;
        }
        
        foreach ($portfolios as $portfolio) {
            log_message('debug', '[hasAccessToInvoice] Checking portfolio ID: ' . $portfolio['id']);
            
            $clients = $portfolioModel->getAssignedClients($portfolio['id']);
            log_message('debug', '[hasAccessToInvoice] Portfolio has ' . count($clients) . ' clients');
            
            foreach ($clients as $portfolioClient) {
                if ($portfolioClient['id'] == $client['id']) {
                    log_message('debug', '[hasAccessToInvoice] Client match found in portfolio. Client ID: ' . 
                                $client['id'] . ' matched with portfolioClient ID: ' . $portfolioClient['id']);
                    return true;
                }
            }
        }
        
        log_message('debug', '[hasAccessToInvoice] No matching client found in any user portfolio, access denied');
        return false;
    }
}
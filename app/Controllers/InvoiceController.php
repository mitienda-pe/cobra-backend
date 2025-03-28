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
        
        // Get user data from auth
        $data = [
            'auth' => $this->auth
        ];
        
        // Get organization ID from Auth library or from query params for superadmin
        $organizationId = $this->auth->organizationId();
        if ($this->auth->hasRole('superadmin')) {
            $data['organizations'] = $this->organizationModel->findAll();
            $organizationId = $this->request->getGet('organization_id') ?: null;
            $data['selected_organization_id'] = $organizationId;
        }
        
        // Build the query
        $builder = $this->invoiceModel
            ->select('invoices.*, clients.business_name')
            ->join('clients', 'clients.id = invoices.client_id', 'left');
            
        if ($organizationId) {
            $builder->where('invoices.organization_id', $organizationId);
        }
        
        // Get invoices with pagination
        $data['invoices'] = $builder->orderBy('invoices.created_at', 'DESC')
                                  ->paginate(10);
        
        $data['pager'] = $this->invoiceModel->pager;
        
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
            'validation' => \Config\Services::validation()
        ];
        
        // Get organizations for superadmin dropdown
        if ($this->auth->hasRole('superadmin')) {
            $data['organizations'] = $this->organizationModel->findAll();
        }
        
        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        
        // Get clients for the selected organization
        if (!empty($organizationId)) {
            $clients = $this->clientModel->where('organization_id', $organizationId)
                                      ->where('status', 'active')
                                      ->where('deleted_at IS NULL')
                                      ->findAll();
            
            $data['clients'] = $clients;
        } else {
            $data['clients'] = [];
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
                'currency'       => 'required|in_list[PEN,USD]'
            ];
            
            // Add organization_id rule for superadmins
            if ($this->auth->hasRole('superadmin')) {
                $rules['organization_id'] = 'required|is_natural_no_zero';
            }
            
            if ($this->validate($rules)) {
                // Get organization_id based on role
                $invoiceOrgId = $this->auth->hasRole('superadmin')
                    ? $this->request->getPost('organization_id')
                    : $this->auth->organizationId();
                
                $invoiceNumber = $this->request->getPost('invoice_number');
                
                // Check if invoice number already exists in this organization
                $existingInvoice = $this->invoiceModel->where('organization_id', $invoiceOrgId)
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
                    'currency'       => $this->request->getPost('currency'),
                ];
                
                try {
                    if ($this->invoiceModel->insert($invoiceData)) {
                        return redirect()->to('/invoices')
                            ->with('success', 'Factura creada exitosamente.');
                    } else {
                        log_message('error', 'Error al crear factura: ' . json_encode($this->invoiceModel->errors()));
                        return redirect()->back()
                            ->withInput()
                            ->with('error', 'Error al crear la factura: ' . implode(', ', $this->invoiceModel->errors()));
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Error creating invoice: ' . $e->getMessage());
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Error al crear la factura: ' . $e->getMessage());
                }
            }
            
            // Si la validación falla, regresamos a la vista con los errores
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }
        
        return view('invoices/create', $data);
    }
    
    public function view($uuid)
    {
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->where('uuid', $uuid)->first();

        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        // Verificar que el usuario tenga acceso a esta factura
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            if ($invoice['organization_id'] !== $this->auth->user()->organization_id) {
                return redirect()->to('/invoices')->with('error', 'No tienes permiso para ver esta factura.');
            }
        }

        // Debug
        log_message('debug', 'Invoice data: ' . print_r($invoice, true));

        // Cargar el cliente
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);

        // Cargar pagos asociados
        $paymentModel = new \App\Models\PaymentModel();
        $payments = $paymentModel->select('payments.*, users.name as collector_name')
            ->join('users', 'users.id = payments.user_id')
            ->where('invoice_id', $invoice['id'])
            ->where('payments.deleted_at IS NULL')
            ->orderBy('payment_date', 'DESC')
            ->findAll();

        // Calcular totales
        $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
        $total_paid = floatval($paymentInfo['total_paid']);
        $remaining_amount = floatval($paymentInfo['remaining']);

        $data = [
            'auth' => $this->auth,
            'invoice' => $invoice,
            'client' => $client,
            'payments' => $payments,
            'total_paid' => $total_paid,
            'remaining_amount' => $remaining_amount
        ];

        return view('invoices/view', $data);
    }

    public function edit($uuid = null)
    {
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/invoices')->with('error', 'No tiene permisos para editar facturas.');
        }

        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        $invoice = $this->invoiceModel->where('uuid', $uuid)->first();
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        
        // Get clients for dropdown
        $clientModel = new ClientModel();
        $clients = $clientModel->where('organization_id', $organizationId)
                             ->where('status', 'active')
                             ->findAll();

        $data = [
            'auth' => $this->auth,
            'invoice' => $invoice,
            'clients' => $clients,
            'validation' => \Config\Services::validation()
        ];

        return view('invoices/edit', $data);
    }

    public function update($uuid = null)
    {
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/invoices')->with('error', 'No tiene permisos para editar facturas.');
        }

        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        $invoice = $this->invoiceModel->where('uuid', $uuid)->first();
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        // Validate form
        $rules = [
            'client_id' => 'required|is_natural_no_zero',
            'invoice_number' => 'required|max_length[50]',
            'concept' => 'required|max_length[255]',
            'amount' => 'required|numeric',
            'due_date' => 'required|valid_date',
            'currency' => 'required|in_list[PEN,USD]',
            'status' => 'required|in_list[pending,paid,cancelled,rejected,expired]',
            'external_id' => 'permit_empty|max_length[36]',
            'notes' => 'permit_empty'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->validator);
        }

        // Update invoice
        $data = [
            'client_id' => $this->request->getPost('client_id'),
            'invoice_number' => $this->request->getPost('invoice_number'),
            'concept' => $this->request->getPost('concept'),
            'amount' => $this->request->getPost('amount'),
            'due_date' => $this->request->getPost('due_date'),
            'currency' => $this->request->getPost('currency'),
            'status' => $this->request->getPost('status'),
            'external_id' => $this->request->getPost('external_id') ?: null,
            'notes' => $this->request->getPost('notes') ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($this->invoiceModel->update($invoice['id'], $data)) {
            return redirect()->to('/invoices/view/' . $uuid)
                           ->with('success', 'Factura actualizada correctamente.');
        }

        return redirect()->back()
                       ->withInput()
                       ->with('error', 'Error al actualizar la factura. Por favor intente nuevamente.');
    }

    public function delete($uuid = null)
    {
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/invoices')->with('error', 'No tiene permisos para eliminar facturas.');
        }

        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        $invoice = $this->invoiceModel->where('uuid', $uuid)->first();
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
        }

        if ($this->invoiceModel->delete($invoice['id'])) {
            return redirect()->to('/invoices')
                           ->with('success', 'Factura eliminada correctamente.');
        }

        return redirect()->to('/invoices')
                       ->with('error', 'Error al eliminar la factura. Por favor intente nuevamente.');
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

            return view('invoices/import', $data);
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
                    ? ($this->request->getVar('organization_id') ?? $this->auth->organizationId())
                    : $this->auth->organizationId();

                if (!$organizationId) {
                    throw new \Exception('No se ha seleccionado una organización.');
                }

                // Process CSV file
                $importedCount = 0;
                $errors = [];
                
                if (($handle = fopen(WRITEPATH . 'uploads/' . $fileName, "r")) !== FALSE) {
                    $header = fgetcsv($handle); // Get header row
                    
                    // Normalize header names
                    $header = array_map(function($field) {
                        return trim(strtolower($field));
                    }, $header);
                    
                    // Required fields check in header
                    $requiredFields = ['document_number', 'invoice_number', 'concept', 'amount', 'currency', 'due_date'];
                    $missingFields = array_diff($requiredFields, $header);
                    
                    if (!empty($missingFields)) {
                        throw new \Exception('El archivo CSV no tiene todas las columnas requeridas. Faltan: ' . implode(', ', $missingFields));
                    }
                    
                    // Start database transaction
                    $db = \Config\Database::connect();
                    $db->transStart();
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        // Skip empty rows
                        if (empty(array_filter($data))) {
                            continue;
                        }

                        // Create associative array from row data
                        $rowData = array_combine($header, $data);
                        
                        // Check for empty required fields in this row
                        $emptyFields = [];
                        foreach ($requiredFields as $field) {
                            if (!isset($rowData[$field]) || trim($rowData[$field]) === '') {
                                $emptyFields[] = $field;
                            }
                        }
                        
                        if (!empty($emptyFields)) {
                            throw new \Exception('Fila ' . ($importedCount + 1) . ': Campos requeridos vacíos: ' . implode(', ', $emptyFields));
                        }

                        try {
                            // Find client by document number
                            $client = $this->clientModel->where('document_number', $rowData['document_number'])
                                                      ->where('organization_id', $organizationId)
                                                      ->first();
                                                      
                            if (!$client) {
                                throw new \Exception("Cliente con documento {$rowData['document_number']} no encontrado");
                            }
                            
                            // Validate amount format
                            if (!is_numeric(str_replace(',', '.', $rowData['amount']))) {
                                throw new \Exception('El monto debe ser un número válido');
                            }
                            
                            // Validate currency
                            if (!in_array(strtoupper($rowData['currency']), ['PEN', 'USD'])) {
                                throw new \Exception('La moneda debe ser PEN o USD');
                            }
                            
                            // Validate date format
                            $dueDate = date('Y-m-d', strtotime($rowData['due_date']));
                            if ($dueDate === false) {
                                throw new \Exception('Formato de fecha inválido. Use YYYY-MM-DD');
                            }
                            
                            // Prepare invoice data
                            $invoiceData = [
                                'organization_id' => (int)$organizationId,
                                'client_id'      => (int)$client['id'],
                                'client_uuid'    => $client['uuid'],
                                'invoice_number' => $rowData['invoice_number'],
                                'concept'        => $rowData['concept'],
                                'amount'         => (float)str_replace(',', '.', $rowData['amount']),
                                'currency'       => strtoupper($rowData['currency']),
                                'due_date'       => $dueDate,
                                'external_id'    => $rowData['external_id'] ?? null,
                                'notes'          => $rowData['notes'] ?? null,
                                'status'         => 'pending'
                            ];
                            
                            // Check if invoice already exists
                            $existingInvoice = $this->invoiceModel->where('invoice_number', $invoiceData['invoice_number'])
                                                                ->where('organization_id', $organizationId)
                                                                ->first();
                                                                
                            if ($existingInvoice) {
                                throw new \Exception("La factura {$invoiceData['invoice_number']} ya existe");
                            }
                            
                            // Insert invoice
                            $result = $this->invoiceModel->insert($invoiceData);
                            if ($result === false) {
                                throw new \Exception('Error al insertar factura: ' . implode(', ', $this->invoiceModel->errors()));
                            }
                            
                            $importedCount++;
                            
                        } catch (\Exception $e) {
                            $errors[] = "Fila " . ($importedCount + 1) . ": " . $e->getMessage();
                        }
                    }
                    
                    fclose($handle);
                    
                    // Commit transaction if no errors
                    if (empty($errors)) {
                        $db->transComplete();
                        if ($db->transStatus() === false) {
                            throw new \Exception('Error en la transacción de la base de datos');
                        }
                        return redirect()->to('/invoices')->with('message', "Se importaron {$importedCount} facturas exitosamente.");
                    } else {
                        $db->transRollback();
                        return redirect()->back()->with('errors', $errors);
                    }
                    
                } else {
                    throw new \Exception('No se pudo abrir el archivo CSV.');
                }
                
            } catch (\Exception $e) {
                if (isset($db)) {
                    $db->transRollback();
                }
                return redirect()->back()->with('error', 'Error al procesar el archivo: ' . $e->getMessage());
            }
        }
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

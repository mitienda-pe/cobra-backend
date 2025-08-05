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
        
        // Get filter parameters
        $statusFilter = $this->request->getGet('status') ?: 'pending';
        $sortBy = $this->request->getGet('sort') ?: 'invoices.created_at';
        $sortOrder = $this->request->getGet('order') ?: 'DESC';
        
        // Build the query with additional fields
        $builder = $this->invoiceModel
            ->select('
                invoices.id, 
                invoices.uuid, 
                invoices.invoice_number, 
                invoices.concept, 
                invoices.amount, 
                invoices.total_amount, 
                invoices.due_date, 
                invoices.status, 
                invoices.created_at,
                invoices.issue_date,
                clients.business_name,
                clients.document_number as client_document,
                COUNT(instalments.id) as instalments_count
            ')
            ->join('clients', 'clients.id = invoices.client_id', 'left')
            ->join('instalments', 'instalments.invoice_id = invoices.id', 'left')
            ->groupBy('invoices.id');
            
        if ($organizationId) {
            $builder->where('invoices.organization_id', $organizationId);
        }
        
        // Apply status filter
        if ($statusFilter && $statusFilter !== 'all') {
            $builder->where('invoices.status', $statusFilter);
        }
        
        // Apply sorting - validate sort field for security
        $allowedSortFields = [
            'invoices.invoice_number',
            'invoices.created_at', 
            'invoices.issue_date',
            'invoices.due_date',
            'invoices.total_amount',
            'invoices.status',
            'clients.business_name',
            'clients.document_number'
        ];
        
        if (in_array($sortBy, $allowedSortFields)) {
            $order = (strtoupper($sortOrder) === 'ASC') ? 'ASC' : 'DESC';
            $builder->orderBy($sortBy, $order);
        } else {
            // Default sort by issue_date or created_at DESC
            $builder->orderBy('invoices.issue_date IS NOT NULL DESC, invoices.issue_date DESC, invoices.created_at DESC');
        }
        
        // Get invoices with pagination
        $data['invoices'] = $builder->paginate(15);
        $data['pager'] = $this->invoiceModel->pager;
        
        // Pass filter values to view
        $data['current_status'] = $statusFilter;
        $data['current_sort'] = $sortBy;
        $data['current_order'] = $sortOrder;
        
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
                'total_amount'         => 'required|numeric',
                'issue_date'     => 'required|valid_date',
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
                    'amount' => floatval(str_replace(',', '.', $this->request->getPost('total_amount'))),
                    'total_amount' => floatval(str_replace(',', '.', $this->request->getPost('total_amount'))),
                    'issue_date'     => $this->request->getPost('issue_date'),
                    'due_date'       => $this->request->getPost('due_date'),
                    'external_id'    => $this->request->getPost('external_id') ?: null,
                    'notes'          => $this->request->getPost('notes') ?: null,
                    'status'         => 'pending',
                    'currency'       => $this->request->getPost('currency'),
                ];
                
                try {
                    if ($this->invoiceModel->insert($invoiceData)) {
                        $invoiceId = $this->invoiceModel->getInsertID();
                        
                        // Siempre crear cuotas para la factura
                        $numInstalments = (int)$this->request->getPost('num_instalments');
                        
                        if ($numInstalments <= 0) {
                            $numInstalments = 1; // Asegurar al menos una cuota
                        }
                        
                        // Cargar el modelo de cuotas
                        $instalmentModel = new \App\Models\InstalmentModel();
                        
                        // Calcular monto por cuota
                        $totalAmount = (float)$invoiceData['total_amount'];
                        $instalmentAmount = round($totalAmount / $numInstalments, 2);
                        
                        // Ajustar el último monto para que sume exactamente el total
                        $lastInstalmentAmount = $totalAmount - ($instalmentAmount * ($numInstalments - 1));
                        
                        // Fecha base para las cuotas (fecha de vencimiento de la factura)
                        $baseDate = new \DateTime($invoiceData['due_date']);
                        
                        // Intervalo entre cuotas (solo se usa si hay más de una cuota)
                        $interval = ($numInstalments > 1) ? (int)$this->request->getPost('instalment_interval') : 0;
                        if ($interval <= 0) {
                            $interval = 30; // Valor predeterminado
                        }
                        
                        // Crear las cuotas
                        for ($i = 1; $i <= $numInstalments; $i++) {
                            // Para la primera cuota usamos la fecha de vencimiento de la factura
                            if ($i > 1) {
                                // Para las siguientes cuotas, añadimos el intervalo
                                $baseDate->modify("+{$interval} days");
                            }
                            
                            $dueDate = $baseDate->format('Y-m-d');
                            
                            // Determinar el monto de esta cuota
                            $amount = ($i == $numInstalments) ? $lastInstalmentAmount : $instalmentAmount;
                            
                            $instalmentData = [
                                'invoice_id' => $invoiceId,
                                'number' => $i,
                                'amount' => $amount,
                                'due_date' => $dueDate,
                                'status' => 'pending',
                                'notes' => ($numInstalments > 1) ? "Cuota {$i} de {$numInstalments}" : "Pago único"
                            ];
                            
                            $instalmentModel->insert($instalmentData);
                        }
                        
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
    
    public function view($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/invoices')->with('error', 'ID de factura no proporcionado.');
        }

        $invoice = $this->invoiceModel->where('uuid', $uuid)->first();
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
        $paymentInfo = $this->invoiceModel->calculateRemainingAmount($invoice['id']);
        $total_paid = floatval($paymentInfo['total_paid']);
        $remaining_amount = floatval($paymentInfo['remaining']);

        // Cargar cuotas asociadas a la factura
        $instalmentModel = new \App\Models\InstalmentModel();
        $instalments = $instalmentModel->getByInvoiceForCollection($invoice['id']);
        $has_instalments = !empty($instalments);
        
        // Si no hay cuotas, crear una cuota virtual para mostrar en la vista
        if (empty($instalments)) {
            $instalments[] = [
                'id' => 0,
                'invoice_id' => $invoice['id'],
                'invoice_number' => 1,
                'amount' => $invoice['total_amount'],
                'due_date' => $invoice['due_date'],
                'status' => $invoice['status'],
                'is_virtual' => true
            ];
            $has_instalments = false;
        } else {
            $has_instalments = true;
        }
        
        // Actualizar el estado de las cuotas según los pagos registrados
        foreach ($instalments as $key => $instalment) {
            $instalmentModel->updateStatus($instalment['id']);
            
            // Recargar la cuota con el estado actualizado
            $instalments[$key] = $instalmentModel->find($instalment['id']);
        }
        
        // Asegurarnos de que las cuotas estén ordenadas por número
        usort($instalments, function($a, $b) {
            return $a['number'] - $b['number'];
        });
        
        // Categorizar las cuotas para mostrar información adicional en la vista
        $today = date('Y-m-d');
        foreach ($instalments as &$instalment) {
            if (!is_array($instalment) || !isset($instalment['status']) || !isset($instalment['due_date'])) {
                continue;
            }
            // Determinar si es una cuota vencida
            $instalment['is_overdue'] = ($instalment['status'] !== 'paid' && $instalment['due_date'] < $today);
            
            // Determinar si es una cuota que se puede pagar (todas las anteriores están pagadas)
            $instalment['can_be_paid'] = isset($instalment['is_virtual']) ? true : $instalmentModel->canBePaid($instalment['id']);
            
            // Determinar si es una cuota futura (no se puede pagar aún)
            $instalment['is_future'] = !$instalment['can_be_paid'] && $instalment['status'] !== 'paid';
        }

        // Cargar QR hashes asociados a la factura
        $ligoQRHashModel = new \App\Models\LigoQRHashModel();
        $qrHashes = $ligoQRHashModel->where('invoice_id', $invoice['id'])
                                   ->orderBy('created_at', 'DESC')
                                   ->findAll();
        
        // Crear un array indexado por instalment_id para acceso rápido
        $qrHashesByInstalment = [];
        foreach ($qrHashes as $qrHash) {
            if ($qrHash['instalment_id']) {
                $qrHashesByInstalment[$qrHash['instalment_id']] = $qrHash;
            }
        }

        $data = [
            'auth' => $this->auth,
            'invoice' => $invoice,
            'client' => $client,
            'payments' => $payments,
            'total_paid' => $total_paid,
            'remaining_amount' => $remaining_amount,
            'instalments' => $instalments,
            'has_instalments' => $has_instalments,
            'qrHashes' => $qrHashes,
            'qrHashesByInstalment' => $qrHashesByInstalment
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

        // Get organization ID from invoice
        $organizationId = $invoice['organization_id'];
        
        // Get clients for dropdown
        $clientModel = new ClientModel();
        $clients = $clientModel->where('organization_id', $organizationId)
                             ->where('status', 'active')
                             ->findAll();

        // Get instalments info
        $instalmentModel = new \App\Models\InstalmentModel();
        $instalments = $instalmentModel->getByInvoice($invoice['id']);
        $numInstalments = count($instalments) > 0 ? count($instalments) : 1;
        $instalmentInterval = 30; // Valor por defecto
        
        // Si hay cuotas, calcular el intervalo entre la primera y la segunda (si existe)
        if (count($instalments) > 1) {
            $date1 = new \DateTime($instalments[0]['due_date']);
            $date2 = new \DateTime($instalments[1]['due_date']);
            $interval = $date1->diff($date2);
            $instalmentInterval = $interval->days;
        }

        $data = [
            'auth' => $this->auth,
            'invoice' => $invoice,
            'clients' => $clients,
            'num_instalments' => $numInstalments,
            'instalment_interval' => $instalmentInterval,
            'validation' => \Config\Services::validation()
        ];
        
        // Ensure the amount field exists for backward compatibility with the view
        if (!isset($data['invoice']['amount'])) {
            $data['invoice']['amount'] = $invoice['total_amount'] ?? 0;
        }

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
            'total_amount' => 'required|numeric',
            'issue_date' => 'required|valid_date',
            'due_date' => 'required|valid_date',
            'currency' => 'required|in_list[PEN,USD]',
            'status' => 'required|in_list[pending,paid,cancelled,rejected,expired]',
            'external_id' => 'permit_empty|max_length[36]',
            'notes' => 'permit_empty',
            'num_instalments' => 'required|integer|greater_than[0]|less_than_equal_to[12]'
        ];
        
        // Si hay más de una cuota, el intervalo es requerido
        if ((int)$this->request->getPost('num_instalments') > 1) {
            $rules['instalment_interval'] = 'required|integer|greater_than[0]|less_than_equal_to[90]';
        }

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
            'amount' => $this->request->getPost('total_amount'),
            'total_amount' => $this->request->getPost('total_amount'),
            'issue_date' => $this->request->getPost('issue_date'),
            'due_date' => $this->request->getPost('due_date'),
            'currency' => $this->request->getPost('currency'),
            'status' => $this->request->getPost('status'),
            'external_id' => $this->request->getPost('external_id') ?: null,
            'notes' => $this->request->getPost('notes') ?: null
        ];

        // Check if invoice number already exists (excluding current invoice)
        $existingInvoice = $this->invoiceModel->where('invoice_number', $data['invoice_number'])
                                            ->where('id !=', $invoice['id'])
                                            ->where('organization_id', $invoice['organization_id'])
                                            ->first();

        if ($existingInvoice) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Ya existe otra factura con este número en la organización.');
        }

        try {
            $db = \Config\Database::connect();
            $db->transStart();
            
            // Update invoice
            $this->invoiceModel->update($invoice['id'], $data);
            
            // Procesar cuotas
            $numInstalments = (int)$this->request->getPost('num_instalments');
            $instalmentModel = new \App\Models\InstalmentModel();
            
            // Verificar si hay pagos asociados a cuotas
            $paymentModel = new \App\Models\PaymentModel();
            $paymentsWithInstalments = $paymentModel->where('invoice_id', $invoice['id'])
                                                   ->where('instalment_id IS NOT NULL')
                                                   ->countAllResults();
            
            // Si hay pagos asociados a cuotas y se está cambiando el número de cuotas, mostrar advertencia
            $currentInstalments = $instalmentModel->where('invoice_id', $invoice['id'])->countAllResults();
            
            if ($paymentsWithInstalments > 0 && $numInstalments != $currentInstalments) {
                $db->transRollback();
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'No se puede cambiar el número de cuotas porque hay pagos asociados a las cuotas existentes.');
            }
            
            // Eliminar cuotas existentes
            $instalmentModel->where('invoice_id', $invoice['id'])->delete();
            
            // Crear nuevas cuotas
            $totalAmount = (float)$data['total_amount'];
            $instalmentAmount = round($totalAmount / $numInstalments, 2);
            $lastInstalmentAmount = $totalAmount - ($instalmentAmount * ($numInstalments - 1));
            
            // Fecha base para las cuotas (fecha de vencimiento de la factura)
            $baseDate = new \DateTime($data['due_date']);
            
            // Intervalo entre cuotas (solo se usa si hay más de una cuota)
            $interval = ($numInstalments > 1) ? (int)$this->request->getPost('instalment_interval') : 0;
            if ($interval <= 0) {
                $interval = 30; // Valor predeterminado
            }
            
            // Crear las cuotas
            for ($i = 1; $i <= $numInstalments; $i++) {
                // Para la primera cuota usamos la fecha de vencimiento de la factura
                if ($i > 1) {
                    // Para las siguientes cuotas, añadimos el intervalo
                    $baseDate->modify("+{$interval} days");
                }
                
                $dueDate = $baseDate->format('Y-m-d');
                
                // Determinar el monto de esta cuota
                $amount = ($i == $numInstalments) ? $lastInstalmentAmount : $instalmentAmount;
                
                $instalmentData = [
                    'invoice_id' => $invoice['id'],
                    'number' => $i,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => 'pending',
                    'notes' => ($numInstalments > 1) ? "Cuota {$i} de {$numInstalments}" : "Pago único"
                ];
                
                $instalmentModel->insert($instalmentData);
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'Error al actualizar la factura.');
            }
            
            return redirect()->to('/invoices/view/' . $uuid)
                           ->with('success', 'Factura actualizada exitosamente.');
        } catch (\Exception $e) {
            log_message('error', 'Error updating invoice: ' . $e->getMessage());
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Error al actualizar la factura: ' . $e->getMessage());
        }
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
                
                // Get default instalment settings
                $defaultNumInstalments = (int)$this->request->getVar('default_num_instalments') ?: 1;
                $defaultInstalmentInterval = (int)$this->request->getVar('default_instalment_interval') ?: 30;
                $overrideCsvInstalments = (bool)$this->request->getVar('override_csv_instalments');

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
                    $requiredFields = ['document_number', 'number', 'concept', 'total_amount', 'currency', 'due_date'];
                    $missingFields = array_diff($requiredFields, $header);
                    
                    if (!empty($missingFields)) {
                        throw new \Exception('El archivo CSV no tiene todas las columnas requeridas. Faltan: ' . implode(', ', $missingFields));
                    }
                    
                    // Check if CSV has instalment columns
                    $hasInstalmentColumns = in_array('num_instalments', $header) && in_array('instalment_interval', $header);
                    
                    // Start database transaction
                    $db = \Config\Database::connect();
                    $db->transStart();
                    
                    // Load instalment model
                    $instalmentModel = new \App\Models\InstalmentModel();
                    
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
                            if (!is_numeric(str_replace(',', '.', $rowData['total_amount']))) {
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
                                'invoice_number' => $rowData['number'] ?? $rowData['invoice_number'],
                                'concept'        => $rowData['concept'],
                                'amount' => (float)str_replace(',', '.', $rowData['total_amount']),
                                'total_amount' => (float)str_replace(',', '.', $rowData['total_amount']),
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
                            
                            $invoiceId = $this->invoiceModel->getInsertID();
                            
                            // Determine number of instalments and interval
                            $numInstalments = $defaultNumInstalments;
                            $instalmentInterval = $defaultInstalmentInterval;
                            
                            // Override with CSV values if available and option is enabled
                            if ($hasInstalmentColumns && $overrideCsvInstalments) {
                                if (isset($rowData['num_instalments']) && is_numeric($rowData['num_instalments']) && (int)$rowData['num_instalments'] > 0) {
                                    $numInstalments = (int)$rowData['num_instalments'];
                                }
                                
                                if (isset($rowData['instalment_interval']) && is_numeric($rowData['instalment_interval']) && (int)$rowData['instalment_interval'] > 0) {
                                    $instalmentInterval = (int)$rowData['instalment_interval'];
                                }
                            }
                            
                            // Create instalments
                            $instalmentAmount = $invoiceData['total_amount'] / $numInstalments;
                            $instalmentAmount = round($instalmentAmount, 2); // Redondear a 2 decimales
                            
                            // Ajustar la última cuota para que el total sea exacto
                            $lastInstalmentAmount = $invoiceData['total_amount'] - ($instalmentAmount * ($numInstalments - 1));
                            
                            for ($i = 0; $i < $numInstalments; $i++) {
                                $instalmentDueDate = date('Y-m-d', strtotime($dueDate . ' + ' . ($i * $instalmentInterval) . ' days'));
                                $amount = ($i == $numInstalments - 1) ? $lastInstalmentAmount : $instalmentAmount;
                                
                                $instalmentData = [
                                    'invoice_id' => $invoiceId,
                                    'number' => $i + 1,
                                    'amount' => $amount,
                                    'due_date' => $instalmentDueDate,
                                    'status' => 'pending'
                                ];
                                
                                if (!$instalmentModel->insert($instalmentData)) {
                                    log_message('error', 'No se pudo crear la cuota: ' . json_encode($instalmentData) . ' - Errores: ' . json_encode($instalmentModel->errors()));
                                }
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
                        return redirect()->to('/invoices')->with('message', "Se importaron {$importedCount} facturas exitosamente con sus respectivas cuotas.");
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

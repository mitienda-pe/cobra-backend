<?php

namespace App\Controllers;

use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PaymentsController extends BaseController
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
        log_message('debug', '====== PAYMENTS INDEX ======');
        
        // Refresh organization context from session
        $currentOrgId = $this->refreshOrganizationContext();
        
        $paymentModel = new PaymentModel();
        $auth = $this->auth;
        
        // Date filters
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        
        // Initialize payments array
        $payments = [];
        
        try {
            // Filter payments based on role
            if ($auth->hasRole('superadmin') || $auth->hasRole('admin')) {
                // Admin/Superadmin can see all payments from their organization
                if ($currentOrgId) {
                    // Try to get payments by organization - this uses a JOIN with invoices
                    $payments = $paymentModel->getByOrganization(
                        $currentOrgId, // Use the refreshed organization context
                        $dateStart,
                        $dateEnd
                    );
                    
                    log_message('debug', 'Admin/Superadmin fetched ' . count($payments) . ' payments for organization ' . $currentOrgId);
                } else {
                    // If no organization selected, show all payments for superadmin only
                    if ($auth->hasRole('superadmin')) {
                        // Get all payments and manually join with invoice and client info
                        $allPayments = $paymentModel->findAll();
                        $invoiceModel = new InvoiceModel();
                        $clientModel = new ClientModel();
                        $userModel = new \App\Models\UserModel();
                        
                        foreach ($allPayments as &$payment) {
                            $invoice = $invoiceModel->find($payment['invoice_id']);
                            if ($invoice) {
                                $payment['invoice_number'] = $invoice['invoice_number'];
                                $payment['concept'] = $invoice['concept'];
                                
                                $client = $clientModel->find($invoice['client_id']);
                                if ($client) {
                                    $payment['business_name'] = $client['business_name'];
                                    $payment['document_number'] = $client['document_number'];
                                }
                                
                                $user = $userModel->find($payment['user_id']);
                                if ($user) {
                                    $payment['collector_name'] = $user['name'];
                                }
                            }
                        }
                        
                        $payments = $allPayments;
                        log_message('debug', 'Superadmin fetched ' . count($payments) . ' payments (all organizations)');
                    }
                }
            } else {
                // Regular users can only see their own payments
                $payments = $paymentModel->getByUser(
                    $auth->user()['id'],
                    $dateStart,
                    $dateEnd
                );
                
                log_message('debug', 'User fetched ' . count($payments) . ' payments');
                
                // Add invoice and client information
                $invoiceModel = new InvoiceModel();
                $clientModel = new ClientModel();
                
                foreach ($payments as &$payment) {
                    $invoice = $invoiceModel->find($payment['invoice_id']);
                    if ($invoice) {
                        $payment['invoice_number'] = $invoice['invoice_number'];
                        $payment['concept'] = $invoice['concept'];
                        
                        $client = $clientModel->find($invoice['client_id']);
                        if ($client) {
                            $payment['business_name'] = $client['business_name'];
                            $payment['document_number'] = $client['document_number'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            log_message('error', 'Error fetching payments: ' . $e->getMessage());
            // Return empty payments array if there's an error
            $payments = [];
        }
        
        // Initialize view data
        $data = [
            'payments' => $payments,
            'date_start' => $dateStart,
            'date_end' => $dateEnd,
        ];
        
        // Use the trait to prepare organization-related data for the view
        $data = $this->prepareOrganizationData($data);
        
        return view('payments/index', $data);
    }
    
    public function create($invoiceId = null)
    {
        // Only collector users can create payments
        if (!$this->auth->hasAnyRole(['superadmin', 'admin', 'user'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para registrar pagos.');
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organizations for the dropdown (for superadmin)
        if ($this->auth->hasRole('superadmin')) {
            $organizationModel = new \App\Models\OrganizationModel();
            $data['organizations'] = $organizationModel->findAll();
        }
        
        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        
        // If invoice ID is provided, pre-fill the form
        if ($invoiceId) {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($invoiceId);
            
            if (!$invoice) {
                return redirect()->to('/invoices')->with('error', 'Factura no encontrada.');
            }
            
            // Check if user has access to this invoice
            if (!$this->hasAccessToInvoice($invoice)) {
                return redirect()->to('/invoices')->with('error', 'No tiene permisos para registrar pagos para esta factura.');
            }
            
            // Check if invoice is already paid
            if ($invoice['status'] === 'paid') {
                return redirect()->to('/invoices/view/' . $invoiceId)->with('error', 'Esta factura ya está pagada.');
            }
            
            // Check if invoice is cancelled or rejected
            if (in_array($invoice['status'], ['cancelled', 'rejected'])) {
                return redirect()->to('/invoices/view/' . $invoiceId)->with('error', 'No se puede registrar pagos para una factura cancelada o rechazada.');
            }
            
            // Calculate remaining amount
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoiceId);
            $invoice['total_paid'] = $paymentInfo['total_paid'];
            $invoice['remaining_amount'] = $paymentInfo['remaining'];
            
            $data['invoice'] = $invoice;
            $data['payment_info'] = $paymentInfo;
            
            // Get client information
            $clientModel = new ClientModel();
            $client = $clientModel->find($invoice['client_id']);
            $data['client'] = $client;
        }
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'invoice_id'     => 'required|is_natural_no_zero',
                'amount'         => 'required|numeric|greater_than[0]',
                'payment_method' => 'required|max_length[50]',
                'reference_code' => 'permit_empty|max_length[100]',
                'notes'          => 'permit_empty',
                'latitude'       => 'permit_empty|decimal',
                'longitude'      => 'permit_empty|decimal',
            ];
            
            if ($this->validate($rules)) {
                // Check if user has access to the invoice
                $invoiceModel = new InvoiceModel();
                $invoice = $invoiceModel->find($this->request->getPost('invoice_id'));
                
                if (!$invoice) {
                    return redirect()->back()->withInput()
                        ->with('error', 'Factura no encontrada.');
                }
                
                if (!$this->hasAccessToInvoice($invoice)) {
                    return redirect()->back()->withInput()
                        ->with('error', 'No tiene permisos para registrar pagos para esta factura.');
                }
                
                // Check if invoice is already paid
                if ($invoice['status'] === 'paid') {
                    return redirect()->back()->withInput()
                        ->with('error', 'Esta factura ya está pagada.');
                }
                
                // Check if invoice is cancelled or rejected
                if (in_array($invoice['status'], ['cancelled', 'rejected'])) {
                    return redirect()->back()->withInput()
                        ->with('error', 'No se puede registrar pagos para una factura cancelada o rechazada.');
                }
                
                // Calculate payment info to validate payment amount
                $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                $paymentAmount = (float)$this->request->getPost('amount');
                
                // Validate payment amount - cannot exceed remaining amount
                if ($paymentAmount > $paymentInfo['remaining']) {
                    return redirect()->back()->withInput()
                        ->with('error', 'El monto del pago excede el saldo pendiente de la factura. Monto máximo permitido: $' . 
                              number_format($paymentInfo['remaining'], 2));
                }
                
                $paymentModel = new PaymentModel();
                
                // Prepare data
                $data = [
                    'invoice_id'     => $this->request->getPost('invoice_id'),
                    'user_id'        => $this->auth->user()['id'],
                    'amount'         => $paymentAmount,
                    'payment_method' => $this->request->getPost('payment_method'),
                    'reference_code' => $this->request->getPost('reference_code'),
                    'payment_date'   => date('Y-m-d H:i:s'),
                    'status'         => 'completed',
                    'notes'          => $this->request->getPost('notes'),
                    'latitude'       => $this->request->getPost('latitude') ?: null,
                    'longitude'      => $this->request->getPost('longitude') ?: null,
                    'is_notified'    => false,
                ];
                
                $paymentId = $paymentModel->insert($data);
                
                if ($paymentId) {
                    // Calculate total paid after this payment
                    $newTotalPaid = $paymentInfo['total_paid'] + $paymentAmount;
                    $isFullyPaid = $newTotalPaid >= $invoice['amount'];
                    
                    // Create success message
                    $message = 'Pago de $' . number_format($paymentAmount, 2) . ' registrado exitosamente.';
                    if ($isFullyPaid) {
                        $message .= ' La factura ha sido marcada como pagada.';
                    } else {
                        $message .= ' Saldo pendiente: $' . number_format($invoice['amount'] - $newTotalPaid, 2);
                    }
                    
                    return redirect()->to('/invoices/view/' . $this->request->getPost('invoice_id'))
                        ->with('message', $message);
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al registrar el pago: ' . implode(', ', $paymentModel->errors()));
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // If no invoice ID is provided, show a form with organization and invoice selection
        if (!$invoiceId) {
            $invoiceModel = new InvoiceModel();
            
            // For superadmin, check if an organization is selected
            if ($this->auth->hasRole('superadmin')) {
                if (!empty($organizationId)) {
                    $invoices = $invoiceModel->where('organization_id', $organizationId)
                                           ->where('status', 'pending')
                                           ->findAll();
                } else {
                    $invoices = [];
                }
            } else if ($this->auth->hasRole('admin')) {
                // For admin, use their organization
                $invoices = $invoiceModel->where('organization_id', $this->auth->organizationId())
                                        ->where('status', 'pending')
                                        ->findAll();
            } else {
                // For regular users, get invoices from their assigned portfolios
                $invoices = $invoiceModel->getByUser($this->auth->user()['id'], 'pending');
            }
            
            // Get client information and calculate remaining amounts
            $clientModel = new ClientModel();
            foreach ($invoices as &$invoice) {
                $client = $clientModel->find($invoice['client_id']);
                if ($client) {
                    $invoice['client_name'] = $client['business_name'];
                    $invoice['document_number'] = $client['document_number'];
                } else {
                    $invoice['client_name'] = 'Cliente no encontrado';
                    $invoice['document_number'] = '';
                }
                
                // Calculate remaining amount
                $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                $invoice['total_paid'] = $paymentInfo['total_paid'];
                $invoice['remaining_amount'] = $paymentInfo['remaining'];
            }
            
            $data['invoices'] = $invoices;
        }
        
        return view('payments/create', $data);
    }
    
    public function view($id = null)
    {
        if (!$id) {
            return redirect()->to('/payments')->with('error', 'ID de pago no proporcionado.');
        }
        
        $paymentModel = new PaymentModel();
        $payment = $paymentModel->find($id);
        
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Pago no encontrado.');
        }
        
        // Check if user has access to this payment
        if (!$this->hasAccessToPayment($payment)) {
            return redirect()->to('/payments')->with('error', 'No tiene permisos para ver este pago.');
        }
        
        // Get invoice information
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($payment['invoice_id']);
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Get collector information
        $userModel = new \App\Models\UserModel();
        $collector = $userModel->find($payment['user_id']);
        
        $data = [
            'payment'   => $payment,
            'invoice'   => $invoice,
            'client'    => $client,
            'collector' => $collector,
            'auth'      => $this->auth,
        ];
        
        return view('payments/view', $data);
    }
    
    public function delete($id = null)
    {
        // Only admins and superadmins can delete payments
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar pagos.');
        }
        
        if (!$id) {
            return redirect()->to('/payments')->with('error', 'ID de pago no proporcionado.');
        }
        
        $paymentModel = new PaymentModel();
        $payment = $paymentModel->find($id);
        
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Pago no encontrado.');
        }
        
        // Check if user has access to this payment
        if (!$this->hasAccessToPayment($payment)) {
            return redirect()->to('/payments')->with('error', 'No tiene permisos para eliminar este pago.');
        }
        
        // Store invoice ID for later use
        $invoiceId = $payment['invoice_id'];
        $wasCompleted = ($payment['status'] === 'completed');
        
        $deleted = $paymentModel->delete($id);
        
        if ($deleted) {
            // If this was a completed payment, check if we need to update the invoice status
            if ($wasCompleted) {
                $invoiceModel = new InvoiceModel();
                $invoice = $invoiceModel->find($invoiceId);
                
                if ($invoice && $invoice['status'] === 'paid') {
                    // Check if there are any other completed payments for this invoice
                    $otherPayments = $paymentModel->where('invoice_id', $invoiceId)
                                                 ->where('status', 'completed')
                                                 ->countAllResults();
                    
                    if ($otherPayments === 0) {
                        // No other completed payments, set invoice back to pending
                        $invoiceModel->update($invoiceId, ['status' => 'pending']);
                    }
                }
            }
            
            return redirect()->to('/payments')->with('message', 'Pago eliminado exitosamente.');
        } else {
            return redirect()->to('/payments')->with('error', 'Error al eliminar el pago.');
        }
    }
    
    public function report()
    {
        // Only admins and superadmins can access reports
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para acceder a los reportes.');
        }
        
        $dateStart = $this->request->getGet('date_start') ?: date('Y-m-01'); // Default to first day of current month
        $dateEnd = $this->request->getGet('date_end') ?: date('Y-m-d'); // Default to current date
        
        $paymentModel = new PaymentModel();
        $payments = $paymentModel->getByOrganization(
            $this->auth->organizationId(),
            $dateStart,
            $dateEnd
        );
        
        // Group payments by payment method
        $paymentsByMethod = [];
        $totalAmount = 0;
        
        foreach ($payments as $payment) {
            if (!isset($paymentsByMethod[$payment['payment_method']])) {
                $paymentsByMethod[$payment['payment_method']] = [
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            
            $paymentsByMethod[$payment['payment_method']]['count']++;
            $paymentsByMethod[$payment['payment_method']]['amount'] += $payment['amount'];
            $totalAmount += $payment['amount'];
        }
        
        // Group payments by collector
        $paymentsByCollector = [];
        
        foreach ($payments as $payment) {
            $collectorName = $payment['collector_name'] ?: 'Sin cobrador';
            
            if (!isset($paymentsByCollector[$collectorName])) {
                $paymentsByCollector[$collectorName] = [
                    'count' => 0,
                    'amount' => 0,
                ];
            }
            
            $paymentsByCollector[$collectorName]['count']++;
            $paymentsByCollector[$collectorName]['amount'] += $payment['amount'];
        }
        
        $data = [
            'payments'           => $payments,
            'paymentsByMethod'   => $paymentsByMethod,
            'paymentsByCollector' => $paymentsByCollector,
            'totalAmount'        => $totalAmount,
            'auth'               => $this->auth,
            'date_start'         => $dateStart,
            'date_end'           => $dateEnd,
        ];
        
        return view('payments/report', $data);
    }
    
    /**
     * Check if user has access to a payment
     */
    private function hasAccessToPayment($payment)
    {
        if ($this->auth->hasRole('superadmin')) {
            log_message('debug', '[hasAccessToPayment] User is superadmin, access granted');
            return true;
        }
        
        if ($this->auth->hasRole('admin')) {
            // For admins, check if payment is for an invoice in their organization
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if (!$invoice) {
                return false;
            }
            
            return $invoice['organization_id'] == $this->auth->organizationId();
        } else {
            // For users, check if they created the payment or if they have access to the invoice
            if ($payment['user_id'] == $this->auth->user()['id']) {
                return true;
            }
            
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if (!$invoice) {
                return false;
            }
            
            return $this->hasAccessToInvoice($invoice);
        }
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
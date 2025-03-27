<?php

namespace App\Controllers;

use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PaymentController extends BaseController
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
    
    public function create($invoiceUuid = null)
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
        
        // If invoice UUID is provided, pre-fill the form
        if ($invoiceUuid) {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->where('uuid', $invoiceUuid)->first();
            
            if (!$invoice) {
                return redirect()->to('/payments')->with('error', 'Factura no encontrada.');
            }
            
            // Check if user has access to this invoice
            if (!$this->hasAccessToInvoice($invoice)) {
                return redirect()->to('/payments')->with('error', 'No tiene permisos para registrar pagos para esta factura.');
            }
            
            // Check if invoice is already paid
            if ($invoice['status'] === 'paid') {
                return redirect()->to('/invoices/view/' . $invoice['uuid'])->with('error', 'Esta factura ya está pagada.');
            }
            
            // Check if invoice is cancelled or rejected
            if (in_array($invoice['status'], ['cancelled', 'rejected'])) {
                return redirect()->to('/invoices/view/' . $invoice['uuid'])->with('error', 'No se puede registrar pagos para una factura cancelada o rechazada.');
            }
            
            // Calculate remaining amount
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
            $invoice['total_paid'] = $paymentInfo['total_paid'];
            $invoice['remaining_amount'] = $paymentInfo['remaining'];
            
            $data['invoice'] = $invoice;
            $data['payment_info'] = $paymentInfo;
            
            // Get client information
            $clientModel = new ClientModel();
            $client = $clientModel->find($invoice['client_id']);
            $data['client'] = $client;
        }
        
        return view('payments/create', $data);
    }

    public function searchInvoices()
    {
        // Validate request
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $term = $this->request->getGet('term');
        if (empty($term)) {
            return $this->response->setJSON(['error' => 'Search term is required'])->setStatusCode(400);
        }

        $invoiceModel = new InvoiceModel();
        $organizationId = $this->auth->organizationId();

        // Build base query
        $builder = $invoiceModel->select('invoices.*, clients.business_name, clients.document_number')
            ->join('clients', 'clients.id = invoices.client_id')
            ->where('invoices.status !=', 'paid')
            ->where('invoices.status !=', 'cancelled')
            ->where('invoices.status !=', 'rejected');

        // Add organization filter if not superadmin
        if (!$this->auth->hasRole('superadmin')) {
            $builder->where('invoices.organization_id', $organizationId);
        }

        // Search by invoice number or client info
        $builder->groupStart()
            ->like('invoices.invoice_number', $term)
            ->orLike('clients.business_name', $term)
            ->orLike('clients.document_number', $term)
            ->groupEnd();

        $invoices = $builder->findAll(10); // Limit to 10 results

        $results = [];
        foreach ($invoices as $invoice) {
            // Calculate remaining amount
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
            
            $results[] = [
                'id' => $invoice['id'],
                'uuid' => $invoice['uuid'],
                'text' => "#{$invoice['invoice_number']} - {$invoice['business_name']} ({$invoice['document_number']}) - Pendiente: S/ " . number_format($paymentInfo['remaining'], 2),
                'invoice_number' => $invoice['invoice_number'],
                'business_name' => $invoice['business_name'],
                'document_number' => $invoice['document_number'],
                'remaining' => $paymentInfo['remaining'],
                'total_amount' => $invoice['total_amount']
            ];
        }

        return $this->response->setJSON(['results' => $results]);
    }
    
    public function view($id = null)
    {
        if (!$id) {
            return redirect()->to('/payments')->with('error', 'ID de pago no especificado');
        }
        
        $paymentModel = new PaymentModel();
        $payment = $paymentModel->find($id);
        
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Pago no encontrado');
        }
        
        // Get invoice information
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($payment['invoice_id']);
        
        if (!$invoice) {
            return redirect()->to('/payments')->with('error', 'Factura asociada no encontrada');
        }
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Get user (collector) information
        $userModel = new \App\Models\UserModel();
        $collector = $userModel->find($payment['user_id']);
        
        $data = [
            'payment' => $payment,
            'invoice' => $invoice,
            'client' => $client,
            'collector' => $collector,
            'auth' => $this->auth,
        ];
        
        return view('payments/view', $data);
    }
    
    public function delete($id = null)
    {
        // Only admins and superadmins can delete payments
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/payments')->with('error', 'No tiene permisos para eliminar pagos.');
        }
        
        if (!$id) {
            return redirect()->to('/payments')->with('error', 'ID de pago no especificado');
        }
        
        $paymentModel = new PaymentModel();
        $payment = $paymentModel->find($id);
        
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Pago no encontrado');
        }
        
        try {
            // Start transaction
            $db = \Config\Database::connect();
            $db->transStart();
            
            // Delete payment
            $paymentModel->delete($id);
            
            // Update invoice status
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if ($invoice) {
                $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                if ($paymentInfo['remaining'] > 0 && $invoice['status'] === 'paid') {
                    $invoiceModel->update($invoice['id'], ['status' => 'pending']);
                }
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Error en la transacción.');
            }
            
            return redirect()->to('/payments')
                ->with('success', 'Pago eliminado exitosamente.');
                
        } catch (\Exception $e) {
            log_message('error', 'Error deleting payment: ' . $e->getMessage());
            return redirect()->to('/payments')
                ->with('error', 'Error al eliminar el pago: ' . $e->getMessage());
        }
    }
    
    public function report()
    {
        // Only admins and superadmins can view reports
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para ver reportes.');
        }
        
        // Get date range filters
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        
        // Get organization filter
        $organizationId = $this->auth->hasRole('superadmin')
            ? $this->request->getGet('organization_id')
            : $this->auth->organizationId();
        
        $paymentModel = new PaymentModel();
        
        try {
            // Get payment totals by method
            $paymentsByMethod = $paymentModel->getPaymentTotalsByMethod(
                $organizationId,
                $dateStart,
                $dateEnd
            );
            
            // Get payment totals by collector
            $paymentsByCollector = $paymentModel->getPaymentTotalsByCollector(
                $organizationId,
                $dateStart,
                $dateEnd
            );
            
            // Get payment totals by day
            $paymentsByDay = $paymentModel->getPaymentTotalsByDay(
                $organizationId,
                $dateStart,
                $dateEnd
            );
            
            $data = [
                'payments_by_method' => $paymentsByMethod,
                'payments_by_collector' => $paymentsByCollector,
                'payments_by_day' => $paymentsByDay,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'auth' => $this->auth,
            ];
            
            // Add organizations for superadmin dropdown
            if ($this->auth->hasRole('superadmin')) {
                $organizationModel = new \App\Models\OrganizationModel();
                $data['organizations'] = $organizationModel->findAll();
                $data['selected_organization'] = $organizationId;
            }
            
            return view('payments/report', $data);
            
        } catch (\Exception $e) {
            log_message('error', 'Error generating payment report: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al generar el reporte de pagos: ' . $e->getMessage());
        }
    }
    
    private function hasAccessToInvoice($invoice)
    {
        $auth = $this->auth;
        
        // Superadmin can access all invoices
        if ($auth->hasRole('superadmin')) {
            return true;
        }
        
        // Get client's organization
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        if (!$client) {
            return false;
        }
        
        // Admin can access invoices from their organization
        if ($auth->hasRole('admin')) {
            return $client['organization_id'] === $auth->user()['organization_id'];
        }
        
        // Regular users can only access invoices from their assigned portfolios
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($auth->user()['id']);
        
        foreach ($portfolios as $portfolio) {
            // Check if client belongs to this portfolio
            $db = \Config\Database::connect();
            $exists = $db->table('client_portfolio')
                ->where('portfolio_id', $portfolio['id'])
                ->where('client_id', $client['id'])
                ->countAllResults() > 0;
            
            if ($exists) {
                return true;
            }
        }
        
        return false;
    }
}

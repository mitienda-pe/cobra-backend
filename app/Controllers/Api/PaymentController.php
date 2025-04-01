<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Models\InstalmentModel;

class PaymentController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    protected $paymentModel;
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
        $this->paymentModel = new \App\Models\PaymentModel();
    }
    
    /**
     * List payments based on user role and filters
     */
    public function index()
    {
        $status = $this->request->getGet('status');
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $clientId = $this->request->getGet('client_id');
        
        // Different queries based on user role
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can see all payments for their organization
            $payments = $this->paymentModel->getByOrganization(
                $this->user['organization_id'],
                $status,
                $dateStart,
                $dateEnd
            );
            
            if ($clientId) {
                $payments = array_filter($payments, function($payment) use ($clientId) {
                    return $payment['client_id'] == $clientId;
                });
            }
        } else {
            // Regular users can only see payments from their assigned portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            $clientIds = [];
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $client) {
                    $clientIds[] = $client['id'];
                }
            }
            
            $payments = $this->paymentModel->getByClients($clientIds, $status);
            
            if ($dateStart) {
                $payments = array_filter($payments, function($payment) use ($dateStart) {
                    return $payment['payment_date'] >= $dateStart;
                });
            }
            
            if ($dateEnd) {
                $payments = array_filter($payments, function($payment) use ($dateEnd) {
                    return $payment['payment_date'] <= $dateEnd;
                });
            }
        }
        
        return $this->respond(['payments' => array_values($payments)]);
    }
    
    /**
     * Get a single payment
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Payment ID is required');
        }
        
        $payment = $this->paymentModel->find($id);
        
        if (!$payment) {
            return $this->failNotFound('Payment not found');
        }
        
        // Check if user has access to this payment
        if (!$this->canAccessPayment($payment)) {
            return $this->failForbidden('You do not have access to this payment');
        }
        
        return $this->respond(['payment' => $payment]);
    }
    
    /**
     * Search for invoices to register payments
     */
    public function searchInvoices()
    {
        $search = $this->request->getGet('search');
        $portfolioModel = new PortfolioModel();
        $clientModel = new ClientModel();
        
        if (!$search) {
            return $this->respond(['invoices' => []]);
        }
        
        // Get user's portfolio
        $portfolio = $portfolioModel->where('collector_id', $this->user['id'])->first();
        
        if (!$portfolio) {
            return $this->failForbidden('No portfolio assigned to this collector');
        }
        
        // Get clients assigned to this portfolio
        $clients = $portfolioModel->getAssignedClients($portfolio['id']);
        
        if (empty($clients)) {
            return $this->respond(['invoices' => []]);
        }
        
        // Get client IDs
        $clientIds = array_column($clients, 'id');
        
        // Search invoices by invoice number or client name
        $invoiceModel = new InvoiceModel();
        $invoices = $invoiceModel->searchByPortfolio(
            $portfolio['id'],
            $search,
            $clientIds
        );
        
        // Include client information
        if (!empty($invoices)) {
            $clientIds = array_unique(array_column($invoices, 'client_id'));
            $clients = [];
            
            // Get all clients in a single query
            if (!empty($clientIds)) {
                $clientsData = $clientModel->whereIn('id', $clientIds)->findAll();
                foreach ($clientsData as $client) {
                    $clients[$client['id']] = $client;
                }
            }
            
            // Add client data to each invoice
            foreach ($invoices as $key => $invoice) {
                if (isset($clients[$invoice['client_id']])) {
                    $invoices[$key]['client'] = $clients[$invoice['client_id']];
                }
            }
        }
        
        return $this->respond(['invoices' => $invoices]);
    }
    
    /**
     * Register a payment as a collector from the mobile app
     */
    public function registerMobilePayment()
    {
        log_message('debug', '====== INICIO REGISTER MOBILE PAYMENT ======');
        log_message('debug', 'User data: ' . json_encode($this->user));
        log_message('debug', 'Request data: ' . json_encode($this->request->getVar()));
        
        // Validate request
        $rules = [
            'invoice_id'    => 'required|is_natural_no_zero',
            'instalment_id' => 'permit_empty|is_natural_no_zero',
            'amount'        => 'required|numeric',
            'payment_date'  => 'required|valid_date',
            'payment_method'=> 'required|in_list[cash,transfer,deposit,check,other]',
            'notes'         => 'permit_empty'
        ];
        
        if (!$this->validate($rules)) {
            log_message('debug', 'Validation errors: ' . json_encode($this->validator->getErrors()));
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        // Get invoice
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($this->request->getVar('invoice_id'));
        
        if (!$invoice) {
            log_message('debug', 'Invoice not found: ' . $this->request->getVar('invoice_id'));
            return $this->failNotFound('Invoice not found');
        }
        
        log_message('debug', 'Invoice found: ' . json_encode($invoice));
        
        // Si se proporciona un ID de cuota, verificar primero el acceso a la cuota
        $instalmentId = $this->request->getVar('instalment_id');
        if (!empty($instalmentId)) {
            $instalmentModel = new InstalmentModel();
            $instalment = $instalmentModel->find($instalmentId);
            
            if (!$instalment) {
                log_message('debug', 'Instalment not found: ' . $instalmentId);
                return $this->failNotFound('Instalment not found');
            }
            
            log_message('debug', 'Instalment found: ' . json_encode($instalment));
            
            // Verificar que la cuota pertenece a la factura
            if ($instalment['invoice_id'] != $invoice['id']) {
                log_message('debug', 'Instalment does not belong to invoice');
                return $this->failValidationErrors('Instalment does not belong to this invoice');
            }
            
            // Verificar acceso al cliente de la factura
            $clientModel = new ClientModel();
            $client = $clientModel->find($invoice['client_id']);
            
            if (!$client) {
                log_message('debug', 'Client not found for invoice: ' . $invoice['id']);
                return $this->failNotFound('Client not found');
            }
            
            log_message('debug', 'Client found: ' . json_encode($client));
            
            // Verificar si el cliente está en el portafolio del usuario
            $db = \Config\Database::connect();
            
            // Obtener las carteras asignadas al usuario
            $userPortfolios = $db->table('portfolio_user')
                ->select('portfolio_uuid')
                ->where('user_uuid', $this->user['uuid'])
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
                
            if (empty($userPortfolios)) {
                log_message('debug', 'User does not have portfolios');
                return $this->failForbidden('You do not have access to this invoice');
            }
            
            $portfolioUuids = array_column($userPortfolios, 'portfolio_uuid');
            
            // Verificar si el cliente está en alguna de las carteras del usuario
            $clientInPortfolio = $db->table('client_portfolio')
                ->where('client_uuid', $client['uuid'])
                ->whereIn('portfolio_uuid', $portfolioUuids)
                ->where('deleted_at IS NULL')
                ->countAllResults();
                
            log_message('debug', 'Client in portfolio: ' . ($clientInPortfolio > 0 ? 'true' : 'false'));
            log_message('debug', 'Client UUID: ' . $client['uuid']);
            log_message('debug', 'Portfolio UUIDs: ' . json_encode($portfolioUuids));
            
            if ($clientInPortfolio == 0) {
                return $this->failForbidden('You do not have access to this client');
            }
            
            // Verificar que la cuota puede ser pagada (todas las cuotas anteriores están pagadas)
            if (!$instalmentModel->canBePaid($instalmentId)) {
                log_message('debug', 'Instalment cannot be paid yet');
                return $this->failValidationErrors('Previous instalments must be paid first');
            }
            
            // Verificar que el monto del pago no es mayor que el monto restante de la cuota
            $instalmentPayments = $this->paymentModel
                ->where('instalment_id', $instalmentId)
                ->where('status', 'completed')
                ->findAll();
                
            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }
            
            $instalmentRemaining = $instalment['amount'] - $instalmentPaid;
            $paymentAmount = (float)$this->request->getVar('amount');
            
            if ($paymentAmount > $instalmentRemaining) {
                log_message('debug', 'Payment amount exceeds instalment remaining amount');
                return $this->failValidationErrors('Payment amount cannot exceed the instalment remaining amount');
            }
            
            log_message('debug', 'Instalment validation passed');
        } else {
            // Si no se proporciona un ID de cuota, verificar el acceso a la factura
            // Check if user has access to this invoice
            $hasAccess = $this->canAccessInvoice($invoice);
            log_message('debug', 'Has access to invoice: ' . ($hasAccess ? 'true' : 'false'));
            
            if (!$hasAccess) {
                return $this->failForbidden('You do not have access to this invoice');
            }
        }
        
        // Create payment
        $data = [
            'organization_id' => $invoice['organization_id'],
            'invoice_id'      => $invoice['id'],
            'instalment_id'   => $instalmentId ?: null,
            'client_id'       => $invoice['client_id'],
            'amount'          => $this->request->getVar('amount'),
            'payment_date'    => $this->request->getVar('payment_date'),
            'payment_method'  => $this->request->getVar('payment_method'),
            'status'          => 'pending',
            'notes'           => $this->request->getVar('notes'),
            'registered_by'   => $this->user['id']
        ];
        
        $paymentId = $this->paymentModel->insert($data);
        
        if (!$paymentId) {
            return $this->failServerError('Failed to register payment');
        }
        
        // Update invoice status if payment is full
        $totalPaid = $this->paymentModel->getTotalPaidForInvoice($invoice['id']);
        
        if ($totalPaid >= $invoice['amount']) {
            $invoiceModel->update($invoice['id'], ['status' => 'paid']);
        } else if ($totalPaid > 0) {
            $invoiceModel->update($invoice['id'], ['status' => 'partial']);
        }
        
        // Update instalment status if applicable
        if (!empty($instalmentId)) {
            $instalmentModel = new InstalmentModel();
            $instalmentModel->updateStatus($instalmentId);
            
            // Check if all instalments are paid
            if ($instalmentModel->areAllPaid($invoice['id'])) {
                $invoiceModel->update($invoice['id'], ['status' => 'paid']);
            }
        }
        
        $payment = $this->paymentModel->find($paymentId);
        
        return $this->respondCreated(['payment' => $payment]);
    }
    
    /**
     * Get instalments for an invoice
     */
    public function getInstalments($invoiceId)
    {
        if (!$invoiceId) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($invoiceId);
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        $instalmentModel = new InstalmentModel();
        // Usar el método que ordena las cuotas por prioridad de cobranza
        $instalments = $instalmentModel->getByInvoiceForCollection($invoiceId);
        
        // Calculate remaining amount for each instalment
        foreach ($instalments as &$instalment) {
            $instalmentPayments = $this->paymentModel
                ->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();
                
            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }
            
            $instalment['paid_amount'] = $instalmentPaid;
            $instalment['remaining_amount'] = $instalment['amount'] - $instalmentPaid;
        }
        
        return $this->respond(['instalments' => $instalments]);
    }
    
    /**
     * Check if user can access a payment
     */
    private function canAccessPayment($payment)
    {
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can access any payment in their organization
            return $payment['organization_id'] == $this->user['organization_id'];
        } else {
            // For regular users, check if they have access to the client through portfolios
            return $this->canAccessInvoice(['id' => $payment['invoice_id'], 'organization_id' => $payment['organization_id']]);
        }
    }
    
    /**
     * Verifica si el usuario puede acceder a una factura
     */
    private function canAccessInvoice($invoice)
    {
        log_message('debug', '====== INICIO CAN ACCESS INVOICE ======');
        log_message('debug', 'Invoice data: ' . json_encode($invoice));
        log_message('debug', 'User data: ' . json_encode($this->user));
        
        // Check if invoice is null or not an array
        if (!$invoice || !is_array($invoice)) {
            log_message('debug', 'Invoice is not an array');
            return false;
        }
        
        // Check if invoice has required fields
        if (!isset($invoice['id']) || !isset($invoice['organization_id']) || !isset($invoice['client_id'])) {
            log_message('debug', 'Invoice does not have required fields');
            return false;
        }
        
        // Check if user has role
        if (!isset($this->user['role'])) {
            log_message('debug', 'User does not have role');
            return false;
        }
        
        // Superadmin can access everything
        if ($this->user['role'] === 'superadmin') {
            log_message('debug', 'User is superadmin');
            return true;
        }
        
        // Admin can access invoices from their organization
        if ($this->user['role'] === 'admin') {
            if (!isset($this->user['organization_id'])) {
                log_message('debug', 'User does not have organization ID');
                return false;
            }
            log_message('debug', 'User organization ID: ' . $this->user['organization_id']);
            log_message('debug', 'Invoice organization ID: ' . $invoice['organization_id']);
            return $invoice['organization_id'] == $this->user['organization_id'];
        }
        
        // Regular user can only access invoices from clients in their portfolios
        $clientModel = new \App\Models\ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        if (!$client) {
            log_message('debug', 'Client not found');
            return false;
        }
        
        log_message('debug', 'Client data: ' . json_encode($client));
        
        // Verificar si el cliente está en el portafolio del usuario
        if (!isset($this->user['uuid'])) {
            log_message('debug', 'User does not have UUID');
            return false;
        }
        
        $db = \Config\Database::connect();
        
        // Obtener las carteras asignadas al usuario
        $userPortfolios = $db->table('portfolio_user')
            ->select('portfolio_uuid')
            ->where('user_uuid', $this->user['uuid'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();
            
        if (empty($userPortfolios)) {
            log_message('debug', 'User does not have portfolios');
            return false;
        }
        
        $portfolioUuids = array_column($userPortfolios, 'portfolio_uuid');
        
        // Verificar si el cliente está en alguna de las carteras del usuario
        if (!isset($client['uuid'])) {
            log_message('debug', 'Client does not have UUID');
            return false;
        }
        
        $clientInPortfolio = $db->table('client_portfolio')
            ->where('client_uuid', $client['uuid'])
            ->whereIn('portfolio_uuid', $portfolioUuids)
            ->where('deleted_at IS NULL')
            ->countAllResults();
        
        log_message('debug', 'Client in portfolio: ' . ($clientInPortfolio > 0 ? 'true' : 'false'));
        log_message('debug', 'Client UUID: ' . $client['uuid']);
        log_message('debug', 'Portfolio UUIDs: ' . json_encode($portfolioUuids));
        
        return $clientInPortfolio > 0;
    }
}

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
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
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
        
        $paymentModel = new PaymentModel();
        
        // Different queries based on user role
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can see all payments for their organization
            $payments = $paymentModel->getByOrganization(
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
            
            $payments = $paymentModel->getByClients($clientIds, $status);
            
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
        
        $paymentModel = new PaymentModel();
        $payment = $paymentModel->find($id);
        
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
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        // Get invoice
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($this->request->getVar('invoice_id'));
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Validate instalment if provided
        $instalmentId = $this->request->getVar('instalment_id');
        if (!empty($instalmentId)) {
            $instalmentModel = new InstalmentModel();
            $instalment = $instalmentModel->find($instalmentId);
            
            if (!$instalment || $instalment['invoice_id'] != $invoice['id']) {
                return $this->failValidationErrors(['instalment_id' => 'Invalid instalment for this invoice']);
            }
            
            // Check if payment amount is valid for the instalment
            $paymentModel = new PaymentModel();
            $instalmentPayments = $paymentModel
                ->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();
                
            $instalmentPaid = 0;
            foreach ($instalmentPayments as $payment) {
                $instalmentPaid += $payment['amount'];
            }
            
            $instalmentRemaining = $instalment['amount'] - $instalmentPaid;
            $paymentAmount = $this->request->getVar('amount');
            
            if ($paymentAmount > $instalmentRemaining) {
                return $this->failValidationErrors(['amount' => 'Payment amount cannot exceed the remaining instalment amount']);
            }
        }
        
        // Create payment
        $paymentModel = new PaymentModel();
        
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
        
        $paymentId = $paymentModel->insert($data);
        
        if (!$paymentId) {
            return $this->failServerError('Failed to register payment');
        }
        
        // Update invoice status if payment is full
        $totalPaid = $paymentModel->getTotalPaidForInvoice($invoice['id']);
        
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
        
        $payment = $paymentModel->find($paymentId);
        
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
        $instalments = $instalmentModel->getByInvoice($invoiceId);
        
        // Calculate remaining amount for each instalment
        $paymentModel = new PaymentModel();
        foreach ($instalments as &$instalment) {
            $instalmentPayments = $paymentModel
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
     * Check if user can access an invoice
     */
    private function canAccessInvoice($invoice)
    {
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can access any invoice in their organization
            return $invoice['organization_id'] == $this->user['organization_id'];
        } else {
            // For regular users, check if they have access to the client through portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            // Get all client IDs from user's portfolios
            $clientIds = [];
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $client) {
                    $clientIds[] = $client['id'];
                }
            }
            
            // Check if invoice's client is in user's portfolios
            $invoiceModel = new InvoiceModel();
            $fullInvoice = $invoiceModel->find($invoice['id']);
            
            if (!$fullInvoice) {
                return false;
            }
            
            return in_array($fullInvoice['client_id'], $clientIds);
        }
    }
}

<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use App\Models\PortfolioModel;

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
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $invoiceId = $this->request->getGet('invoice_id');
        
        $paymentModel = new PaymentModel();
        
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            $payments = $paymentModel->getByOrganization(
                $this->user['organization_id'],
                $dateStart,
                $dateEnd
            );
        } else {
            $payments = $paymentModel->getByUser(
                $this->user['id'],
                $dateStart,
                $dateEnd
            );
        }
        
        if ($invoiceId) {
            $payments = array_filter($payments, function($payment) use ($invoiceId) {
                return $payment['invoice_id'] == $invoiceId;
            });
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
        
        // Get related invoice
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($payment['invoice_id']);
        
        // Include invoice information in the response
        $payment['invoice'] = $invoice;
        
        return $this->respond(['payment' => $payment]);
    }
    
    /**
     * Create a new payment
     */
    public function create()
    {
        $rules = [
            'invoice_id'     => 'required|is_natural_no_zero',
            'amount'         => 'required|numeric',
            'payment_method' => 'required|max_length[50]',
            'reference_code' => 'permit_empty|max_length[100]',
            'notes'          => 'permit_empty',
            'latitude'       => 'permit_empty|decimal',
            'longitude'      => 'permit_empty|decimal',
            'external_id'    => 'permit_empty|max_length[36]'
        ];
        
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        // Check if invoice exists and user has access to it
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($this->request->getVar('invoice_id'));
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Check if invoice is already paid
        if ($invoice['status'] === 'paid') {
            return $this->fail('Invoice is already paid', 400);
        }
        
        // Check if invoice is cancelled or rejected
        if (in_array($invoice['status'], ['cancelled', 'rejected'])) {
            return $this->fail('Cannot pay a cancelled or rejected invoice', 400);
        }
        
        $paymentModel = new PaymentModel();
        
        $data = [
            'invoice_id'     => $this->request->getVar('invoice_id'),
            'user_id'        => $this->user['id'],
            'amount'         => $this->request->getVar('amount'),
            'payment_method' => $this->request->getVar('payment_method'),
            'reference_code' => $this->request->getVar('reference_code'),
            'payment_date'   => date('Y-m-d H:i:s'),
            'status'         => 'completed',
            'notes'          => $this->request->getVar('notes'),
            'latitude'       => $this->request->getVar('latitude'),
            'longitude'      => $this->request->getVar('longitude'),
            'external_id'    => $this->request->getVar('external_id'),
            'is_notified'    => false
        ];
        
        $paymentId = $paymentModel->insert($data);
        
        if (!$paymentId) {
            return $this->failServerError('Failed to create payment');
        }
        
        $payment = $paymentModel->find($paymentId);
        
        // Update invoice status (this is done automatically by the model's beforeInsert method)
        
        return $this->respondCreated(['payment' => $payment]);
    }
    
    /**
     * Update a payment (limited functionality)
     */
    public function update($id = null)
    {
        // Only admins and superadmins can update payments
        if (!in_array($this->user['role'], ['superadmin', 'admin'])) {
            return $this->failForbidden('You do not have permission to update payments');
        }
        
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
        
        $rules = [
            'status'         => 'permit_empty|in_list[completed,pending,rejected,cancelled]',
            'reference_code' => 'permit_empty|max_length[100]',
            'notes'          => 'permit_empty',
            'external_id'    => 'permit_empty|max_length[36]'
        ];
        
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        $data = [];
        
        // Only update provided fields
        if ($this->request->getVar('status') !== null) {
            $data['status'] = $this->request->getVar('status');
        }
        
        if ($this->request->getVar('reference_code') !== null) {
            $data['reference_code'] = $this->request->getVar('reference_code');
        }
        
        if ($this->request->getVar('notes') !== null) {
            $data['notes'] = $this->request->getVar('notes');
        }
        
        if ($this->request->getVar('external_id') !== null) {
            $data['external_id'] = $this->request->getVar('external_id');
        }
        
        if (empty($data)) {
            return $this->fail('No data provided for update', 400);
        }
        
        $updated = $paymentModel->update($id, $data);
        
        if (!$updated) {
            return $this->failServerError('Failed to update payment');
        }
        
        $payment = $paymentModel->find($id);
        
        // If payment status was changed to something other than 'completed',
        // we may need to update the invoice status back to 'pending'
        if (isset($data['status']) && $data['status'] !== 'completed') {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if ($invoice && $invoice['status'] === 'paid') {
                // Check if there are any other completed payments for this invoice
                $otherPayments = $paymentModel->where('invoice_id', $invoice['id'])
                                             ->where('id !=', $id)
                                             ->where('status', 'completed')
                                             ->countAllResults();
                
                if ($otherPayments === 0) {
                    // No other completed payments, set invoice back to pending
                    $invoiceModel->update($invoice['id'], ['status' => 'pending']);
                }
            }
        }
        
        return $this->respond(['payment' => $payment]);
    }
    
    /**
     * Delete a payment
     */
    public function delete($id = null)
    {
        // Only admins and superadmins can delete payments
        if (!in_array($this->user['role'], ['superadmin', 'admin'])) {
            return $this->failForbidden('You do not have permission to delete payments');
        }
        
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
        
        $invoiceId = $payment['invoice_id'];
        $wasCompleted = ($payment['status'] === 'completed');
        
        $deleted = $paymentModel->delete($id);
        
        if (!$deleted) {
            return $this->failServerError('Failed to delete payment');
        }
        
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
        
        return $this->respondDeleted(['message' => 'Payment deleted successfully']);
    }
    
    /**
     * Get payment by external ID
     */
    public function findByExternalId()
    {
        $externalId = $this->request->getGet('external_id');
        
        if (!$externalId) {
            return $this->failValidationErrors('External ID is required');
        }
        
        $paymentModel = new PaymentModel();
        $payment = $paymentModel->getByExternalId($externalId);
        
        if (!$payment) {
            return $this->failNotFound('Payment not found');
        }
        
        // Check if user has access to this payment
        if (!$this->canAccessPayment($payment)) {
            return $this->failForbidden('You do not have access to this payment');
        }
        
        // Get related invoice
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($payment['invoice_id']);
        
        // Include invoice information in the response
        $payment['invoice'] = $invoice;
        
        return $this->respond(['payment' => $payment]);
    }
    
    /**
     * Check if user can access a payment
     */
    private function canAccessPayment($payment)
    {
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // For admins, check if payment is for an invoice in their organization
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if (!$invoice) {
                return false;
            }
            
            return $invoice['organization_id'] == $this->user['organization_id'];
        } else {
            // For users, check if they are assigned to the client or if they created the payment
            if ($payment['user_id'] == $this->user['id']) {
                return true;
            }
            
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if (!$invoice) {
                return false;
            }
            
            return $this->canAccessInvoice($invoice);
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
            
            return in_array($invoice['client_id'], $clientIds);
        }
    }
}
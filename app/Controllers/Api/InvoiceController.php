<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\InvoiceModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;

class InvoiceController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * Get the authenticated user
     * This method ensures we have a user object even if session data is missing
     */
    protected function getAuthUser()
    {
        if ($this->user) {
            return $this->user;
        }
        
        // Try to get user from request object (set by ApiAuthFilter)
        if (isset($this->request) && isset($this->request->user)) {
            $this->user = $this->request->user;
            return $this->user;
        }
        
        // If still no user, return a default user with role 'guest'
        return [
            'id' => 0,
            'role' => 'guest',
            'organization_id' => 0
        ];
    }
    
    /**
     * List invoices based on user role and filters
     */
    public function index()
    {
        $status = $this->request->getGet('status');
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        $clientId = $this->request->getGet('client_id');
        
        $invoiceModel = new InvoiceModel();
        
        // Different queries based on user role
        if ($this->getAuthUser()['role'] === 'superadmin' || $this->getAuthUser()['role'] === 'admin') {
            // Admins and superadmins can see all invoices for their organization
            $invoices = $invoiceModel->getByOrganization(
                $this->getAuthUser()['organization_id'],
                $status,
                $dateStart,
                $dateEnd
            );
            
            if ($clientId) {
                $invoices = array_filter($invoices, function($invoice) use ($clientId) {
                    return $invoice['client_id'] == $clientId;
                });
            }
        } else {
            // Regular users can only see invoices from their assigned portfolios
            $invoices = $invoiceModel->getByUser($this->getAuthUser()['id'], $status);
            
            if ($clientId) {
                $invoices = array_filter($invoices, function($invoice) use ($clientId) {
                    return $invoice['client_id'] == $clientId;
                });
            }
            
            if ($dateStart) {
                $invoices = array_filter($invoices, function($invoice) use ($dateStart) {
                    return $invoice['due_date'] >= $dateStart;
                });
            }
            
            if ($dateEnd) {
                $invoices = array_filter($invoices, function($invoice) use ($dateEnd) {
                    return $invoice['due_date'] <= $dateEnd;
                });
            }
        }
        
        return $this->respond(['invoices' => array_values($invoices)]);
    }
    
    /**
     * Get a single invoice
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Include client information in the response
        $invoice['client'] = $client;
        
        return $this->respond(['invoice' => $invoice]);
    }
    
    /**
     * Create a new invoice
     */
    public function create()
    {
        // Only admins and superadmins can create invoices via API
        if (!in_array($this->getAuthUser()['role'], ['superadmin', 'admin'])) {
            return $this->failForbidden('You do not have permission to create invoices');
        }
        
        $rules = [
            'client_id'      => 'required|is_natural_no_zero',
            'invoice_number' => 'required|max_length[50]',
            'concept'        => 'required|max_length[255]',
            'amount'         => 'required|numeric',
            'due_date'       => 'required|valid_date',
            'external_id'    => 'permit_empty|max_length[36]',
            'notes'          => 'permit_empty'
        ];
        
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        // Check if client exists and belongs to user's organization
        $clientModel = new ClientModel();
        $client = $clientModel->find($this->request->getVar('client_id'));
        
        if (!$client || $client['organization_id'] != $this->getAuthUser()['organization_id']) {
            return $this->failNotFound('Client not found or not in your organization');
        }
        
        $invoiceModel = new InvoiceModel();
        
        $data = [
            'organization_id' => $this->getAuthUser()['organization_id'],
            'client_id'       => $this->request->getVar('client_id'),
            'invoice_number'  => $this->request->getVar('invoice_number'),
            'concept'         => $this->request->getVar('concept'),
            'amount'          => $this->request->getVar('amount'),
            'due_date'        => $this->request->getVar('due_date'),
            'status'          => 'pending',
            'external_id'     => $this->request->getVar('external_id'),
            'notes'           => $this->request->getVar('notes')
        ];
        
        $invoiceId = $invoiceModel->insert($data);
        
        if (!$invoiceId) {
            return $this->failServerError('Failed to create invoice');
        }
        
        $invoice = $invoiceModel->find($invoiceId);
        
        return $this->respondCreated(['invoice' => $invoice]);
    }
    
    /**
     * Update an invoice
     */
    public function update($id = null)
    {
        // Only admins and superadmins can update invoices via API
        if (!in_array($this->getAuthUser()['role'], ['superadmin', 'admin'])) {
            return $this->failForbidden('You do not have permission to update invoices');
        }
        
        if (!$id) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if ($invoice['organization_id'] != $this->getAuthUser()['organization_id']) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        $rules = [
            'invoice_number' => 'permit_empty|max_length[50]',
            'concept'        => 'permit_empty|max_length[255]',
            'amount'         => 'permit_empty|numeric',
            'due_date'       => 'permit_empty|valid_date',
            'status'         => 'permit_empty|in_list[pending,paid,cancelled,rejected,expired]',
            'external_id'    => 'permit_empty|max_length[36]',
            'notes'          => 'permit_empty'
        ];
        
        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }
        
        $data = [];
        
        // Only update provided fields
        if ($this->request->getVar('invoice_number') !== null) {
            $data['invoice_number'] = $this->request->getVar('invoice_number');
        }
        
        if ($this->request->getVar('concept') !== null) {
            $data['concept'] = $this->request->getVar('concept');
        }
        
        if ($this->request->getVar('amount') !== null) {
            $data['amount'] = $this->request->getVar('amount');
        }
        
        if ($this->request->getVar('due_date') !== null) {
            $data['due_date'] = $this->request->getVar('due_date');
        }
        
        if ($this->request->getVar('status') !== null) {
            $data['status'] = $this->request->getVar('status');
        }
        
        if ($this->request->getVar('external_id') !== null) {
            $data['external_id'] = $this->request->getVar('external_id');
        }
        
        if ($this->request->getVar('notes') !== null) {
            $data['notes'] = $this->request->getVar('notes');
        }
        
        if (empty($data)) {
            return $this->fail('No data provided for update', 400);
        }
        
        $updated = $invoiceModel->update($id, $data);
        
        if (!$updated) {
            return $this->failServerError('Failed to update invoice');
        }
        
        $invoice = $invoiceModel->find($id);
        
        return $this->respond(['invoice' => $invoice]);
    }
    
    /**
     * Delete an invoice
     */
    public function delete($id = null)
    {
        // Only admins and superadmins can delete invoices
        if (!in_array($this->getAuthUser()['role'], ['superadmin', 'admin'])) {
            return $this->failForbidden('You do not have permission to delete invoices');
        }
        
        if (!$id) {
            return $this->failValidationErrors('Invoice ID is required');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($id);
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if ($invoice['organization_id'] != $this->getAuthUser()['organization_id']) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        $deleted = $invoiceModel->delete($id);
        
        if (!$deleted) {
            return $this->failServerError('Failed to delete invoice');
        }
        
        return $this->respondDeleted(['message' => 'Invoice deleted successfully']);
    }
    
    /**
     * Get invoice by external ID
     */
    public function findByExternalId()
    {
        $externalId = $this->request->getGet('external_id');
        
        if (!$externalId) {
            return $this->failValidationErrors('External ID is required');
        }
        
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->getByExternalId($externalId, $this->getAuthUser()['organization_id']);
        
        if (!$invoice) {
            return $this->failNotFound('Invoice not found');
        }
        
        // Check if user has access to this invoice
        if (!$this->canAccessInvoice($invoice)) {
            return $this->failForbidden('You do not have access to this invoice');
        }
        
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        // Include client information in the response
        $invoice['client'] = $client;
        
        return $this->respond(['invoice' => $invoice]);
    }
    
    /**
     * Get overdue invoices
     */
    public function overdue()
    {
        $invoiceModel = new InvoiceModel();
        
        // Different queries based on user role
        if ($this->getAuthUser()['role'] === 'superadmin' || $this->getAuthUser()['role'] === 'admin') {
            // Admins and superadmins can see all invoices for their organization
            $invoices = $invoiceModel->getByOrganization(
                $this->getAuthUser()['organization_id'],
                'pending'
            );
        } else {
            // Regular users can only see invoices from their assigned portfolios
            $invoices = $invoiceModel->getByUser($this->getAuthUser()['id'], 'pending');
        }
        
        // Filter overdue invoices
        $today = date('Y-m-d');
        $overdue = array_filter($invoices, function($invoice) use ($today) {
            return $invoice['due_date'] < $today;
        });
        
        return $this->respond(['invoices' => array_values($overdue)]);
    }
    
    /**
     * Check if user can access an invoice
     */
    private function canAccessInvoice($invoice)
    {
        if ($this->getAuthUser()['role'] === 'superadmin' || $this->getAuthUser()['role'] === 'admin') {
            // Admins and superadmins can access any invoice in their organization
            return $invoice['organization_id'] == $this->getAuthUser()['organization_id'];
        } else {
            // For regular users, check if they have access to the client through portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->getAuthUser()['id']);
            
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
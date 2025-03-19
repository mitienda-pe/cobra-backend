<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\ClientModel;
use App\Models\PortfolioModel;

class ClientController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * List clients based on user role
     */
    public function index()
    {
        // Check if organization_id is provided for non-API requests
        $organizationId = $this->request->getGet('organization_id');
        
        // If this is a web request (not an API request with api_user in session)
        if (!$this->user && $organizationId) {
            $clientModel = new ClientModel();
            $clients = $clientModel->where('organization_id', $organizationId)
                                 ->where('status', 'active')
                                 ->findAll();
            
            return $this->respond($clients);
        }
        
        // API request handling
        if ($this->user) {
            $clientModel = new ClientModel();
            
            if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
                // Admins and superadmins can see all clients in their organization
                $clients = $clientModel->getByOrganization($this->user['organization_id']);
            } else {
                // Regular users can only see clients from their assigned portfolios
                $portfolioModel = new PortfolioModel();
                $portfolios = $portfolioModel->getByUser($this->user['id']);
                
                $clients = [];
                foreach ($portfolios as $portfolio) {
                    $portfolioClients = $clientModel->getByPortfolio($portfolio['id']);
                    $clients = array_merge($clients, $portfolioClients);
                }
                
                // Remove duplicates
                $uniqueClients = [];
                foreach ($clients as $client) {
                    $uniqueClients[$client['id']] = $client;
                }
                
                $clients = array_values($uniqueClients);
            }
            
            return $this->respond(['clients' => $clients]);
        }
        
        return $this->failUnauthorized('Unauthorized access');
    }
    
    /**
     * Get a single client
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Client ID is required');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->find($id);
        
        if (!$client) {
            return $this->failNotFound('Client not found');
        }
        
        // Check if user has access to this client
        if (!$this->canAccessClient($client)) {
            return $this->failForbidden('You do not have access to this client');
        }
        
        return $this->respond(['client' => $client]);
    }
    
    /**
     * Get client by external ID
     */
    public function findByExternalId()
    {
        $externalId = $this->request->getGet('external_id');
        
        if (!$externalId) {
            return $this->failValidationErrors('External ID is required');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->getByExternalId($externalId, $this->user['organization_id']);
        
        if (!$client) {
            return $this->failNotFound('Client not found');
        }
        
        // Check if user has access to this client
        if (!$this->canAccessClient($client)) {
            return $this->failForbidden('You do not have access to this client');
        }
        
        return $this->respond(['client' => $client]);
    }
    
    /**
     * Get client by document number
     */
    public function findByDocument()
    {
        $documentNumber = $this->request->getGet('document_number');
        
        if (!$documentNumber) {
            return $this->failValidationErrors('Document number is required');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->where('document_number', $documentNumber)
                             ->where('organization_id', $this->user['organization_id'])
                             ->first();
        
        if (!$client) {
            return $this->failNotFound('Client not found');
        }
        
        // Check if user has access to this client
        if (!$this->canAccessClient($client)) {
            return $this->failForbidden('You do not have access to this client');
        }
        
        return $this->respond(['client' => $client]);
    }
    
    /**
     * Get client by UUID
     */
    public function findByUuid()
    {
        $uuid = $this->request->getGet('uuid');
        
        if (!$uuid) {
            return $this->failValidationErrors('UUID is required');
        }
        
        $clientModel = new ClientModel();
        $client = $clientModel->findByUuid($uuid, $this->user['organization_id']);
        
        if (!$client) {
            return $this->failNotFound('Client not found');
        }
        
        // Check if user has access to this client
        if (!$this->canAccessClient($client)) {
            return $this->failForbidden('You do not have access to this client');
        }
        
        return $this->respond(['client' => $client]);
    }
    
    /**
     * Check if user can access a client
     */
    private function canAccessClient($client)
    {
        if (!$this->user) {
            return false;
        }
        
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can access any client in their organization
            return $client['organization_id'] == $this->user['organization_id'];
        } else {
            // For regular users, check if client is in any of their portfolios
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            foreach ($portfolios as $portfolio) {
                $clients = $portfolioModel->getAssignedClients($portfolio['id']);
                foreach ($clients as $portfolioClient) {
                    if ($portfolioClient['id'] == $client['id']) {
                        return true;
                    }
                }
            }
            
            return false;
        }
    }
}
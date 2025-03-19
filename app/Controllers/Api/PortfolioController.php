<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\PortfolioModel;

class PortfolioController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * List portfolios based on user role
     */
    public function index()
    {
        $portfolioModel = new PortfolioModel();
        
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can see all portfolios in their organization
            $portfolios = $portfolioModel->getByOrganization($this->user['organization_id']);
        } else {
            // Regular users can only see their assigned portfolios
            $portfolios = $portfolioModel->getByUser($this->user['id']);
        }
        
        return $this->respond(['portfolios' => $portfolios]);
    }
    
    /**
     * Get a single portfolio
     */
    public function show($id = null)
    {
        if (!$id) {
            return $this->failValidationErrors('Portfolio ID is required');
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->find($id);
        
        if (!$portfolio) {
            return $this->failNotFound('Portfolio not found');
        }
        
        // Check if user has access to this portfolio
        if (!$this->canAccessPortfolio($portfolio)) {
            return $this->failForbidden('You do not have access to this portfolio');
        }
        
        // Get clients and users assigned to this portfolio
        $clients = $portfolioModel->getAssignedClients($id);
        $users = $portfolioModel->getAssignedUsers($id);
        
        // Include assignments in the response
        $portfolio['clients'] = $clients;
        $portfolio['users'] = $users;
        
        return $this->respond(['portfolio' => $portfolio]);
    }
    
    /**
     * Get portfolios assigned to current user
     */
    public function myPortfolios()
    {
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($this->user['id']);
        
        return $this->respond(['portfolios' => $portfolios]);
    }
    
    /**
     * Check if user can access a portfolio
     */
    private function canAccessPortfolio($portfolio)
    {
        if ($this->user['role'] === 'superadmin' || $this->user['role'] === 'admin') {
            // Admins and superadmins can access any portfolio in their organization
            return $portfolio['organization_id'] == $this->user['organization_id'];
        } else {
            // For regular users, check if they are assigned to the portfolio
            $portfolioModel = new PortfolioModel();
            $portfolios = $portfolioModel->getByUser($this->user['id']);
            
            foreach ($portfolios as $userPortfolio) {
                if ($userPortfolio['id'] == $portfolio['id']) {
                    return true;
                }
            }
            
            return false;
        }
    }
}
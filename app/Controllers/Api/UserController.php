<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\UserModel;
use App\Models\PortfolioModel;

class UserController extends ResourceController
{
    protected $format = 'json';
    protected $user;
    
    public function __construct()
    {
        // User will be set by the auth filter
        $this->user = session()->get('api_user');
    }
    
    /**
     * Get current user profile
     */
    public function profile()
    {
        $userModel = new UserModel();
        $user = $userModel->find($this->user['id']);
        
        if (!$user) {
            return $this->failNotFound('User not found');
        }
        
        // Don't return sensitive information
        unset($user['password']);
        unset($user['remember_token']);
        unset($user['reset_token']);
        unset($user['reset_token_expires_at']);
        
        // Get user's portfolios
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($user['id']);
        
        // Include portfolios in the response
        $user['portfolios'] = $portfolios;
        
        return $this->respond(['user' => $user]);
    }
    
    /**
     * List users (admin and superadmin only)
     */
    public function index()
    {
        // Check if organization_id is provided for non-API requests
        $organizationId = $this->request->getGet('organization_id');
        
        // If this is a web request (not an API request with api_user in session)
        if (!$this->user && $organizationId) {
            $userModel = new UserModel();
            $users = $userModel->where('organization_id', $organizationId)->findAll();
            
            // Remove sensitive information
            foreach ($users as &$user) {
                unset($user['password']);
                unset($user['remember_token']);
                unset($user['reset_token']);
                unset($user['reset_token_expires_at']);
            }
            
            return $this->respond($users);
        }
        
        // API request handling
        if ($this->user) {
            // Only admins and superadmins can list users
            if (!in_array($this->user['role'], ['superadmin', 'admin'])) {
                return $this->failForbidden('You do not have permission to access this resource');
            }
            
            $userModel = new UserModel();
            
            if ($this->user['role'] === 'superadmin') {
                // Superadmins can see all users
                $users = $userModel->findAll();
            } else {
                // Admins can only see users in their organization
                $users = $userModel->where('organization_id', $this->user['organization_id'])->findAll();
            }
            
            // Remove sensitive information
            foreach ($users as &$user) {
                unset($user['password']);
                unset($user['remember_token']);
                unset($user['reset_token']);
                unset($user['reset_token_expires_at']);
            }
            
            return $this->respond(['users' => $users]);
        }
        
        return $this->failUnauthorized('Unauthorized access');
    }
    
    /**
     * Get users by portfolio
     */
    public function byPortfolio($portfolioId = null)
    {
        if (!$portfolioId) {
            return $this->failValidationErrors('Portfolio ID is required');
        }
        
        $portfolioModel = new PortfolioModel();
        $portfolio = $portfolioModel->find($portfolioId);
        
        if (!$portfolio) {
            return $this->failNotFound('Portfolio not found');
        }
        
        // Check if user has access to this portfolio
        if (!$this->canAccessPortfolio($portfolio)) {
            return $this->failForbidden('You do not have access to this portfolio');
        }
        
        $users = $portfolioModel->getAssignedUsers($portfolioId);
        
        // Remove sensitive information
        foreach ($users as &$user) {
            unset($user['password']);
            unset($user['remember_token']);
            unset($user['reset_token']);
            unset($user['reset_token_expires_at']);
        }
        
        return $this->respond(['users' => $users]);
    }
    
    /**
     * Check if user can access a portfolio
     */
    private function canAccessPortfolio($portfolio)
    {
        if (!$this->user) {
            return false;
        }
        
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
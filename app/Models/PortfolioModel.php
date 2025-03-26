<?php

namespace App\Models;

use CodeIgniter\Model;

class PortfolioModel extends Model
{
    protected $table            = 'portfolios';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'organization_id', 'name', 'description', 'status'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'organization_id' => 'required|is_natural_no_zero',
        'name'            => 'required|min_length[3]|max_length[100]',
        'status'          => 'required|in_list[active,inactive]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get portfolios by organization
     */
    public function getByOrganization($organizationId)
    {
        return $this->where('organization_id', $organizationId)->findAll();
    }
    
    /**
     * Get portfolios assigned to a user
     */
    public function getByUser($userId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('portfolios p');
        $builder->select('p.*');
        $builder->join('portfolio_user pu', 'p.id = pu.portfolio_id');
        $builder->where('pu.user_id', $userId);
        $builder->where('p.deleted_at IS NULL');
        
        return $builder->get()->getResultArray();
    }
    
    /**
     * Assign users to a portfolio
     */
    public function assignUsers($portfolioId, array $userIds)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('portfolio_user');
        
        // First, delete existing assignments
        $builder->where('portfolio_id', $portfolioId)->delete();
        
        // Then, create new assignments
        $data = [];
        foreach ($userIds as $userId) {
            $data[] = [
                'portfolio_id' => $portfolioId,
                'user_id'      => $userId,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
        }
        
        if (!empty($data)) {
            $builder->insertBatch($data);
        }
        
        return true;
    }
    
    /**
     * Assign clients to a portfolio
     */
    public function assignClients($portfolioId, array $clientIds)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('client_portfolio');
        
        // First, delete existing assignments
        $builder->where('portfolio_id', $portfolioId)->delete();
        
        // Then, create new assignments
        $data = [];
        foreach ($clientIds as $clientId) {
            $data[] = [
                'portfolio_id' => $portfolioId,
                'client_id'    => $clientId,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
        }
        
        if (!empty($data)) {
            $builder->insertBatch($data);
        }
        
        return true;
    }
    
    /**
     * Get users assigned to a portfolio
     */
    public function getAssignedUsers($portfolioId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('users u');
        $builder->select('u.id, u.name, u.email, u.role');
        $builder->join('portfolio_user pu', 'u.id = pu.user_id');
        $builder->where('pu.portfolio_id', $portfolioId);
        $builder->where('u.deleted_at IS NULL');
        
        return $builder->get()->getResultArray();
    }
    
    /**
     * Get clients assigned to a portfolio
     */
    public function getAssignedClients($portfolioId)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('clients c');
        $builder->select('c.*');
        $builder->join('client_portfolio cp', 'c.id = cp.client_id');
        $builder->where('cp.portfolio_id', $portfolioId);
        $builder->where('c.deleted_at IS NULL');
        
        return $builder->get()->getResultArray();
    }
    
    /**
     * Get portfolios by client ID
     */
    public function getByClient($clientId)
    {
        $db = \Config\Database::connect();
        
        $query = $db->table('portfolios p')
            ->select('p.*')
            ->join('portfolio_clients pc', 'pc.portfolio_id = p.id')
            ->where('pc.client_id', $clientId)
            ->where('p.deleted_at IS NULL')
            ->get();
        
        return $query->getResultArray();
    }
}
<?php

namespace App\Models;

use CodeIgniter\Model;

class ClientModel extends Model
{
    protected $table            = 'clients';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'uuid', 'organization_id', 'external_id', 'business_name', 'legal_name', 
        'document_number', 'contact_name', 'contact_phone', 'address', 
        'ubigeo', 'zip_code', 'latitude', 'longitude', 'status'
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
        'business_name'   => 'required|min_length[3]|max_length[100]',
        'legal_name'      => 'required|min_length[3]|max_length[100]',
        'document_number' => 'required|min_length[3]|max_length[20]',
        'status'          => 'required|in_list[active,inactive]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $beforeInsert   = ['generateUuid', 'generateExternalId'];
    protected $beforeUpdate   = [];

    /**
     * Generate UUID before insert
     */
    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            $data['data']['uuid'] = bin2hex(random_bytes(16));
        }
        return $data;
    }

    /**
     * Generate external UUID if not provided
     */
    protected function generateExternalId(array $data)
    {
        if (!isset($data['data']['external_id']) || empty($data['data']['external_id'])) {
            $data['data']['external_id'] = bin2hex(random_bytes(16));
        }
        
        return $data;
    }
    
    /**
     * Get clients by organization
     */
    public function getByOrganization($organizationId)
    {
        return $this->where('organization_id', $organizationId)->findAll();
    }
    
    /**
     * Get clients by portfolio
     */
    public function getByPortfolio($portfolioUuid)
    {
        $db = \Config\Database::connect();
        return $db->table('clients c')
                 ->select('c.*')
                 ->join('client_portfolio cp', 'cp.client_uuid = c.uuid')
                 ->where('cp.portfolio_uuid', $portfolioUuid)
                 ->where('c.deleted_at IS NULL')
                 ->get()
                 ->getResultArray();
    }
    
    /**
     * Get client by external ID
     */
    public function getByExternalId($externalId, $organizationId = null)
    {
        $query = $this->where('external_id', $externalId);
        
        if ($organizationId) {
            $query = $query->where('organization_id', $organizationId);
        }
        
        return $query->first();
    }
    
    /**
     * Find client by ID
     */
    public function findById($id, $organizationId = null)
    {
        $query = $this->where('id', $id);
        
        if ($organizationId) {
            $query = $query->where('organization_id', $organizationId);
        }
        
        return $query->first();
    }
    
    /**
     * Get portfolios by client
     */
    public function getPortfolios($clientUuid)
    {
        $db = \Config\Database::connect();
        return $db->table('portfolios p')
                 ->select('p.*')
                 ->join('client_portfolio cp', 'cp.portfolio_uuid = p.uuid')
                 ->where('cp.client_uuid', $clientUuid)
                 ->where('p.deleted_at IS NULL')
                 ->get()
                 ->getResultArray();
    }
}
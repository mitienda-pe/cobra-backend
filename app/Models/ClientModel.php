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
        'organization_id', 'external_id', 'uuid', 'business_name', 'legal_name', 
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
    protected $beforeInsert   = ['generateExternalId', 'generateUuid'];
    protected $beforeUpdate   = [];

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
     * Generate UUID before insert
     */
    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            helper('uuid');
            $data['data']['uuid'] = generate_unique_uuid('clients', 'uuid');
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
    public function getByPortfolio($portfolioId)
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
     * Find client by UUID
     */
    public function findByUuid($uuid, $organizationId = null)
    {
        $query = $this->where('uuid', $uuid);
        
        if ($organizationId) {
            $query = $query->where('organization_id', $organizationId);
        }
        
        return $query->first();
    }
}
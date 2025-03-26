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
        'organization_id',
        'uuid',
        'name',
        'description',
        'status',
        'created_at',
        'updated_at',
        'deleted_at'
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
        'uuid'            => 'required|is_unique[portfolios.uuid,id,{id}]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    protected $beforeInsert = ['generateUuid'];
    protected $beforeUpdate = ['updateTimestamp'];

    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            helper('uuid');
            $uuid = generate_uuid();
            $data['data']['uuid'] = substr($uuid, 0, 8); // Usar solo los primeros 8 caracteres
        }
        
        return $data;
    }

    protected function updateTimestamp(array $data)
    {
        $data['data']['updated_at'] = date('Y-m-d H:i:s');
        return $data;
    }

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
    public function assignUsers($portfolioUuid, array $userUuids)
    {
        $db = \Config\Database::connect();
        
        // Eliminar asignaciones existentes
        $db->table('portfolio_user')
           ->where('portfolio_uuid', $portfolioUuid)
           ->delete();
        
        // Insertar nuevas asignaciones
        $data = [];
        foreach ($userUuids as $userUuid) {
            $data[] = [
                'portfolio_uuid' => $portfolioUuid,
                'user_uuid' => $userUuid,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        if (!empty($data)) {
            $db->table('portfolio_user')->insertBatch($data);
        }
    }
    
    /**
     * Assign clients to a portfolio
     */
    public function assignClients($portfolioUuid, array $clientUuids)
    {
        $db = \Config\Database::connect();
        
        // Eliminar asignaciones existentes
        $db->table('client_portfolio')
           ->where('portfolio_uuid', $portfolioUuid)
           ->delete();
        
        // Insertar nuevas asignaciones
        $data = [];
        foreach ($clientUuids as $clientUuid) {
            $data[] = [
                'portfolio_uuid' => $portfolioUuid,
                'client_uuid' => $clientUuid,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        if (!empty($data)) {
            $db->table('client_portfolio')->insertBatch($data);
        }
    }
    
    /**
     * Get users assigned to a portfolio
     */
    public function getAssignedUsers($portfolioUuid)
    {
        $db = \Config\Database::connect();
        return $db->table('users u')
                 ->select('u.*')
                 ->join('portfolio_user pu', 'pu.user_uuid = u.uuid')
                 ->where('pu.portfolio_uuid', $portfolioUuid)
                 ->where('u.deleted_at IS NULL')
                 ->get()
                 ->getResultArray();
    }
    
    /**
     * Get clients assigned to a portfolio
     */
    public function getAssignedClients($portfolioUuid)
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
     * Get portfolios by client ID
     */
    public function getByClient($clientId)
    {
        $db = \Config\Database::connect();
        
        $query = $db->table('portfolios p')
            ->select('p.*')
            ->join('client_portfolio pc', 'pc.portfolio_id = p.id')
            ->where('pc.client_id', $clientId)
            ->where('p.deleted_at IS NULL')
            ->get();
        
        return $query->getResultArray();
    }
}
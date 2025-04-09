<?php

namespace App\Models;

use CodeIgniter\Model;

class OrganizationModel extends Model
{
    protected $table            = 'organizations';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = ['uuid', 'name', 'code', 'description', 'status', 'ligo_api_key', 'ligo_api_secret', 'ligo_webhook_secret', 'ligo_enabled', 'ligo_auth_token', 'ligo_username', 'ligo_password', 'ligo_company_id', 'ligo_token', 'ligo_token_expiry', 'ligo_auth_error'];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'name'     => 'required|min_length[3]|max_length[100]',
        'code'     => 'required|min_length[2]|max_length[50]',
        'status'   => 'required|in_list[active,inactive]',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Select fields
    protected $selectedFields = ['id', 'uuid', 'name', 'code', 'description', 'status', 'created_at', 'updated_at'];

    // Callbacks
    protected $beforeInsert   = ['generateUuid'];
    
    /**
     * Generate UUID before insert
     */
    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            helper('uuid');
            $data['data']['uuid'] = generate_unique_uuid('organizations', 'uuid');
        }
        return $data;
    }

    public function getActiveOrganizations()
    {
        return $this->where('status', 'active')->findAll();
    }
}
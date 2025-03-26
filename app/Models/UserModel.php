<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'organization_id', 'uuid', 'name', 'email', 'phone', 'password', 'role', 'status',
        'remember_token', 'reset_token', 'reset_token_expires_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'name'     => 'required|min_length[3]|max_length[100]',
        'email'    => 'required|valid_email|is_unique[users.email,deleted_at,NULL]',
        'phone'    => 'permit_empty|min_length[6]|max_length[20]|is_unique[users.phone,deleted_at,NULL]',
        'password' => 'required|min_length[8]',
        'role'     => 'required|in_list[superadmin,admin,user]',
        'status'   => 'required|in_list[active,inactive]',
    ];
    
    /**
     * Get validation rules for update
     */
    public function getValidationRulesForUpdate($id = null)
    {
        return [
            'name'     => 'required|min_length[3]|max_length[100]',
            'email'    => "required|valid_email|is_unique[users.email,id,$id,deleted_at,NULL]",
            'phone'    => "permit_empty|min_length[6]|max_length[20]|is_unique[users.phone,id,$id,deleted_at,NULL]",
            'role'     => 'required|in_list[superadmin,admin,user]',
            'status'   => 'required|in_list[active,inactive]',
            'password' => 'permit_empty|min_length[8]'
        ];
    }
    
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Before insert callbacks
     */
    protected $beforeInsert = ['hashPassword', 'generateUuid'];
    
    /**
     * Before update callbacks
     */
    protected $beforeUpdate = ['hashPassword'];

    /**
     * Hash password before inserting or updating
     */
    protected function hashPassword(array $data)
    {
        if (! isset($data['data']['password'])) {
            return $data;
        }

        $data['data']['password'] = password_hash($data['data']['password'], PASSWORD_BCRYPT);
        
        return $data;
    }
    
    /**
     * Generate UUID before insert
     */
    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            helper('uuid');
            $data['data']['uuid'] = generate_unique_uuid('users', 'uuid');
        }
        return $data;
    }

    /**
     * Authenticate user
     */
    public function authenticate($email, $password)
    {
        $user = $this->where('email', $email)
                     ->where('status', 'active')
                     ->first();
        
        if (is_null($user)) {
            return false;
        }
        
        return password_verify($password, $user['password']) ? $user : false;
    }

    /**
     * Get user by role
     */
    public function getByRole($role)
    {
        return $this->where('role', $role)->findAll();
    }

    /**
     * Get users by organization
     */
    public function getByOrganization($organizationId)
    {
        return $this->where('organization_id', $organizationId)->findAll();
    }
}
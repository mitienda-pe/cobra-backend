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
        'phone'    => 'required|min_length[10]|is_unique[users.phone,deleted_at,NULL]',
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
            'phone'    => "required|min_length[10]|is_unique[users.phone,id,$id,deleted_at,NULL]",
            'role'     => 'required|in_list[superadmin,admin,user]',
            'status'   => 'required|in_list[active,inactive]',
            'password' => 'permit_empty|min_length[8]'
        ];
    }
    
    protected $validationMessages   = [
        'name' => [
            'required' => 'El nombre es requerido',
            'min_length' => 'El nombre debe tener al menos 3 caracteres',
            'max_length' => 'El nombre no puede tener más de 100 caracteres'
        ],
        'email' => [
            'required' => 'El correo electrónico es requerido',
            'valid_email' => 'El correo electrónico no es válido',
            'is_unique' => 'Este correo electrónico ya está registrado'
        ],
        'phone' => [
            'required' => 'El número de teléfono es requerido',
            'min_length' => 'El número de teléfono debe tener al menos 10 dígitos',
            'is_unique' => 'Este número de teléfono ya está registrado'
        ],
        'password' => [
            'required' => 'La contraseña es requerida',
            'min_length' => 'La contraseña debe tener al menos 8 caracteres'
        ],
        'role' => [
            'required' => 'El rol es requerido',
            'in_list' => 'El rol seleccionado no es válido'
        ]
    ];
    
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Before insert callbacks
     */
    protected $beforeInsert = ['hashPassword', 'generateUuid', 'formatPhone'];
    
    /**
     * Before update callbacks
     */
    protected $beforeUpdate = ['hashPassword', 'formatPhone'];

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
     * Format phone number to E.164 format
     */
    protected function formatPhone(array $data)
    {
        if (isset($data['data']['phone'])) {
            helper('phone');
            $data['data']['phone'] = format_phone_e164($data['data']['phone']);
            
            // If phone formatting failed, unset it to prevent invalid data
            if ($data['data']['phone'] === null || !is_valid_e164($data['data']['phone'])) {
                unset($data['data']['phone']);
            }
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
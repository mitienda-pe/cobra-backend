<?php

namespace App\Models;

use CodeIgniter\Model;

class UserApiTokenModel extends Model
{
    protected $table            = 'user_api_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'client_id',
        'token',
        'device_info',
        'expires_at',
        'revoked',
        'created_at'
    ];

    // Dates
    protected $useTimestamps = false; // We'll manage timestamps manually
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [
        'client_id' => 'required',
        'token'    => 'required',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    
    /**
     * Get a token by value
     */
    public function getByToken($token)
    {
        return $this->where('token', $token)
                    ->where('revoked', false)
                    ->where('expires_at >', date('Y-m-d H:i:s'))
                    ->first();
    }
    
    /**
     * Revoke a token
     */
    public function revokeToken($token)
    {
        return $this->where('token', $token)
                    ->set(['revoked' => true])
                    ->update();
    }
}
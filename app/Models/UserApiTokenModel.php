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
        'user_id', 'name', 'token', 'refresh_token', 'scopes', 
        'last_used_at', 'expires_at', 'revoked'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [
        'user_id' => 'required|is_natural_no_zero',
        'name'    => 'required|min_length[3]|max_length[100]',
        'token'   => 'required',
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;
    
    /**
     * Create a new token for a user
     */
    public function createToken($userId, $name, $scopes = [], $expiresAt = null)
    {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        $refreshToken = bin2hex(random_bytes(32));
        
        // Default expiration is 30 days from now
        if ($expiresAt === null) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        }
        
        $data = [
            'user_id'       => $userId,
            'name'          => $name,
            'token'         => $token,
            'refresh_token' => $refreshToken,
            'scopes'        => json_encode($scopes),
            'expires_at'    => $expiresAt,
            'revoked'       => false
        ];
        
        $this->insert($data);
        
        return [
            'accessToken'  => $token,
            'refreshToken' => $refreshToken,
            'expiresAt'    => $expiresAt
        ];
    }
    
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
     * Refresh a token
     */
    public function refreshToken($refreshToken)
    {
        $token = $this->where('refresh_token', $refreshToken)
                      ->where('revoked', false)
                      ->first();
        
        if (!$token) {
            return false;
        }
        
        // Generate new tokens
        $newToken = bin2hex(random_bytes(32));
        $newRefreshToken = bin2hex(random_bytes(32));
        
        // Set new expiration date to 30 days from now
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $this->update($token['id'], [
            'token'         => $newToken,
            'refresh_token' => $newRefreshToken,
            'expires_at'    => $expiresAt,
            'last_used_at'  => date('Y-m-d H:i:s')
        ]);
        
        return [
            'accessToken'  => $newToken,
            'refreshToken' => $newRefreshToken,
            'expiresAt'    => $expiresAt
        ];
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
    
    /**
     * Update last used timestamp
     */
    public function updateLastUsed($tokenId)
    {
        return $this->update($tokenId, ['last_used_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Get all active tokens for a user
     */
    public function getTokensByUser($userId)
    {
        return $this->where('user_id', $userId)
                    ->where('revoked', false)
                    ->findAll();
    }
    
    /**
     * Check if token has required scope
     */
    public function tokenCan($token, $scope)
    {
        $scopes = json_decode($token['scopes'], true);
        
        if (in_array('*', $scopes)) {
            return true;
        }
        
        return in_array($scope, $scopes);
    }
}
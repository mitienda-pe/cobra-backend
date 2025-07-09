<?php

namespace App\Models;

use CodeIgniter\Model;

class UserOtpModel extends Model
{
    protected $table            = 'user_otp_codes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'phone',
        'email',
        'code',
        'organization_code',
        'device_info',
        'delivery_status',
        'delivery_info',
        'expires_at',
        'created_at'
    ];

    // Dates
    protected $useTimestamps = false; // We'll manage timestamps manually
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';
    protected $deletedField  = '';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Verify OTP code
     */
    public function verifyOTP($phone, $email, $code, $organizationCode = null)
    {
        log_message('info', '=== VERIFY OTP MODEL START ===');
        
        $where = [
            'code' => $code,
            'expires_at >' => date('Y-m-d H:i:s')
        ];

        if (!empty($phone)) {
            $where['phone'] = $phone;
        }

        if (!empty($email)) {
            $where['email'] = $email;
        }

        if (!empty($organizationCode)) {
            $where['organization_code'] = $organizationCode;
        }
        
        log_message('info', 'OTP verification WHERE conditions: ' . json_encode($where));

        $otp = $this->where($where)
                   ->orderBy('created_at', 'DESC')
                   ->first();

        log_message('info', 'OTP verification result: ' . json_encode($otp));

        return $otp;
    }

    /**
     * Update delivery status
     */
    public function updateDeliveryStatus($identifier, $code, $status, $info = null)
    {
        $where = [
            'code' => $code,
        ];

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $where['email'] = $identifier;
        } else {
            $where['phone'] = $identifier;
        }

        return $this->where($where)
                   ->set([
                       'delivery_status' => $status,
                       'delivery_info' => $info
                   ])
                   ->update();
    }
}

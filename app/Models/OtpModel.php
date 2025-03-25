<?php

namespace App\Models;

use CodeIgniter\Model;

class OtpModel extends Model
{
    protected $table = 'user_otps';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $allowedFields = ['user_id', 'otp', 'method', 'device_info', 'expires_at', 'verified'];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    
    protected $validationRules = [
        'user_id' => 'required|numeric',
        'otp' => 'required|exact_length[6]',
        'method' => 'required|in_list[sms,email]',
        'device_info' => 'required',
        'expires_at' => 'required|valid_date'
    ];
}

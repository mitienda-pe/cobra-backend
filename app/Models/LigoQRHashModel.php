<?php
namespace App\Models;

use CodeIgniter\Model;

class LigoQRHashModel extends Model
{
    protected $table = 'ligo_qr_hashes';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'hash', 'real_hash', 'hash_error', 'order_id', 'id_qr', 'invoice_id', 'instalment_id', 'amount', 'currency', 'description', 'environment', 'created_at', 'updated_at'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}

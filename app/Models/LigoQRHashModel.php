<?php
namespace App\Models;

use CodeIgniter\Model;

class LigoQRHashModel extends Model
{
    protected $table = 'ligo_qr_hashes';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'hash', 'order_id', 'invoice_id', 'instalment_id', 'amount', 'currency', 'description', 'created_at', 'updated_at'
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}

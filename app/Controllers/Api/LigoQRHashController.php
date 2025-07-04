<?php
namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use App\Models\LigoQRHashModel;

class LigoQRHashController extends ResourceController
{
    protected $format = 'json';

    public function index()
    {
        $model = new LigoQRHashModel();
        $hashes = $model->orderBy('created_at', 'DESC')->findAll(50);
        return $this->respond($hashes);
    }
}

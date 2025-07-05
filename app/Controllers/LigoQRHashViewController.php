<?php
namespace App\Controllers;

use App\Models\LigoQRHashModel;
use CodeIgniter\Controller;

class LigoQRHashViewController extends Controller
{
    public function index()
    {
        $model = new LigoQRHashModel();
        $hashes = $model->orderBy('created_at', 'DESC')->findAll(100);
        return view('hashes/ligo_hashes', ['hashes' => $hashes]);
    }
}

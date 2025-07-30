<?php
namespace App\Controllers;

use App\Models\LigoQRHashModel;
use App\Models\InvoiceModel;
use App\Models\InstalmentModel;
use CodeIgniter\Controller;

class LigoQRHashViewController extends Controller
{
    public function index()
    {
        $model = new LigoQRHashModel();
        $invoiceModel = new InvoiceModel();
        $instalmentModel = new InstalmentModel();
        
        // Obtener hashes con información adicional
        $hashes = $model->select('ligo_qr_hashes.*, invoices.invoice_number, invoices.uuid as invoice_uuid, instalments.number as instalment_number, instalments.status as instalment_status')
                        ->join('invoices', 'invoices.id = ligo_qr_hashes.invoice_id', 'left')
                        ->join('instalments', 'instalments.id = ligo_qr_hashes.instalment_id', 'left')
                        ->orderBy('ligo_qr_hashes.created_at', 'DESC')
                        ->findAll(100);
        
        return view('hashes/ligo_hashes', ['hashes' => $hashes]);
    }
}

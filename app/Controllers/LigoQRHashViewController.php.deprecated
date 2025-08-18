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
        $paymentModel = new \App\Models\PaymentModel();
        
        // Obtener hashes con información adicional
        $hashes = $model->select('ligo_qr_hashes.*, invoices.invoice_number, invoices.uuid as invoice_uuid, instalments.number as instalment_number, instalments.status as instalment_status, instalments.amount as instalment_amount')
                        ->join('invoices', 'invoices.id = ligo_qr_hashes.invoice_id', 'left')
                        ->join('instalments', 'instalments.id = ligo_qr_hashes.instalment_id', 'left')
                        ->orderBy('ligo_qr_hashes.created_at', 'DESC')
                        ->findAll(100);
        
        // Calcular el estado real de pago para cada hash
        foreach ($hashes as &$hash) {
            if ($hash['instalment_id']) {
                // Obtener pagos para esta cuota
                $payments = $paymentModel->where('instalment_id', $hash['instalment_id'])->findAll();
                
                $totalPaid = 0;
                foreach ($payments as $payment) {
                    $paymentAmount = $payment['amount'];
                    // Normalizar montos de Ligo QR (convertir centavos a soles)
                    if ($payment['payment_method'] === 'ligo_qr' && $paymentAmount >= 100) {
                        $paymentAmount = $paymentAmount / 100;
                    }
                    $totalPaid += $paymentAmount;
                }
                
                // Determinar si está realmente pagado basándose en los pagos
                $instalmentAmount = $hash['instalment_amount'] ?? 0;
                $hash['is_actually_paid'] = $totalPaid >= $instalmentAmount;
                $hash['total_paid'] = $totalPaid;
            } else {
                $hash['is_actually_paid'] = false;
                $hash['total_paid'] = 0;
            }
        }
        
        return view('hashes/ligo_hashes', ['hashes' => $hashes]);
    }
}

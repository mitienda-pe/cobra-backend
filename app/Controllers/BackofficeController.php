<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\API\ResponseTrait;

class BackofficeController extends Controller
{
    use ResponseTrait;

    protected $ligoModel;
    protected $organizationModel;

    public function __construct()
    {
        $this->ligoModel = new \App\Models\LigoModel();
        $this->organizationModel = new \App\Models\OrganizationModel();
        helper(['form', 'url']);
    }

    public function index()
    {
        $data = [
            'title' => 'Backoffice Ligo',
            'breadcrumb' => 'Backoffice'
        ];

        return view('backoffice/index', $data);
    }

    public function balance()
    {
        $data = [
            'title' => 'Balance de Cuenta - Ligo',
            'breadcrumb' => 'Balance de Cuenta'
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            log_message('debug', 'BackofficeController: Processing balance request');
            
            // Verificar sesión y organización
            $session = session();
            $organizationId = $session->get('selected_organization_id');
            log_message('debug', 'BackofficeController: Organization ID from session: ' . ($organizationId ?? 'null'));
            
            if (!$organizationId) {
                log_message('error', 'BackofficeController: No organization selected in session');
                return $this->fail('No hay organización seleccionada', 400);
            }
            
            // Usar el account_id de la organización automáticamente
            $response = $this->ligoModel->getAccountBalanceForOrganization();
            
            log_message('debug', 'BackofficeController: Balance response: ' . json_encode($response));
            
            if (isset($response['error'])) {
                log_message('error', 'BackofficeController: Balance error: ' . $response['error']);
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/balance', $data);
    }

    public function transactions()
    {
        $data = [
            'title' => 'Transacciones - Ligo',
            'breadcrumb' => 'Transacciones'
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            log_message('debug', 'BackofficeController: Processing transactions request');
            
            // Verificar sesión y organización
            $session = session();
            $organizationId = $session->get('selected_organization_id');
            log_message('debug', 'BackofficeController: Organization ID from session: ' . ($organizationId ?? 'null'));
            
            if (!$organizationId) {
                log_message('error', 'BackofficeController: No organization selected in session');
                return $this->fail('No hay organización seleccionada', 400);
            }
            
            $requestData = [
                'page' => $this->request->getPost('page') ?: 1,
                'startDate' => $this->request->getPost('startDate'),
                'endDate' => $this->request->getPost('endDate')
            ];

            log_message('debug', 'BackofficeController: Request data: ' . json_encode($requestData));

            if (empty($requestData['startDate']) || empty($requestData['endDate'])) {
                log_message('error', 'BackofficeController: Missing required dates');
                return $this->fail('Las fechas de inicio y fin son requeridas', 400);
            }

            $response = $this->ligoModel->listTransactionsForOrganization($requestData);
            
            log_message('debug', 'BackofficeController: Transactions response: ' . json_encode($response));
            
            if (isset($response['error'])) {
                log_message('error', 'BackofficeController: Transactions error: ' . $response['error']);
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/transactions', $data);
    }

    public function transactionDetail($id)
    {
        $data = [
            'title' => 'Detalle de Transacción - Ligo',
            'breadcrumb' => 'Detalle de Transacción',
            'transaction_id' => $id
        ];

        if ($this->request->isAJAX()) {
            $response = $this->ligoModel->getTransactionDetail($id);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/transaction_detail', $data);
    }

    public function recharges()
    {
        $data = [
            'title' => 'Recargas - Ligo',
            'breadcrumb' => 'Recargas'
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            $requestData = [
                'page' => $this->request->getPost('page') ?: 1,
                'startDate' => $this->request->getPost('startDate'),
                'endDate' => $this->request->getPost('endDate')
            ];

            if (empty($requestData['startDate']) || empty($requestData['endDate'])) {
                return $this->fail('Las fechas de inicio y fin son requeridas', 400);
            }

            $response = $this->ligoModel->listRecharges($requestData);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return view('backoffice/recharges', $data);
    }

    public function transfer()
    {
        // Obtener organización seleccionada del contexto (solo para superadmin)
        $selectedOrgId = session()->get('selected_organization_id');
        if (!$selectedOrgId) {
            return redirect()->to('organizations')->with('error', 'Debe seleccionar una organización primero');
        }
        
        $organizationModel = new \App\Models\OrganizationModel();
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        
        // Obtener la organización seleccionada
        $organization = $organizationModel->find($selectedOrgId);
        if (!$organization || $organization['status'] !== 'active') {
            return redirect()->to('organizations')->with('error', 'Organización no válida o inactiva');
        }
        
        // Validar que la organización tenga CCI configurado
        if (empty($organization['cci'])) {
            return redirect()->back()->with('error', 'La organización seleccionada no tiene CCI configurado. Configure el CCI en la edición de la organización.');
        }
        
        // Obtener configuración activa del superadmin
        $superadminConfig = $superadminLigoConfigModel->where('enabled', 1)
                                                      ->where('is_active', 1)
                                                      ->first();
        
        $data = [
            'title' => 'Transferencia Ordinaria - Ligo',
            'breadcrumb' => 'Transferencia Ordinaria',
            'organization' => $organization,
            'superadminConfig' => $superadminConfig
        ];

        if ($this->request->getMethod() === 'post') {
            // Validar que hay configuración del superadmin
            if (!$superadminConfig) {
                if ($this->request->isAJAX()) {
                    return $this->fail('No hay configuración de Ligo del superadmin disponible', 400);
                }
                return redirect()->back()->with('error', 'No hay configuración de Ligo del superadmin disponible');
            }

            // Construir datos de transferencia usando la organización del contexto
            $transferData = [
                'organization_id' => $selectedOrgId,
                'creditorCCI' => $this->request->getPost('creditorCCI'),
                'amount' => $this->request->getPost('amount'),
                'currency' => $this->request->getPost('currency') ?: 'PEN',
                'unstructuredInformation' => $this->request->getPost('unstructuredInformation')
            ];

            if ($this->request->isAJAX()) {
                $response = $this->ligoModel->processOrdinaryTransferFromSuperadmin($transferData, $superadminConfig, $organization);
                
                if (isset($response['error'])) {
                    return $this->fail($response['error'], 400);
                }

                return $this->respond($response);
            } else {
                $response = $this->ligoModel->processOrdinaryTransferFromSuperadmin($transferData, $superadminConfig, $organization);
                
                if (isset($response['error'])) {
                    return redirect()->back()->with('error', $response['error']);
                }

                return redirect()->back()->with('success', 'Transferencia procesada exitosamente');
            }
        }

        return view('backoffice/transfer', $data);
    }

    public function transferStatus($transferId)
    {
        if ($this->request->isAJAX()) {
            $response = $this->ligoModel->getTransferStatus($transferId);
            
            if (isset($response['error'])) {
                return $this->fail($response['error'], 400);
            }

            return $this->respond($response);
        }

        return $this->fail('Invalid request', 400);
    }

    public function hashes()
    {
        $ligoQRHashModel = new \App\Models\LigoQRHashModel();
        $paymentModel = new \App\Models\PaymentModel();
        
        // Obtener hashes con información adicional
        $hashes = $ligoQRHashModel->select('ligo_qr_hashes.*, invoices.invoice_number, invoices.uuid as invoice_uuid, instalments.number as instalment_number, instalments.status as instalment_status, instalments.amount as instalment_amount')
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

        $data = [
            'title' => 'Hashes QR Ligo',
            'hashes' => $hashes
        ];

        return view('backoffice/hashes', $data);
    }
}
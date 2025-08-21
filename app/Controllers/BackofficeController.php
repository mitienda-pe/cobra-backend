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
        log_message('info', 'BackofficeController::__construct() - Controller instantiated');
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
        log_message('info', 'BackofficeController::balance() - Method called');
        
        // Get session and check if superadmin has access without organization
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        $auth = new \App\Libraries\Auth();
        $isSuperadmin = $auth->hasRole('superadmin');
        
        log_message('debug', 'BackofficeController::balance() - organizationId: ' . ($organizationId ?? 'null') . ', isSuperadmin: ' . ($isSuperadmin ? 'true' : 'false'));
        log_message('debug', 'BackofficeController::balance() - Request method: ' . $this->request->getMethod() . ', isAJAX: ' . ($this->request->isAJAX() ? 'true' : 'false'));
        
        // For superadmin without organization, show general balance view
        if ($isSuperadmin && !$organizationId) {
            $data = [
                'title' => 'Balance General - Ligo',
                'breadcrumb' => 'Balance General',
                'is_general_view' => true,
                'organization' => null
            ];
            
            if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
                log_message('info', 'BackofficeController: Superadmin requesting general CCI balance');
                
                // Query the general CCI account balance using centralized configuration
                $response = $this->ligoModel->getAccountBalanceForOrganization();
                
                log_message('debug', 'BackofficeController: General balance response: ' . json_encode($response));
                
                if (isset($response['error'])) {
                    log_message('error', 'BackofficeController: General balance error: ' . $response['error']);
                    return $this->fail($response['error'], 400);
                }

                return $this->respond($response);
            }
            
            return view('backoffice/balance', $data);
        }
        
        // Original organization-specific logic
        if (!$organizationId) {
            return redirect()->to('organizations')->with('error', 'Debe seleccionar una organización primero');
        }
        
        // Calculate available balance from transfers for this organization
        $transferModel = new \App\Models\TransferModel();
        $balanceData = $transferModel->calculateOrganizationBalance($organizationId);
        $organization = $this->organizationModel->find($organizationId);
        
        // If calculated balance is 0, try to get real balance from Ligo
        $finalBalance = $balanceData['available_balance'];
        if ($finalBalance <= 0) {
            log_message('info', 'BackofficeController: Calculated balance is 0, checking Ligo balance...');
            $ligoBalance = $this->ligoModel->getAccountBalanceForOrganization();
            if (!isset($ligoBalance['error']) && isset($ligoBalance['data']['amount'])) {
                $finalBalance = floatval($ligoBalance['data']['amount']);
                log_message('info', 'BackofficeController: Using Ligo balance: ' . $finalBalance);
            }
        }
        
        $data = [
            'title' => 'Balance de Cuenta - Ligo',
            'breadcrumb' => 'Balance de Cuenta',
            'accountBalance' => $finalBalance,
            'organization' => $organization,
            'is_general_view' => false
        ];

        if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
            log_message('debug', 'BackofficeController: Processing balance request');
            
            // Verificar sesión y organización
            $session = session();
            $organizationId = $session->get('selected_organization_id');
            $auth = new \App\Libraries\Auth();
            $isSuperadmin = $auth->hasRole('superadmin');
            
            log_message('debug', 'BackofficeController: Organization ID from session: ' . ($organizationId ?? 'null'));
            log_message('debug', 'BackofficeController: Is superadmin: ' . ($isSuperadmin ? 'true' : 'false'));
            
            // For superadmin, organization selection is not required for balance query
            if (!$organizationId && !$isSuperadmin) {
                log_message('error', 'BackofficeController: No organization selected and user is not superadmin');
                return $this->fail('No hay organización seleccionada', 400);
            }
            
            if (!$organizationId && $isSuperadmin) {
                log_message('info', 'BackofficeController: Superadmin requesting general balance (no organization selected)');
            }
            
            // Usar el account_id de la configuración centralizada (funciona con o sin organización seleccionada)
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
        // Get session and check if superadmin has access without organization
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        $auth = new \App\Libraries\Auth();
        $isSuperadmin = $auth->hasRole('superadmin');
        
        // For superadmin without organization, show general transactions view
        if ($isSuperadmin && !$organizationId) {
            $data = [
                'title' => 'Transacciones Generales - Ligo',
                'breadcrumb' => 'Transacciones Generales',
                'is_general_view' => true
            ];
            
            if ($this->request->isAJAX() && $this->request->getMethod() === 'post') {
                // For general view, return message indicating organization selection needed for specific transactions
                return $this->respond([
                    'message' => 'Vista general - seleccione una organización para ver transacciones específicas',
                    'general_view' => true,
                    'data' => []
                ]);
            }
            
            return view('backoffice/transactions', $data);
        }
        
        // Original organization-specific logic
        if (!$organizationId) {
            return redirect()->to('organizations')->with('error', 'Debe seleccionar una organización primero');
        }
        
        $data = [
            'title' => 'Transacciones - Ligo',
            'breadcrumb' => 'Transacciones',
            'is_general_view' => false
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
        
        // Calculate available balance from transfers for this organization
        $transferModel = new \App\Models\TransferModel();
        $balanceData = $transferModel->calculateOrganizationBalance($selectedOrgId);
        
        // If calculated balance is 0, try to get real balance from Ligo
        $finalBalance = $balanceData['available_balance'];
        if ($finalBalance <= 0) {
            log_message('info', 'BackofficeController: Calculated balance is 0, checking Ligo balance...');
            $ligoBalance = $this->ligoModel->getAccountBalanceForOrganization();
            if (!isset($ligoBalance['error']) && isset($ligoBalance['data']['amount'])) {
                $finalBalance = floatval($ligoBalance['data']['amount']);
                log_message('info', 'BackofficeController: Using Ligo balance: ' . $finalBalance);
            } else {
                log_message('warning', 'BackofficeController: Could not get Ligo balance: ' . json_encode($ligoBalance));
            }
        }
        
        $data = [
            'title' => 'Transferencia Ordinaria - Ligo',
            'breadcrumb' => 'Transferencia Ordinaria',
            'accountBalance' => $finalBalance,
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

    /**
     * Step 1: Account Inquiry - Verify destination account
     */
    public function transferStep1()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod() !== 'post') {
            return $this->fail('Invalid request', 400);
        }

        $selectedOrgId = session()->get('selected_organization_id');
        if (!$selectedOrgId) {
            return $this->fail('Debe seleccionar una organización primero', 400);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();

        $organization = $organizationModel->find($selectedOrgId);
        if (!$organization || $organization['status'] !== 'active') {
            return $this->fail('Organización no válida o inactiva', 400);
        }

        if (empty($organization['cci'])) {
            return $this->fail('La organización no tiene CCI configurado', 400);
        }

        $superadminConfig = $superadminLigoConfigModel->where('enabled', 1)
                                                      ->where('is_active', 1)
                                                      ->first();

        if (!$superadminConfig) {
            return $this->fail('No hay configuración de Ligo del superadmin disponible', 400);
        }

        $creditorCCI = $this->request->getPost('creditorCCI');
        $currency = $this->request->getPost('currency') ?: 'PEN';

        if (empty($creditorCCI)) {
            return $this->fail('CCI del acreedor es requerido', 400);
        }

        $response = $this->ligoModel->performAccountInquiry($superadminConfig, $organization, $creditorCCI, $currency);

        if (isset($response['error'])) {
            return $this->fail($response['error'], 400);
        }

        return $this->respond([
            'success' => true,
            'data' => $response,
            'message' => 'Cuenta verificada exitosamente'
        ]);
    }

    /**
     * Step 2: Get Account Inquiry Result by ID
     */
    public function transferStep2()
    {
        if (!$this->request->isAJAX() || $this->request->getMethod() !== 'post') {
            return $this->fail('Invalid request', 400);
        }

        $accountInquiryId = $this->request->getPost('accountInquiryId');
        
        if (empty($accountInquiryId)) {
            return $this->fail('Account Inquiry ID es requerido', 400);
        }

        $response = $this->ligoModel->getAccountInquiryResult($accountInquiryId);

        if (isset($response['error'])) {
            return $this->fail($response['error'], 400);
        }

        return $this->respond([
            'success' => true,
            'data' => $response,
            'message' => 'Información de cuenta obtenida exitosamente'
        ]);
    }

    /**
     * Step 3: Calculate Transfer Fee
     */
    public function transferStep3()
    {
        try {
            log_message('info', 'BackofficeController: transferStep3 - Request received. Method: ' . $this->request->getMethod() . ', AJAX: ' . ($this->request->isAJAX() ? 'YES' : 'NO'));
            
            if (!$this->request->isAJAX() || $this->request->getMethod() !== 'post') {
                log_message('error', 'BackofficeController: transferStep3 - Invalid request method or not AJAX');
                return $this->fail('Invalid request', 400);
            }
        } catch (\Exception $e) {
            log_message('error', 'BackofficeController: transferStep3 - Exception in initial checks: ' . $e->getMessage());
            return $this->fail('Internal error', 500);
        }

        $debtorCCI = $this->request->getPost('debtorCCI');
        $creditorCCI = $this->request->getPost('creditorCCI');
        $amount = $this->request->getPost('amount');
        $currency = $this->request->getPost('currency') ?: 'PEN';

        log_message('debug', 'BackofficeController: transferStep3 - debtorCCI: ' . ($debtorCCI ?? 'NULL') . ', creditorCCI: ' . ($creditorCCI ?? 'NULL') . ', amount: ' . ($amount ?? 'NULL') . ', currency: ' . $currency);

        if (empty($debtorCCI) || empty($creditorCCI) || empty($amount)) {
            return $this->fail('Todos los campos son requeridos (debtorCCI, creditorCCI, amount)', 400);
        }

        if (!is_numeric($amount) || $amount <= 0) {
            return $this->fail('El monto debe ser un número válido mayor a 0', 400);
        }

        try {
            $response = $this->ligoModel->calculateTransferFee($debtorCCI, $creditorCCI, $amount, $currency);

            if (isset($response['error'])) {
                log_message('error', 'BackofficeController: transferStep3 - LigoModel error: ' . $response['error']);
                return $this->fail($response['error'], 400);
            }

            log_message('info', 'BackofficeController: transferStep3 - Success');
            return $this->respond([
                'success' => true,
                'data' => $response,
                'message' => 'Comisión calculada exitosamente'
            ]);
        } catch (\Exception $e) {
            log_message('error', 'BackofficeController: transferStep3 - Exception: ' . $e->getMessage());
            return $this->fail('Internal error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Step 4: Execute Transfer
     */
    public function transferStep4()
    {
        log_message('error', '🎯 BackofficeController: transferStep4 START - DEBUG LOG');
        
        if (!$this->request->isAJAX() || $this->request->getMethod() !== 'post') {
            log_message('error', 'BackofficeController: transferStep4 - Invalid request method or not AJAX');
            return $this->fail('Invalid request', 400);
        }

        $selectedOrgId = session()->get('selected_organization_id');
        log_message('error', '🏢 BackofficeController: transferStep4 - Selected org ID: ' . ($selectedOrgId ?? 'null') . ' - DEBUG LOG');
        
        if (!$selectedOrgId) {
            log_message('error', 'BackofficeController: transferStep4 - No organization selected');
            return $this->fail('Debe seleccionar una organización primero', 400);
        }

        $organizationModel = new \App\Models\OrganizationModel();
        $superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();

        $organization = $organizationModel->find($selectedOrgId);
        $superadminConfig = $superadminLigoConfigModel->where('enabled', 1)
                                                      ->where('is_active', 1)
                                                      ->first();

        log_message('info', '🏢 BackofficeController: transferStep4 - Organization found: ' . ($organization ? 'Yes' : 'No'));
        log_message('info', '⚙️ BackofficeController: transferStep4 - SuperadminConfig found: ' . ($superadminConfig ? 'Yes' : 'No'));

        // Recoger todos los datos necesarios del frontend
        $transferData = [
            'debtorCCI' => $this->request->getPost('debtorCCI'),
            'creditorCCI' => $this->request->getPost('creditorCCI'),
            'amount' => $this->request->getPost('amount'),
            'currency' => $this->request->getPost('currency') ?: 'PEN',
            'feeAmount' => $this->request->getPost('feeAmount'),
            'feeCode' => $this->request->getPost('feeCode'),
            'applicationCriteria' => $this->request->getPost('applicationCriteria'),
            'messageTypeId' => $this->request->getPost('messageTypeId'),
            'instructionId' => $this->request->getPost('instructionId'),
            'unstructuredInformation' => $this->request->getPost('unstructuredInformation'),
            'feeId' => $this->request->getPost('feeId'),
            'feeLigo' => $this->request->getPost('feeLigo')
        ];

        log_message('info', '📦 BackofficeController: transferStep4 - Transfer data received: ' . json_encode($transferData));

        // Validar campos requeridos
        $requiredFields = ['debtorCCI', 'creditorCCI', 'amount', 'feeAmount', 'feeCode'];
        foreach ($requiredFields as $field) {
            if (empty($transferData[$field]) && $transferData[$field] !== '0') {
                log_message('error', 'BackofficeController: transferStep4 - Missing required field: ' . $field);
                return $this->fail("Campo requerido: {$field}", 400);
            }
        }

        log_message('info', '✅ BackofficeController: transferStep4 - All required fields validated, calling executeTransfer');
        $response = $this->ligoModel->executeTransfer($superadminConfig, $organization, $transferData);

        if (isset($response['error'])) {
            return $this->fail($response['error'], 400);
        }

        return $this->respond([
            'success' => true,
            'data' => $response,
            'message' => 'Transferencia ejecutada exitosamente'
        ]);
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
        // Get session and check if superadmin has access without organization
        $session = session();
        $organizationId = $session->get('selected_organization_id');
        $auth = new \App\Libraries\Auth();
        $isSuperadmin = $auth->hasRole('superadmin');
        
        $ligoQRHashModel = new \App\Models\LigoQRHashModel();
        $paymentModel = new \App\Models\PaymentModel();
        
        // Build query - if organization selected, filter by it; if superadmin without organization, show all
        $hashQuery = $ligoQRHashModel->select('ligo_qr_hashes.*, invoices.invoice_number, invoices.uuid as invoice_uuid, invoices.organization_id, organizations.name as organization_name, instalments.number as instalment_number, instalments.status as instalment_status, instalments.amount as instalment_amount')
                                    ->join('invoices', 'invoices.id = ligo_qr_hashes.invoice_id', 'left')
                                    ->join('organizations', 'organizations.id = invoices.organization_id', 'left')
                                    ->join('instalments', 'instalments.id = ligo_qr_hashes.instalment_id', 'left');
        
        // Filter by organization if one is selected, or if user is not superadmin
        if ($organizationId) {
            $hashQuery = $hashQuery->where('invoices.organization_id', $organizationId);
        } else if (!$isSuperadmin) {
            // Non-superadmin users must have an organization
            return redirect()->to('organizations')->with('error', 'Debe seleccionar una organización primero');
        }
        
        // Get hashes with information
        $hashes = $hashQuery->orderBy('ligo_qr_hashes.created_at', 'DESC')
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

        $title = 'Hashes QR Ligo';
        if ($isSuperadmin && !$organizationId) {
            $title = 'Hashes QR Ligo - Todas las Organizaciones';
        }

        $data = [
            'title' => $title,
            'hashes' => $hashes,
            'is_general_view' => ($isSuperadmin && !$organizationId)
        ];

        return view('backoffice/hashes', $data);
    }

    /**
     * List transfers with statistics
     */
    public function transfers()
    {
        // Get session and check if superadmin has access without organization
        $session = session();
        $selectedOrgId = $session->get('selected_organization_id');
        $auth = new \App\Libraries\Auth();
        $isSuperadmin = $auth->hasRole('superadmin');
        
        // Non-superadmin users must have an organization selected
        if (!$selectedOrgId && !$isSuperadmin) {
            return redirect()->to('organizations')->with('error', 'Debe seleccionar una organización primero');
        }

        $transferModel = new \App\Models\TransferModel();
        $db = \Config\Database::connect();

        // Build query - if organization selected, filter by it; if superadmin without organization, show all
        $query = $db->table('transfers t')
                   ->select('t.*, u.name as user_name, o.name as organization_name')
                   ->join('users u', 't.user_id = u.id', 'left')
                   ->join('organizations o', 't.organization_id = o.id', 'left');
                   
        // Filter by organization if one is selected
        if ($selectedOrgId) {
            $query = $query->where('t.organization_id', $selectedOrgId);
        }
        
        $transfers = $query->orderBy('t.created_at', 'DESC')
                          ->limit(100) // Show more records for general view
                          ->get()
                          ->getResultArray();

        // Get statistics - pass organization ID if selected, null for all organizations
        $stats = $transferModel->getTransferStats($selectedOrgId);

        $title = 'Historial de Transferencias Ligo';
        if ($isSuperadmin && !$selectedOrgId) {
            $title = 'Historial de Transferencias Ligo - Todas las Organizaciones';
        }

        $data = [
            'title' => $title,
            'transfers' => $transfers,
            'stats' => $stats,
            'is_general_view' => ($isSuperadmin && !$selectedOrgId)
        ];

        return view('backoffice/transfers', $data);
    }

    /**
     * Get transfer details
     */
    public function transferDetails($transferId)
    {
        if (!$this->request->isAJAX()) {
            return $this->fail('Invalid request', 400);
        }

        $selectedOrgId = session()->get('selected_organization_id');
        $auth = new \App\Libraries\Auth();
        $isSuperadmin = $auth->hasRole('superadmin');
        
        // Non-superadmin users must have an organization selected
        if (!$selectedOrgId && !$isSuperadmin) {
            return $this->fail('Debe seleccionar una organización primero', 400);
        }

        $transferModel = new \App\Models\TransferModel();
        
        // Build query - if organization selected, filter by it; if superadmin without organization, allow access to any
        if ($selectedOrgId) {
            $transfer = $transferModel->where('organization_id', $selectedOrgId)
                                     ->find($transferId);
        } else {
            // Superadmin can access any transfer
            $transfer = $transferModel->find($transferId);
        }

        if (!$transfer) {
            return $this->fail('Transferencia no encontrada', 404);
        }

        return $this->respond([
            'success' => true,
            'data' => $transfer
        ]);
    }

    /**
     * Get Ligo response for transfer
     */
    public function transferLigoResponse($transferId)
    {
        if (!$this->request->isAJAX()) {
            return $this->fail('Invalid request', 400);
        }

        $selectedOrgId = session()->get('selected_organization_id');
        $auth = new \App\Libraries\Auth();
        $isSuperadmin = $auth->hasRole('superadmin');
        
        // Non-superadmin users must have an organization selected
        if (!$selectedOrgId && !$isSuperadmin) {
            return $this->fail('Debe seleccionar una organización primero', 400);
        }

        $transferModel = new \App\Models\TransferModel();
        
        // Build query - if organization selected, filter by it; if superadmin without organization, allow access to any
        if ($selectedOrgId) {
            $transfer = $transferModel->select('ligo_response')
                                     ->where('organization_id', $selectedOrgId)
                                     ->find($transferId);
        } else {
            // Superadmin can access any transfer
            $transfer = $transferModel->select('ligo_response')
                                     ->find($transferId);
        }

        if (!$transfer) {
            return $this->fail('Transferencia no encontrada', 404);
        }

        return $this->respond([
            'success' => true,
            'data' => $transfer
        ]);
    }
}
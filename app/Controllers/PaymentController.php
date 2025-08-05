<?php

namespace App\Controllers;

use App\Models\PaymentModel;
use App\Models\InvoiceModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Models\InstalmentModel;
use App\Libraries\Auth;
use App\Traits\OrganizationTrait;

class PaymentController extends BaseController
{
    use OrganizationTrait;
    
    protected $auth;
    protected $session;
    protected $organizationModel;
    protected $paymentModel;
    
    public function __construct()
    {
        $this->auth = new Auth();
        $this->session = \Config\Services::session();
        $this->organizationModel = new \App\Models\OrganizationModel();
        $this->paymentModel = new PaymentModel();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        // Only users with proper roles can view payments
        if (!$this->auth->hasAnyRole(['superadmin', 'admin', 'user'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para ver pagos.');
        }
        
        $data = [
            'auth' => $this->auth,
            'date_start' => $this->request->getGet('date_start'),
            'date_end' => $this->request->getGet('date_end')
        ];
        
        // Get organization ID from Auth library or from query params for superadmin
        $organizationId = $this->auth->organizationId();
        if ($this->auth->hasRole('superadmin')) {
            $data['organizations'] = $this->organizationModel->findAll();
            $organizationId = $this->request->getGet('organization_id') ?: null;
            $data['selected_organization_id'] = $organizationId;
        }
        
        // Build base query
        $builder = $this->paymentModel->select('payments.*, invoices.invoice_number as invoice_number, invoices.currency, clients.business_name')
            ->join('invoices', 'invoices.id = payments.invoice_id')
            ->join('clients', 'clients.id = invoices.client_id')
            ->where('payments.deleted_at IS NULL');
            
        // Add organization filter if not superadmin
        if ($organizationId) {
            $builder->where('invoices.organization_id', $organizationId);
        }
        
        // Add date filters if provided
        if (!empty($data['date_start'])) {
            $builder->where('DATE(payments.payment_date) >=', $data['date_start']);
        }
        if (!empty($data['date_end'])) {
            $builder->where('DATE(payments.payment_date) <=', $data['date_end']);
        }
        
        // Get paginated results
        $data['payments'] = $builder->orderBy('payments.payment_date', 'DESC')
                                  ->paginate(10);
                                  
        $data['pager'] = $this->paymentModel->pager;
        
        return view('payments/index', $data);
    }
    
    public function create($invoiceUuid = null, $instalmentId = null)
    {
        // Only collector users can create payments
        if (!$this->auth->hasAnyRole(['superadmin', 'admin', 'user'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para registrar pagos.');
        }
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'invoice_id'     => 'required|is_natural_no_zero',
                'instalment_id'  => 'permit_empty|is_natural_no_zero',
                'amount'         => 'required|numeric|greater_than[0]',
                'payment_method' => 'required|max_length[50]',
                'reference_code' => 'permit_empty|max_length[100]',
                'notes'          => 'permit_empty',
                'latitude'       => 'permit_empty|decimal',
                'longitude'      => 'permit_empty|decimal',
            ];
            
            // Check if payment method is Ligo QR - handled by JavaScript now
            // We don't need server-side redirection anymore since we're using a modal
            if ($this->request->getPost('payment_method') === 'ligo_qr' && !$this->request->isAJAX()) {
                // For non-AJAX requests or fallback, we'll still validate
                $invoiceModel = new InvoiceModel();
                $invoice = $invoiceModel->find($this->request->getPost('invoice_id'));
                
                if (!$invoice) {
                    return redirect()->back()->withInput()
                        ->with('error', 'Factura no encontrada.');
                }
                
                // Check if user has access to this invoice
                if (!$this->hasAccessToInvoice($invoice)) {
                    return redirect()->back()->withInput()
                        ->with('error', 'No tiene permisos para registrar pagos para esta factura.');
                }
                
                // Return to the form with a message to use the modal
                return redirect()->back()->withInput()
                    ->with('info', 'Por favor, utilice el modal para generar el código QR de Ligo.');
            }
            
            if ($this->validate($rules)) {
                $paymentModel = new PaymentModel();
                
                // Get invoice information
                $invoiceModel = new InvoiceModel();
                $invoice = $invoiceModel->find($this->request->getPost('invoice_id'));
                
                if (!$invoice) {
                    return redirect()->back()->withInput()
                        ->with('error', 'Factura no encontrada.');
                }
                
                // Ensure the amount field exists for backward compatibility
                if (!isset($invoice['amount']) && isset($invoice['total_amount'])) {
                    $invoice['amount'] = $invoice['total_amount'];
                }
                
                // Check if user has access to this invoice
                if (!$this->hasAccessToInvoice($invoice)) {
                    return redirect()->back()->withInput()
                        ->with('error', 'No tiene permisos para registrar pagos para esta factura.');
                }
                
                // Check if invoice is already paid
                if ($invoice['status'] === 'paid') {
                    return redirect()->back()->withInput()
                        ->with('error', 'Esta factura ya está pagada.');
                }
                
                // Check if invoice is cancelled or rejected
                if (in_array($invoice['status'], ['cancelled', 'rejected'])) {
                    return redirect()->back()->withInput()
                        ->with('error', 'No se puede registrar pagos para una factura cancelada o rechazada.');
                }
                
                // Calculate remaining amount
                $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                $remainingAmount = $paymentInfo['remaining'];
                
                // Check if payment amount is valid
                $paymentAmount = floatval($this->request->getPost('amount'));
                if ($paymentAmount <= 0) {
                    return redirect()->back()->withInput()->with('error', 'El monto del pago debe ser mayor a cero.');
                }
                
                if ($paymentAmount > $remainingAmount) {
                    return redirect()->back()->withInput()->with('error', 'El monto del pago no puede ser mayor al saldo pendiente de la factura.');
                }
                
                // Prepare payment data
                $paymentData = [
                    'invoice_id'     => $invoice['id'],
                    'instalment_id'  => $this->request->getPost('instalment_id') ?: null,
                    'user_id'        => $this->auth->user()['id'],
                    'amount'         => $paymentAmount,
                    'payment_method' => $this->request->getPost('payment_method'),
                    'reference_code' => $this->request->getPost('reference_code'),
                    'notes'          => $this->request->getPost('notes'),
                    'latitude'       => $this->request->getPost('latitude') ?: null,
                    'longitude'      => $this->request->getPost('longitude') ?: null,
                    'payment_date'   => date('Y-m-d H:i:s'),
                    'status'         => 'completed'
                ];
                
                // Validate instalment if provided
                if (!empty($paymentData['instalment_id'])) {
                    $instalmentModel = new InstalmentModel();
                    $instalment = $instalmentModel->find($paymentData['instalment_id']);
                    
                    if (!$instalment || $instalment['invoice_id'] != $invoice['id']) {
                        return redirect()->back()->withInput()
                            ->with('error', 'La cuota seleccionada no es válida para esta factura.');
                    }
                    
                    // Verificar que se estén pagando las cuotas en orden cronológico
                    if (!$instalmentModel->canBePaid($instalment['id'])) {
                        return redirect()->back()->withInput()
                            ->with('error', 'No se puede pagar esta cuota porque hay cuotas anteriores pendientes de pago. Debe pagar las cuotas en orden cronológico.');
                    }
                    
                    // Check if payment amount is valid for the instalment
                    $instalmentPaid = 0;
                    $instalmentPayments = $paymentModel->where('instalment_id', $instalment['id'])
                                                     ->where('status', 'completed')
                                                     ->findAll();
                    
                    foreach ($instalmentPayments as $payment) {
                        $instalmentPaid += $payment['amount'];
                    }
                    
                    $instalmentRemaining = $instalment['amount'] - $instalmentPaid;
                    
                    if ($paymentAmount > $instalmentRemaining) {
                        return redirect()->back()->withInput()
                            ->with('error', 'El monto del pago no puede ser mayor al saldo pendiente de la cuota.');
                    }
                }
                
                try {
                    // Start transaction
                    $db = \Config\Database::connect();
                    $db->transStart();
                    
                    // Insert payment
                    $paymentId = $this->paymentModel->insert($paymentData);
                    
                    if (!$paymentId) {
                        $error = $this->paymentModel->errors();
                        throw new \Exception(implode(', ', $error));
                    }
                    
                    // Si el pago es para una cuota específica, actualizar el estado de la cuota si corresponde
                    if (!empty($paymentData['instalment_id'])) {
                        $instalmentModel = new InstalmentModel();
                        $instalment = $instalmentModel->find($paymentData['instalment_id']);
                        
                        // Calcular el total pagado para esta cuota después de este nuevo pago
                        $instalmentPayments = $this->paymentModel
                            ->where('instalment_id', $instalment['id'])
                            ->where('status', 'completed')
                            ->findAll();
                            
                        $totalPaid = $paymentAmount; // Iniciar con el pago actual
                        foreach ($instalmentPayments as $payment) {
                            $totalPaid += $payment['amount'];
                        }
                        
                        // Si se ha pagado el monto completo de la cuota, actualizar su estado a "paid"
                        if ($totalPaid >= $instalment['amount']) {
                            $instalmentModel->update($instalment['id'], ['status' => 'paid']);
                            
                            // Habilitar la siguiente cuota para pago si existe
                            $nextInstalment = $instalmentModel
                                ->where('invoice_id', $instalment['invoice_id'])
                                ->where('number', $instalment['number'] + 1)
                                ->first();
                                
                            if ($nextInstalment) {
                                // No necesitamos actualizar nada en la base de datos, ya que el método canBePaid
                                // verificará automáticamente si las cuotas anteriores están pagadas
                                log_message('info', 'Siguiente cuota habilitada para pago: ' . $nextInstalment['id']);
                            }
                        }
                    }
                    
                    // Verificar si se ha pagado el monto total de la factura
                    $invoiceModel = new InvoiceModel();
                    $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                    $remainingAfterPayment = $paymentInfo['remaining'] - $paymentAmount;
                    
                    // Actualizar estado de la factura según el monto pagado
                    if ($remainingAfterPayment <= 0) {
                        // Completamente pagada
                        $invoiceModel->update($invoice['id'], ['status' => 'paid']);
                        
                        // También marcar todas las cuotas pendientes como pagadas cuando la factura esté 100% pagada
                        $instalmentModel = new InstalmentModel();
                        $pendingInstalments = $instalmentModel->where('invoice_id', $invoice['id'])
                                                            ->where('status', 'pending')
                                                            ->findAll();
                        
                        foreach ($pendingInstalments as $pendingInstalment) {
                            $instalmentModel->update($pendingInstalment['id'], ['status' => 'paid']);
                            log_message('info', 'Marked instalment ' . $pendingInstalment['id'] . ' as paid due to invoice completion');
                        }
                    } else if (($paymentInfo['total_paid'] + $paymentAmount) > 0) {
                        // Parcialmente pagada
                        $invoiceModel->update($invoice['id'], ['status' => 'partially_paid']);
                    }
                    
                    $db->transComplete();
                    
                    if ($db->transStatus() === false) {
                        throw new \Exception('Error en la transacción.');
                    }
                    
                    return redirect()->to('/payments')
                        ->with('success', 'Pago registrado exitosamente.');
                        
                } catch (\Exception $e) {
                    log_message('error', 'Error registering payment: ' . $e->getMessage());
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al registrar el pago: ' . $e->getMessage());
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Get organizations for the dropdown (for superadmin)
        if ($this->auth->hasRole('superadmin')) {
            $data['organizations'] = $this->organizationModel->findAll();
        }
        
        // Get organization ID from Auth library
        $organizationId = $this->auth->organizationId();
        
        // If invoice UUID is provided, pre-fill the form
        if ($invoiceUuid) {
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->where('uuid', $invoiceUuid)->first();
            
            if (!$invoice) {
                return redirect()->to('/payments')->with('error', 'Factura no encontrada.');
            }
            
            // Ensure the amount field exists for backward compatibility
            if (!isset($invoice['amount']) && isset($invoice['total_amount'])) {
                $invoice['amount'] = $invoice['total_amount'];
            }
            
            // Check if user has access to this invoice
            if (!$this->hasAccessToInvoice($invoice)) {
                return redirect()->to('/payments')->with('error', 'No tiene permisos para registrar pagos para esta factura.');
            }
            
            // Check if invoice is already paid
            if ($invoice['status'] === 'paid') {
                return redirect()->to('/invoices/view/' . $invoice['uuid'])->with('error', 'Esta factura ya está pagada.');
            }
            
            // Check if invoice is cancelled or rejected
            if (in_array($invoice['status'], ['cancelled', 'rejected'])) {
                return redirect()->to('/invoices/view/' . $invoice['uuid'])->with('error', 'No se puede registrar pagos para una factura cancelada o rechazada.');
            }
            
            // Calculate remaining amount
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
            $invoice['total_paid'] = floatval($paymentInfo['total_paid']);
            $invoice['remaining_amount'] = floatval($paymentInfo['remaining']);
            $invoice['amount'] = floatval($invoice['total_amount'] ?? $paymentInfo['invoice_amount']);
            
            $data['invoice'] = $invoice;
            $data['payment_info'] = $paymentInfo;
            
            // Get client information
            $clientModel = new ClientModel();
            $client = $clientModel->find($invoice['client_id']);
            $data['client'] = $client;
            
            // Get instalments if available
            $instalmentModel = new InstalmentModel();
            $instalments = $instalmentModel->getByInvoice($invoice['id']);
            
            
            if (!empty($instalments)) {
                // Categorizar las cuotas para mostrar información adicional en la vista
                $today = date('Y-m-d');
                
                // Calculate remaining amount for each instalment
                foreach ($instalments as $key => $instalment) {
                    $instalmentPayments = $this->paymentModel
                        ->where('instalment_id', $instalment['id'])
                        ->where('status', 'completed')
                        ->findAll();
                        
                    $instalmentPaid = 0;
                    foreach ($instalmentPayments as $payment) {
                        $paymentAmount = $payment['amount'];
                        // Convertir centavos a soles para pagos de Ligo QR
                        if ($payment['payment_method'] === 'ligo_qr' && $paymentAmount >= 100) {
                            $paymentAmount = $paymentAmount / 100;
                        }
                        $instalmentPaid += $paymentAmount;
                    }
                    
                    $instalments[$key]['paid_amount'] = $instalmentPaid;
                    $instalments[$key]['remaining_amount'] = $instalment['amount'] - $instalmentPaid;
                    
                    // Determinar si es una cuota vencida
                    $instalments[$key]['is_overdue'] = ($instalment['status'] !== 'paid' && $instalment['due_date'] < $today);
                    
                    // Determinar si es una cuota que se puede pagar (todas las anteriores están pagadas)
                    $instalments[$key]['can_be_paid'] = $instalmentModel->canBePaid($instalment['id']);
                    
                    // TEMPORAL: Permitir pagar cualquier cuota pendiente para diagnóstico
                    $instalments[$key]['can_be_paid'] = ($instalment['status'] !== 'paid');
                    
                    // Determinar si es una cuota futura (no se puede pagar aún)
                    $instalments[$key]['is_future'] = !$instalments[$key]['can_be_paid'] && $instalment['status'] !== 'paid';
                }
                
                
                // Remove duplicates based on instalment ID to prevent duplicate options in select
                $uniqueInstalments = [];
                $seenIds = [];
                
                foreach ($instalments as $instalment) {
                    if (!in_array($instalment['id'], $seenIds)) {
                        $uniqueInstalments[] = $instalment;
                        $seenIds[] = $instalment['id'];
                    }
                }
                
                
                $data['instalments'] = $uniqueInstalments;
                
                // Si se proporciona un ID de cuota, verificar que sea válido y preseleccionarlo
                if ($instalmentId) {
                    $selectedInstalment = null;
                    foreach ($instalments as $instalment) {
                        if ($instalment['id'] == $instalmentId) {
                            $selectedInstalment = $instalment;
                            break;
                        }
                    }
                    
                    if ($selectedInstalment) {
                        // Verificar que se pueda pagar esta cuota
                        if (!$selectedInstalment['can_be_paid']) {
                            return redirect()->to('/invoices/view/' . $invoice['uuid'])
                                ->with('error', 'No se puede pagar esta cuota porque hay cuotas anteriores pendientes de pago.');
                        }
                        
                        $data['selected_instalment'] = $selectedInstalment;
                    } else {
                        return redirect()->to('/invoices/view/' . $invoice['uuid'])
                            ->with('error', 'La cuota seleccionada no es válida para esta factura.');
                    }
                }
            }
        }
        
        return view('payments/create', $data);
    }

    public function searchInvoices()
    {
        // Validate request
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error' => 'Invalid request'])->setStatusCode(400);
        }

        $term = $this->request->getGet('term');
        if (empty($term)) {
            return $this->response->setJSON(['error' => 'Search term is required'])->setStatusCode(400);
        }

        $invoiceModel = new InvoiceModel();
        $organizationId = $this->auth->organizationId();

        // Build base query
        $builder = $invoiceModel->select('invoices.*, clients.business_name, clients.document_number')
            ->join('clients', 'clients.id = invoices.client_id')
            ->where('invoices.status !=', 'paid')
            ->where('invoices.status !=', 'cancelled')
            ->where('invoices.status !=', 'rejected');

        // Add organization filter if not superadmin
        if (!$this->auth->hasRole('superadmin')) {
            $builder->where('invoices.organization_id', $organizationId);
        }

        // Search by invoice number or client info
        $builder->groupStart()
            ->like('invoices.invoice_number', $term)
            ->orLike('clients.business_name', $term)
            ->orLike('clients.document_number', $term)
            ->groupEnd();

        $invoices = $builder->findAll(10); // Limit to 10 results

        $results = [];
        foreach ($invoices as $invoice) {
            // Calculate remaining amount
            $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
            $remaining = floatval($paymentInfo['remaining']);
            $totalAmount = floatval($invoice['total_amount'] ?? $invoice['amount'] ?? $paymentInfo['invoice_amount']);
            
            $results[] = [
                'id' => $invoice['id'],
                'uuid' => $invoice['uuid'],
                'text' => "#{$invoice['invoice_number']} - {$invoice['business_name']} ({$invoice['document_number']}) - Pendiente: S/ " . number_format($remaining, 2),
                'invoice_number' => $invoice['invoice_number'],
                'business_name' => $invoice['business_name'],
                'document_number' => $invoice['document_number'],
                'remaining' => $remaining,
                'total_amount' => number_format($totalAmount, 2)
            ];
        }

        return $this->response->setJSON(['results' => $results]);
    }
    
    public function view($uuid = null)
    {
        if (!$uuid) {
            return redirect()->to('/payments')->with('error', 'ID de pago no especificado');
        }
        $payment = $this->paymentModel->where('uuid', $uuid)->first();
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Pago no encontrado');
        }
        // Get invoice information
        $invoiceModel = new InvoiceModel();
        $invoice = $invoiceModel->find($payment['invoice_id']);
        if (!$invoice) {
            return redirect()->to('/payments')->with('error', 'Factura asociada no encontrada');
        }
        // Get client information
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        // Get user (collector) information
        $userModel = new \App\Models\UserModel();
        $collector = $userModel->find($payment['user_id']);
        if (!$collector) {
            $collector = [
                'name' => 'Usuario no disponible',
                'email' => 'N/A',
                'id' => $payment['user_id'] ?? 0
            ];
            log_message('warning', 'Cobrador no encontrado para el pago: ' . $uuid . ', ID de usuario: ' . ($payment['user_id'] ?? 'no definido'));
        }
        // Get instalment information if available
        $instalment = null;
        if (!empty($payment['instalment_id'])) {
            $instalmentModel = new InstalmentModel();
            $instalment = $instalmentModel->find($payment['instalment_id']);
        }
        // --- Buscar hash y QR solo para pagos Ligo pendientes/confirmados ---
        $qr_hash = null;
        $qr_url = null;
        $show_ligo_qr = false;
        if (
            isset($payment['payment_method']) && $payment['payment_method'] === 'ligo_qr' &&
            in_array($payment['status'], ['pendiente', 'confirmado'])
        ) {
            $qrModel = new \App\Models\LigoQRHashModel();
            if (!empty($payment['instalment_id'])) {
                $qr_hash = $qrModel->where('instalment_id', $payment['instalment_id'])->orderBy('created_at', 'desc')->first();
            } else {
                $qr_hash = $qrModel->where('invoice_id', $payment['invoice_id'])->orderBy('created_at', 'desc')->first();
            }
            if ($qr_hash && !empty($qr_hash['hash'])) {
                $qr_data = [
                    'id' => $qr_hash['order_id'] ?? null,
                    'amount' => $qr_hash['amount'] ?? $payment['amount'],
                    'currency' => $qr_hash['currency'] ?? $invoice['currency'],
                    'description' => $qr_hash['description'] ?? '',
                    'merchant' => $invoice['merchant'] ?? ($client['business_name'] ?? ''),
                    'timestamp' => strtotime($qr_hash['created_at']),
                    'hash' => $qr_hash['hash'],
                    'instalment_id' => $payment['instalment_id'] ?? null,
                    'invoice_id' => $payment['invoice_id']
                ];
                $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode(json_encode($qr_data));
                $show_ligo_qr = true;
            }
        }
        $data = [
            'payment' => $payment,
            'invoice' => $invoice,
            'client' => $client,
            'collector' => $collector,
            'instalment' => $instalment,
            'auth' => $this->auth,
            'qr_hash' => $qr_hash,
            'qr_url' => $qr_url,
            'show_ligo_qr' => $show_ligo_qr
        ];
        return view('payments/view', $data);
    }
    
    public function delete($uuid = null)
    {
        // Only admins and superadmins can delete payments
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/payments')->with('error', 'No tiene permisos para eliminar pagos.');
        }
        
        if (!$uuid) {
            return redirect()->to('/payments')->with('error', 'ID de pago no especificado');
        }
        
        // Buscar el pago por UUID en lugar de ID
        $payment = $this->paymentModel->where('uuid', $uuid)->first();
        
        if (!$payment) {
            return redirect()->to('/payments')->with('error', 'Pago no encontrado');
        }
        
        try {
            // Start transaction
            $db = \Config\Database::connect();
            $db->transStart();
            
            // Delete payment
            $this->paymentModel->delete($payment['id']);
            
            // Update invoice status
            $invoiceModel = new InvoiceModel();
            $invoice = $invoiceModel->find($payment['invoice_id']);
            
            if ($invoice) {
                $paymentInfo = $invoiceModel->calculateRemainingAmount($invoice['id']);
                if ($paymentInfo['remaining'] > 0 && $invoice['status'] === 'paid') {
                    $invoiceModel->update($invoice['id'], ['status' => 'pending']);
                }
            }
            
            // Update instalment status if applicable
            if (!empty($payment['instalment_id'])) {
                $instalmentModel = new InstalmentModel();
                $instalmentModel->updateStatus($payment['instalment_id']);
            }
            
            $db->transComplete();
            
            if ($db->transStatus() === false) {
                throw new \Exception('Error en la transacción.');
            }
            
            return redirect()->to('/payments')
                ->with('success', 'Pago eliminado exitosamente.');
                
        } catch (\Exception $e) {
            log_message('error', 'Error deleting payment: ' . $e->getMessage());
            return redirect()->to('/payments')
                ->with('error', 'Error al eliminar el pago: ' . $e->getMessage());
        }
    }
    
    public function report()
    {
        // Only admins and superadmins can view reports
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para ver reportes.');
        }
        
        // Get date range filters
        $dateStart = $this->request->getGet('date_start');
        $dateEnd = $this->request->getGet('date_end');
        
        // Get organization filter
        $organizationId = $this->auth->hasRole('superadmin')
            ? $this->request->getGet('organization_id')
            : $this->auth->organizationId();
        
        try {
            // Get payment totals by method
            $paymentsByMethod = $this->paymentModel->getPaymentTotalsByMethod(
                $organizationId,
                $dateStart,
                $dateEnd
            );
            
            // Get payment totals by collector
            $paymentsByCollector = $this->paymentModel->getPaymentTotalsByCollector(
                $organizationId,
                $dateStart,
                $dateEnd
            );
            
            // Get payment totals by day
            $paymentsByDay = $this->paymentModel->getPaymentTotalsByDay(
                $organizationId,
                $dateStart,
                $dateEnd
            );
            
            $data = [
                'payments_by_method' => $paymentsByMethod,
                'payments_by_collector' => $paymentsByCollector,
                'payments_by_day' => $paymentsByDay,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'auth' => $this->auth,
            ];
            
            // Add organizations for superadmin dropdown
            if ($this->auth->hasRole('superadmin')) {
                $data['organizations'] = $this->organizationModel->findAll();
                $data['selected_organization'] = $organizationId;
            }
            
            return view('payments/report', $data);
            
        } catch (\Exception $e) {
            log_message('error', 'Error generating payment report: ' . $e->getMessage());
            return redirect()->to('/dashboard')
                ->with('error', 'Error al generar el reporte de pagos: ' . $e->getMessage());
        }
    }
    
    private function hasAccessToInvoice($invoice)
    {
        $auth = $this->auth;
        
        // Superadmin can access all invoices
        if ($auth->hasRole('superadmin')) {
            return true;
        }
        
        // Get client's organization
        $clientModel = new ClientModel();
        $client = $clientModel->find($invoice['client_id']);
        
        if (!$client) {
            return false;
        }
        
        // Admin can access invoices from their organization
        if ($auth->hasRole('admin')) {
            return $client['organization_id'] === $auth->user()['organization_id'];
        }
        
        // Regular users can only access invoices from their assigned portfolios
        $portfolioModel = new PortfolioModel();
        $portfolios = $portfolioModel->getByUser($auth->user()['id']);
        
        foreach ($portfolios as $portfolio) {
            // Check if client belongs to this portfolio
            $db = \Config\Database::connect();
            $exists = $db->table('client_portfolio')
                ->where('portfolio_id', $portfolio['id'])
                ->where('client_id', $client['id'])
                ->countAllResults() > 0;
            
            if ($exists) {
                return true;
            }
        }
        
        return false;
    }
}

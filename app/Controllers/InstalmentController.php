<?php

namespace App\Controllers;

use App\Models\InstalmentModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Traits\OrganizationTrait;

class InstalmentController extends BaseController
{
    use OrganizationTrait;
    
    protected $instalmentModel;
    protected $invoiceModel;
    protected $paymentModel;
    protected $clientModel;
    
    public function __construct()
    {
        $this->instalmentModel = new InstalmentModel();
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->clientModel = new ClientModel();
        
        helper(['form', 'url']);
    }
    
    /**
     * Lista todas las cuotas de una factura
     */
    public function index($invoiceId)
    {
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        $client = $this->clientModel->find($invoice['client_id']);
        
        // Usar el método que ordena las cuotas por prioridad de cobranza
        $instalments = $this->instalmentModel->getByInvoiceForCollection($invoiceId);
        
        // Obtener pagos por cuota
        $payments = [];
        foreach ($instalments as $instalment) {
            $payments[$instalment['id']] = $this->paymentModel
                ->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();
        }
        
        // Categorizar las cuotas para mostrar información adicional en la vista
        $today = date('Y-m-d');
        foreach ($instalments as &$instalment) {
            // Determinar si es una cuota vencida
            $instalment['is_overdue'] = ($instalment['status'] !== 'paid' && $instalment['due_date'] < $today);
            
            // Determinar si es una cuota que se puede pagar (todas las anteriores están pagadas)
            $instalment['can_be_paid'] = $this->instalmentModel->canBePaid($instalment['id']);
            
            // Determinar si es una cuota futura (no se puede pagar aún)
            $instalment['is_future'] = !$instalment['can_be_paid'] && $instalment['status'] !== 'paid';
        }
        
        return view('instalments/index', [
            'invoice' => $invoice,
            'client' => $client,
            'instalments' => $instalments,
            'payments' => $payments
        ]);
    }
    
    /**
     * Muestra el formulario para crear cuotas
     */
    public function create($invoiceId)
    {
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Verificar si ya tiene cuotas
        $existingInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->countAllResults();
        if ($existingInstalments > 0) {
            return redirect()->to('/invoice/' . $invoiceId . '/instalments')
                ->with('error', 'Esta factura ya tiene cuotas creadas');
        }
        
        $client = $this->clientModel->find($invoice['client_id']);
        
        // Asegurar que la factura tenga una fecha de emisión
        if (!isset($invoice['issue_date'])) {
            $invoice['issue_date'] = date('Y-m-d'); // Usar la fecha actual como fallback
        }
        
        return view('instalments/create', [
            'invoice' => $invoice,
            'client' => $client
        ]);
    }
    
    /**
     * Procesa la creación de cuotas
     */
    public function store()
    {
        $invoiceId = $this->request->getPost('invoice_id');
        
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Verificar si ya tiene cuotas
        $existingInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->countAllResults();
        if ($existingInstalments > 0) {
            return redirect()->to('/invoice/' . $invoiceId . '/instalments')
                ->with('error', 'Esta factura ya tiene cuotas creadas');
        }
        
        // Validar datos
        $rules = [
            'num_instalments' => 'required|is_natural_no_zero',
            'first_due_date' => 'required|valid_date',
            'instalment_type' => 'required|in_list[equal,custom]'
        ];
        
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        
        $numInstalments = $this->request->getPost('num_instalments');
        $firstDueDate = $this->request->getPost('first_due_date');
        $instalmentType = $this->request->getPost('instalment_type');
        $interval = $this->request->getPost('interval') ?? 30; // días entre cuotas
        
        // Crear cuotas
        $db = \Config\Database::connect();
        $db->transBegin();
        
        try {
            if ($instalmentType === 'equal') {
                // Cuotas iguales
                $amount = round($invoice['amount'] / $numInstalments, 2);
                $lastAmount = $invoice['amount'] - ($amount * ($numInstalments - 1)); // Ajustar última cuota
                
                for ($i = 1; $i <= $numInstalments; $i++) {
                    $dueDate = date('Y-m-d', strtotime($firstDueDate . ' + ' . (($i - 1) * $interval) . ' days'));
                    
                    $this->instalmentModel->insert([
                        'invoice_id' => $invoiceId,
                        'number' => $i,
                        'amount' => ($i == $numInstalments) ? $lastAmount : $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending'
                    ]);
                }
            } else {
                // Cuotas personalizadas
                $totalAmount = 0;
                
                for ($i = 1; $i <= $numInstalments; $i++) {
                    $amount = $this->request->getPost('amount_' . $i);
                    $dueDate = $this->request->getPost('due_date_' . $i);
                    
                    if (!$amount || !$dueDate) {
                        throw new \Exception('Datos de cuota incompletos');
                    }
                    
                    $totalAmount += $amount;
                    
                    $this->instalmentModel->insert([
                        'invoice_id' => $invoiceId,
                        'number' => $i,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending'
                    ]);
                }
                
                // Verificar que el total coincida con el monto de la factura
                if (abs($totalAmount - $invoice['amount']) > 0.01) {
                    throw new \Exception('El total de las cuotas no coincide con el monto de la factura');
                }
            }
            
            $db->transCommit();
            return redirect()->to('/invoice/' . $invoiceId . '/instalments')
                ->with('success', 'Cuotas creadas correctamente');
                
        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->withInput()
                ->with('error', 'Error al crear cuotas: ' . $e->getMessage());
        }
    }
    
    /**
     * Elimina todas las cuotas de una factura
     */
    public function delete($invoiceId)
    {
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        // Verificar si hay pagos asociados a las cuotas
        $instalments = $this->instalmentModel->getByInvoice($invoiceId);
        $instalmentIds = array_column($instalments, 'id');
        
        if (!empty($instalmentIds)) {
            $paymentsCount = $this->paymentModel
                ->whereIn('instalment_id', $instalmentIds)
                ->countAllResults();
                
            if ($paymentsCount > 0) {
                return redirect()->to('/invoice/' . $invoiceId . '/instalments')
                    ->with('error', 'No se pueden eliminar las cuotas porque ya tienen pagos asociados');
            }
            
            // Eliminar cuotas
            foreach ($instalmentIds as $id) {
                $this->instalmentModel->delete($id);
            }
            
            return redirect()->to('/invoice/' . $invoiceId . '/instalments')
                ->with('success', 'Cuotas eliminadas correctamente');
        }
        
        return redirect()->to('/invoice/' . $invoiceId . '/instalments')
            ->with('error', 'No hay cuotas para eliminar');
    }
    
    /**
     * Verifica si el usuario puede acceder a una factura
     */
    private function canAccessInvoice($invoiceId)
    {
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return false;
        }
        
        $user = session()->get('user');
        
        // Superadmin puede acceder a todo
        if ($user['role'] === 'superadmin') {
            return true;
        }
        
        // Admin puede acceder a facturas de su organización
        if ($user['role'] === 'admin') {
            return $invoice['organization_id'] == $user['organization_id'];
        }
        
        // Usuario regular solo puede acceder a facturas de sus clientes asignados
        $client = $this->clientModel->find($invoice['client_id']);
        if (!$client) {
            return false;
        }
        
        // Verificar si el cliente está en el portafolio del usuario
        $db = \Config\Database::connect();
        $portfolioClients = $db->table('portfolio_clients')
            ->join('portfolios', 'portfolios.id = portfolio_clients.portfolio_id')
            ->join('portfolio_users', 'portfolios.id = portfolio_users.portfolio_id')
            ->where('portfolio_users.user_id', $user['id'])
            ->where('portfolio_clients.client_id', $client['id'])
            ->countAllResults();
            
        return $portfolioClients > 0;
    }
}

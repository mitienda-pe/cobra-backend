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
        
        helper(['form', 'url', 'text']);
    }
    
    /**
     * Lista todas las cuotas de una factura
     */
    public function index($invoiceId)
    {
        // Verificar si el ID es un UUID o un ID numérico
        if (!is_numeric($invoiceId)) {
            // No es numérico, asumimos que es un UUID
            $invoice = $this->invoiceModel->where('uuid', $invoiceId)->first();
        } else {
            // Es un ID numérico, buscar por id
            $invoice = $this->invoiceModel->find($invoiceId);
        }
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        $client = $this->clientModel->find($invoice['client_id']);
        
        // Usar el método que ordena las cuotas por prioridad de cobranza
        $instalments = $this->instalmentModel->getByInvoiceForCollection($invoice['id']);
        
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
        
        // Asegurar que la factura tenga una fecha de emisión
        if (!isset($invoice['issue_date'])) {
            $invoice['issue_date'] = date('Y-m-d'); // Usar la fecha actual como fallback
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
        // Verificar si el ID es un UUID o un ID numérico
        if (!is_numeric($invoiceId)) {
            // No es numérico, asumimos que es un UUID
            $invoice = $this->invoiceModel->where('uuid', $invoiceId)->first();
        } else {
            // Es un ID numérico, buscar por id
            $invoice = $this->invoiceModel->find($invoiceId);
        }
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        // Verificar si ya tiene cuotas
        $existingInstalments = $this->instalmentModel->where('invoice_id', $invoice['id'])->countAllResults();
        if ($existingInstalments > 0) {
            return redirect()->to('/invoice/' . $invoice['uuid'] . '/instalments')
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
        
        // Verificar si el ID es un UUID o un ID numérico
        if (!is_numeric($invoiceId)) {
            // No es numérico, asumimos que es un UUID
            $invoice = $this->invoiceModel->where('uuid', $invoiceId)->first();
        } else {
            // Es un ID numérico, buscar por id
            $invoice = $this->invoiceModel->find($invoiceId);
        }
        
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        // Verificar si ya tiene cuotas
        $existingInstalments = $this->instalmentModel->where('invoice_id', $invoice['id'])->countAllResults();
        if ($existingInstalments > 0) {
            return redirect()->to('/invoice/' . $invoice['uuid'] . '/instalments')
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
                        'invoice_id' => $invoice['id'],
                        'number' => $i,
                        'amount' => ($i == $numInstalments) ? $lastAmount : $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'uuid' => random_string('alnum', 36)
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
                        'invoice_id' => $invoice['id'],
                        'number' => $i,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending',
                        'uuid' => random_string('alnum', 36)
                    ]);
                }
                
                // Verificar que el total coincida con el monto de la factura
                if (abs($totalAmount - $invoice['amount']) > 0.01) {
                    throw new \Exception('El total de las cuotas no coincide con el monto de la factura');
                }
            }
            
            $db->transCommit();
            return redirect()->to('/invoice/' . $invoice['uuid'] . '/instalments')
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
        // Verificar si el ID es un UUID o un ID numérico
        if (!is_numeric($invoiceId)) {
            // No es numérico, asumimos que es un UUID
            $invoice = $this->invoiceModel->where('uuid', $invoiceId)->first();
        } else {
            // Es un ID numérico, buscar por id
            $invoice = $this->invoiceModel->find($invoiceId);
        }
        
        if (!$invoice) {
            return redirect()->to('/invoices')->with('error', 'Factura no encontrada');
        }
        
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return redirect()->to('/invoices')->with('error', 'No tiene acceso a esta factura');
        }
        
        // Verificar si hay pagos asociados a las cuotas
        $instalments = $this->instalmentModel->getByInvoice($invoice['id']);
        foreach ($instalments as $instalment) {
            $payments = $this->paymentModel->where('instalment_id', $instalment['id'])->findAll();
            if (!empty($payments)) {
                return redirect()->to('/invoice/' . $invoice['uuid'] . '/instalments')
                    ->with('error', 'No se pueden eliminar las cuotas porque hay pagos asociados');
            }
        }
        
        // Eliminar todas las cuotas
        $this->instalmentModel->where('invoice_id', $invoice['id'])->delete();
        
        return redirect()->to('/invoice/' . $invoice['uuid'] . '/instalments')
            ->with('success', 'Cuotas eliminadas correctamente');
    }
    
    /**
     * Verifica si el usuario puede acceder a una factura
     */
    private function canAccessInvoice($invoiceId)
    {
        // Primero intentamos buscar por ID numérico
        $invoice = $this->invoiceModel->find($invoiceId);
        
        // Si no encontramos la factura, intentamos buscar por UUID
        if (!$invoice && !is_numeric($invoiceId)) {
            $invoice = $this->invoiceModel->where('uuid', $invoiceId)->first();
        }
        
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
    
    /**
     * Crea automáticamente una cuota única para una factura
     * 
     * @param int $invoiceId ID de la factura
     * @return bool Verdadero si se creó la cuota, falso en caso contrario
     */
    public function createSingleInstalment($invoiceId)
    {
        // Obtener la factura
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return false;
        }
        
        // Verificar si ya tiene cuotas
        $existingInstalments = $this->instalmentModel->where('invoice_id', $invoice['id'])->countAllResults();
        if ($existingInstalments > 0) {
            return false; // Ya tiene cuotas, no crear una nueva
        }
        
        // Crear una cuota única con el monto total de la factura
        $data = [
            'invoice_id' => $invoice['id'],
            'number' => 1,
            'amount' => $invoice['amount'],
            'due_date' => $invoice['due_date'],
            'status' => $invoice['status']
        ];
        
        return $this->instalmentModel->insert($data) ? true : false;
    }
    
    /**
     * Lista todas las cuotas vencidas o vigentes filtradas por cartera y organización
     */
    public function list()
    {
        // Verificar si el usuario está autenticado
        if (!session()->get('isLoggedIn')) {
            return redirect()->to('/login');
        }
        
        // Obtener el ID de la organización del usuario
        $organizationId = $this->getCurrentOrganizationId();
        
        // Obtener parámetros de filtro
        $portfolioId = $this->request->getGet('portfolio_id');
        $status = $this->request->getGet('status') ?: 'pending'; // Por defecto, mostrar cuotas pendientes
        $dueDate = $this->request->getGet('due_date') ?: 'all'; // all, overdue, upcoming
        
        // Si tenemos un ID de cartera, necesitamos obtener su UUID
        $portfolioUuid = null;
        if ($portfolioId) {
            $portfolioModel = new \App\Models\PortfolioModel();
            $portfolio = $portfolioModel->find($portfolioId);
            if ($portfolio) {
                $portfolioUuid = $portfolio['uuid'];
            }
        }
        
        // Preparar la consulta base
        $db = \Config\Database::connect();
        $builder = $db->table('instalments i');
        $builder->select('i.*, inv.invoice_number, inv.uuid as invoice_uuid, c.business_name as client_name');
        $builder->join('invoices inv', 'i.invoice_id = inv.id');
        $builder->join('clients c', 'inv.client_id = c.id');
        $builder->where('i.deleted_at IS NULL');
        $builder->where('inv.deleted_at IS NULL');
        $builder->where('c.deleted_at IS NULL');
        
        // Filtrar por organización
        if ($organizationId && !$this->auth->hasRole('superadmin')) {
            $builder->where('inv.organization_id', $organizationId);
        } else if ($organizationId && $this->auth->hasRole('superadmin')) {
            $builder->where('inv.organization_id', $organizationId);
        }
        
        // Filtrar por cartera
        if ($portfolioUuid) {
            // Primero obtenemos los IDs de los clientes en la cartera
            $clientsQuery = $db->table('client_portfolio cp')
                            ->select('c.id')
                            ->join('clients c', 'cp.client_uuid = c.uuid')
                            ->where('cp.portfolio_uuid', $portfolioUuid)
                            ->where('cp.deleted_at IS NULL')
                            ->where('c.deleted_at IS NULL')
                            ->getCompiledSelect();
                            
            $builder->whereIn('c.id', $clientsQuery);
        }
        
        // Filtrar por estado
        if ($status !== 'all') {
            $builder->where('i.status', $status);
        }
        
        // Filtrar por fecha de vencimiento
        $today = date('Y-m-d');
        if ($dueDate === 'overdue') {
            $builder->where('i.due_date <', $today);
        } else if ($dueDate === 'upcoming') {
            $builder->where('i.due_date >=', $today);
        }
        
        // Ordenar por fecha de vencimiento (más próximas primero)
        $builder->orderBy('i.due_date', 'ASC');
        
        // Ejecutar la consulta
        $instalments = $builder->get()->getResultArray();
        
        // Obtener carteras para el filtro
        $portfolioModel = new \App\Models\PortfolioModel();
        if ($organizationId && !$this->auth->hasRole('superadmin')) {
            $portfolios = $portfolioModel->where('organization_id', $organizationId)->findAll();
        } else {
            $portfolios = $portfolioModel->findAll();
        }
        
        // Categorizar las cuotas para mostrar información adicional en la vista
        foreach ($instalments as &$instalment) {
            // Determinar si es una cuota vencida
            $instalment['is_overdue'] = ($instalment['status'] !== 'paid' && $instalment['due_date'] < $today);
            
            // Calcular el monto pagado para esta cuota
            $paymentModel = new PaymentModel();
            $payments = $paymentModel
                ->where('instalment_id', $instalment['id'])
                ->where('status', 'completed')
                ->findAll();
                
            $paidAmount = 0;
            foreach ($payments as $payment) {
                $paidAmount += $payment['amount'];
            }
            
            $instalment['paid_amount'] = $paidAmount;
            $instalment['remaining_amount'] = $instalment['amount'] - $paidAmount;
        }
        
        // Preparar datos para la vista
        $data = [
            'auth' => $this->auth,
            'instalments' => $instalments,
            'portfolios' => $portfolios,
            'selectedPortfolio' => $portfolioId,
            'selectedStatus' => $status,
            'selectedDueDate' => $dueDate,
            'organizationId' => $organizationId
        ];
        
        return view('instalments/list', $data);
    }
}

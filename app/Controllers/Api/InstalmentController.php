<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InstalmentModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use CodeIgniter\API\ResponseTrait;

class InstalmentController extends BaseController
{
    use ResponseTrait;
    
    protected $instalmentModel;
    protected $invoiceModel;
    protected $paymentModel;
    protected $clientModel;
    protected $portfolioModel;
    protected $request;
    protected $auth;
    
    public function __construct()
    {
        $this->instalmentModel = new InstalmentModel();
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->clientModel = new ClientModel();
        $this->portfolioModel = new PortfolioModel();
        $this->request = \Config\Services::request();
        $this->auth = service('auth');
    }
    
    /**
     * Get the authenticated user
     * This method ensures we have a user object even if request data is missing
     */
    protected function getAuthUser()
    {
        // Try to get user from request object (set by ApiAuthFilter)
        if (isset($this->request) && isset($this->request->user)) {
            return $this->request->user;
        }
        
        // If no user, return null
        return null;
    }
    
    /**
     * Obtiene todas las cuotas de una factura
     */
    public function getByInvoice($invoiceId)
    {
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return $this->failForbidden('No tiene acceso a esta factura');
        }
        
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return $this->failNotFound('Factura no encontrada');
        }
        
        $instalments = $this->instalmentModel->getByInvoice($invoiceId);
        
        // Obtener pagos por cuota si se solicita
        $includePayments = $this->request->getGet('include_payments') === 'true';
        if ($includePayments) {
            foreach ($instalments as &$instalment) {
                $instalment['payments'] = $this->paymentModel
                    ->where('instalment_id', $instalment['id'])
                    ->where('status', 'completed')
                    ->findAll();
            }
        }
        
        // Incluir información del cliente si se solicita
        $includeClient = $this->request->getGet('include_client') === 'true';
        $response = [
            'invoice' => $invoice,
            'instalments' => $instalments
        ];
        
        if ($includeClient) {
            $client = $this->clientModel->find($invoice['client_id']);
            $response['client'] = $client;
        }
        
        return $this->respond($response);
    }
    
    /**
     * Crea cuotas para una factura
     */
    public function create()
    {
        $json = $this->request->getJSON(true);
        if (!isset($json['invoice_id'])) {
            return $this->fail('Se requiere el ID de la factura', 400);
        }
        
        $invoiceId = $json['invoice_id'];
        
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return $this->failForbidden('No tiene acceso a esta factura');
        }
        
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return $this->failNotFound('Factura no encontrada');
        }
        
        // Verificar si ya tiene cuotas
        $existingInstalments = $this->instalmentModel->where('invoice_id', $invoiceId)->countAllResults();
        if ($existingInstalments > 0) {
            return $this->fail('Esta factura ya tiene cuotas creadas', 400);
        }
        
        // Validar datos
        if (!isset($json['num_instalments']) || !isset($json['first_due_date']) || !isset($json['instalment_type'])) {
            return $this->fail('Datos incompletos', 400);
        }
        
        $numInstalments = $json['num_instalments'];
        $firstDueDate = $json['first_due_date'];
        $instalmentType = $json['instalment_type'];
        $interval = $json['interval'] ?? 30; // días entre cuotas
        
        if (!is_numeric($numInstalments) || $numInstalments <= 0) {
            return $this->fail('El número de cuotas debe ser mayor a cero', 400);
        }
        
        if (!in_array($instalmentType, ['equal', 'custom'])) {
            return $this->fail('Tipo de cuota inválido', 400);
        }
        
        // Crear cuotas
        $db = \Config\Database::connect();
        $db->transBegin();
        
        try {
            $createdInstalments = [];
            
            if ($instalmentType === 'equal') {
                // Cuotas iguales
                $amount = round($invoice['amount'] / $numInstalments, 2);
                $lastAmount = $invoice['amount'] - ($amount * ($numInstalments - 1)); // Ajustar última cuota
                
                for ($i = 1; $i <= $numInstalments; $i++) {
                    $dueDate = date('Y-m-d', strtotime($firstDueDate . ' + ' . (($i - 1) * $interval) . ' days'));
                    
                    $instalmentId = $this->instalmentModel->insert([
                        'invoice_id' => $invoiceId,
                        'number' => $i,
                        'amount' => ($i == $numInstalments) ? $lastAmount : $amount,
                        'due_date' => $dueDate,
                        'status' => 'pending'
                    ]);
                    
                    $createdInstalments[] = $this->instalmentModel->find($instalmentId);
                }
            } else {
                // Cuotas personalizadas
                if (!isset($json['instalments']) || !is_array($json['instalments'])) {
                    throw new \Exception('Se requiere el detalle de las cuotas');
                }
                
                $totalAmount = 0;
                $number = 1;
                
                foreach ($json['instalments'] as $instalment) {
                    if (!isset($instalment['amount']) || !isset($instalment['due_date'])) {
                        throw new \Exception('Datos de cuota incompletos');
                    }
                    
                    $totalAmount += $instalment['amount'];
                    
                    $instalmentId = $this->instalmentModel->insert([
                        'invoice_id' => $invoiceId,
                        'number' => $number,
                        'amount' => $instalment['amount'],
                        'due_date' => $instalment['due_date'],
                        'status' => 'pending',
                        'notes' => $instalment['notes'] ?? null
                    ]);
                    
                    $createdInstalments[] = $this->instalmentModel->find($instalmentId);
                    $number++;
                }
                
                // Verificar que el total coincida con el monto de la factura
                if (abs($totalAmount - $invoice['amount']) > 0.01) {
                    throw new \Exception('El total de las cuotas no coincide con el monto de la factura');
                }
            }
            
            $db->transCommit();
            return $this->respondCreated([
                'message' => 'Cuotas creadas correctamente',
                'instalments' => $createdInstalments
            ]);
                
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->fail('Error al crear cuotas: ' . $e->getMessage(), 400);
        }
    }
    
    /**
     * Elimina todas las cuotas de una factura
     */
    public function delete($invoiceId)
    {
        // Verificar acceso a la factura
        if (!$this->canAccessInvoice($invoiceId)) {
            return $this->failForbidden('No tiene acceso a esta factura');
        }
        
        // Verificar si hay pagos asociados a las cuotas
        $instalments = $this->instalmentModel->getByInvoice($invoiceId);
        $instalmentIds = array_column($instalments, 'id');
        
        if (!empty($instalmentIds)) {
            $paymentsCount = $this->paymentModel
                ->whereIn('instalment_id', $instalmentIds)
                ->countAllResults();
                
            if ($paymentsCount > 0) {
                return $this->fail('No se pueden eliminar las cuotas porque ya tienen pagos asociados', 400);
            }
            
            // Eliminar cuotas
            foreach ($instalmentIds as $id) {
                $this->instalmentModel->delete($id);
            }
            
            return $this->respondDeleted(['message' => 'Cuotas eliminadas correctamente']);
        }
        
        return $this->fail('No hay cuotas para eliminar', 400);
    }
    
    /**
     * Obtiene las cuotas pendientes de las facturas en las carteras del usuario
     * 
     * @param string $portfolioUuid UUID de la cartera (opcional)
     * @return \CodeIgniter\HTTP\Response
     */
    public function portfolioInstalments($portfolioUuid = null)
    {
        // Obtener el usuario autenticado
        $user = $this->getAuthUser();
        
        if (!$user) {
            log_message('error', 'InstalmentController::portfolioInstalments - Usuario no autenticado');
            return $this->failUnauthorized('Usuario no autenticado');
        }
        
        // Si no se proporcionó un UUID en la URL, intentar obtenerlo de los parámetros de consulta
        if ($portfolioUuid === null) {
            $portfolioUuid = $this->request->getGet('portfolio_uuid');
        }
        
        // Obtener parámetros de filtro
        $status = $this->request->getGet('status') ?: 'pending'; // Por defecto, mostrar cuotas pendientes
        $dueDate = $this->request->getGet('due_date') ?: 'all'; // all, overdue, upcoming
        
        // Preparar la consulta base
        $db = \Config\Database::connect();
        $builder = $db->table('instalments i');
        
        // Seleccionar campos adaptándose a la estructura de la base de datos
        // Intentar usar invoice_number si existe, de lo contrario usar number
        try {
            // Verificar si existe la columna invoice_number en la tabla invoices
            $hasInvoiceNumber = $db->fieldExists('invoice_number', 'invoices');
            
            if ($hasInvoiceNumber) {
                $builder->select('i.*, inv.invoice_number, inv.uuid as invoice_uuid, c.business_name as client_name, c.uuid as client_uuid');
            } else {
                $builder->select('i.*, inv.number as invoice_number, inv.uuid as invoice_uuid, c.business_name as client_name, c.uuid as client_uuid');
            }
        } catch (\Exception $e) {
            // Si hay error al verificar la estructura, usar una consulta más segura
            $builder->select('i.*, inv.uuid as invoice_uuid, c.business_name as client_name, c.uuid as client_uuid');
            log_message('error', 'Error al verificar estructura de la tabla: ' . $e->getMessage());
        }
        
        $builder->join('invoices inv', 'i.invoice_id = inv.id');
        $builder->join('clients c', 'inv.client_id = c.id');
        $builder->where('i.deleted_at IS NULL');
        $builder->where('inv.deleted_at IS NULL');
        $builder->where('c.deleted_at IS NULL');
        
        // Si el usuario no es superadmin o admin, filtrar por sus carteras
        if (!$this->auth->hasRole('superadmin') && !$this->auth->hasRole('admin')) {
            // Obtener las carteras asignadas al usuario
            $userPortfolios = $db->table('portfolio_user')
                ->select('portfolio_uuid')
                ->where('user_uuid', $user['uuid'])
                ->where('deleted_at IS NULL')
                ->get()
                ->getResultArray();
                
            if (empty($userPortfolios)) {
                return $this->respond([
                    'status' => 'success',
                    'data' => []
                ]);
            }
            
            $portfolioUuids = array_column($userPortfolios, 'portfolio_uuid');
            
            // Obtener los clientes en esas carteras
            $clientsInPortfolios = $db->table('client_portfolio cp')
                ->select('c.id')
                ->join('clients c', 'cp.client_uuid = c.uuid')
                ->whereIn('cp.portfolio_uuid', $portfolioUuids)
                ->where('cp.deleted_at IS NULL')
                ->where('c.deleted_at IS NULL')
                ->get()
                ->getResultArray();
                
            if (empty($clientsInPortfolios)) {
                return $this->respond([
                    'status' => 'success',
                    'data' => []
                ]);
            }
            
            $clientIds = array_column($clientsInPortfolios, 'id');
            $builder->whereIn('c.id', $clientIds);
        }
        
        // Filtrar por cartera específica si se proporciona
        if ($portfolioUuid) {
            try {
                // Obtener los clientes en esa cartera
                $clientsInPortfolio = $db->table('client_portfolio cp')
                    ->select('c.id')
                    ->join('clients c', 'cp.client_uuid = c.uuid')
                    ->where('cp.portfolio_uuid', $portfolioUuid)
                    ->where('cp.deleted_at IS NULL')
                    ->where('c.deleted_at IS NULL')
                    ->get()
                    ->getResultArray();
                    
                if (empty($clientsInPortfolio)) {
                    return $this->respond([
                        'status' => 'success',
                        'data' => []
                    ]);
                }
                
                $clientIds = array_column($clientsInPortfolio, 'id');
                $builder->whereIn('c.id', $clientIds);
            } catch (\Exception $e) {
                log_message('error', 'Error al filtrar por cartera: ' . $e->getMessage());
                return $this->respond([
                    'status' => 'success',
                    'data' => []
                ]);
            }
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
        
        return $this->respond([
            'status' => 'success',
            'data' => $instalments
        ]);
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
        
        // Usar la propiedad user de la clase en lugar de obtenerla del request
        $user = $this->getAuthUser();
        
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

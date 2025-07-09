<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InstalmentModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use App\Models\PortfolioModel;
use App\Models\TokenModel;
use App\Models\UserModel;
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
        if (property_exists($this->request, 'user')) {
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
        
        try {
            // Verificar si existe la columna invoice_number en la tabla invoices
            $hasInvoiceNumber = $db->fieldExists('invoice_number', 'invoices');
            
            // Seleccionar todos los campos necesarios
            $selectFields = [
                'i.*', 
                'inv.uuid as invoice_uuid',
                'inv.concept as invoice_concept',
                'inv.due_date as invoice_due_date',
                'inv.issue_date as invoice_issue_date',
                'inv.currency as invoice_currency',
                'c.business_name as client_business_name',
                'c.document_number as client_document',
                'c.contact_name as client_contact_name',
                'c.address as client_address',
                'c.contact_phone as client_phone',
                'c.email as client_email',
                'c.ubigeo as client_ubigeo',
                'c.zip_code as client_zip_code',
                'c.latitude as client_latitude',
                'c.longitude as client_longitude',
                'c.uuid as client_uuid'
            ];
            
            // Añadir invoice_number o number dependiendo de la estructura
            if ($hasInvoiceNumber) {
                $selectFields[] = 'inv.invoice_number';
            } else {
                $selectFields[] = 'inv.invoice_number as invoice_number';
            }
            
            // Construir la lista de campos para la consulta
            $selectFieldsStr = implode(', ', $selectFields);
            
            // Obtener el número total de cuotas por factura para incluirlo en la respuesta
            $countSubquery = $db->table('instalments')
                ->select('invoice_id, COUNT(*) as instalment_count')
                ->groupBy('invoice_id')
                ->getCompiledSelect();
            
            // Construir una subconsulta para obtener la cuota más antigua pendiente por factura
            // Esta subconsulta selecciona el ID de la cuota con el número más bajo (más antigua) 
            // que aún no ha sido pagada para cada factura
            $oldestInstalmentSubquery = $db->table('instalments i2')
                ->select('MIN(i2.id) as id')
                ->where('i2.status', 'pending')
                ->where('i2.deleted_at IS NULL');
            
            // Añadir condición de fecha de vencimiento si es necesario
            $today = date('Y-m-d');
            if ($dueDate === 'overdue') {
                $oldestInstalmentSubquery->where('i2.due_date <', $today);
            } else if ($dueDate === 'upcoming') {
                $oldestInstalmentSubquery->where('i2.due_date >=', $today);
            }
            
            $oldestInstalmentSubquery->groupBy('i2.invoice_id');
            $oldestInstalmentSubqueryStr = $oldestInstalmentSubquery->getCompiledSelect();
            
            // Consulta principal que une la subconsulta de la cuota más antigua
            $builder = $db->table('instalments i');
            $builder->select("$selectFieldsStr, IFNULL(ic.instalment_count, 0) as invoice_instalment_count");
            $builder->join('invoices inv', 'i.invoice_id = inv.id');
            $builder->join('clients c', 'inv.client_id = c.id');
            $builder->join("($countSubquery) as ic", 'i.invoice_id = ic.invoice_id', 'left');
            // Unir con la subconsulta para obtener solo las cuotas más antiguas pendientes
            $builder->join("($oldestInstalmentSubqueryStr) as oldest", 'i.id = oldest.id', 'inner');
            
            $builder->where('i.deleted_at IS NULL');
            $builder->where('inv.deleted_at IS NULL');
            $builder->where('c.deleted_at IS NULL');
            
            // Si el usuario no es superadmin o admin, filtrar por sus carteras
            if (!$this->auth->hasRole('superadmin') && !$this->auth->hasRole('admin')) {
                // Obtener las carteras asignadas al usuario
                $userPortfolios = $db->table('portfolio_user')
                    ->select('portfolio_uuid')
                    ->where('user_uuid', $user['uuid'])
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
            
            // Ordenar por fecha de vencimiento (más próximas primero)
            $builder->orderBy('i.due_date', 'ASC');
            
            // Ejecutar la consulta
            $instalments = $builder->get()->getResultArray();
            
        } catch (\Exception $e) {
            // Si hay error en la consulta avanzada, intentar con una consulta más simple
            log_message('error', 'Error en consulta avanzada de cuotas: ' . $e->getMessage());
            
            // Consulta simple como fallback
            $builder = $db->table('instalments i');
            $builder->select('i.*, inv.uuid as invoice_uuid, inv.currency as invoice_currency, inv.concept as invoice_concept, inv.due_date as invoice_due_date, inv.issue_date as invoice_issue_date, c.business_name as client_business_name, c.document_number as client_document, c.contact_name as client_contact_name, c.uuid as client_uuid');
            $builder->join('invoices inv', 'i.invoice_id = inv.id');
            $builder->join('clients c', 'inv.client_id = c.id');
            $builder->where('i.status', 'pending');
            $builder->where('i.deleted_at IS NULL');
            $builder->orderBy('i.due_date', 'ASC');
            
            // Ejecutar la consulta simple
            $allInstalments = $builder->get()->getResultArray();
            
            // Filtrar manualmente para obtener solo la cuota más antigua por factura
            $processedInvoices = [];
            $instalments = [];
            
            foreach ($allInstalments as $instalment) {
                $invoiceId = $instalment['invoice_id'];
                
                // Si esta factura ya fue procesada, omitir esta cuota
                if (in_array($invoiceId, $processedInvoices)) {
                    continue;
                }
                
                // Marcar esta factura como procesada
                $processedInvoices[] = $invoiceId;
                
                // Añadir esta cuota al resultado
                $instalments[] = $instalment;
            }
        }
        
        return $this->respond([
            'status' => 'success',
            'data' => $instalments
        ]);
    }
    
    /**
     * Obtiene una cuota específica por ID
     * 
     * @param int $id ID de la cuota
     * @return \CodeIgniter\HTTP\Response
     */
    public function show($id)
    {
        // Buscar la cuota
        $instalment = $this->instalmentModel->find($id);
        if (!$instalment) {
            return $this->failNotFound('Cuota no encontrada');
        }
        
        // Verificar acceso a la factura asociada
        if (!$this->canAccessInvoice($instalment['invoice_id'])) {
            return $this->failForbidden('No tiene acceso a esta cuota');
        }
        
        // Obtener información adicional según los parámetros
        $includeInvoice = $this->request->getGet('include_invoice') === 'true';
        $includeClient = $this->request->getGet('include_client') === 'true';
        $includePayments = $this->request->getGet('include_payments') === 'true';
        
        // Siempre incluir información detallada
        $db = \Config\Database::connect();
        $builder = $db->table('instalments i');
        
        try {
            // Verificar las columnas que existen en la tabla invoices
            $invoiceColumns = $db->getFieldNames('invoices');
            
            // Seleccionar campos básicos de la cuota
            $selectFields = ['i.*'];
            
            // Verificar y añadir campos de la factura
            $invoiceFields = [
                'uuid' => 'invoice_uuid',
                'concept' => 'invoice_concept',
                'due_date' => 'invoice_due_date',
                'issue_date' => 'invoice_issue_date',
                'status' => 'invoice_status',
                'currency' => 'invoice_currency'
            ];
            
            // Añadir amount solo si existe
            if (in_array('amount', $invoiceColumns)) {
                $invoiceFields['amount'] = 'invoice_amount';
            }
            
            // Verificar si existe invoice_number o number
            if (in_array('invoice_number', $invoiceColumns)) {
                $invoiceFields['invoice_number'] = 'invoice_number';
            } elseif (in_array('number', $invoiceColumns)) {
                $invoiceFields['number'] = 'invoice_number';
            }
            
            // Añadir campos de factura a la consulta
            foreach ($invoiceFields as $field => $alias) {
                if (in_array($field, $invoiceColumns)) {
                    $selectFields[] = "inv.{$field} as {$alias}";
                }
            }
            
            // Verificar las columnas que existen en la tabla clients
            $clientColumns = $db->getFieldNames('clients');
            
            // Verificar y añadir campos del cliente
            $clientFields = [
                'business_name' => 'client_business_name',
                'document_number' => 'client_document',
                'contact_name' => 'client_contact_name',
                'address' => 'client_address',
                'contact_phone' => 'client_phone',
                'email' => 'client_email',
                'ubigeo' => 'client_ubigeo',
                'zip_code' => 'client_zip_code',
                'latitude' => 'client_latitude',
                'longitude' => 'client_longitude',
                'uuid' => 'client_uuid'
            ];
            
            // Añadir campos de cliente a la consulta
            foreach ($clientFields as $field => $alias) {
                if (in_array($field, $clientColumns)) {
                    $selectFields[] = "c.{$field} as {$alias}";
                }
            }
            
            $builder->select(implode(', ', $selectFields));
            
            // Obtener el número total de cuotas por factura para incluirlo en la respuesta
            $subquery = $db->table('instalments')
                ->select('invoice_id, COUNT(*) as instalment_count')
                ->groupBy('invoice_id');
                
            $builder->join("({$subquery->getCompiledSelect()}) as ic", 'i.invoice_id = ic.invoice_id', 'left');
            $builder->select('IFNULL(ic.instalment_count, 0) as invoice_instalment_count');
            
            $builder->join('invoices inv', 'i.invoice_id = inv.id');
            $builder->join('clients c', 'inv.client_id = c.id');
            $builder->where('i.id', $id);
            $builder->where('i.deleted_at IS NULL');
            $builder->where('inv.deleted_at IS NULL');
            $builder->where('c.deleted_at IS NULL');
            
            // Ejecutar la consulta
            $detailedInstalment = $builder->get()->getRowArray();
            
            if (!$detailedInstalment) {
                return $this->failNotFound('Cuota no encontrada');
            }
            
            $response = [
                'instalment' => $detailedInstalment
            ];
            
        } catch (\Exception $e) {
            // Si hay error en la consulta detallada, usar el enfoque simple
            log_message('error', 'Error al obtener detalle de cuota: ' . $e->getMessage());
            
            $response = [
                'instalment' => $instalment
            ];
            
            // Incluir información de la factura
            if ($includeInvoice || true) { // Siempre incluir factura en caso de error
                $invoice = $this->invoiceModel->find($instalment['invoice_id']);
                $response['invoice'] = $invoice;
            }
            
            // Incluir información del cliente
            if ($includeClient || true) { // Siempre incluir cliente en caso de error
                $invoice = $this->invoiceModel->find($instalment['invoice_id']);
                $client = $this->clientModel->find($invoice['client_id']);
                $response['client'] = $client;
            }
        }
        
        // Incluir pagos asociados a la cuota si se solicita
        if ($includePayments) {
            $payments = $this->paymentModel
                ->where('instalment_id', $id)
                ->where('status', 'completed')
                ->findAll();
            $response['payments'] = $payments;
        }
        
        return $this->respond($response);
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
        
        // Obtener las carteras asignadas al usuario
        $userPortfolios = $db->table('portfolio_user')
            ->select('portfolio_uuid')
            ->where('user_uuid', $user['uuid'])
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();
            
        if (empty($userPortfolios)) {
            return false;
        }
        
        $portfolioUuids = array_column($userPortfolios, 'portfolio_uuid');
        
        // Verificar si el cliente está en alguna de las carteras del usuario
        $clientInPortfolio = $db->table('client_portfolio')
            ->where('client_uuid', $client['uuid'])
            ->whereIn('portfolio_uuid', $portfolioUuids)
            ->where('deleted_at IS NULL')
            ->countAllResults();
            
        return $clientInPortfolio > 0;
    }
}

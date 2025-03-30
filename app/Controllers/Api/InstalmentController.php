<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\InstalmentModel;
use App\Models\InvoiceModel;
use App\Models\PaymentModel;
use App\Models\ClientModel;
use CodeIgniter\API\ResponseTrait;

class InstalmentController extends BaseController
{
    use ResponseTrait;
    
    protected $instalmentModel;
    protected $invoiceModel;
    protected $paymentModel;
    protected $clientModel;
    protected $request;
    protected $user;
    
    public function __construct()
    {
        $this->instalmentModel = new InstalmentModel();
        $this->invoiceModel = new InvoiceModel();
        $this->paymentModel = new PaymentModel();
        $this->clientModel = new ClientModel();
        $this->request = \Config\Services::request();
        
        // El usuario será establecido por el filtro de autenticación
        $this->user = session()->get('api_user');
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
     * Verifica si el usuario puede acceder a una factura
     */
    private function canAccessInvoice($invoiceId)
    {
        $invoice = $this->invoiceModel->find($invoiceId);
        if (!$invoice) {
            return false;
        }
        
        // Usar la propiedad user de la clase en lugar de obtenerla del request
        $user = $this->user;
        
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

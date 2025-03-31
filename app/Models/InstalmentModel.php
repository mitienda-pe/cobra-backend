<?php

namespace App\Models;

use CodeIgniter\Model;

class InstalmentModel extends Model
{
    protected $table            = 'instalments';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'uuid', 'invoice_id', 'number', 'amount', 'due_date', 'status', 'notes'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'invoice_id' => 'required|is_natural_no_zero',
        'number'     => 'required|is_natural_no_zero',
        'amount'     => 'required|numeric',
        'due_date'   => 'required|valid_date',
        'status'     => 'required|in_list[pending,paid,cancelled]',
    ];
    
    // Callbacks
    protected $beforeInsert = ['generateUuid'];
    
    /**
     * Generate UUID before insert
     */
    protected function generateUuid(array $data)
    {
        if (!isset($data['data']['uuid'])) {
            helper('uuid');
            $data['data']['uuid'] = generate_unique_uuid('instalments', 'uuid');
        }
        return $data;
    }
    
    /**
     * Get instalments by invoice
     */
    public function getByInvoice($invoiceId)
    {
        return $this->where('invoice_id', $invoiceId)
                    ->orderBy('number', 'ASC')
                    ->findAll();
    }
    
    /**
     * Obtiene las cuotas de una factura ordenadas por prioridad de cobranza:
     * 1. Cuotas vencidas (pendientes) primero, ordenadas por fecha de vencimiento (más antiguas primero)
     * 2. Cuotas por vencer (pendientes) ordenadas por fecha de vencimiento (más próximas primero)
     * 3. Cuotas pagadas ordenadas por número
     * 4. Cuotas futuras cuya cuota anterior no ha vencido aún
     * 
     * @param int $invoiceId ID de la factura
     * @return array Arreglo de cuotas ordenadas por prioridad
     */
    public function getByInvoiceForCollection($invoiceId) {
        $today = date('Y-m-d');
        
        // Obtener todas las cuotas de la factura
        $instalments = $this->where('invoice_id', $invoiceId)->findAll();
        
        // Categorizar las cuotas
        $overdue = []; // Vencidas (pendientes)
        $upcoming = []; // Próximas a vencer (pendientes)
        $paid = []; // Pagadas
        $future = []; // Futuras (cuya cuota anterior no ha vencido)
        
        // Ordenar las cuotas en las categorías correspondientes
        foreach ($instalments as $instalment) {
            if ($instalment['status'] === 'paid') {
                $paid[] = $instalment;
            } else {
                if ($instalment['due_date'] < $today) {
                    $overdue[] = $instalment;
                } else {
                    // Verificar si es una cuota futura cuya cuota anterior no ha vencido
                    $isPreviousOverdue = false;
                    
                    if ($instalment['number'] > 1) {
                        // Buscar la cuota anterior
                        foreach ($instalments as $prev) {
                            if ($prev['number'] == $instalment['number'] - 1) {
                                // Si la cuota anterior no está pagada y no ha vencido, esta es "futura"
                                if ($prev['status'] !== 'paid' && $prev['due_date'] >= $today) {
                                    $isPreviousOverdue = true;
                                }
                                break;
                            }
                        }
                    }
                    
                    if ($isPreviousOverdue) {
                        $future[] = $instalment;
                    } else {
                        $upcoming[] = $instalment;
                    }
                }
            }
        }
        
        // Ordenar cada categoría
        usort($overdue, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']); // Más antiguas primero
        });
        
        usort($upcoming, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']); // Más próximas primero
        });
        
        usort($paid, function($a, $b) {
            return $a['number'] - $b['number']; // Por número de cuota
        });
        
        usort($future, function($a, $b) {
            return strtotime($a['due_date']) - strtotime($b['due_date']); // Por fecha de vencimiento
        });
        
        // Combinar las categorías en el orden deseado
        return array_merge($overdue, $upcoming, $paid, $future);
    }
    
    /**
     * Verifica si una cuota puede ser pagada según el orden cronológico
     * Solo se permite pagar una cuota si todas las cuotas anteriores están pagadas
     * 
     * @param int $instalmentId ID de la cuota a verificar
     * @return bool true si la cuota puede ser pagada, false en caso contrario
     */
    public function canBePaid($instalmentId) {
        $instalment = $this->find($instalmentId);
        
        if (!$instalment) {
            return false;
        }
        
        // Si es la primera cuota, siempre se puede pagar
        if ($instalment['number'] == 1) {
            return true;
        }
        
        // Verificar que todas las cuotas anteriores estén pagadas
        $previousInstalments = $this->where('invoice_id', $instalment['invoice_id'])
                                   ->where('number <', $instalment['number'])
                                   ->findAll();
        
        foreach ($previousInstalments as $prev) {
            if ($prev['status'] !== 'paid') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Update instalment status based on payments
     */
    public function updateStatus($instalmentId)
    {
        $db = \Config\Database::connect();
        
        // Get instalment
        $instalment = $this->find($instalmentId);
        if (!$instalment) {
            return false;
        }
        
        // Calculate total paid for this instalment
        $totalPaid = $db->table('payments')
                        ->selectSum('amount')
                        ->where('instalment_id', $instalmentId)
                        ->where('status', 'completed')
                        ->get()
                        ->getRow()
                        ->amount ?? 0;
        
        // Update status based on total paid
        if ($totalPaid >= $instalment['amount']) {
            $this->update($instalmentId, ['status' => 'paid']);
            
            // Log the status update
            log_message('info', "Instalment {$instalmentId} marked as paid. Total paid: {$totalPaid}, Required: {$instalment['amount']}");
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if all instalments for an invoice are paid
     */
    public function areAllPaid($invoiceId)
    {
        $pendingCount = $this->where('invoice_id', $invoiceId)
                             ->whereIn('status', ['pending'])
                             ->countAllResults();
        
        return $pendingCount === 0;
    }
}

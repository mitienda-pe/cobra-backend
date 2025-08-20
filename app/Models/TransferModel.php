<?php

namespace App\Models;

use CodeIgniter\Model;

class TransferModel extends Model
{
    protected $table = 'transfers';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'organization_id',
        'user_id',
        'reference_transaction_id',
        'account_inquiry_id',
        'instruction_id',
        'debtor_cci',
        'creditor_cci',
        'creditor_name',
        'amount',
        'currency',
        'fee_amount',
        'fee_code',
        'fee_id',
        'unstructured_information',
        'message_type_id',
        'transaction_type',
        'channel',
        'status',
        'response_code',
        'ligo_response',
        'error_message',
        'transfer_type',
        'ligo_environment'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'organization_id' => 'required|integer',
        'reference_transaction_id' => 'required|max_length[50]',
        'debtor_cci' => 'required|max_length[20]',
        'creditor_cci' => 'required|max_length[20]',
        'creditor_name' => 'required|max_length[255]',
        'amount' => 'required|decimal',
        'currency' => 'required|max_length[3]',
        'status' => 'required|max_length[20]'
    ];

    protected $validationMessages = [
        'organization_id' => [
            'required' => 'La organización es requerida',
            'integer' => 'La organización debe ser un número válido'
        ],
        'reference_transaction_id' => [
            'required' => 'El ID de referencia de transacción es requerido',
            'max_length' => 'El ID de referencia no puede exceder 50 caracteres'
        ],
        'debtor_cci' => [
            'required' => 'El CCI del deudor es requerido',
            'max_length' => 'El CCI del deudor debe tener máximo 20 caracteres'
        ],
        'creditor_cci' => [
            'required' => 'El CCI del acreedor es requerido',
            'max_length' => 'El CCI del acreedor debe tener máximo 20 caracteres'
        ],
        'creditor_name' => [
            'required' => 'El nombre del acreedor es requerido',
            'max_length' => 'El nombre del acreedor no puede exceder 255 caracteres'
        ],
        'amount' => [
            'required' => 'El monto es requerido',
            'decimal' => 'El monto debe ser un número decimal válido'
        ],
        'currency' => [
            'required' => 'La moneda es requerida',
            'max_length' => 'La moneda debe tener máximo 3 caracteres'
        ],
        'status' => [
            'required' => 'El estado es requerido',
            'max_length' => 'El estado no puede exceder 20 caracteres'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Create a new transfer record
     */
    public function createTransfer($data)
    {
        // Ensure created_at and updated_at are set
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        log_message('error', '💾 TransferModel: Creando transferencia - Data: ' . json_encode($data));
        
        $result = $this->insert($data);
        if ($result) {
            log_message('error', '✅ TransferModel: Transferencia creada exitosamente - ID: ' . $result);
            return $result;
        } else {
            $errors = $this->errors();
            log_message('error', '❌ TransferModel: Error al crear transferencia - Errores: ' . json_encode($errors));
            return false;
        }
    }

    /**
     * Update transfer status
     */
    public function updateTransferStatus($id, $status, $ligoResponse = null, $errorMessage = null)
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($ligoResponse !== null) {
            $data['ligo_response'] = json_encode($ligoResponse);
        }

        if ($errorMessage !== null) {
            $data['error_message'] = $errorMessage;
        }

        log_message('error', '🔄 TransferModel: Actualizando status - ID: ' . $id . ' Status: ' . $status);
        
        return $this->update($id, $data);
    }

    /**
     * Get transfers by organization
     */
    public function getTransfersByOrganization($organizationId, $limit = 50, $offset = 0)
    {
        return $this->where('organization_id', $organizationId)
                    ->orderBy('created_at', 'DESC')
                    ->findAll($limit, $offset);
    }

    /**
     * Get transfer statistics
     */
    public function getTransferStats($organizationId = null)
    {
        $builder = $this->builder();
        
        if ($organizationId) {
            $builder->where('organization_id', $organizationId);
        }

        $stats = [
            'total' => $builder->countAllResults(false),
            'successful' => $builder->where('status', 'completed')->countAllResults(false),
            'processing' => $builder->where('status', 'processing')->countAllResults(false),
            'pending' => $builder->where('status', 'pending')->countAllResults(false),
            'failed' => $builder->where('status', 'failed')->countAllResults(false)
        ];

        // Calculate total amounts
        $amountBuilder = $this->builder();
        if ($organizationId) {
            $amountBuilder->where('organization_id', $organizationId);
        }
        $amountBuilder->where('status', 'completed');
        $amountBuilder->selectSum('amount', 'total_amount');
        $result = $amountBuilder->get()->getRowArray();
        $stats['total_amount'] = $result['total_amount'] ?? 0;

        return $stats;
    }

    /**
     * Get recent transfers
     */
    public function getRecentTransfers($organizationId = null, $limit = 10)
    {
        $builder = $this->builder();
        
        if ($organizationId) {
            $builder->where('organization_id', $organizationId);
        }

        return $builder->orderBy('created_at', 'DESC')
                      ->limit($limit)
                      ->get()
                      ->getResultArray();
    }

    /**
     * Get withdrawal transfers for an organization
     */
    public function getWithdrawalsByOrganization($organizationId, $limit = 50, $offset = 0)
    {
        return $this->where('organization_id', $organizationId)
                    ->where('transfer_type', 'withdrawal')
                    ->orderBy('created_at', 'DESC')
                    ->findAll($limit, $offset);
    }

    /**
     * Calculate organization's effective balance
     * (incoming transfers minus outgoing transfers/withdrawals)
     */
    public function calculateOrganizationBalance($organizationId)
    {
        $db = \Config\Database::connect();
        
        // Sum of completed incoming transfers (to organization)
        $incomingQuery = $db->table($this->table)
            ->selectSum('amount', 'total_incoming')
            ->where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->where('transfer_type !=', 'withdrawal')
            ->get();
        
        $incoming = $incomingQuery->getRowArray()['total_incoming'] ?? 0;
        
        // Sum of completed withdrawals (from organization)
        $withdrawalQuery = $db->table($this->table)
            ->selectSum('amount', 'total_withdrawals')
            ->where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->where('transfer_type', 'withdrawal')
            ->get();
        
        $withdrawals = $withdrawalQuery->getRowArray()['total_withdrawals'] ?? 0;
        
        return [
            'incoming' => floatval($incoming),
            'withdrawals' => floatval($withdrawals),
            'available_balance' => floatval($incoming) - floatval($withdrawals)
        ];
    }

    /**
     * Get transfer statistics including withdrawals
     */
    public function getDetailedTransferStats($organizationId = null)
    {
        $builder = $this->builder();
        
        if ($organizationId) {
            $builder->where('organization_id', $organizationId);
        }

        $stats = [
            'total' => $builder->countAllResults(false),
            'successful' => $builder->where('status', 'completed')->countAllResults(false),
            'processing' => $builder->where('status', 'processing')->countAllResults(false),
            'pending' => $builder->where('status', 'pending')->countAllResults(false),
            'failed' => $builder->where('status', 'failed')->countAllResults(false)
        ];

        // Separate by transfer type
        $regularBuilder = $this->builder();
        if ($organizationId) {
            $regularBuilder->where('organization_id', $organizationId);
        }
        $stats['regular_transfers'] = $regularBuilder->where('transfer_type !=', 'withdrawal')->countAllResults(false);

        $withdrawalBuilder = $this->builder();
        if ($organizationId) {
            $withdrawalBuilder->where('organization_id', $organizationId);
        }
        $stats['withdrawals'] = $withdrawalBuilder->where('transfer_type', 'withdrawal')->countAllResults(false);

        return $stats;
    }
}
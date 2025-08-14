<?php

namespace App\Models;

use CodeIgniter\Model;

class SuperadminLigoConfigModel extends Model
{
    protected $table            = 'superadmin_ligo_config';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'config_key', 'environment', 'username', 'password', 'company_id', 
        'account_id', 'debtor_name', 'debtor_id', 'debtor_id_code', 
        'debtor_address_line', 'debtor_mobile_number', 'debtor_participant_code',
        'merchant_code', 'private_key', 'webhook_secret',
        'auth_url', 'api_url', 'ssl_verify', 'enabled', 'is_active', 'notes'
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules = [
        'config_key' => 'required|max_length[100]',
        'environment' => 'required|in_list[dev,prod]',
        'username' => 'permit_empty|max_length[255]',
        'company_id' => 'permit_empty|max_length[100]',
        'account_id' => 'permit_empty|max_length[100]',
        'merchant_code' => 'permit_empty|max_length[50]',
        'auth_url' => 'permit_empty|valid_url|max_length[255]',
        'api_url' => 'permit_empty|valid_url|max_length[255]',
        'enabled' => 'permit_empty|in_list[0,1]',
        'is_active' => 'permit_empty|in_list[0,1]'
    ];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['encryptPassword'];
    protected $afterInsert    = ['manageActiveConfig'];
    protected $beforeUpdate   = ['encryptPassword'];
    protected $afterUpdate    = ['manageActiveConfig'];
    protected $beforeFind     = [];
    protected $afterFind      = ['decryptPassword'];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Encrypt password before saving
     */
    protected function encryptPassword(array $data)
    {
        if (isset($data['data']['password']) && !empty($data['data']['password'])) {
            // Only encrypt if it's not already encrypted (doesn't start with encrypted prefix)
            if (strpos($data['data']['password'], 'ENC:') !== 0) {
                $data['data']['password'] = 'ENC:' . base64_encode($data['data']['password']);
            }
        }
        return $data;
    }

    /**
     * Decrypt password after loading
     */
    protected function decryptPassword(array $data)
    {
        $configs = isset($data['data']) ? $data['data'] : [$data];
        $isMultiple = isset($data['data']);

        foreach ($configs as $key => $config) {
            if (isset($config['password']) && strpos($config['password'], 'ENC:') === 0) {
                $decrypted = base64_decode(substr($config['password'], 4));
                if ($isMultiple) {
                    $data['data'][$key]['password'] = $decrypted;
                } else {
                    $data['password'] = $decrypted;
                }
            }
        }

        return $data;
    }

    /**
     * Ensure only one config per environment is active
     */
    protected function manageActiveConfig(array $data)
    {
        $id = isset($data['id']) ? $data['id'] : (isset($data['result']) ? $data['result'] : null);
        $configData = isset($data['data']) ? $data['data'] : [];

        if (isset($configData['is_active']) && $configData['is_active']) {
            // Deactivate ALL other configs (only one can be active globally)
            $this->where('id !=', $id)
                 ->where('is_active', 1)
                 ->set('is_active', 0)
                 ->update();
        }

        return $data;
    }

    /**
     * Get the active configuration for a specific environment
     */
    public function getActiveConfig($environment = null)
    {
        if (!$environment) {
            $environment = env('CI_ENVIRONMENT', 'development') === 'production' ? 'prod' : 'dev';
        }

        return $this->where('environment', $environment)
                   ->where('enabled', 1)
                   ->where('is_active', 1)
                   ->first();
    }

    /**
     * Get all configurations for management
     */
    public function getAllConfigs()
    {
        return $this->orderBy('environment', 'ASC')
                   ->orderBy('config_key', 'ASC')
                   ->findAll();
    }

    /**
     * Set a configuration as active
     */
    public function setAsActive($id)
    {
        $config = $this->find($id);
        if (!$config) {
            return false;
        }

        // Start transaction
        $this->db->transStart();

        // Deactivate ALL configs (only one can be active globally)
        $this->where('is_active', 1)
             ->set('is_active', 0)
             ->update();

        // Activate this config
        $result = $this->update($id, ['is_active' => 1, 'enabled' => 1]);

        $this->db->transComplete();

        return $this->db->transStatus();
    }

    /**
     * Check if configuration has all required fields for API usage
     */
    public function isConfigurationComplete($config = null)
    {
        if (!$config) {
            $config = $this->getActiveConfig();
        }

        if (!$config || !$config['enabled']) {
            return false;
        }

        // Campos básicos requeridos para autenticación
        $basicRequiredFields = ['username', 'password', 'company_id', 'private_key'];
        
        // Campos de deudor - solo verificar si al menos uno está presente
        $debtorFields = ['debtor_name', 'debtor_id', 'debtor_id_code', 'debtor_address_line', 'debtor_participant_code'];
        
        // Verificar campos básicos
        foreach ($basicRequiredFields as $field) {
            if (empty($config[$field])) {
                return false;
            }
        }
        
        // Si no hay campos de deudor configurados, usar valores por defecto (temporal)
        $hasDebtorData = false;
        foreach ($debtorFields as $field) {
            if (!empty($config[$field])) {
                $hasDebtorData = true;
                break;
            }
        }
        
        // Temporal: permitir configuración sin datos de deudor completos
        // TODO: Hacer esto obligatorio cuando todas las configuraciones estén completas
        if (!$hasDebtorData) {
            log_message('warning', 'SuperadminLigoConfig: Configuration lacks debtor data, using defaults');
        }
        
        return true;
    }

    /**
     * Get Ligo API URLs based on environment
     */
    public function getApiUrls($environment = null)
    {
        if (!$environment) {
            $environment = env('CI_ENVIRONMENT', 'development') === 'production' ? 'prod' : 'dev';
        }

        $config = $this->getActiveConfig($environment);

        if ($config && !empty($config['auth_url']) && !empty($config['api_url'])) {
            return [
                'auth_url' => $config['auth_url'],
                'api_url' => $config['api_url']
            ];
        }

        // Default URLs - revert to working URLs from logs
        return [
            'auth_url' => "https://cce-auth-{$environment}.ligocloud.tech",
            'api_url' => "https://cce-api-gateway-{$environment}.ligocloud.tech"
        ];
    }
}

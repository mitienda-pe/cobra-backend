<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

class SuperadminLigoConfigController extends BaseController
{
    protected $superadminLigoConfigModel;

    public function __construct()
    {
        $this->superadminLigoConfigModel = new \App\Models\SuperadminLigoConfigModel();
        helper(['form', 'url']);
        log_message('error', '🏗️ SuperadminLigoConfigController constructor called');
    }

    /**
     * Check if user is superadmin
     */
    private function checkSuperadminAccess()
    {
        $session = session();
        $user = $session->get('user');
        
        if (!$user || $user['role'] !== 'superadmin') {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }
    }

    /**
     * Display Ligo configuration management
     */
    public function index()
    {
        error_log('📋 DIRECT PHP LOG - SuperadminLigoConfigController::index() called - Page loading');
        log_message('error', '📋 SuperadminLigoConfigController::index() called');
        $this->checkSuperadminAccess();

        $data = [
            'title' => 'Configuración Centralizada de Ligo',
            'breadcrumb' => 'Configuración Ligo',
            'configs' => $this->superadminLigoConfigModel->getAllConfigs()
        ];

        return view('superadmin/ligo_config/index', $data);
    }

    /**
     * Show form to edit configuration
     */
    public function edit($id = null)
    {
        $this->checkSuperadminAccess();

        if (!$id) {
            return redirect()->to('superadmin/ligo-config')->with('error', 'ID de configuración requerido');
        }

        $config = $this->superadminLigoConfigModel->find($id);
        if (!$config) {
            return redirect()->to('superadmin/ligo-config')->with('error', 'Configuración no encontrada');
        }

        $data = [
            'title' => 'Editar Configuración Ligo - ' . ucfirst($config['environment']),
            'breadcrumb' => 'Editar Configuración',
            'config' => $config
        ];

        return view('superadmin/ligo_config/edit', $data);
    }

    /**
     * Update configuration
     */
    public function update($id = null)
    {
        $this->checkSuperadminAccess();

        if (!$id) {
            return redirect()->to('superadmin/ligo-config')->with('error', 'ID de configuración requerido');
        }

        $config = $this->superadminLigoConfigModel->find($id);
        if (!$config) {
            return redirect()->to('superadmin/ligo-config')->with('error', 'Configuración no encontrada');
        }

        // Validate input
        $validation = \Config\Services::validation();
        $validation->setRules([
            'username' => 'permit_empty|max_length[255]',
            'password' => 'permit_empty',
            'company_id' => 'permit_empty|max_length[100]',
            'account_id' => 'permit_empty|max_length[100]',
            'merchant_code' => 'permit_empty|max_length[50]',
            'private_key' => 'permit_empty',
            'debtor_name' => 'permit_empty|max_length[255]',
            'debtor_id' => 'permit_empty|max_length[50]',
            'debtor_id_code' => 'permit_empty|max_length[10]',
            'debtor_address_line' => 'permit_empty|max_length[255]',
            'debtor_mobile_number' => 'permit_empty|max_length[20]',
            'debtor_participant_code' => 'permit_empty|max_length[10]',
            'debtor_phone_number' => 'permit_empty|max_length[20]',
            'debtor_type_of_person' => 'permit_empty|max_length[5]',
            'creditor_address_line' => 'permit_empty|max_length[255]',
            'transaction_type' => 'permit_empty|max_length[10]',
            'channel' => 'permit_empty|max_length[10]',
            'auth_url' => 'permit_empty|valid_url',
            'api_url' => 'permit_empty|valid_url',
            'enabled' => 'permit_empty|in_list[0,1]',
            'is_active' => 'permit_empty|in_list[0,1]',
            'notes' => 'permit_empty'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        // Prepare data for update
        $updateData = [];
        $fields = ['username', 'password', 'company_id', 'account_id', 'merchant_code', 
                  'private_key', 'debtor_name', 'debtor_id', 'debtor_id_code', 
                  'debtor_address_line', 'debtor_mobile_number', 'debtor_participant_code',
                  'debtor_phone_number', 'debtor_type_of_person', 'creditor_address_line',
                  'transaction_type', 'channel', 'webhook_secret', 'auth_url', 'api_url', 'notes'];

        foreach ($fields as $field) {
            $value = $this->request->getPost($field);
            if ($value !== null && $value !== '') {
                $updateData[$field] = $value;
            }
        }

        // Handle checkboxes
        $updateData['enabled'] = $this->request->getPost('enabled') ? 1 : 0;
        $updateData['is_active'] = $this->request->getPost('is_active') ? 1 : 0;

        // Update configuration
        if ($this->superadminLigoConfigModel->update($id, $updateData)) {
            return redirect()->to('superadmin/ligo-config')->with('message', 'Configuración actualizada exitosamente');
        } else {
            return redirect()->back()->withInput()->with('error', 'Error al actualizar la configuración');
        }
    }

    /**
     * Set configuration as active
     */
    public function setActive($id = null)
    {
        $this->checkSuperadminAccess();

        if (!$id) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID requerido']);
        }

        if ($this->superadminLigoConfigModel->setAsActive($id)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Configuración activada exitosamente']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Error al activar la configuración']);
        }
    }

    /**
     * Test configuration
     */
    public function test($id = null)
    {
        // TEMPORARY DEBUG: Log that the test method was called
        error_log('🚀 DIRECT PHP LOG - SuperadminLigoConfigController::test() called with ID: ' . ($id ?? 'null'));
        log_message('error', '🚀 SuperadminLigoConfigController::test() called with ID: ' . ($id ?? 'null'));
        
        $this->checkSuperadminAccess();

        if (!$id) {
            log_message('error', '❌ SuperadminLigoConfigController::test() - No ID provided');
            return $this->response->setJSON(['success' => false, 'message' => 'ID requerido']);
        }

        $config = $this->superadminLigoConfigModel->find($id);
        if (!$config) {
            return $this->response->setJSON(['success' => false, 'message' => 'Configuración no encontrada']);
        }

        log_message('info', '[SuperadminLigoConfig] Testing configuration ID: ' . $id . ', Environment: ' . $config['environment']);
        log_message('debug', '[SuperadminLigoConfig] Config details: ' . json_encode([
            'environment' => $config['environment'],
            'username' => $config['username'] ?? 'null',
            'company_id' => $config['company_id'] ?? 'null',
            'account_id' => $config['account_id'] ?? 'null',
            'has_password' => !empty($config['password']),
            'has_private_key' => !empty($config['private_key']),
            'auth_url' => $config['auth_url'] ?? 'default',
            'api_url' => $config['api_url'] ?? 'default'
        ]));

        // Test the configuration by trying to authenticate
        $ligoModel = new \App\Models\LigoModel();
        
        // Temporarily set this config as active for testing
        $originalActive = $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                                         ->where('is_active', 1)
                                                         ->first();
        
        log_message('debug', '[SuperadminLigoConfig] Original active config: ' . ($originalActive ? $originalActive['id'] : 'none'));
        
        // Set test config as active
        $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                       ->set('is_active', 0)
                                       ->update();
        $this->superadminLigoConfigModel->update($id, ['is_active' => 1]);
        
        log_message('info', '[SuperadminLigoConfig] Temporarily activated config ID: ' . $id . ' for testing');

        // Test authentication
        try {
            log_message('info', '[SuperadminLigoConfig] Starting authentication test...');
            $testResult = $ligoModel->getAccountBalanceForOrganization();
            log_message('debug', '[SuperadminLigoConfig] Test result: ' . json_encode($testResult));
            
            // Restore original active config
            if ($originalActive) {
                $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                               ->set('is_active', 0)
                                               ->update();
                $this->superadminLigoConfigModel->update($originalActive['id'], ['is_active' => 1]);
                log_message('debug', '[SuperadminLigoConfig] Restored original active config ID: ' . $originalActive['id']);
            } else {
                log_message('debug', '[SuperadminLigoConfig] No original active config to restore');
            }

            if (isset($testResult['error'])) {
                log_message('error', '[SuperadminLigoConfig] Test failed with error: ' . $testResult['error']);
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => 'Error en prueba: ' . $testResult['error'],
                    'debug_info' => [
                        'environment' => $config['environment'],
                        'username' => $config['username'],
                        'company_id' => $config['company_id'],
                        'auth_url' => $config['auth_url'] ?? 'default'
                    ]
                ]);
            } else {
                log_message('info', '[SuperadminLigoConfig] Test successful for config ID: ' . $id);
                return $this->response->setJSON([
                    'success' => true, 
                    'message' => 'Configuración probada exitosamente',
                    'data' => $testResult
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', '[SuperadminLigoConfig] Exception during test: ' . $e->getMessage());
            log_message('error', '[SuperadminLigoConfig] Exception trace: ' . $e->getTraceAsString());
            
            // Restore original active config
            if ($originalActive) {
                $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                               ->set('is_active', 0)
                                               ->update();
                $this->superadminLigoConfigModel->update($originalActive['id'], ['is_active' => 1]);
                log_message('debug', '[SuperadminLigoConfig] Restored original active config after exception');
            }

            return $this->response->setJSON([
                'success' => false, 
                'message' => 'Error en prueba: ' . $e->getMessage(),
                'debug_info' => [
                    'environment' => $config['environment'],
                    'username' => $config['username'],
                    'company_id' => $config['company_id']
                ]
            ]);
        }
    }
}

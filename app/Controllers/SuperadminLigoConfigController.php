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
                  'private_key', 'webhook_secret', 'auth_url', 'api_url', 'notes'];

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
        $this->checkSuperadminAccess();

        if (!$id) {
            return $this->response->setJSON(['success' => false, 'message' => 'ID requerido']);
        }

        $config = $this->superadminLigoConfigModel->find($id);
        if (!$config) {
            return $this->response->setJSON(['success' => false, 'message' => 'Configuración no encontrada']);
        }

        // Test the configuration by trying to authenticate
        $ligoModel = new \App\Models\LigoModel();
        
        // Temporarily set this config as active for testing
        $originalActive = $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                                         ->where('is_active', 1)
                                                         ->first();
        
        // Set test config as active
        $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                       ->set('is_active', 0)
                                       ->update();
        $this->superadminLigoConfigModel->update($id, ['is_active' => 1]);

        // Test authentication
        try {
            $testResult = $ligoModel->getAccountBalanceForOrganization();
            
            // Restore original active config
            if ($originalActive) {
                $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                               ->set('is_active', 0)
                                               ->update();
                $this->superadminLigoConfigModel->update($originalActive['id'], ['is_active' => 1]);
            }

            if (isset($testResult['error'])) {
                return $this->response->setJSON([
                    'success' => false, 
                    'message' => 'Error en prueba: ' . $testResult['error']
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => true, 
                    'message' => 'Configuración probada exitosamente',
                    'data' => $testResult
                ]);
            }
        } catch (\Exception $e) {
            // Restore original active config
            if ($originalActive) {
                $this->superadminLigoConfigModel->where('environment', $config['environment'])
                                               ->set('is_active', 0)
                                               ->update();
                $this->superadminLigoConfigModel->update($originalActive['id'], ['is_active' => 1]);
            }

            return $this->response->setJSON([
                'success' => false, 
                'message' => 'Error en prueba: ' . $e->getMessage()
            ]);
        }
    }
}

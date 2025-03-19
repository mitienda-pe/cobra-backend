<?php

namespace App\Controllers;

use App\Models\WebhookModel;
use App\Models\WebhookLogModel;
use App\Libraries\Auth;

class WebhooksController extends BaseController
{
    protected $auth;
    
    public function __construct()
    {
        $this->auth = new Auth();
        helper(['form', 'url']);
    }
    
    public function index()
    {
        // Only superadmins and admins can manage webhooks
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para gestionar webhooks.');
        }
        
        $webhookModel = new WebhookModel();
        $webhooks = $webhookModel->where('organization_id', $this->auth->organizationId())->findAll();
        
        $data = [
            'webhooks' => $webhooks,
            'auth' => $this->auth,
        ];
        
        return view('webhooks/index', $data);
    }
    
    public function create()
    {
        // Only superadmins and admins can create webhooks
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para crear webhooks.');
        }
        
        $data = [
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name'     => 'required|min_length[3]|max_length[100]',
                'url'      => 'required|valid_url',
                'events'   => 'required',
                'is_active' => 'permit_empty',
            ];
            
            if ($this->validate($rules)) {
                $webhookModel = new WebhookModel();
                
                // Generate a secret key
                $secret = $webhookModel->generateSecret();
                
                // Prepare data
                $data = [
                    'organization_id' => $this->auth->organizationId(),
                    'name'            => $this->request->getPost('name'),
                    'url'             => $this->request->getPost('url'),
                    'secret'          => $secret,
                    'events'          => $this->request->getPost('events'),
                    'is_active'       => $this->request->getPost('is_active') ? 1 : 0,
                ];
                
                $webhookId = $webhookModel->insert($data);
                
                if ($webhookId) {
                    return redirect()->to('/webhooks')->with('message', 'Webhook creado exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al crear el webhook.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // Available events
        $events = [
            'payment.created'  => 'Pago Creado',
            'payment.updated'  => 'Pago Actualizado',
            'invoice.created'  => 'Factura Creada',
            'invoice.updated'  => 'Factura Actualizada',
            'invoice.paid'     => 'Factura Pagada',
        ];
        
        $data['events'] = $events;
        
        return view('webhooks/create', $data);
    }
    
    public function edit($id = null)
    {
        // Only superadmins and admins can edit webhooks
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para editar webhooks.');
        }
        
        if (!$id) {
            return redirect()->to('/webhooks')->with('error', 'ID de webhook no proporcionado.');
        }
        
        $webhookModel = new WebhookModel();
        $webhook = $webhookModel->find($id);
        
        if (!$webhook) {
            return redirect()->to('/webhooks')->with('error', 'Webhook no encontrado.');
        }
        
        // Check if webhook belongs to user's organization
        if ($webhook['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/webhooks')->with('error', 'No tiene permisos para editar este webhook.');
        }
        
        $data = [
            'webhook' => $webhook,
            'auth' => $this->auth,
        ];
        
        // Handle form submission
        if ($this->request->getMethod() === 'post') {
            $rules = [
                'name'     => 'required|min_length[3]|max_length[100]',
                'url'      => 'required|valid_url',
                'events'   => 'required',
                'is_active' => 'permit_empty',
            ];
            
            if ($this->validate($rules)) {
                // Prepare data
                $data = [
                    'name'      => $this->request->getPost('name'),
                    'url'       => $this->request->getPost('url'),
                    'events'    => $this->request->getPost('events'),
                    'is_active' => $this->request->getPost('is_active') ? 1 : 0,
                ];
                
                // Regenerate secret key if requested
                if ($this->request->getPost('regenerate_secret')) {
                    $data['secret'] = $webhookModel->generateSecret();
                }
                
                $updated = $webhookModel->update($id, $data);
                
                if ($updated) {
                    return redirect()->to('/webhooks')->with('message', 'Webhook actualizado exitosamente.');
                } else {
                    return redirect()->back()->withInput()
                        ->with('error', 'Error al actualizar el webhook.');
                }
            } else {
                return redirect()->back()->withInput()
                    ->with('errors', $this->validator->getErrors());
            }
        }
        
        // Available events
        $events = [
            'payment.created'  => 'Pago Creado',
            'payment.updated'  => 'Pago Actualizado',
            'invoice.created'  => 'Factura Creada',
            'invoice.updated'  => 'Factura Actualizada',
            'invoice.paid'     => 'Factura Pagada',
        ];
        
        $data['events'] = $events;
        $data['selectedEvents'] = explode(',', $webhook['events']);
        
        return view('webhooks/edit', $data);
    }
    
    public function delete($id = null)
    {
        // Only superadmins and admins can delete webhooks
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para eliminar webhooks.');
        }
        
        if (!$id) {
            return redirect()->to('/webhooks')->with('error', 'ID de webhook no proporcionado.');
        }
        
        $webhookModel = new WebhookModel();
        $webhook = $webhookModel->find($id);
        
        if (!$webhook) {
            return redirect()->to('/webhooks')->with('error', 'Webhook no encontrado.');
        }
        
        // Check if webhook belongs to user's organization
        if ($webhook['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/webhooks')->with('error', 'No tiene permisos para eliminar este webhook.');
        }
        
        // Delete webhook logs first
        $db = \Config\Database::connect();
        $db->table('webhook_logs')->where('webhook_id', $id)->delete();
        
        $deleted = $webhookModel->delete($id);
        
        if ($deleted) {
            return redirect()->to('/webhooks')->with('message', 'Webhook eliminado exitosamente.');
        } else {
            return redirect()->to('/webhooks')->with('error', 'Error al eliminar el webhook.');
        }
    }
    
    public function logs($id = null)
    {
        // Only superadmins and admins can view webhook logs
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para ver logs de webhooks.');
        }
        
        if (!$id) {
            return redirect()->to('/webhooks')->with('error', 'ID de webhook no proporcionado.');
        }
        
        $webhookModel = new WebhookModel();
        $webhook = $webhookModel->find($id);
        
        if (!$webhook) {
            return redirect()->to('/webhooks')->with('error', 'Webhook no encontrado.');
        }
        
        // Check if webhook belongs to user's organization
        if ($webhook['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/webhooks')->with('error', 'No tiene permisos para ver logs de este webhook.');
        }
        
        $webhookLogModel = new WebhookLogModel();
        $logs = $webhookLogModel->getByWebhook($id, 100);
        
        $data = [
            'webhook' => $webhook,
            'logs'    => $logs,
            'auth'    => $this->auth,
        ];
        
        return view('webhooks/logs', $data);
    }
    
    public function test($id = null)
    {
        // Only superadmins and admins can test webhooks
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para probar webhooks.');
        }
        
        if (!$id) {
            return redirect()->to('/webhooks')->with('error', 'ID de webhook no proporcionado.');
        }
        
        $webhookModel = new WebhookModel();
        $webhook = $webhookModel->find($id);
        
        if (!$webhook) {
            return redirect()->to('/webhooks')->with('error', 'Webhook no encontrado.');
        }
        
        // Check if webhook belongs to user's organization
        if ($webhook['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/webhooks')->with('error', 'No tiene permisos para probar este webhook.');
        }
        
        $result = $webhookModel->testWebhook($id);
        
        if ($result['success']) {
            return redirect()->to('/webhooks/logs/' . $id)->with('message', 'Webhook probado exitosamente.');
        } else {
            return redirect()->to('/webhooks/logs/' . $id)->with('error', 'Error al probar el webhook: ' . ($result['error'] ?: 'Código de respuesta ' . $result['status_code']));
        }
    }
    
    public function retry($logId = null)
    {
        // Only superadmins and admins can retry webhooks
        if (!$this->auth->hasAnyRole(['superadmin', 'admin'])) {
            return redirect()->to('/dashboard')->with('error', 'No tiene permisos para reintentar webhooks.');
        }
        
        if (!$logId) {
            return redirect()->to('/webhooks')->with('error', 'ID de log no proporcionado.');
        }
        
        $webhookLogModel = new WebhookLogModel();
        $log = $webhookLogModel->find($logId);
        
        if (!$log) {
            return redirect()->to('/webhooks')->with('error', 'Log no encontrado.');
        }
        
        $webhookModel = new WebhookModel();
        $webhook = $webhookModel->find($log['webhook_id']);
        
        // Check if webhook belongs to user's organization
        if ($webhook['organization_id'] != $this->auth->organizationId()) {
            return redirect()->to('/webhooks')->with('error', 'No tiene permisos para reintentar este webhook.');
        }
        
        $result = $webhookLogModel->retry($logId);
        
        if ($result) {
            return redirect()->to('/webhooks/logs/' . $log['webhook_id'])->with('message', 'Webhook reintentado exitosamente.');
        } else {
            return redirect()->to('/webhooks/logs/' . $log['webhook_id'])->with('error', 'Error al reintentar el webhook.');
        }
    }
}
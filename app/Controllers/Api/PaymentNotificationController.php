<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;

class PaymentNotificationController extends ResourceController
{
    protected $format = 'json';

    /**
     * Server-Sent Events endpoint
     * La app móvil se conecta aquí para recibir notificaciones en tiempo real
     */
    public function stream($orderId)
    {
        // Configurar headers para SSE
        $this->response->setHeader('Content-Type', 'text/event-stream');
        $this->response->setHeader('Cache-Control', 'no-cache');
        $this->response->setHeader('Connection', 'keep-alive');
        $this->response->setHeader('Access-Control-Allow-Origin', '*');
        $this->response->setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $this->response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Accept');
        
        // Log para debugging
        log_message('info', "🔴 SSE: Cliente conectado para order_id: {$orderId}");
        
        // Verificar estado del pago
        $payment = $this->checkPaymentStatus($orderId);
        
        if ($payment && $payment['status'] === 'completed') {
            // Pago encontrado - enviar notificación inmediata
            echo "event: payment_success\n";
            echo "data: " . json_encode([
                'order_id' => $orderId,
                'payment_id' => $payment['uuid'] ?? $payment['id'],
                'amount' => floatval($payment['amount']),
                'status' => 'completed',
                'payment_method' => $payment['payment_method'] ?? 'qr',
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => 'Payment completed successfully!'
            ]) . "\n\n";
            
            log_message('info', "🟢 SSE: Pago completado enviado para order_id: {$orderId}");
        } else {
            // Pago pendiente - enviar heartbeat
            echo ": heartbeat " . date('Y-m-d H:i:s') . "\n\n";
            log_message('info', "🔵 SSE: Heartbeat enviado para order_id: {$orderId}");
        }
        
        flush();
        exit;
    }

    /**
     * Polling endpoint (fallback si SSE falla)
     * La app consulta este endpoint cada 3 segundos
     */
    public function events($orderId)
    {
        log_message('info', "🟡 POLLING: Consulta para order_id: {$orderId}");
        
        $payment = $this->checkPaymentStatus($orderId);
        
        if ($payment && $payment['status'] === 'completed') {
            $response = [
                'status' => 'completed',
                'order_id' => $orderId,
                'payment_id' => $payment['uuid'] ?? $payment['id'],
                'amount' => floatval($payment['amount']),
                'payment_method' => $payment['payment_method'] ?? 'qr',
                'timestamp' => date('Y-m-d H:i:s'),
                'message' => 'Payment completed successfully!'
            ];
            
            log_message('info', "🟢 POLLING: Pago completado enviado para order_id: {$orderId}");
            return $this->respond($response);
        } else {
            log_message('info', "🔵 POLLING: Pago pendiente para order_id: {$orderId}");
            return $this->respond(['status' => 'pending']);
        }
    }

    /**
     * Verificar estado del pago en la base de datos
     */
    private function checkPaymentStatus($orderId)
    {
        $db = \Config\Database::connect();
        
        try {
            // Buscar el pago por el campo que contiene el order_id
            // Según el webhook de Ligo, el order_id puede estar en el campo reference_code
            $builder = $db->table('payments');
            
            $payment = $builder->select('id, uuid, amount, status, payment_method, reference_code, created_at, updated_at')
                              ->groupStart()
                                  ->where('reference_code', $orderId)
                                  ->orWhere('uuid', $orderId)
                                  ->orWhere('external_id', $orderId)
                              ->groupEnd()
                              ->where('deleted_at IS NULL')
                              ->orderBy('id', 'DESC')
                              ->get()
                              ->getRowArray();
            
            if ($payment) {
                log_message('info', "🔍 DB Query: order_id={$orderId}, found=YES, status={$payment['status']}, amount={$payment['amount']}, reference_code={$payment['reference_code']}");
            } else {
                log_message('info', "🔍 DB Query: order_id={$orderId}, found=NO");
                
                // También buscar en notas por si el order_id está almacenado ahí
                $payment = $builder->select('id, uuid, amount, status, payment_method, reference_code, notes, created_at, updated_at')
                                  ->like('notes', $orderId)
                                  ->where('deleted_at IS NULL')
                                  ->orderBy('id', 'DESC')
                                  ->get()
                                  ->getRowArray();
                
                if ($payment) {
                    log_message('info', "🔍 DB Query NOTES: order_id={$orderId}, found=YES, status={$payment['status']}, notes=" . substr($payment['notes'] ?? '', 0, 100));
                } else {
                    log_message('info', "🔍 DB Query NOTES: order_id={$orderId}, found=NO");
                }
            }
            
        } catch (\Exception $e) {
            log_message('error', "❌ Error en checkPaymentStatus: " . $e->getMessage());
            return null;
        }
        
        return $payment;
    }

    /**
     * Endpoint para simular webhook de pago completado (solo para testing)
     * POST /api/payments/webhook-test
     */
    public function webhookTest()
    {
        // Solo permitir en modo desarrollo
        if (ENVIRONMENT === 'production') {
            return $this->fail('Not allowed in production', 403);
        }

        $data = $this->request->getJSON(true);
        $orderId = $data['order_id'] ?? null;
        
        if (!$orderId) {
            return $this->fail('order_id is required', 400);
        }

        log_message('info', "🧪 WEBHOOK TEST: Simulando pago completado para order_id: {$orderId}");

        $db = \Config\Database::connect();
        
        try {
            // Buscar si ya existe un pago para este order_id
            $builder = $db->table('payments');
            $existingPayment = $builder->select('id, status, reference_code')
                                      ->groupStart()
                                          ->where('reference_code', $orderId)
                                          ->orWhere('uuid', $orderId)
                                          ->orWhere('external_id', $orderId)
                                      ->groupEnd()
                                      ->where('deleted_at IS NULL')
                                      ->get()
                                      ->getRowArray();

            if ($existingPayment) {
                // Actualizar pago existente a completed
                $updateData = [
                    'status' => 'completed',
                    'payment_method' => 'qr',
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $builder->where('id', $existingPayment['id'])->update($updateData);
                
                log_message('info', "🟢 WEBHOOK TEST: Pago actualizado - ID: {$existingPayment['id']}, order_id: {$orderId}");
                
                return $this->respond([
                    'success' => true,
                    'message' => 'Payment marked as completed',
                    'order_id' => $orderId,
                    'payment_id' => $existingPayment['id'],
                    'action' => 'updated'
                ]);
            } else {
                // Crear nuevo pago simulado
                $paymentData = [
                    'invoice_id' => 1, // ID dummy
                    'amount' => $data['amount'] ?? 10.00,
                    'payment_method' => 'qr',
                    'reference_code' => $orderId,
                    'external_id' => $orderId,
                    'payment_date' => date('Y-m-d H:i:s'),
                    'status' => 'completed',
                    'notes' => json_encode([
                        'test_webhook' => true,
                        'order_id' => $orderId,
                        'simulated_at' => date('Y-m-d H:i:s')
                    ]),
                    'uuid' => $this->generateUUID(),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                $builder->insert($paymentData);
                $paymentId = $db->insertID();

                log_message('info', "🟢 WEBHOOK TEST: Nuevo pago creado - ID: {$paymentId}, order_id: {$orderId}");

                return $this->respond([
                    'success' => true,
                    'message' => 'Test payment created as completed',
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'action' => 'created'
                ]);
            }

        } catch (\Exception $e) {
            log_message('error', "❌ WEBHOOK TEST Error: " . $e->getMessage());
            return $this->fail('Database error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generar UUID simple para testing
     */
    private function generateUUID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}
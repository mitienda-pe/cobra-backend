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
                              ->where('reference_code', $orderId)
                              ->orWhere('uuid', $orderId)
                              ->where('deleted_at IS NULL')
                              ->orderBy('id', 'DESC')
                              ->get()
                              ->getRowArray();
            
            if ($payment) {
                log_message('info', "🔍 DB Query: order_id={$orderId}, found=YES, status={$payment['status']}, amount={$payment['amount']}");
            } else {
                log_message('info', "🔍 DB Query: order_id={$orderId}, found=NO");
                
                // Buscar también en instalments si el pago es por cuotas
                $instalmentBuilder = $db->table('instalments i')
                    ->select('p.id, p.uuid, p.amount, p.status, p.payment_method, p.reference_code, p.created_at, p.updated_at')
                    ->join('payments p', 'p.instalment_id = i.id', 'left')
                    ->where('i.order_id', $orderId)
                    ->where('p.deleted_at IS NULL')
                    ->orderBy('p.id', 'DESC');
                
                $payment = $instalmentBuilder->get()->getRowArray();
                
                if ($payment) {
                    log_message('info', "🔍 DB Query INSTALMENT: order_id={$orderId}, found=YES, status={$payment['status']}, amount={$payment['amount']}");
                } else {
                    log_message('info', "🔍 DB Query INSTALMENT: order_id={$orderId}, found=NO");
                }
            }
            
        } catch (\Exception $e) {
            log_message('error', "❌ Error en checkPaymentStatus: " . $e->getMessage());
            return null;
        }
        
        return $payment;
    }
}
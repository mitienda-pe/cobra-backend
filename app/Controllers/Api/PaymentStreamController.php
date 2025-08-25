<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;

class PaymentStreamController extends Controller
{

    /**
     * SSE endpoint for real-time payment notifications
     * Usage: /api/payments/stream/{qr_id}
     */
    public function stream($qrId = null)
    {
        if (!$qrId) {
            return $this->fail('QR ID is required', 400);
        }

        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable Nginx buffering
        
        // Prevent timeout
        set_time_limit(0);
        ignore_user_abort(false);
        
        // Initialize cache safely
        try {
            $cache = \Config\Services::cache();
        } catch (\Exception $e) {
            log_message('error', "🔴 [SSE] Cache service error: " . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['error' => 'Cache service unavailable']);
        }
        
        $maxTime = 30; // 30 seconds max connection for testing
        $startTime = time();
        
        log_message('info', "🔴 [SSE] Client connected for QR: $qrId");
        
        // Send initial connection confirmation
        $this->sendSSEEvent('connected', ['qr_id' => $qrId, 'message' => 'Listening for payment...']);
        
        while (time() - $startTime < $maxTime) {
            // Check if client disconnected
            if (connection_aborted()) {
                log_message('info', "🔴 [SSE] Client disconnected for QR: $qrId");
                break;
            }
            
            // Check for payment update in cache
            $paymentEvent = $cache->get("payment_event_$qrId");
            
            if ($paymentEvent) {
                log_message('info', "✅ [SSE] Payment event found for QR: $qrId");
                
                // Send payment success event
                $this->sendSSEEvent('payment_success', $paymentEvent);
                
                // Clean up cache
                $cache->delete("payment_event_$qrId");
                
                // Send close event and exit
                $this->sendSSEEvent('close', ['message' => 'Payment completed successfully']);
                break;
            }
            
            // Send heartbeat every 10 seconds to keep connection alive
            if (time() % 10 == 0) {
                $this->sendSSEEvent('heartbeat', ['timestamp' => time()]);
            }
            
            // Sleep for 1 second before next check
            sleep(1);
        }
        
        log_message('info', "🔴 [SSE] Connection closed for QR: $qrId");
        exit();
    }
    
    /**
     * Send SSE formatted event
     */
    private function sendSSEEvent($event, $data)
    {
        echo "event: $event\n";
        echo "data: " . json_encode($data) . "\n\n";
        
        // Force immediate output
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    /**
     * Test connection endpoint
     */
    public function testConnection()
    {
        return $this->response->setJSON([
            'success' => true,
            'message' => 'SSE Controller is accessible',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Check for cached payment event (for polling)
     */
    public function checkPayment($qrId = null)
    {
        if (!$qrId) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'QR ID is required'
            ]);
        }
        
        try {
            $cache = \Config\Services::cache();
            
            // Buscar primero por QR ID directo
            $paymentEvent = $cache->get("payment_event_$qrId");
            
            // Si no se encuentra y parece un order_id (UUID), buscar por order_id también
            if (!$paymentEvent && preg_match('/^[0-9a-f-]{36}$/i', $qrId)) {
                log_message('info', "🔍 [POLLING] QR ID looks like order_id, searching in database: $qrId");
                
                // Buscar en la base de datos el id_qr real para este order_id
                $db = \Config\Database::connect();
                $hashRecord = $db->table('ligo_qr_hashes')
                              ->where('order_id', $qrId)
                              ->orderBy('id', 'DESC')
                              ->get()
                              ->getRowArray();
                
                if ($hashRecord && $hashRecord['id_qr']) {
                    $realQrId = $hashRecord['id_qr'];
                    log_message('info', "✅ [POLLING] Found real QR ID: $realQrId for order_id: $qrId");
                    
                    // Buscar el evento con el QR ID real
                    $paymentEvent = $cache->get("payment_event_$realQrId");
                    
                    if ($paymentEvent) {
                        log_message('info', "✅ [POLLING] Payment event found with real QR ID: $realQrId");
                        $cache->delete("payment_event_$realQrId");
                    }
                }
            }
            
            if ($paymentEvent) {
                log_message('info', "✅ [POLLING] Payment event found for identifier: $qrId");
                
                return $this->response->setJSON([
                    'success' => true,
                    'payment_found' => true,
                    'payment_data' => $paymentEvent
                ]);
            } else {
                return $this->response->setJSON([
                    'success' => true,
                    'payment_found' => false,
                    'message' => 'No payment event found'
                ]);
            }
        } catch (\Exception $e) {
            log_message('error', "❌ [POLLING] Error checking payment: " . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Error checking payment: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Simple stream test without cache dependency
     */
    public function testStream($qrId = null)
    {
        if (!$qrId) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'QR ID is required'
            ]);
        }
        
        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        
        // Send immediate test response and close
        echo "event: connected\n";
        echo "data: " . json_encode(['qr_id' => $qrId, 'message' => 'Test stream working']) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Close immediately
        echo "event: close\n";
        echo "data: " . json_encode(['message' => 'Test completed']) . "\n\n";
        
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        exit();
    }
    
    /**
     * Test endpoint to simulate payment completion
     * Usage: POST /api/payments/test-event/{qr_id}
     */
    public function testEvent($qrId = null)
    {
        if (!$qrId) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'QR ID is required'
            ]);
        }
        
        $cache = \Config\Services::cache();
        
        $testPaymentEvent = [
            'qr_id' => $qrId,
            'status' => 'completed',
            'amount' => 100.50,
            'currency' => 'PEN',
            'payment_date' => date('Y-m-d H:i:s'),
            'instruction_id' => 'TEST_' . time(),
            'message' => 'Payment completed successfully!'
        ];
        
        // Store event in cache for SSE to pick up
        $cache->save("payment_event_$qrId", $testPaymentEvent, 300); // 5 minutes TTL
        
        log_message('info', "🧪 [SSE] Test payment event created for QR: $qrId");
        
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Test payment event created',
            'qr_id' => $qrId,
            'event_data' => $testPaymentEvent
        ]);
    }
}
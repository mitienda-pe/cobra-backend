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
        
        $cache = \Config\Services::cache();
        $maxTime = 300; // 5 minutes max connection
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
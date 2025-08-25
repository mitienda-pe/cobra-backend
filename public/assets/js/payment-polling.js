/**
 * Payment Real-time Notifications with Polling (fallback for SSE issues)
 * 
 * Usage:
 * const notifications = new PaymentPolling();
 * notifications.listenForPayment('QR_ID_HERE', {
 *   onPaymentSuccess: (data) => console.log('Payment received!', data),
 *   onError: (error) => console.error('Error:', error),
 *   onConnectionEnd: () => console.log('Polling stopped')
 * });
 */
class PaymentPolling {
    constructor() {
        this.intervalId = null;
        this.isListening = false;
        this.qrId = null;
        this.callbacks = {};
        this.pollCount = 0;
        this.maxPolls = 100; // 5 minutes at 3-second intervals
    }

    /**
     * Start polling for payment notifications for a specific QR code
     */
    listenForPayment(qrId, callbacks = {}) {
        if (this.isListening) {
            console.warn('Already listening for payments. Stop current listener first.');
            return;
        }

        this.qrId = qrId;
        this.callbacks = callbacks;
        this.isListening = true;
        this.pollCount = 0;

        console.log('🔄 Starting payment polling for QR:', qrId);
        
        if (this.callbacks.onConnected) {
            this.callbacks.onConnected({qr_id: qrId, message: 'Polling started'});
        }

        // Start polling every 3 seconds
        this.intervalId = setInterval(() => {
            this.checkForPayment();
        }, 3000);

        // Initial check immediately
        this.checkForPayment();

        return this;
    }

    /**
     * Check for payment using simple AJAX request
     */
    checkForPayment() {
        if (!this.isListening) return;
        
        this.pollCount++;
        
        // Stop polling after max attempts
        if (this.pollCount > this.maxPolls) {
            console.log('🕐 Max polling time reached');
            this.stopListening();
            if (this.callbacks.onConnectionEnd) {
                this.callbacks.onConnectionEnd({message: 'Polling timeout reached'});
            }
            return;
        }

        // Simple check: try to get cached payment event
        fetch(`/sse/check-payment/${this.qrId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.payment_found) {
                    console.log('🎉 Payment found via polling!', data.payment_data);
                    
                    if (this.callbacks.onPaymentSuccess) {
                        this.callbacks.onPaymentSuccess(data.payment_data);
                    }
                    
                    this.stopListening();
                    
                    if (this.callbacks.onConnectionEnd) {
                        this.callbacks.onConnectionEnd({message: 'Payment completed successfully'});
                    }
                } else {
                    // Send heartbeat every 10th poll (30 seconds)
                    if (this.pollCount % 10 === 0 && this.callbacks.onHeartbeat) {
                        this.callbacks.onHeartbeat({
                            timestamp: Date.now(),
                            poll_count: this.pollCount
                        });
                    }
                }
            })
            .catch(error => {
                console.error('❌ Polling error:', error);
                if (this.callbacks.onError) {
                    this.callbacks.onError(error);
                }
            });
    }

    /**
     * Stop polling for payment notifications
     */
    stopListening() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
        
        this.isListening = false;
        this.qrId = null;
        this.callbacks = {};
        this.pollCount = 0;
        console.log('🛑 Payment polling stopped');
    }

    /**
     * Check if currently listening for payments
     */
    isActive() {
        return this.isListening && this.intervalId !== null;
    }

    /**
     * Get current QR ID being listened to
     */
    getCurrentQrId() {
        return this.qrId;
    }
}

// Global instance for easy access
window.PaymentPolling = PaymentPolling;
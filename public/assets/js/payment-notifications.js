/**
 * Payment Real-time Notifications with Server-Sent Events (SSE)
 * 
 * Usage:
 * const notifications = new PaymentNotifications();
 * notifications.listenForPayment('QR_ID_HERE', {
 *   onPaymentSuccess: (data) => console.log('Payment received!', data),
 *   onError: (error) => console.error('Error:', error),
 *   onConnectionEnd: () => console.log('Connection closed')
 * });
 */
class PaymentNotifications {
    constructor() {
        this.eventSource = null;
        this.isListening = false;
        this.qrId = null;
        this.callbacks = {};
    }

    /**
     * Start listening for payment notifications for a specific QR code
     * @param {string} qrId - The QR code ID to listen for
     * @param {Object} callbacks - Callback functions
     * @param {Function} callbacks.onPaymentSuccess - Called when payment is successful
     * @param {Function} callbacks.onError - Called on error
     * @param {Function} callbacks.onConnectionEnd - Called when connection closes
     * @param {Function} callbacks.onHeartbeat - Called on heartbeat (optional)
     * @param {Function} callbacks.onConnected - Called when initially connected (optional)
     */
    listenForPayment(qrId, callbacks = {}) {
        if (this.isListening) {
            console.warn('Already listening for payments. Stop current listener first.');
            return;
        }

        this.qrId = qrId;
        this.callbacks = callbacks;
        this.isListening = true;

        // Create EventSource connection
        const streamUrl = `/sse/stream/${qrId}`;
        this.eventSource = new EventSource(streamUrl);

        // Handle connection opened
        this.eventSource.onopen = (event) => {
            console.log('🔗 Payment notification connection established for QR:', qrId);
        };

        // Handle connected event
        this.eventSource.addEventListener('connected', (event) => {
            const data = JSON.parse(event.data);
            console.log('✅ Connected to payment stream:', data);
            if (this.callbacks.onConnected) {
                this.callbacks.onConnected(data);
            }
        });

        // Handle payment success event
        this.eventSource.addEventListener('payment_success', (event) => {
            const paymentData = JSON.parse(event.data);
            console.log('🎉 Payment received!', paymentData);
            
            if (this.callbacks.onPaymentSuccess) {
                this.callbacks.onPaymentSuccess(paymentData);
            }
        });

        // Handle heartbeat event (optional)
        this.eventSource.addEventListener('heartbeat', (event) => {
            const data = JSON.parse(event.data);
            console.log('💓 Heartbeat:', data.timestamp);
            
            if (this.callbacks.onHeartbeat) {
                this.callbacks.onHeartbeat(data);
            }
        });

        // Handle close event
        this.eventSource.addEventListener('close', (event) => {
            const data = JSON.parse(event.data);
            console.log('🔚 Connection closing:', data.message);
            
            this.stopListening();
            
            if (this.callbacks.onConnectionEnd) {
                this.callbacks.onConnectionEnd(data);
            }
        });

        // Handle connection errors
        this.eventSource.onerror = (event) => {
            console.error('❌ Payment notification error:', event);
            
            if (this.callbacks.onError) {
                this.callbacks.onError(event);
            }
            
            // Auto-reconnect after error (optional)
            if (this.isListening && this.eventSource.readyState === EventSource.CLOSED) {
                console.log('🔄 Connection lost. Will retry...');
                setTimeout(() => {
                    if (this.isListening) {
                        this.listenForPayment(this.qrId, this.callbacks);
                    }
                }, 5000); // Retry after 5 seconds
            }
        };

        return this;
    }

    /**
     * Stop listening for payment notifications
     */
    stopListening() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        
        this.isListening = false;
        this.qrId = null;
        this.callbacks = {};
        console.log('🛑 Payment notification listener stopped');
    }

    /**
     * Check if currently listening for payments
     * @returns {boolean}
     */
    isActive() {
        return this.isListening && this.eventSource && this.eventSource.readyState === EventSource.OPEN;
    }

    /**
     * Get current QR ID being listened to
     * @returns {string|null}
     */
    getCurrentQrId() {
        return this.qrId;
    }
}

// Global instance for easy access
window.PaymentNotifications = PaymentNotifications;

// Example usage with jQuery (if available)
if (typeof $ !== 'undefined') {
    /**
     * jQuery plugin for easy integration
     * Usage: $('#qr-container').listenForPayment('QR_ID', { ... });
     */
    $.fn.listenForPayment = function(qrId, callbacks = {}) {
        const notifications = new PaymentNotifications();
        
        // Store instance in element data for later access
        this.data('paymentNotifications', notifications);
        
        // Start listening
        return notifications.listenForPayment(qrId, callbacks);
    };

    /**
     * Stop listening for payment
     * Usage: $('#qr-container').stopPaymentListener();
     */
    $.fn.stopPaymentListener = function() {
        const notifications = this.data('paymentNotifications');
        if (notifications) {
            notifications.stopListening();
            this.removeData('paymentNotifications');
        }
        return this;
    };
}
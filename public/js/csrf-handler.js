/**
 * CSRF Handler for AJAX requests
 * 
 * This script automatically adds the CSRF token to all AJAX requests
 * and provides a way to bypass CSRF for specific routes.
 */
(function() {
    // Get CSRF token and name from the page
    const getCSRFToken = function() {
        const tokenInput = document.querySelector('input[name^="csrf_"]');
        if (!tokenInput) {
            console.warn('CSRF token not found on page');
            return { name: null, value: null };
        }
        
        return {
            name: tokenInput.name,
            value: tokenInput.value
        };
    };
    
    // Add CSRF to FormData
    const addCSRFToFormData = function(formData) {
        const token = getCSRFToken();
        if (token.name && token.value) {
            formData.append(token.name, token.value);
        }
        return formData;
    };
    
    // Routes that should bypass CSRF
    const bypassRoutes = [
        '/clients/import',
        '/invoices/import'
    ];
    
    // Check if the URL should bypass CSRF
    const shouldBypassCSRF = function(url) {
        for (const route of bypassRoutes) {
            if (url.includes(route)) {
                console.log('Bypassing CSRF for route:', url);
                return true;
            }
        }
        return false;
    };
    
    // Override the fetch function to add CSRF token
    const originalFetch = window.fetch;
    window.fetch = function(resource, options = {}) {
        const url = resource.url || resource;
        if (shouldBypassCSRF(url)) {
            console.log('CSRF bypass applied for:', url);
            return originalFetch(resource, options);
        }
        
        options = options || {};
        
        // Handle different request types
        if (options.body instanceof FormData) {
            options.body = addCSRFToFormData(options.body);
        } else if (options.method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(options.method.toUpperCase())) {
            options.headers = options.headers || {};
            const token = getCSRFToken();
            
            if (token.name && token.value) {
                options.headers['X-CSRF-TOKEN'] = token.value;
                
                // If sending JSON, we need to include the token in the body
                if (options.headers['Content-Type'] === 'application/json') {
                    try {
                        let body = {};
                        if (options.body) {
                            body = JSON.parse(options.body);
                        }
                        body[token.name] = token.value;
                        options.body = JSON.stringify(body);
                    } catch (e) {
                        console.error('Error adding CSRF token to JSON body:', e);
                    }
                }
            }
        }
        
        return originalFetch(resource, options);
    };
    
    // Override XMLHttpRequest to add CSRF token
    const originalXHROpen = XMLHttpRequest.prototype.open;
    const originalXHRSend = XMLHttpRequest.prototype.send;
    
    XMLHttpRequest.prototype.open = function(method, url, ...args) {
        this._csrfMethod = method;
        this._csrfUrl = url;
        this._csrfBypass = shouldBypassCSRF(url);
        return originalXHROpen.apply(this, [method, url, ...args]);
    };
    
    XMLHttpRequest.prototype.send = function(data) {
        if (this._csrfBypass) {
            console.log('CSRF bypass applied for XHR:', this._csrfUrl);
            return originalXHRSend.call(this, data);
        }
        
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._csrfMethod.toUpperCase())) {
            const token = getCSRFToken();
            
            if (token.name && token.value) {
                this.setRequestHeader('X-CSRF-TOKEN', token.value);
                
                // Add token to form data
                if (data instanceof FormData) {
                    data.append(token.name, token.value);
                } else if (typeof data === 'string' && data.charAt(0) === '{') {
                    // Attempt to handle JSON string
                    try {
                        const json = JSON.parse(data);
                        json[token.name] = token.value;
                        data = JSON.stringify(json);
                    } catch (e) {
                        console.error('Error adding CSRF token to JSON string:', e);
                    }
                }
            }
        }
        
        return originalXHRSend.call(this, data);
    };
    
    // Log CSRF status on page load
    document.addEventListener('DOMContentLoaded', function() {
        const token = getCSRFToken();
        console.log('CSRF Handler initialized. Token found:', !!token.value);
    });
})();
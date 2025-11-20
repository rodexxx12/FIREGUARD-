/**
 * Building Registration Security Enhancement
 * Adds CSRF protection and input validation to building registration forms
 */

class BuildingSecurity {
    constructor() {
        this.csrfToken = null;
        this.rateLimitInfo = null;
        this.init();
    }
    
    async init() {
        await this.loadCSRFToken();
        this.setupFormSecurity();
        this.setupInputValidation();
        this.setupRateLimitHandling();
    }
    
    /**
     * Load CSRF token from server
     */
    async loadCSRFToken() {
        try {
            const response = await fetch('secure_main.php?action=csrf_token', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.csrfToken = data.csrf_token;
                
                // Add CSRF token to all forms
                this.addCSRFTokenToForms();
            }
        } catch (error) {
            console.error('Failed to load CSRF token:', error);
        }
    }
    
    /**
     * Add CSRF token to all forms
     */
    addCSRFTokenToForms() {
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            // Check if CSRF token input already exists
            if (!form.querySelector('input[name="csrf_token"]')) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = this.csrfToken;
                form.appendChild(csrfInput);
            }
        });
    }
    
    /**
     * Setup form security enhancements
     */
    setupFormSecurity() {
        // Intercept form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.tagName === 'FORM') {
                this.handleFormSubmission(e);
            }
        });
        
        // Intercept AJAX requests
        this.interceptAjaxRequests();
    }
    
    /**
     * Handle form submission with security checks
     */
    handleFormSubmission(event) {
        const form = event.target;
        
        // Add CSRF token if missing
        if (!form.querySelector('input[name="csrf_token"]') && this.csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = this.csrfToken;
            form.appendChild(csrfInput);
        }
        
        // Validate form data
        if (!this.validateFormData(form)) {
            event.preventDefault();
            return false;
        }
        
        // Add rate limiting check
        if (!this.checkRateLimit()) {
            event.preventDefault();
            this.showRateLimitMessage();
            return false;
        }
        
        // Log form submission attempt
        this.logSecurityEvent('FORM_SUBMISSION_ATTEMPT', form.action);
    }
    
    /**
     * Intercept AJAX requests to add security headers
     */
    interceptAjaxRequests() {
        const originalFetch = window.fetch;
        const originalXHR = XMLHttpRequest.prototype.open;
        
        // Intercept fetch requests
        window.fetch = async (url, options = {}) => {
            if (this.isBuildingRelatedRequest(url)) {
                options = this.addSecurityHeaders(options);
            }
            return originalFetch(url, options);
        };
        
        // Intercept XMLHttpRequest
        XMLHttpRequest.prototype.open = function(method, url, ...args) {
            if (this.isBuildingRelatedRequest && this.isBuildingRelatedRequest(url)) {
                this.addSecurityHeaders = this.addSecurityHeaders.bind(this);
            }
            return originalXHR.apply(this, [method, url, ...args]);
        };
    }
    
    /**
     * Check if request is building-related
     */
    isBuildingRelatedRequest(url) {
        return url.includes('main.php') || 
               url.includes('secure_main.php') || 
               url.includes('building') ||
               url.includes('get_barangays.php') ||
               url.includes('get_geo_fences.php');
    }
    
    /**
     * Add security headers to requests
     */
    addSecurityHeaders(options = {}) {
        options.headers = options.headers || {};
        
        // Add CSRF token
        if (this.csrfToken) {
            if (options.method === 'POST' || options.method === 'PUT' || options.method === 'DELETE') {
                if (options.body instanceof FormData) {
                    options.body.append('csrf_token', this.csrfToken);
                } else if (typeof options.body === 'string') {
                    try {
                        const data = JSON.parse(options.body);
                        data.csrf_token = this.csrfToken;
                        options.body = JSON.stringify(data);
                    } catch (e) {
                        // If not JSON, add as form data
                        const formData = new FormData();
                        formData.append('csrf_token', this.csrfToken);
                        formData.append('data', options.body);
                        options.body = formData;
                    }
                }
            }
        }
        
        // Add security headers
        options.headers['X-Requested-With'] = 'XMLHttpRequest';
        options.headers['X-Content-Type-Options'] = 'nosniff';
        
        return options;
    }
    
    /**
     * Validate form data before submission
     */
    validateFormData(form) {
        const inputs = form.querySelectorAll('input, textarea, select');
        let isValid = true;
        
        inputs.forEach(input => {
            if (!this.validateInput(input)) {
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    /**
     * Validate individual input
     */
    validateInput(input) {
        const value = input.value.trim();
        const type = input.type;
        const name = input.name;
        
        // Required field validation
        if (input.required && !value) {
            this.showFieldError(input, 'This field is required');
            return false;
        }
        
        // Type-specific validation
        switch (type) {
            case 'email':
                if (value && !this.isValidEmail(value)) {
                    this.showFieldError(input, 'Invalid email format');
                    return false;
                }
                break;
                
            case 'tel':
                if (value && !this.isValidPhone(value)) {
                    this.showFieldError(input, 'Invalid phone number format');
                    return false;
                }
                break;
                
            case 'number':
                if (value && !this.isValidNumber(value, input)) {
                    this.showFieldError(input, 'Invalid number format');
                    return false;
                }
                break;
                
            case 'text':
                if (this.containsSQLInjection(value)) {
                    this.showFieldError(input, 'Invalid characters detected');
                    return false;
                }
                break;
        }
        
        // Name-specific validation
        switch (name) {
            case 'building_name':
                if (value && value.length > 255) {
                    this.showFieldError(input, 'Building name too long (max 255 characters)');
                    return false;
                }
                break;
                
            case 'total_floors':
                if (value && (value < 1 || value > 200)) {
                    this.showFieldError(input, 'Number of floors must be between 1 and 200');
                    return false;
                }
                break;
                
            case 'construction_year':
                const currentYear = new Date().getFullYear();
                if (value && (value < 1800 || value > currentYear)) {
                    this.showFieldError(input, `Construction year must be between 1800 and ${currentYear}`);
                    return false;
                }
                break;
                
            case 'building_area':
                if (value && (value < 0 || value > 999999.99)) {
                    this.showFieldError(input, 'Building area must be between 0 and 999,999.99');
                    return false;
                }
                break;
        }
        
        this.clearFieldError(input);
        return true;
    }
    
    /**
     * Check for SQL injection patterns
     */
    containsSQLInjection(value) {
        const dangerousPatterns = [
            /(\b(union|select|insert|update|delete|drop|create|alter)\b)/i,
            /[;\'"]/,
            /(\bor\b|\band\b)\s+\d+\s*=\s*\d+/i,
            /(\bscript\b|\bjavascript\b|\bvbscript\b)/i,
            /(\bonload\b|\bonerror\b|\bonclick\b)/i
        ];
        
        return dangerousPatterns.some(pattern => pattern.test(value));
    }
    
    /**
     * Validate email format
     */
    isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * Validate phone number format
     */
    isValidPhone(phone) {
        const phoneRegex = /^[0-9+\-\(\)\s]+$/;
        return phoneRegex.test(phone) && phone.length <= 20;
    }
    
    /**
     * Validate number input
     */
    isValidNumber(value, input) {
        const num = parseFloat(value);
        const min = parseFloat(input.min);
        const max = parseFloat(input.max);
        
        if (isNaN(num)) return false;
        if (!isNaN(min) && num < min) return false;
        if (!isNaN(max) && num > max) return false;
        
        return true;
    }
    
    /**
     * Show field error
     */
    showFieldError(input, message) {
        this.clearFieldError(input);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'field-error text-danger small mt-1';
        errorDiv.textContent = message;
        
        input.classList.add('is-invalid');
        input.parentNode.appendChild(errorDiv);
    }
    
    /**
     * Clear field error
     */
    clearFieldError(input) {
        input.classList.remove('is-invalid');
        const errorDiv = input.parentNode.querySelector('.field-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }
    
    /**
     * Setup input validation
     */
    setupInputValidation() {
        // Real-time validation on input
        document.addEventListener('input', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                this.validateInput(e.target);
            }
        });
        
        // Clear errors on focus
        document.addEventListener('focus', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                this.clearFieldError(e.target);
            }
        }, true);
    }
    
    /**
     * Setup rate limiting handling
     */
    setupRateLimitHandling() {
        // Check for rate limit info in localStorage
        const rateLimitInfo = localStorage.getItem('building_rate_limit');
        if (rateLimitInfo) {
            this.rateLimitInfo = JSON.parse(rateLimitInfo);
        }
    }
    
    /**
     * Check rate limit
     */
    checkRateLimit() {
        if (!this.rateLimitInfo) return true;
        
        const now = Date.now();
        const timeDiff = now - this.rateLimitInfo.lastAttempt;
        
        if (timeDiff < this.rateLimitInfo.timeWindow) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Show rate limit message
     */
    showRateLimitMessage() {
        const message = 'Too many requests. Please wait before trying again.';
        this.showNotification(message, 'warning');
    }
    
    /**
     * Log security event
     */
    logSecurityEvent(event, details) {
        console.log(`Security Event: ${event} - ${details}`);
        
        // Send to server for logging
        fetch('secure_main.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'log_security_event',
                event: event,
                details: details,
                csrf_token: this.csrfToken
            })
        }).catch(error => {
            console.error('Failed to log security event:', error);
        });
    }
    
    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
    
    /**
     * Handle AJAX errors with security context
     */
    handleAjaxError(xhr, status, error) {
        if (xhr.status === 403) {
            this.showNotification('Security violation detected. Please refresh the page.', 'danger');
            // Refresh CSRF token
            this.loadCSRFToken();
        } else if (xhr.status === 429) {
            this.showNotification('Rate limit exceeded. Please wait before trying again.', 'warning');
        } else if (xhr.status === 400) {
            const response = JSON.parse(xhr.responseText);
            this.showNotification(response.message || 'Validation error', 'warning');
        }
    }
}

// Initialize security when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.buildingSecurity = new BuildingSecurity();
});

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BuildingSecurity;
}

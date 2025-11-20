// Login Security and Back Button Prevention
(function() {
    'use strict';
    
    // Check if user is logged in (you can modify this based on your session management)
    function isLoggedIn() {
        // Check for session indicators in the page
        return document.body.classList.contains('logged-in') || 
               document.querySelector('[data-user-logged-in]') ||
               window.location.pathname.includes('/dashboard') ||
               window.location.pathname.includes('/admin') ||
               window.location.pathname.includes('/firefighter') ||
               window.location.pathname.includes('/superadmin');
    }
    
    // Clear form data and prevent resubmission
    function clearLoginForm() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            // Clear all form fields
            loginForm.reset();
            
            // Clear any cached form data
            const inputs = loginForm.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => {
                input.value = '';
                input.setAttribute('autocomplete', 'off');
            });
            
            // Remove any stored form data
            if (window.sessionStorage) {
                sessionStorage.removeItem('loginFormData');
            }
            if (window.localStorage) {
                localStorage.removeItem('loginFormData');
            }
        }
    }
    
    // Prevent back button functionality
    function preventBackButton() {
        // Method 1: Replace current history entry
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // Method 2: Add a new history entry to prevent going back
        if (window.history && window.history.pushState) {
            window.history.pushState(null, null, window.location.href);
        }
        
        // Method 3: Listen for popstate events and prevent back navigation
        window.addEventListener('popstate', function(event) {
            // Force forward navigation
            window.history.forward();
            
            // Or redirect to current page
            // window.location.href = window.location.href;
        });
        
        // Method 4: Disable browser back button (more aggressive)
        window.addEventListener('beforeunload', function(event) {
            // Clear form data before leaving
            clearLoginForm();
        });
    }
    
    // Enhanced form submission handler
    function handleSecureLoginSubmission() {
        const loginForm = document.getElementById('loginForm');
        if (!loginForm) return;
        
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            // Disable form to prevent double submission
            this.style.pointerEvents = 'none';
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner"></span> Verifying...';
            
            // Show loading alert
            const alert = Swal.fire({
                title: 'Verifying credentials...',
                html: 'Please wait...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
            
            // Submit form data
            fetch('', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                alert.close();
                
                if (result.success) {
                    // Clear form data immediately
                    clearLoginForm();
                    
                    // Show success message
                    Swal.fire({
                        title: 'Login Successful!',
                        text: 'Redirecting...',
                        icon: 'success',
                        timer: 1500,
                        showConfirmButton: false,
                        timerProgressBar: true
                    }).then(() => {
                        // Apply back button prevention before redirect
                        preventBackButton();
                        
                        // Redirect to dashboard
                        if (result.redirect) {
                            // Use replace instead of href to prevent back navigation
                            window.location.replace(result.redirect);
                        }
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        title: 'Login Failed',
                        text: result.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Clear form data on error too
                        clearLoginForm();
                    });
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                alert.close();
                
                Swal.fire({
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                }).then(() => {
                    clearLoginForm();
                });
            })
            .finally(() => {
                // Re-enable form
                loginForm.style.pointerEvents = 'auto';
                submitButton.disabled = false;
                submitButton.innerHTML = originalText;
            });
        });
    }
    
    // Clear browser cache and prevent caching
    function clearBrowserCache() {
        // Clear any cached data
        if (window.caches) {
            caches.keys().then(names => {
                names.forEach(name => {
                    caches.delete(name);
                });
            });
        }
        
        // Clear session storage (preserve captcha verification)
        if (window.sessionStorage) {
            const captchaVerified = sessionStorage.getItem('captchaVerified');
            sessionStorage.clear();
            if (captchaVerified) {
                sessionStorage.setItem('captchaVerified', captchaVerified);
            }
        }
        
        // Clear specific localStorage items
        if (window.localStorage) {
            localStorage.removeItem('loginFormData');
            localStorage.removeItem('userCredentials');
            localStorage.removeItem('rememberMe');
        }
    }
    
    // Disable form autocomplete and caching
    function disableFormCaching() {
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            // Disable autocomplete
            loginForm.setAttribute('autocomplete', 'off');
            
            // Disable form caching
            loginForm.setAttribute('data-form-cache', 'disabled');
            
            // Clear inputs on page load
            const inputs = loginForm.querySelectorAll('input');
            inputs.forEach(input => {
                input.setAttribute('autocomplete', 'off');
                input.setAttribute('data-cache', 'disabled');
                
                // Clear any cached values
                if (input.type === 'text' || input.type === 'password') {
                    input.value = '';
                }
            });
        }
    }
    
    // Initialize security measures
    function initializeLoginSecurity() {
        // Clear browser cache
        clearBrowserCache();
        
        // Disable form caching
        disableFormCaching();
        
        // Clear form data
        clearLoginForm();
        
        // Set up secure form submission
        handleSecureLoginSubmission();
        
        // If user is already logged in, prevent back button
        if (isLoggedIn()) {
            preventBackButton();
        }
        
        // Additional security: Clear form data on page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                clearLoginForm();
            }
        });
        
        // Clear form data when page is about to be unloaded
        window.addEventListener('beforeunload', function() {
            clearLoginForm();
        });
        
        // Prevent right-click context menu on login form
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
            
            // Prevent text selection on form
            loginForm.style.userSelect = 'none';
            loginForm.style.webkitUserSelect = 'none';
            loginForm.style.mozUserSelect = 'none';
            loginForm.style.msUserSelect = 'none';
        }
    }
    
    // Run when DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeLoginSecurity);
    } else {
        initializeLoginSecurity();
    }
    
    // Export functions for global access if needed
    window.loginSecurity = {
        clearForm: clearLoginForm,
        preventBack: preventBackButton,
        clearCache: clearBrowserCache
    };
    
})();

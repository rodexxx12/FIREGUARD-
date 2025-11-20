<!-- Login Overlay -->
<div class="login-overlay">
    <div class="login-container" id="loginContainer">
        <form id="loginForm" method="post" action="" autocomplete="off" data-form-cache="disabled">
            <input type="hidden" name="action" value="login">
            <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
            <h2>FIREGUARD</h2>
            
            <div class="input-group">
                <input type="text" id="username" name="username" required autocomplete="off" data-cache="disabled" placeholder="Username">
            </div>

            <div class="input-group">
                <input type="password" id="password" name="password" required autocomplete="off" data-cache="disabled" placeholder="Password">
                <span class="password-toggle" id="togglePassword">👁️</span>
            </div>

            <div class="options">
                <label for="remember">
                    <input type="checkbox" id="remember" name="remember">
                    Remember me
                </label>
                <a href="#" id="forgotPasswordLink">Forgot password?</a>
            </div>

            <div class="form-feedback-wrapper">
                <div class="alert alert-dismissible fade form-feedback-alert d-none" role="alert" data-feedback="container">
                    <div class="d-flex align-items-start gap-2">
                        <span class="form-feedback-icon flex-shrink-0" data-feedback="icon"></span>
                        <div class="form-feedback-message flex-grow-1" data-feedback="message"></div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" aria-label="Close"></button>
                </div>
            </div>

            <button type="submit" class="login-btn" id="loginButton">
                <span id="loginButtonText">LOGIN</span>
            </button>

            <div class="register-link">
                Don't have an account? <a href="../../reg/registration.php">Register</a>
            </div>
        </form>
    </div>
</div>

<!-- Login Toggle Button -->
<div class="login-toggle" id="loginToggle">
    <i class="fas fa-sign-in-alt"></i>
    <span>Login</span>
</div>

<!-- Bootstrap Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="feedbackModalTitle">Success</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="fs-2" id="feedbackModalIcon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p class="mb-0" id="feedbackModalBody">Your action was successful.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal" id="feedbackModalCloseBtn">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// SMART SECURITY SCRIPT - Only clears on back button, allows normal typing
(function() {
    'use strict';
    
    let isUserTyping = false;
    let typingTimeout;
    
    // Clear form only when user goes back
    function clearFormOnBack() {
        const form = document.getElementById('loginForm');
        if (form && !isUserTyping) {
            form.reset();
            const inputs = form.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => {
                input.value = '';
                input.setAttribute('autocomplete', 'off');
            });
        }
    }
    
    // Detect when user is typing
    function detectTyping() {
        isUserTyping = true;
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isUserTyping = false;
        }, 2000); // Consider user stopped typing after 2 seconds
    }
    
    // Prevent back button and clear form only on back navigation
    function preventBackButton() {
        if (window.history) {
            window.history.replaceState(null, null, window.location.href);
            window.history.pushState(null, null, window.location.href);
            
            window.addEventListener('popstate', function(e) {
                // Clear form when user tries to go back
                clearFormOnBack();
                // Force forward navigation
                window.history.forward();
            });
        }
    }
    
    // Add typing detection to form inputs
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('loginForm');
        if (form) {
            const inputs = form.querySelectorAll('input[type="text"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('input', detectTyping);
                input.addEventListener('keydown', detectTyping);
                input.addEventListener('keyup', detectTyping);
            });
        }
    });
    
    // Initialize back button prevention
    preventBackButton();
    
})();
</script> 
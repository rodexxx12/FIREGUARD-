<?php
// Resolve the appropriate reCAPTCHA site key based on current host
$host = $_SERVER['HTTP_HOST'] ?? '';
$hostOnly = $host ? explode(':', $host)[0] : '';
$recaptchaConfigPath = __DIR__ . '/../../functions/recaptcha_config.php';
$recaptchaConfig = is_file($recaptchaConfigPath) ? include $recaptchaConfigPath : null;
$siteKey = null;
if (is_array($recaptchaConfig)) {
    // Try full host first (with port), then host without port
    if (!empty($recaptchaConfig['domains'][$host]['site_key'])) {
        $siteKey = $recaptchaConfig['domains'][$host]['site_key'];
    } elseif (!empty($recaptchaConfig['domains'][$hostOnly]['site_key'])) {
        $siteKey = $recaptchaConfig['domains'][$hostOnly]['site_key'];
    } elseif (!empty($recaptchaConfig['default']['site_key'])) {
        $siteKey = $recaptchaConfig['default']['site_key'];
    }
}
// Final fallback to prevent empty attribute (avoids rendering errors)
if (!$siteKey) {
    $siteKey = '6LcueRIsAAAAANGY2K15h5wZbFSyOX9-LAsdaea8';
}
?>
<!-- Bootstrap Captcha Verification Modal -->
<div class="modal fade" id="captchaModal" tabindex="-1" aria-labelledby="captchaModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background: white; border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
            <div class="modal-header" style="border-bottom: 1px solid #e9ecef; background: white;">
                <div class="text-center w-100">
                    <i class="fas fa-shield-alt mb-3" style="font-size: 42px; color: #198754;"></i>
                    <h3 class="modal-title" id="captchaModalLabel" style="color: #212529; font-weight: 500;">Security Verification</h3>
                    <p class="mb-0 mt-2" style="color: #6c757d; font-size: 14px;">Please verify that you are not a robot</p>
                </div>
            </div>
            <div class="modal-body text-center" style="padding: 30px;">
                <div class="g-recaptcha d-flex justify-content-center" data-sitekey="<?php echo htmlspecialchars($siteKey, ENT_QUOTES, 'UTF-8'); ?>" data-callback="onCaptchaSuccess"></div>
                <div id="captchaError" class="alert alert-danger mt-3" style="display: none;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span class="ms-2">Please complete the reCAPTCHA verification</span>
                </div>
            </div>
            <div class="modal-footer justify-content-center" style="border-top: 1px solid #e9ecef; background: #f8f9fa;">
                <small style="color: #6c757d;">Protected by Google reCAPTCHA</small>
            </div>
        </div>
    </div>
</div>
<!-- 6Lc4E_orAAAAAK3REpoS5DX36K1F6LXUUYmXZ9HY -->
<script>
// Initialize Bootstrap modal
let captchaModal;
let captchaCallback = null; // Callback function to execute after successful verification

// Function to show captcha modal (called when login button is clicked)
function showCaptchaModal(callback) {
    const isVerified = sessionStorage.getItem('captchaVerified');
    
    // Check if already verified in this session
    if (isVerified === 'true') {
        // Already verified, execute callback immediately
        if (callback && typeof callback === 'function') {
            callback();
        }
        return;
    }
    
    // Disable login form while showing captcha
    disableLoginForm();
    
    // Store callback to execute after verification
    captchaCallback = callback;
    
    // Show modal
    if (captchaModal) {
        // Hide any previous errors
        const captchaError = document.getElementById('captchaError');
        if (captchaError) {
            captchaError.style.display = 'none';
        }
        
        // Show error after a short delay to prompt user
        setTimeout(() => {
            const isVerifiedAfterDelay = sessionStorage.getItem('captchaVerified');
            if (isVerifiedAfterDelay !== 'true' && captchaError) {
                captchaError.style.display = 'block';
            }
        }, 1000);
        
        // Reset captcha to get new verification
        if (typeof grecaptcha !== 'undefined') {
            try {
                grecaptcha.reset();
            } catch (e) {
                console.log('Captcha reset error:', e);
            }
        }
        
        captchaModal.show();
    }
}

// Initialize modal on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap modal
    const modalElement = document.getElementById('captchaModal');
    if (modalElement) {
        captchaModal = new bootstrap.Modal(modalElement);
    }
    
    // Prevent modal from closing on outside click
    modalElement.addEventListener('click', function(e) {
        if (e.target === this) {
            e.stopPropagation();
        }
    });
    
    // Check if already verified in this session
    const isVerified = sessionStorage.getItem('captchaVerified');
    
    if (isVerified !== 'true') {
        // Show captcha modal on page load if not verified
        setTimeout(() => {
            if (captchaModal) {
                captchaModal.show();
            }
        }, 300);
        
        // Disable login form until verified
        disableLoginForm();
    } else {
        // Already verified, enable everything
        enableLoginForm();
    }
});

// Function to disable login form until captcha is verified
function disableLoginForm() {
    const loginContainer = document.getElementById('loginContainer');
    const loginToggle = document.getElementById('loginToggle');
    
    if (loginContainer) {
        loginContainer.style.pointerEvents = 'none';
        loginContainer.style.opacity = '0.5';
        
        // Add a visual indicator
        const form = document.getElementById('loginForm');
        if (form) {
            const inputs = form.querySelectorAll('input, button');
            inputs.forEach(input => {
                input.disabled = true;
                input.style.cursor = 'not-allowed';
            });
        }
    }
    
    // Disable login toggle button
    if (loginToggle) {
        loginToggle.style.pointerEvents = 'none';
        loginToggle.style.opacity = '0.5';
        loginToggle.style.cursor = 'not-allowed';
    }
}

// Function to enable login form after captcha verification
function enableLoginForm() {
    const loginContainer = document.getElementById('loginContainer');
    const loginToggle = document.getElementById('loginToggle');
    
    if (loginContainer) {
        loginContainer.style.pointerEvents = 'auto';
        loginContainer.style.opacity = '1';
        
        // Enable form inputs
        const form = document.getElementById('loginForm');
        if (form) {
            const inputs = form.querySelectorAll('input, button');
            inputs.forEach(input => {
                input.disabled = false;
                input.style.cursor = 'auto';
            });
        }
    }
    
    // Enable login toggle button
    if (loginToggle) {
        loginToggle.style.pointerEvents = 'auto';
        loginToggle.style.opacity = '1';
        loginToggle.style.cursor = 'pointer';
    }
}

// Update onCaptchaSuccess to enable the form
function onCaptchaSuccess(response) {
    // Hide error if visible
    document.getElementById('captchaError').style.display = 'none';
    
    // Store verification in session storage (persists for entire browser session)
    sessionStorage.setItem('captchaVerified', 'true');
    
    // Hide Bootstrap modal
    if (captchaModal) {
        captchaModal.hide();
    }
    
    // Enable page content
    document.body.classList.add('content-visible');
    
    // Enable login form now that captcha is verified
    enableLoginForm();
    
    // Execute callback if provided
    if (captchaCallback && typeof captchaCallback === 'function') {
        captchaCallback();
        captchaCallback = null; // Clear callback after execution
    }
}
</script>


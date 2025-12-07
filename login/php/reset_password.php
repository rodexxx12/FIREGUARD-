<?php
require_once '../functions/security.php';
require_once '../functions/session.php';
require_once '../functions/db.php';
require_once '../functions/email.php';

initSecureSession();

if ((empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') && !loginEnvBool('ALLOW_HTTP_RESET', false)) {
    http_response_code(403);
    exit('Password reset requires a secure (HTTPS) connection.');
}

// Initialize DB connection
$conn = getDatabaseConnection();

// Get the token and email from the URL parameters or POST data
// Handle multiple levels of encoding
$rawToken = $_GET['token'] ?? $_POST['token'] ?? '';
$rawEmail = $_GET['email'] ?? $_POST['email'] ?? '';

// Decode multiple times if needed (some email clients double-encode)
$token = $rawToken;
$email = $rawEmail;

// Try decoding up to 5 times to handle multiple levels of encoding
for ($i = 0; $i < 5; $i++) {
    $decodedToken = urldecode($token);
    $decodedEmail = urldecode($email);
    
    // If decoding doesn't change the value, we're done
    if ($decodedToken === $token && $decodedEmail === $email) {
        break;
    }
    
    $token = $decodedToken;
    $email = $decodedEmail;
}

// Also try rawurldecode in case urldecode didn't catch everything
$token = rawurldecode($token);
$email = rawurldecode($email);

// Final trim and normalize
$token = trim($token);
$email = trim(strtolower($email));

// Remove any whitespace or newlines that might have been introduced
$token = preg_replace('/\s+/', '', $token);

$show_form = false;
$error = '';
$error_details = ''; // Store detailed error for debugging
$success = '';

// Check if we're in debug mode (you can set this via environment variable or remove for production)
// Enable debug mode by default for troubleshooting - add ?debug=1 to URL to see details
$debug_mode = loginEnvBool('DEBUG_PASSWORD_RESET', true) || (isset($_GET['debug']) && $_GET['debug'] === '1');

// Clean up expired tokens first
cleanupExpiredTokens();

// 1. Validate token and email
if (empty($token) || empty($email)) {
    $error = "Missing parameters (token or email).";
    $error_details = "Token length: " . strlen($token) . ", Email: " . ($email ?: 'empty');
    error_log("Password reset: Missing parameters. " . $error_details);
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email address.";
    $error_details = "Email provided: " . htmlspecialchars($email);
    error_log("Password reset: Invalid email format - " . $email);
} else {
    // Log token info for debugging (first 10 chars only for security)
    $tokenInfo = "Token length: " . strlen($token) . ", Token preview: " . substr($token, 0, 10) . "... Email: " . $email;
    error_log("Password reset attempt: " . $tokenInfo);
    
    // Validate the reset token
    $resetData = validatePasswordResetToken($token, $email);
    
    if ($resetData) {
        $show_form = true;
    } else {
        $error = "Invalid or expired reset link. Please request a new one.";
        
        // Collect detailed error information
        $error_details = "Token length: " . strlen($token) . ", Is hex: " . (ctype_xdigit($token) ? 'yes' : 'no');
        
        // Try to get more details from the database
        try {
            $conn = getDatabaseConnection();
            
            // Check if token exists at all
            $stmt = $conn->prepare("SELECT email, expires_at, created_at FROM password_resets WHERE token = ? LIMIT 1");
            $stmt->execute([$token]);
            $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tokenRow) {
                $storedEmail = strtolower(trim($tokenRow['email']));
                $error_details .= "<br>Token found in database. Stored email: " . htmlspecialchars($storedEmail);
                $error_details .= "<br>Provided email: " . htmlspecialchars($email);
                
                if ($storedEmail !== $email) {
                    $error_details .= "<br><strong>Email mismatch!</strong>";
                }
                
                $expiresAt = new DateTime($tokenRow['expires_at']);
                $now = new DateTime();
                $error_details .= "<br>Expires at: " . $expiresAt->format('Y-m-d H:i:s');
                $error_details .= "<br>Current time: " . $now->format('Y-m-d H:i:s');
                
                if ($expiresAt < $now) {
                    $error_details .= "<br><strong>Token has expired!</strong>";
                }
            } else {
                // Check if there are any tokens for this email
                $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(expires_at) as latest_expiry FROM password_resets WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))");
                $stmt->execute([$email]);
                $emailInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($emailInfo && $emailInfo['count'] > 0) {
                    $error_details .= "<br>Token not found, but found " . $emailInfo['count'] . " token(s) for this email.";
                    if ($emailInfo['latest_expiry']) {
                        $latestExpiry = new DateTime($emailInfo['latest_expiry']);
                        $now = new DateTime();
                        if ($latestExpiry < $now) {
                            $error_details .= "<br>Latest token expired at: " . $latestExpiry->format('Y-m-d H:i:s');
                        }
                    }
                } else {
                    $error_details .= "<br>No tokens found in database for this email.";
                }
            }
        } catch (Exception $e) {
            $error_details .= "<br>Database error: " . htmlspecialchars($e->getMessage());
        }
        
        error_log("Password reset validation failed. " . $error_details);
    }
}

// 2. Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
    // Get token and email from POST (they should be in hidden fields)
    $rawToken = $_POST['token'] ?? '';
    $rawEmail = $_POST['email'] ?? '';
    
    // Decode multiple times if needed
    $token = $rawToken;
    $email = $rawEmail;
    
    for ($i = 0; $i < 5; $i++) {
        $decodedToken = urldecode($token);
        $decodedEmail = urldecode($email);
        
        if ($decodedToken === $token && $decodedEmail === $email) {
            break;
        }
        
        $token = $decodedToken;
        $email = $decodedEmail;
    }
    
    // Also try rawurldecode in case urldecode didn't catch everything
    $token = rawurldecode($token);
    $email = rawurldecode($email);
    
    // Final trim and normalize
    $token = trim($token);
    $email = trim(strtolower($email));
    
    // Remove any whitespace or newlines that might have been introduced
    $token = preg_replace('/\s+/', '', $token);
    
    // CSRF Protection: Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'], 'reset_password_form')) {
        $error = "Invalid or expired security token. Please refresh the page and try again.";
    } elseif (empty($token) || empty($email)) {
        $error = "Missing reset token or email. Please use the link from your email.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Validate token again before resetting
            if (!validatePasswordResetToken($token, $email)) {
                $error = "Invalid or expired reset link. Please request a new one.";
            } elseif (resetPassword($token, $email, $new_password)) {
                $success = "Your password has been successfully updated! You can now login with your new password.";
                $show_form = false;
            } else {
                $error = "Failed to update password. The reset link may have expired or is invalid.";
            }
        }
    }
}

// Generate CSRF token for the form
$csrf_token = generateCsrfToken('reset_password_form');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD</title>
    <link rel="icon" type="image/png" href="components/fireguardlogo.png?v=1">
    <link rel="shortcut icon" type="image/png" href="components/fireguardlogo.png?v=1">
    <link rel="apple-touch-icon" href="components/fireguardlogo.png?v=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: white;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 0;
            margin: 0;
        }
        
        .container-fluid {
            padding: 20px;
        }
        
        .reset-card {
            background: white;
            border-radius: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin: 0 auto;
            max-width: 400px;
            width: 100%;
        }
        
        .reset-header {
            background: #ffc107;
            color: #212529;
            border-radius: 0;
            padding: 15px;
            text-align: center;
        }
        
        .reset-header img {
            width: 50px;
            height: 50px;
            margin-bottom: 8px;
        }
        
        .reset-header h4 {
            font-size: 1.25rem;
            margin-bottom: 5px;
        }
        
        .reset-header p {
            font-size: 14px;
            margin: 0;
        }
        
        .reset-body {
            padding: 20px;
        }
        
        .form-control {
            border-radius: 0;
            border: 1px solid #ced4da;
            padding: 10px 12px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            font-size: 16px; /* Prevents zoom on iOS */
        }
        
        .form-control:focus {
            border-color: #ffc107;
            box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
        }
        
        .btn-reset {
            background: #ffc107;
            color: #212529;
            border: none;
            border-radius: 0;
            padding: 10px 20px;
            font-weight: 500;
            transition: background-color 0.15s ease-in-out;
            font-size: 16px;
            width: 100%;
        }
        
        .btn-reset:hover {
            background: #e0a800;
            color: #212529;
        }
        
        .alert {
            border-radius: 0;
            border: none;
            padding: 12px 15px;
            font-size: 14px;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-weak { color: #ffc107; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .password-input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            padding: 5px;
            font-size: 16px;
        }
        
        .password-toggle:hover {
            color: #495057;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .text-center a {
            font-size: 14px;
        }
        
        /* Mobile Responsive */
        @media (max-width: 576px) {
            .container-fluid {
                padding: 10px;
            }
            
            .reset-card {
                margin: 10px auto;
                max-width: 100%;
            }
            
            .reset-header {
                padding: 15px;
            }
            
            .reset-header img {
                width: 40px;
                height: 40px;
                margin-bottom: 6px;
            }
            
            .reset-header h4 {
                font-size: 1.1rem;
            }
            
            .reset-header p {
                font-size: 13px;
            }
            
            .reset-body {
                padding: 20px;
            }
            
            .form-control {
                padding: 12px 10px;
                font-size: 16px;
            }
            
            .btn-reset {
                padding: 12px 20px;
                font-size: 16px;
            }
            
            .alert {
                padding: 10px 12px;
                font-size: 13px;
            }
            
            .form-label {
                font-size: 13px;
            }
            
            .password-toggle {
                right: 8px;
                font-size: 14px;
            }
        }
        
        /* Tablet Responsive */
        @media (min-width: 577px) and (max-width: 768px) {
            .reset-card {
                max-width: 350px;
            }
            
            .reset-header {
                padding: 18px;
            }
            
            .reset-body {
                padding: 22px;
            }
        }
        
        /* Desktop Responsive */
        @media (min-width: 769px) {
            .reset-card {
                max-width: 400px;
            }
        }
        
        /* Large Desktop */
        @media (min-width: 1200px) {
            .reset-card {
                max-width: 450px;
            }
            
            .reset-header {
                padding: 18px;
            }
            
            .reset-body {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid d-flex align-items-center justify-content-center min-vh-100">
        <div class="reset-card">
                    <div class="reset-header">
                        <img src="fireguardlogo.png" alt="Fire Guard Logo" style="width: 60px; height: 60px; margin-bottom: 10px;">
                        <h4 class="mb-0">FIREGUARD</h4>
                        <p class="mb-0 mt-1" style="font-size: 14px;">Reset Your Password</p>
                    </div>
                    
                    <div class="reset-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong><?php echo htmlspecialchars($error); ?></strong>
                                <?php if ($debug_mode && !empty($error_details)): ?>
                                    <hr style="margin: 10px 0; border-color: rgba(255,255,255,0.3);">
                                    <small style="font-size: 12px; opacity: 0.9;">
                                        <strong>Debug Information:</strong><br>
                                        <?php echo $error_details; ?>
                                        <br><br>
                                        <strong>Raw Token:</strong> <?php echo htmlspecialchars(substr($token, 0, 20)) . '...' . ' (length: ' . strlen($token) . ')'; ?><br>
                                        <strong>Raw Email:</strong> <?php echo htmlspecialchars($email); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="/index.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($show_form): ?>
                            <form method="POST" id="resetForm">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lock me-2"></i>New Password
                                    </label>
                                    <div class="password-input-group">
                                        <input type="password" 
                                               name="new_password" 
                                               id="newPassword"
                                               class="form-control" 
                                               required 
                                               minlength="8"
                                               autocomplete="new-password">
                                        <button type="button" class="password-toggle" onclick="togglePassword('newPassword', 'toggleNewPassword')">
                                            <i class="fas fa-eye" id="toggleNewPassword"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-check-circle me-2"></i>Confirm New Password
                                    </label>
                                    <div class="password-input-group">
                                        <input type="password" 
                                               name="confirm_password" 
                                               id="confirmPassword"
                                               class="form-control" 
                                               required 
                                               minlength="8"
                                               autocomplete="new-password">
                                        <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', 'toggleConfirmPassword')">
                                            <i class="fas fa-eye" id="toggleConfirmPassword"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-reset w-100">
                                    <i class="fas fa-key me-2"></i>Reset Password
                                </button>
                            </form>
                            
                            <div class="text-center mt-4">
                                <a href="../index.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </div>
                        <?php endif; ?>
                     </div>
         </div>
     </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password toggle functionality (can be called from anywhere)
        function togglePassword(inputId, iconId) {
            try {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                
                if (!input || !icon) {
                    return;
                }
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            } catch (e) {
                console.error('Error in togglePassword:', e);
            }
        }
        
        // Initialize form event listeners
        function initializeFormListeners() {
            try {
                // Only initialize form-related JavaScript if the form exists
                const resetForm = document.getElementById('resetForm');
                
                // If form doesn't exist, don't initialize anything
                if (!resetForm) {
                    return;
                }
                
                const newPasswordInput = document.getElementById('newPassword');
                
                // Password strength indicator
                if (newPasswordInput && typeof newPasswordInput.addEventListener === 'function') {
                    newPasswordInput.addEventListener('input', function() {
                        try {
                            const password = this.value;
                            const strengthDiv = document.getElementById('passwordStrength');
                            
                            if (!strengthDiv) {
                                return;
                            }
                            
                            let strength = 0;
                            let message = '';
                            let className = '';
                            
                            if (password.length >= 8) strength++;
                            if (password.match(/[a-z]/)) strength++;
                            if (password.match(/[A-Z]/)) strength++;
                            if (password.match(/[0-9]/)) strength++;
                            if (password.match(/[^a-zA-Z0-9]/)) strength++;
                            
                            switch(strength) {
                                case 0:
                                case 1:
                                    message = 'Very Weak';
                                    className = 'strength-weak';
                                    break;
                                case 2:
                                    message = 'Weak';
                                    className = 'strength-weak';
                                    break;
                                case 3:
                                    message = 'Medium';
                                    className = 'strength-medium';
                                    break;
                                case 4:
                                    message = 'Strong';
                                    className = 'strength-strong';
                                    break;
                                case 5:
                                    message = 'Very Strong';
                                    className = 'strength-strong';
                                    break;
                            }
                            
                            strengthDiv.textContent = `Password Strength: ${message}`;
                            strengthDiv.className = `password-strength ${className}`;
                        } catch (e) {
                            console.error('Error in password strength indicator:', e);
                        }
                    });
                }
                
                // Form validation (resetForm already checked above)
                if (resetForm && typeof resetForm.addEventListener === 'function') {
                    resetForm.addEventListener('submit', function(e) {
                        try {
                            const passwordInput = document.getElementById('newPassword');
                            const confirmInput = document.getElementById('confirmPassword');
                            
                            if (!passwordInput || !confirmInput) {
                                return;
                            }
                            
                            const password = passwordInput.value;
                            const confirm = confirmInput.value;
                            
                            if (password !== confirm) {
                                e.preventDefault();
                                alert('Passwords do not match!');
                                return false;
                            }
                            
                            if (password.length < 8) {
                                e.preventDefault();
                                alert('Password must be at least 8 characters long!');
                                return false;
                            }
                        } catch (e) {
                            console.error('Error in form validation:', e);
                        }
                    });
                }
            } catch (e) {
                console.error('Error initializing form listeners:', e);
            }
        }
        
        // Wait for DOM to be fully loaded before initializing event listeners
        (function() {
            try {
                if (typeof document === 'undefined' || !document) {
                    return;
                }
                
                // Only initialize if the form exists on the page
                function safeInitialize() {
                    try {
                        const form = document.getElementById('resetForm');
                        if (form) {
                            initializeFormListeners();
                        }
                    } catch (e) {
                        console.error('Error in safeInitialize:', e);
                    }
                }
                
                if (document.readyState === 'loading') {
                    if (typeof document.addEventListener === 'function') {
                        document.addEventListener('DOMContentLoaded', safeInitialize);
                    }
                } else {
                    // DOM is already loaded, use setTimeout to ensure everything is ready
                    setTimeout(safeInitialize, 0);
                }
            } catch (e) {
                console.error('Error setting up DOM ready handler:', e);
            }
        })();
    </script>
</body>
</html>

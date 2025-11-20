<?php
session_start();
require_once '../functions/db.php';
require_once '../functions/email.php';
require_once '../functions/security.php';

// Initialize DB connection
$conn = getDatabaseConnection();

// Get the token and email from the URL parameters
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

$show_form = false;
$error = '';
$success = '';

// Clean up expired tokens first
cleanupExpiredTokens();

// 1. Validate token and email
if (empty($token) || empty($email)) {
    $error = "Missing parameters (token or email).";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Invalid email address.";
} else {
    // Validate the reset token
    $resetData = validatePasswordResetToken($token, $email);
    
    if ($resetData) {
        $show_form = true;
    } else {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
}

// 2. Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
    // CSRF Protection: Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $error = "Invalid or expired security token. Please refresh the page and try again.";
    } else {
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (strlen($new_password) < 8) {
            $error = "Password must be at least 8 characters long.";
        } elseif ($new_password !== $confirm_password) {
            $error = "Passwords do not match.";
        } else {
            // Attempt to reset the password
            if (resetPassword($token, $email, $new_password)) {
                $success = "Your password has been successfully updated! You can now login with your new password.";
                $show_form = false;
            } else {
                $error = "Failed to update password. The reset link may have expired or is invalid.";
            }
        }
    }
}

// Generate CSRF token for the form
$csrf_token = generateCsrfToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD</title>
    <link rel="icon" type="image/png" href="login/php/components/fireguardlogo.png?v=1">
    <link rel="shortcut icon" type="image/png" href="login/php/components/fireguardlogo.png?v=1">
    <link rel="apple-touch-icon" href="login/php/components/fireguardlogo.png?v=1">
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
                                <?php echo htmlspecialchars($error); ?>
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
        // Password toggle functionality
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        // Password strength indicator
        document.getElementById('newPassword').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
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
        });
        
        // Form validation
        document.getElementById('resetForm').addEventListener('submit', function(e) {
            const password = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;
            
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
        });
    </script>
</body>
</html>

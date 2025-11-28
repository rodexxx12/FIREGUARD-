<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/email.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/messages.php';

function handleAjaxRequest() {
    header('Content-Type: application/json');
    try {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Invalid request method. Expected POST, got ' . $_SERVER['REQUEST_METHOD']);
        }
        if (!isset($_POST['action'])) {
            throw new Exception('No action specified');
        }
        $action = $_POST['action'];
        if (!isset($_POST['csrf_token'])) {
            throw new Exception('CSRF token is missing');
        }
        $csrfToken = $_POST['csrf_token'];
        $formMap = [
            'login' => 'login_form',
            'forgot_password' => 'forgot_password_form',
            'reset_password' => 'reset_password_form',
            'contact_form' => 'contact_form'
        ];
        $formKey = $formMap[$action] ?? 'default';
        if (!validateCsrfToken($csrfToken, $formKey)) {
            throw new Exception('Invalid or expired CSRF token. Please refresh the page and try again.');
        }
        $response = [];
        if ($action === 'login') {
            ensureRecaptchaPasses($_POST);
        }
        switch ($action) {
            case 'login':
                $username = sanitizeInput($_POST['username'] ?? '', 64);
                $password = $_POST['password'] ?? '';
                $remember = isset($_POST['remember']);
                
                // Enhanced validation
                $usernameValidation = validateUsername($username);
                if (!$usernameValidation['valid']) {
                    throw new Exception($usernameValidation['message']);
                }
                
                $passwordValidation = validatePassword($password);
                if (!$passwordValidation['valid']) {
                    throw new Exception($passwordValidation['message']);
                }
                
                $response = authenticateUser($username, $password, $remember);
                
                // If login successful, add security headers to prevent caching
                if ($response['success']) {
                    // Additional cache prevention headers
                    header("Cache-Control: no-cache, no-store, must-revalidate, private");
                    header("Pragma: no-cache");
                    header("Expires: 0");
                    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
                    
                    // Add flag to indicate successful login for JavaScript
                    $response['login_success'] = true;
                }
                break;
            case 'forgot_password':
                $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
                if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Please enter a valid email address');
                }
                if (sendPasswordResetEmail($email)) {
                    $response = ['success' => true, 'message' => 'Password reset instructions have been sent to your email'];
                } else {
                    throw new Exception('Failed to send reset email. Please try again.');
                }
                break;
            case 'reset_password':
                $token = $_POST['token'] ?? '';
                $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
                $password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                if (empty($password) || $password !== $confirm_password) {
                    throw new Exception('Passwords do not match or are empty');
                }
                if (strlen($password) < 8) {
                    throw new Exception('Password must be at least 8 characters');
                }
                if (resetPassword($token, $email, $password)) {
                    $response = ['success' => true, 'message' => 'Your password has been reset successfully. Please login with your new password.'];
                } else {
                    throw new Exception('Failed to reset password. Please try again.');
                }
                break;
            case 'contact_form':
                handleContactFormSubmission();
                break;
            default:
                throw new Exception('Invalid action');
        }
        echo json_encode($response);
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

function ensureRecaptchaPasses(array $post): void {
    if ((string)loginEnv('RECAPTCHA_SECRET_KEY', '') === '') {
        error_log('reCAPTCHA secret key missing. Skipping verification until configured.');
        return;
    }
    $captcha = $post['g-recaptcha-response'] ?? '';
    if (!verifyRecaptchaResponse($captcha, getClientIp())) {
        throw new Exception('Please complete the reCAPTCHA challenge.');
    }
}

// Handle AJAX requests when this file is called directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    handleAjaxRequest();
} 
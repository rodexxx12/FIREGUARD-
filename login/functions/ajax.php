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
        if (!isset($_POST['csrf_token'])) {
            throw new Exception('CSRF token is missing');
        }
        if (!validateCsrfToken($_POST['csrf_token'])) {
            throw new Exception('Invalid or expired CSRF token. Please refresh the page and try again.');
        }
        $response = [];
        switch ($_POST['action']) {
            case 'login':
                $username = trim(sanitizeInput($_POST['username'] ?? ''));
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
            case 'validate_credentials':
                $username = trim(sanitizeInput($_POST['username'] ?? ''));
                $password = $_POST['password'] ?? '';
                
                // Basic validation
                if (empty($username) || empty($password)) {
                    $response = ['valid' => false, 'message' => 'Please enter both username and password'];
                } else {
                    $response = validateCredentials($username, $password);
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

/**
 * Validate credentials against all user tables without logging in
 * This is used for real-time validation feedback
 */
function validateCredentials($username, $password) {
    $conn = getDatabaseConnection();
    
    // Trim and sanitize username
    $username = trim($username);
    
    // Basic validation
    if (empty($username) || empty($password)) {
        return ['valid' => false, 'message' => 'Please enter both username and password'];
    }
    
    // Check superadmin table (username or email)
    try {
        $identifier = $username;
        $identifierField = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $stmt = $conn->prepare("
            SELECT superadmin_id, username, email, password, status
            FROM superadmin 
            WHERE {$identifierField} = ? 
            LIMIT 1
        ");
        $stmt->execute([$identifier]);
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            if (password_verify($password, $user['password'])) {
                if (strtolower($user['status'] ?? '') !== 'active') {
                    return ['valid' => false, 'message' => 'Your superadmin account is inactive'];
                }
                return [
                    'valid' => true,
                    'message' => 'Credentials are correct',
                    'user_type' => 'superadmin'
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Error validating superadmin credentials: " . $e->getMessage());
    }

    // Check admin table
    try {
        $stmt = $conn->prepare("
            SELECT admin_id, username, password, status 
            FROM admin 
            WHERE username = ? 
            LIMIT 1
        ");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            if ($user['status'] !== 'Active') {
                return ['valid' => false, 'message' => 'Your account is inactive'];
            }
            if (password_verify($password, $user['password'])) {
                return ['valid' => true, 'message' => 'Credentials are correct', 'user_type' => 'admin'];
            }
        }
    } catch (PDOException $e) {
        error_log("Error validating admin credentials: " . $e->getMessage());
    }

    // Check users table
    try {
        $stmt = $conn->prepare("
            SELECT user_id, username, password, status
            FROM users 
            WHERE LOWER(TRIM(username)) = LOWER(TRIM(?))
            LIMIT 1
        ");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            $status = strtolower(trim($user['status'] ?? ''));
            if ($status !== 'active') {
                return ['valid' => false, 'message' => 'Your account is inactive'];
            }
            if (!empty($user['password']) && password_verify($password, $user['password'])) {
                return ['valid' => true, 'message' => 'Credentials are correct', 'user_type' => 'user'];
            }
        }
    } catch (PDOException $e) {
        error_log("Error validating user credentials: " . $e->getMessage());
    }

    // Check firefighters table
    try {
        $stmt = $conn->prepare("
            SELECT id, username, password, availability 
            FROM firefighters 
            WHERE username = ? 
            LIMIT 1
        ");
        $stmt->execute([$username]);
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch();
            if ($user['availability'] !== 1) {
                return ['valid' => false, 'message' => 'Your account is currently unavailable'];
            }
            if (password_verify($password, $user['password'])) {
                return ['valid' => true, 'message' => 'Credentials are correct', 'user_type' => 'firefighter'];
            }
        }
    } catch (PDOException $e) {
        error_log("Error validating firefighter credentials: " . $e->getMessage());
    }

    // No matching credentials found
    return ['valid' => false, 'message' => 'Invalid username or password'];
}

// Handle AJAX requests when this file is called directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    handleAjaxRequest();
} 
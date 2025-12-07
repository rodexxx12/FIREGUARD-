<?php
/**
 * CSRF Protection Helper
 * Provides CSRF token generation and validation functions
 */

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a CSRF token and store it in session
     * @return string CSRF token
     */
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate token if it doesn't exist or if it's expired
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
            (time() - $_SESSION['csrf_token_time']) > 3600) { // Token expires after 1 hour
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate a CSRF token
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // Check token expiration (1 hour)
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 3600) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        // Use timing-safe comparison to prevent timing attacks
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('getCSRFTokenInput')) {
    /**
     * Get HTML input field for CSRF token
     * @return string HTML input field
     */
    function getCSRFTokenInput() {
        $token = generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('validateCSRFRequest')) {
    /**
     * Validate CSRF token from POST/GET request
     * Exits with error if validation fails
     * @param bool $isAjax Whether this is an AJAX request (default: false)
     */
    function validateCSRFRequest($isAjax = false) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
        
        if (!validateCSRFToken($token)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
                ]);
            } else {
                http_response_code(403);
                die('Invalid or missing CSRF token. Please refresh the page and try again.');
            }
            exit;
        }
    }
}
?>
























<?php
/**
 * CSRF Protection Functions
 * 
 * Provides CSRF token generation and validation for form submissions and API calls
 */

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate a CSRF token and store it in session
     * Token is regenerated every hour for security
     * @return string CSRF token
     */
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate token every hour for security
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
            (time() - $_SESSION['csrf_token_time']) > 3600) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate CSRF token from request
     * @param string|null $token Token to validate (from POST or GET)
     * @return bool True if valid, false otherwise
     */
    function validateCSRFToken($token = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Get token from parameter or POST/GET
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            error_log("CSRF validation failed: No token provided from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }
        
        // Token expires after 2 hours
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 7200) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            error_log("CSRF validation failed: Token expired from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }
        
        // Use hash_equals for timing-safe comparison
        $isValid = hash_equals($_SESSION['csrf_token'], $token);
        
        if (!$isValid) {
            error_log("CSRF validation failed: Token mismatch from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
        
        return $isValid;
    }
}

if (!function_exists('requireCSRFToken')) {
    /**
     * Require and validate CSRF token for state-changing requests
     * Dies with 403 error if token is invalid
     */
    function requireCSRFToken() {
        if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            if (!validateCSRFToken()) {
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    http_response_code(403);
                    header('Content-Type: application/json');
                    die(json_encode(['error' => 'Invalid security token']));
                }
                http_response_code(403);
                die('Invalid security token');
            }
        }
    }
}









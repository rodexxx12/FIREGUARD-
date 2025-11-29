<?php
/**
 * CSRF Protection Helper
 */
class CsrfProtection {
    /**
     * Generate or retrieve CSRF token
     * 
     * @return string CSRF token
     */
    public static function getToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Validate CSRF token
     * 
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public static function validateToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Require valid CSRF token or exit with error
     * 
     * @param string $token Token from request
     * @param bool $isJsonResponse Return JSON response
     */
    public static function requireToken($token, $isJsonResponse = true) {
        if (!self::validateToken($token)) {
            if ($isJsonResponse) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
            } else {
                http_response_code(403);
                die('CSRF token validation failed');
            }
            exit();
        }
    }
}












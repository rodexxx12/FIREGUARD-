<?php
/**
 * CSRF Protection Module
 * 
 * Provides CSRF token generation and validation to prevent Cross-Site Request Forgery attacks
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/session/session.php';
 * require_once __DIR__ . '/../../core/security/csrf.php';
 * 
 * // In form HTML:
 * echo csrfGetTokenInput('registration_form');
 * 
 * // In form processing:
 * if (!csrfValidateToken($_POST['csrf_token'] ?? '', 'registration_form')) {
 *     die('CSRF token validation failed');
 * }
 */

// Load session handler
if (!function_exists('initSecureSession')) {
    require_once __DIR__ . '/../session/session.php';
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    initSecureSession();
}

/**
 * Generate or retrieve CSRF token for a specific form
 * 
 * @param string $form Form identifier (default: 'default')
 * @param int $ttl Token time-to-live in seconds (default: from config)
 * @return string CSRF token
 */
if (!function_exists('csrfGenerateToken')) {
    function csrfGenerateToken($form = 'default', $ttl = null) {
        if (!is_string($form) || $form === '') {
            $form = 'default';
        }
        
        if ($ttl === null) {
            $ttl = config('security.csrf_token_ttl', CSRF_TOKEN_TTL);
        }
        
        // Initialize CSRF tokens array in session
        if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        // Purge expired tokens
        csrfPurgeExpiredTokens();
        
        // Check if token exists and is valid
        if (isset($_SESSION['csrf_tokens'][$form])) {
            $tokenData = $_SESSION['csrf_tokens'][$form];
            
            // Check if token is expired
            if (isset($tokenData['expires_at']) && $tokenData['expires_at'] > time()) {
                return $tokenData['value'];
            }
        }
        
        // Generate new token
        try {
            $token = bin2hex(random_bytes(32)); // 64 character hex string
            
            $_SESSION['csrf_tokens'][$form] = [
                'value' => $token,
                'created_at' => time(),
                'expires_at' => time() + (int)$ttl
            ];
            
            return $token;
            
        } catch (Exception $e) {
            error_log("CSRF token generation failed: " . $e->getMessage());
            throw new Exception("CSRF token generation failed");
        }
    }
}

/**
 * Validate CSRF token
 * 
 * @param string $token Token to validate
 * @param string $form Form identifier (default: 'default')
 * @return bool True if valid, false otherwise
 */
if (!function_exists('csrfValidateToken')) {
    function csrfValidateToken($token, $form = 'default') {
        if (!is_string($token) || $token === '') {
            return false;
        }
        
        if (!is_string($form) || $form === '') {
            $form = 'default';
        }
        
        // Purge expired tokens
        csrfPurgeExpiredTokens();
        
        // Check if token exists in session
        if (!isset($_SESSION['csrf_tokens'][$form])) {
            // Backward compatibility: check for legacy single-token
            if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
                return true;
            }
            return false;
        }
        
        $tokenData = $_SESSION['csrf_tokens'][$form];
        
        // Check if token is expired
        if (isset($tokenData['expires_at']) && $tokenData['expires_at'] <= time()) {
            unset($_SESSION['csrf_tokens'][$form]);
            return false;
        }
        
        // Use timing-safe comparison to prevent timing attacks
        if (!isset($tokenData['value'])) {
            return false;
        }
        
        return hash_equals($tokenData['value'], $token);
    }
}

/**
 * Purge expired CSRF tokens from session
 * 
 * @return void
 */
if (!function_exists('csrfPurgeExpiredTokens')) {
    function csrfPurgeExpiredTokens() {
        if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
            return;
        }
        
        $now = time();
        
        foreach ($_SESSION['csrf_tokens'] as $form => $tokenData) {
            if (isset($tokenData['expires_at']) && $tokenData['expires_at'] <= $now) {
                unset($_SESSION['csrf_tokens'][$form]);
            }
        }
    }
}

/**
 * Get CSRF token as hidden input HTML
 * 
 * @param string $form Form identifier
 * @param string $inputName Input name attribute (default: 'csrf_token')
 * @return string HTML input element
 */
if (!function_exists('csrfGetTokenInput')) {
    function csrfGetTokenInput($form = 'default', $inputName = 'csrf_token') {
        $token = csrfGenerateToken($form);
        $name = escapeAttribute($inputName);
        $value = escapeAttribute($token);
        
        return '<input type="hidden" name="' . $name . '" value="' . $value . '">';
    }
}

/**
 * Get CSRF token as meta tag (for JavaScript)
 * 
 * @param string $form Form identifier
 * @param string $metaName Meta name attribute (default: 'csrf-token')
 * @return string HTML meta tag
 */
if (!function_exists('csrfGetTokenMeta')) {
    function csrfGetTokenMeta($form = 'default', $metaName = 'csrf-token') {
        $token = csrfGenerateToken($form);
        $name = escapeAttribute($metaName);
        $content = escapeAttribute($token);
        
        return '<meta name="' . $name . '" content="' . $content . '">';
    }
}

/**
 * Require valid CSRF token or die with error
 * 
 * @param string $token Token from request
 * @param string $form Form identifier
 * @param bool $jsonResponse Return JSON response (default: true)
 * @return void
 */
if (!function_exists('csrfRequireToken')) {
    function csrfRequireToken($token, $form = 'default', $jsonResponse = true) {
        if (!csrfValidateToken($token, $form)) {
            if ($jsonResponse) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF token validation failed',
                    'code' => ERROR_CSRF_TOKEN_INVALID
                ]);
            } else {
                http_response_code(403);
                die('CSRF token validation failed');
            }
            exit();
        }
    }
}

/**
 * Get CSRF token value (for AJAX requests)
 * 
 * @param string $form Form identifier
 * @return string Token value
 */
if (!function_exists('csrfGetToken')) {
    function csrfGetToken($form = 'default') {
        return csrfGenerateToken($form);
    }
}
?>


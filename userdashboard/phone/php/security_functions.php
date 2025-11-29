<?php
/**
 * Security Functions for Phone Management System
 * 
 * Provides:
 * - CSRF token generation and validation
 * - Input validation and sanitization
 * - XSS protection utilities
 * - Rate limiting helpers
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
            $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
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

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize input data for database storage
     * Removes dangerous characters but preserves data integrity
     * @param mixed $input Input to sanitize
     * @param string $type Type of input (string, int, float, phone, email, etc.)
     * @param int|null $maxLength Maximum length for string inputs
     * @return mixed Sanitized input
     */
    function sanitizeInput($input, $type = 'string', $maxLength = null) {
        if ($input === null || $input === '') {
            return null;
        }
        
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'phone':
                // Remove all non-digit characters
                $phone = preg_replace('/\D/', '', $input);
                // Validate Philippine phone format (11 digits starting with 09)
                if (preg_match('/^09\d{9}$/', $phone)) {
                    return $phone;
                }
                return false;
                
            case 'verification_code':
                // Only allow 6 digits
                $code = preg_replace('/\D/', '', $input);
                if (preg_match('/^\d{6}$/', $code)) {
                    return $code;
                }
                return false;
                
            case 'email':
                $email = filter_var(trim($input), FILTER_SANITIZE_EMAIL);
                return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
                
            case 'string':
            default:
                // Trim whitespace
                $string = trim($input);
                
                // Remove null bytes
                $string = str_replace("\0", '', $string);
                
                // Apply max length if specified
                if ($maxLength !== null && mb_strlen($string) > $maxLength) {
                    $string = mb_substr($string, 0, $maxLength);
                }
                
                return $string;
        }
    }
}

if (!function_exists('validatePhoneNumber')) {
    /**
     * Validate Philippine phone number format
     * @param string $phoneNumber Phone number to validate
     * @return bool True if valid, false otherwise
     */
    function validatePhoneNumber($phoneNumber) {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phoneNumber);
        // Must be exactly 11 digits starting with 09
        return preg_match('/^09\d{9}$/', $phone) === 1;
    }
}

if (!function_exists('validateVerificationCode')) {
    /**
     * Validate verification code format
     * @param string $code Code to validate
     * @return bool True if valid, false otherwise
     */
    function validateVerificationCode($code) {
        return preg_match('/^\d{6}$/', $code) === 1;
    }
}

if (!function_exists('validateLabel')) {
    /**
     * Validate phone label
     * @param string $label Label to validate
     * @return bool|string Sanitized label or false if invalid
     */
    function validateLabel($label) {
        if (empty($label)) {
            return null; // Labels are optional
        }
        
        // Trim and sanitize
        $label = trim($label);
        
        // Max length 100 characters
        if (mb_strlen($label) > 100) {
            return false;
        }
        
        // Allow alphanumeric, spaces, and common punctuation
        if (!preg_match('/^[a-zA-Z0-9\s\-_.,!?()]+$/', $label)) {
            return false;
        }
        
        return $label;
    }
}

if (!function_exists('escapeOutput')) {
    /**
     * Escape output for HTML display (XSS protection)
     * @param string $output Output to escape
     * @param int $flags htmlspecialchars flags
     * @return string Escaped output
     */
    function escapeOutput($output, $flags = ENT_QUOTES | ENT_HTML5) {
        if ($output === null) {
            return '';
        }
        return htmlspecialchars((string)$output, $flags, 'UTF-8');
    }
}

if (!function_exists('validateInteger')) {
    /**
     * Validate and sanitize integer input
     * @param mixed $input Input to validate
     * @param int|null $min Minimum value
     * @param int|null $max Maximum value
     * @return int|false Validated integer or false
     */
    function validateInteger($input, $min = null, $max = null) {
        $int = filter_var($input, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            return false;
        }
        
        if ($min !== null && $int < $min) {
            return false;
        }
        
        if ($max !== null && $int > $max) {
            return false;
        }
        
        return $int;
    }
}

if (!function_exists('checkRateLimit')) {
    /**
     * Simple rate limiting check (session-based)
     * @param string $action Action identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    function checkRateLimit($action, $maxAttempts = 5, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        $currentTime = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => $currentTime
            ];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if ($currentTime - $data['first_attempt'] > $timeWindow) {
            $_SESSION[$key] = [
                'attempts' => 1,
                'first_attempt' => $currentTime
            ];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['attempts'] >= $maxAttempts) {
            error_log("Rate limit exceeded for action: $action from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            return false;
        }
        
        // Increment attempts
        $_SESSION[$key]['attempts']++;
        return true;
    }
}

if (!function_exists('getClientIP')) {
    /**
     * Get client IP address
     * @return string Client IP address
     */
    function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, 
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

if (!function_exists('logSecurityEvent')) {
    /**
     * Log security events
     * @param string $event Event type
     * @param string $details Event details
     * @param string $severity Severity level (low, medium, high, critical)
     */
    function logSecurityEvent($event, $details = '', $severity = 'medium') {
        $logEntry = sprintf(
            "[%s] [%s] %s - %s - IP: %s - User: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($severity),
            $event,
            $details,
            getClientIP(),
            $_SESSION['user_id'] ?? 'anonymous'
        );
        
        $logFile = __DIR__ . '/../security.log';
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
        error_log($logEntry);
    }
}
?>

















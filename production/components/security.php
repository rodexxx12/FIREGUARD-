<?php
/**
 * Security Utilities
 * Provides CSRF protection, input sanitization, and other security functions
 */

if (!function_exists('generateCSRFToken')) {
    /**
     * Generate CSRF token
     * @return string CSRF token
     */
    function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    /**
     * Validate CSRF token
     * @param string $token Token to validate
     * @return bool True if valid, false otherwise
     */
    function validateCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input (replaces deprecated FILTER_SANITIZE_STRING)
     * @param string $input Input to sanitize
     * @param bool $allowHtml Whether to allow HTML (default: false)
     * @return string Sanitized input
     */
    function sanitizeInput($input, $allowHtml = false) {
        if ($input === null) {
            return '';
        }
        
        // Convert to string
        $input = (string)$input;
        
        if ($allowHtml) {
            // Allow HTML but sanitize it
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            // Strip all HTML tags
            $input = strip_tags($input);
            // Escape special characters
            return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }
}

if (!function_exists('sanitizeEmail')) {
    /**
     * Sanitize and validate email
     * @param string $email Email to sanitize
     * @return string|false Sanitized email or false if invalid
     */
    function sanitizeEmail($email) {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('sanitizeInt')) {
    /**
     * Sanitize integer input
     * @param mixed $input Input to sanitize
     * @param int $min Minimum value (default: PHP_INT_MIN)
     * @param int $max Maximum value (default: PHP_INT_MAX)
     * @return int|false Sanitized integer or false if invalid
     */
    function sanitizeInt($input, $min = PHP_INT_MIN, $max = PHP_INT_MAX) {
        $value = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        $value = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $min, 'max_range' => $max]
        ]);
        return $value;
    }
}

if (!function_exists('sanitizeFloat')) {
    /**
     * Sanitize float input
     * @param mixed $input Input to sanitize
     * @return float|false Sanitized float or false if invalid
     */
    function sanitizeFloat($input) {
        return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }
}

if (!function_exists('validateFileUpload')) {
    /**
     * Validate file upload
     * @param array $file $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'error' => string|null]
     */
    function validateFileUpload($file, $allowedTypes = [], $maxSize = 2097152) {
        // Check if file was uploaded
        if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
            ];
            return ['valid' => false, 'error' => $errorMessages[$file['error']] ?? 'Unknown upload error'];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds maximum limit'];
        }
        
        // Validate MIME type
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                return ['valid' => false, 'error' => 'Invalid file type'];
            }
        }
        
        return ['valid' => true, 'error' => null];
    }
}

if (!function_exists('escapeOutput')) {
    /**
     * Escape output for HTML display
     * @param string $output Output to escape
     * @return string Escaped output
     */
    function escapeOutput($output) {
        return htmlspecialchars($output ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('rateLimitCheck')) {
    /**
     * Simple rate limiting check
     * @param string $key Rate limit key (e.g., 'login', 'api')
     * @param int $maxAttempts Maximum attempts
     * @param int $timeWindow Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    function rateLimitCheck($key, $maxAttempts = 5, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $rateLimitKey = "rate_limit_{$key}";
        $now = time();
        
        if (!isset($_SESSION[$rateLimitKey])) {
            $_SESSION[$rateLimitKey] = [
                'attempts' => 0,
                'reset_time' => $now + $timeWindow
            ];
        }
        
        $rateLimit = $_SESSION[$rateLimitKey];
        
        // Reset if time window expired
        if ($now > $rateLimit['reset_time']) {
            $rateLimit = [
                'attempts' => 0,
                'reset_time' => $now + $timeWindow
            ];
        }
        
        $allowed = $rateLimit['attempts'] < $maxAttempts;
        
        if ($allowed) {
            $rateLimit['attempts']++;
        }
        
        $_SESSION[$rateLimitKey] = $rateLimit;
        
        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxAttempts - $rateLimit['attempts']),
            'reset_time' => $rateLimit['reset_time']
        ];
    }
}




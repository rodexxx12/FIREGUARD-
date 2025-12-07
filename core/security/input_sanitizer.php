<?php
/**
 * Input Sanitization Module
 * 
 * Provides functions to sanitize user input before processing
 * Note: Sanitization is not a replacement for validation or output escaping
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/security/input_sanitizer.php';
 * 
 * $email = sanitizeEmail($_POST['email']);
 * $name = sanitizeString($_POST['name']);
 */

/**
 * Sanitize a string by removing dangerous characters
 * 
 * @param mixed $input Input to sanitize
 * @return string Sanitized string
 */
if (!function_exists('sanitizeString')) {
    function sanitizeString($input) {
        if ($input === null) {
            return '';
        }
        
        if (!is_scalar($input)) {
            return '';
        }
        
        $input = (string)$input;
        
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // Remove control characters except newlines and tabs
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
        
        // Trim whitespace
        $input = trim($input);
        
        return $input;
    }
}

/**
 * Sanitize email address
 * 
 * @param mixed $input Email input
 * @return string Sanitized email
 */
if (!function_exists('sanitizeEmail')) {
    function sanitizeEmail($input) {
        if ($input === null) {
            return '';
        }
        
        $input = sanitizeString($input);
        
        // Convert to lowercase
        $input = strtolower($input);
        
        // Remove any characters that shouldn't be in email
        $input = filter_var($input, FILTER_SANITIZE_EMAIL);
        
        return $input;
    }
}

/**
 * Sanitize integer input
 * 
 * @param mixed $input Integer input
 * @return int Sanitized integer (0 if invalid)
 */
if (!function_exists('sanitizeInt')) {
    function sanitizeInt($input) {
        if ($input === null) {
            return 0;
        }
        
        // Convert to integer
        $int = filter_var($input, FILTER_SANITIZE_NUMBER_INT);
        
        return (int)$int;
    }
}

/**
 * Sanitize float input
 * 
 * @param mixed $input Float input
 * @return float Sanitized float (0.0 if invalid)
 */
if (!function_exists('sanitizeFloat')) {
    function sanitizeFloat($input) {
        if ($input === null) {
            return 0.0;
        }
        
        // Convert to float
        $float = filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        
        return (float)$float;
    }
}

/**
 * Sanitize URL
 * 
 * @param mixed $input URL input
 * @return string Sanitized URL
 */
if (!function_exists('sanitizeUrl')) {
    function sanitizeUrl($input) {
        if ($input === null) {
            return '';
        }
        
        $input = sanitizeString($input);
        
        // Remove javascript: and data: protocols
        $input = preg_replace('/^(javascript|data|vbscript):/i', '', $input);
        
        // Sanitize URL
        $input = filter_var($input, FILTER_SANITIZE_URL);
        
        return $input;
    }
}

/**
 * Sanitize filename
 * 
 * @param mixed $input Filename input
 * @return string Sanitized filename
 */
if (!function_exists('sanitizeFilename')) {
    function sanitizeFilename($input) {
        if ($input === null) {
            return '';
        }
        
        $input = sanitizeString($input);
        
        // Remove path components
        $input = basename($input);
        
        // Remove dangerous characters
        $input = preg_replace('/[^a-zA-Z0-9._-]/', '', $input);
        
        // Remove leading dots (hidden files)
        $input = ltrim($input, '.');
        
        // Ensure it's not empty
        if (empty($input)) {
            $input = 'file';
        }
        
        return $input;
    }
}

/**
 * Remove null bytes from string
 * 
 * @param mixed $input Input to clean
 * @return string Cleaned string
 */
if (!function_exists('removeNullBytes')) {
    function removeNullBytes($input) {
        if ($input === null) {
            return '';
        }
        
        if (!is_scalar($input)) {
            return '';
        }
        
        return str_replace("\0", '', (string)$input);
    }
}

/**
 * Sanitize HTML (removes dangerous tags and attributes)
 * Note: For full HTML sanitization, use a library like HTML Purifier
 * This is a basic sanitizer
 * 
 * @param mixed $input HTML input
 * @param array $allowedTags Allowed HTML tags
 * @return string Sanitized HTML
 */
if (!function_exists('sanitizeHtml')) {
    function sanitizeHtml($input, $allowedTags = []) {
        if ($input === null) {
            return '';
        }
        
        $input = removeNullBytes($input);
        
        if (empty($allowedTags)) {
            // Strip all HTML tags
            return strip_tags($input);
        }
        
        // Allow specified tags only
        return strip_tags($input, '<' . implode('><', $allowedTags) . '>');
    }
}

/**
 * Sanitize phone number
 * 
 * @param mixed $input Phone input
 * @return string Sanitized phone number
 */
if (!function_exists('sanitizePhone')) {
    function sanitizePhone($input) {
        if ($input === null) {
            return '';
        }
        
        $input = sanitizeString($input);
        
        // Remove all non-digit characters except + at start
        if (strpos($input, '+') === 0) {
            $input = '+' . preg_replace('/[^0-9]/', '', substr($input, 1));
        } else {
            $input = preg_replace('/[^0-9]/', '', $input);
        }
        
        return $input;
    }
}
?>























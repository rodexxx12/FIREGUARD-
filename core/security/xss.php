<?php
/**
 * XSS Protection Module
 * 
 * Provides output escaping functions to prevent Cross-Site Scripting (XSS) attacks
 * Always escape output based on context (HTML, JavaScript, URL, etc.)
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/security/xss.php';
 * 
 * echo escapeHtml($userInput); // For HTML output
 * echo escapeJs($userInput);   // For JavaScript
 */

/**
 * Escape output for HTML display (XSS protection)
 * 
 * @param mixed $string Output to escape (string, int, float, null)
 * @param int $flags htmlspecialchars flags
 * @return string Escaped output
 */
if (!function_exists('escapeHtml')) {
    function escapeHtml($string, $flags = ENT_QUOTES | ENT_HTML5) {
        if ($string === null) {
            return '';
        }
        
        if (!is_scalar($string)) {
            // For arrays/objects, return empty string or serialize
            return '';
        }
        
        return htmlspecialchars((string)$string, $flags, 'UTF-8', false);
    }
}

/**
 * Escape output for HTML attributes
 * 
 * @param mixed $string Output to escape
 * @return string Escaped output
 */
if (!function_exists('escapeAttribute')) {
    function escapeAttribute($string) {
        if ($string === null) {
            return '';
        }
        
        if (!is_scalar($string)) {
            return '';
        }
        
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8', false);
    }
}

/**
 * Escape output for JavaScript context
 * 
 * @param mixed $data Data to escape (string, array, object)
 * @return string JSON-encoded and escaped output
 */
if (!function_exists('escapeJs')) {
    function escapeJs($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Escape output for URL parameters
 * 
 * @param mixed $string Output to escape
 * @return string URL-encoded output
 */
if (!function_exists('escapeUrl')) {
    function escapeUrl($string) {
        if ($string === null) {
            return '';
        }
        
        if (!is_scalar($string)) {
            return '';
        }
        
        return rawurlencode((string)$string);
    }
}

/**
 * Escape output for CSS context (rarely needed)
 * 
 * @param mixed $string Output to escape
 * @return string Escaped CSS
 */
if (!function_exists('escapeCss')) {
    function escapeCss($string) {
        if ($string === null) {
            return '';
        }
        
        if (!is_scalar($string)) {
            return '';
        }
        
        $string = (string)$string;
        
        // Remove any CSS expressions and escape special characters
        $string = preg_replace('/expression\s*\(/i', '', $string);
        $string = preg_replace('/javascript\s*:/i', '', $string);
        $string = preg_replace('/@import/i', '', $string);
        
        // Escape special characters
        return addcslashes($string, "\0..\37\"'\\");
    }
}

/**
 * Short alias for escapeHtml (common use case)
 * 
 * @param mixed $string Output to escape
 * @return string Escaped output
 */
if (!function_exists('e')) {
    function e($string) {
        return escapeHtml($string);
    }
}

/**
 * Short alias for escapeJs (for JSON output)
 * 
 * @param mixed $data Data to escape
 * @return string JSON-encoded and escaped output
 */
if (!function_exists('je')) {
    function je($data) {
        return escapeJs($data);
    }
}

/**
 * Escape and prepare data for JavaScript variable assignment
 * 
 * @param mixed $data Data to escape
 * @return string JavaScript-safe output
 */
if (!function_exists('jsVar')) {
    function jsVar($data) {
        return escapeJs($data);
    }
}

/**
 * Clean and escape user input for display
 * Combines cleaning and escaping
 * 
 * @param mixed $string Input to clean and escape
 * @return string Clean and escaped output
 */
if (!function_exists('cleanAndEscape')) {
    function cleanAndEscape($string) {
        if ($string === null) {
            return '';
        }
        
        if (!is_scalar($string)) {
            return '';
        }
        
        $string = (string)$string;
        
        // Remove null bytes
        $string = str_replace("\0", '', $string);
        
        // Trim whitespace
        $string = trim($string);
        
        // Escape for HTML
        return escapeHtml($string);
    }
}
?>























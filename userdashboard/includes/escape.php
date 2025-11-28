<?php
/**
 * Output Escaping Functions
 * 
 * Provides secure output escaping for different contexts (HTML, JavaScript, URL)
 */

if (!function_exists('escapeHtml')) {
    /**
     * Escape output for HTML display (XSS protection)
     * @param string $string Output to escape
     * @param int $flags htmlspecialchars flags
     * @return string Escaped output
     */
    function escapeHtml($string, $flags = ENT_QUOTES | ENT_HTML5) {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars((string)$string, $flags, 'UTF-8');
    }
}

if (!function_exists('escapeUrl')) {
    /**
     * Escape output for URL parameters
     * @param string $string Output to escape
     * @return string Escaped output
     */
    function escapeUrl($string) {
        if ($string === null) {
            return '';
        }
        return urlencode((string)$string);
    }
}

if (!function_exists('escapeJs')) {
    /**
     * Escape output for JavaScript (JSON encoding with flags)
     * @param mixed $data Data to escape
     * @return string JSON-encoded and escaped output
     */
    function escapeJs($data) {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('escapeAttribute')) {
    /**
     * Escape output for HTML attributes
     * @param string $string Output to escape
     * @return string Escaped output
     */
    function escapeAttribute($string) {
        if ($string === null) {
            return '';
        }
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}











<?php
/**
 * Security Headers Module
 * 
 * Sets security-related HTTP headers to protect against various attacks
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/security/headers.php';
 * 
 * setSecurityHeaders(); // Call at the start of every request
 */

/**
 * Set all security headers
 * Call this function at the beginning of every request (before any output)
 * 
 * @return void
 */
if (!function_exists('setSecurityHeaders')) {
    function setSecurityHeaders() {
        // Prevent headers from being sent twice
        if (headers_sent()) {
            return;
        }
        
        // X-Content-Type-Options: Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // X-Frame-Options: Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // X-XSS-Protection: Enable browser XSS filter
        header('X-XSS-Protection: 1; mode=block');
        
        // Referrer-Policy: Control referrer information
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content-Security-Policy: Prevent XSS and other attacks
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://www.google.com; " .
               "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https:; " .
               "font-src 'self' https://cdnjs.cloudflare.com; " .
               "connect-src 'self'; " .
               "frame-ancestors 'none';";
        header("Content-Security-Policy: {$csp}");
        
        // Strict-Transport-Security: Force HTTPS (only in production with HTTPS)
        if (isProductionEnvironment() && 
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
        
        // Permissions-Policy: Control browser features
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        
        // Remove X-Powered-By header (if possible)
        header_remove('X-Powered-By');
    }
}

/**
 * Set CORS headers (if needed for API endpoints)
 * 
 * @param array $allowedOrigins Allowed origins (default: same origin only)
 * @param array $allowedMethods Allowed HTTP methods
 * @param array $allowedHeaders Allowed headers
 * @return void
 */
if (!function_exists('setCorsHeaders')) {
    function setCorsHeaders($allowedOrigins = [], $allowedMethods = ['GET', 'POST'], $allowedHeaders = ['Content-Type']) {
        if (headers_sent()) {
            return;
        }
        
        // Get origin from request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // If no allowed origins specified, allow same origin only
        if (empty($allowedOrigins)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $allowedOrigins = [$protocol . $host];
        }
        
        // Check if origin is allowed
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
        }
        
        // Set allowed methods
        if (!empty($allowedMethods)) {
            header('Access-Control-Allow-Methods: ' . implode(', ', $allowedMethods));
        }
        
        // Set allowed headers
        if (!empty($allowedHeaders)) {
            header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
        }
        
        // Set max age for preflight requests
        header('Access-Control-Max-Age: 86400'); // 24 hours
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
}

/**
 * Force HTTPS redirect in production
 * 
 * @return void
 */
if (!function_exists('forceHttps')) {
    function forceHttps() {
        if (!isProductionEnvironment()) {
            return; // Only force HTTPS in production
        }
        
        // Check if already using HTTPS
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        
        if (!$isHttps) {
            $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: {$url}", true, 301);
            exit();
        }
    }
}
?>







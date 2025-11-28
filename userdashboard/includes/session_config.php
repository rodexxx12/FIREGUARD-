<?php
/**
 * Secure Session Configuration
 * 
 * Configures PHP sessions with security best practices
 * 
 * NOTE: This file should be included AFTER session_start() is called
 * It only configures session settings, it doesn't start sessions
 */

if (!function_exists('configureSecureSession')) {
    function configureSecureSession() {
        // Only configure if session is not already active
        // If session is active, just update activity timestamp
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Just update activity - don't reconfigure
            if (isset($_SESSION)) {
                $_SESSION['last_activity'] = time();
            }
            return true;
        }
        
        // Determine if HTTPS is enabled (check environment variable first)
        $httpsEnabled = getenv('HTTPS_ENABLED');
        $isHttps = false;
        
        if ($httpsEnabled !== false) {
            $isHttps = strtolower($httpsEnabled) === 'true';
        } else {
            // Auto-detect HTTPS (but default to false for local development)
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $isLocalhost = (strpos($host, 'localhost') !== false || 
                           strpos($host, '127.0.0.1') !== false);
            
            if (!$isLocalhost) {
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                           (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                           (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
            }
        }
        
        // Configure session settings BEFORE starting (only if not started)
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', 7200); // 2 hours
            ini_set('session.cookie_lifetime', 0); // Until browser closes
            ini_set('session.use_strict_mode', '1');
            
            // DON'T change session name - keep default to avoid conflicts
            // Session name should be set before session_start() if needed
            
            session_start();
            
            // Initialize session tracking (only on first start)
            if (!isset($_SESSION['_session_configured'])) {
                $_SESSION['created'] = time();
                $_SESSION['last_activity'] = time();
                $_SESSION['_session_configured'] = true;
            }
        }
        
        // Update activity timestamp (always)
        if (isset($_SESSION)) {
            $_SESSION['last_activity'] = time();
            
            // Check session timeout (only warn, don't destroy automatically)
            // Let the application decide when to expire sessions
            if (isset($_SESSION['last_activity']) && 
                isset($_SESSION['created']) &&
                (time() - $_SESSION['last_activity']) > 7200) {
                // Session inactive for 2 hours - but don't destroy here
                // Let individual pages/APIs handle session expiration
                // $_SESSION['_session_warning'] = true;
            }
            
            // Regenerate session ID periodically (every 30 minutes)
            if (isset($_SESSION['created']) && (time() - $_SESSION['created']) > 1800) {
                @session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
        
        return true;
    }
}

// Auto-configure session (only if not already started)
// This is safe to call multiple times
if (session_status() === PHP_SESSION_NONE) {
    configureSecureSession();
} else {
    // Session already started, just update activity
    if (isset($_SESSION)) {
        $_SESSION['last_activity'] = time();
    }
}

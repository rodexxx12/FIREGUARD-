<?php
/**
 * Secure Session Handler
 * 
 * Provides secure session management with proper security flags
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/session/session.php';
 * 
 * // Session is automatically started when this file is included
 * $_SESSION['user_id'] = 123;
 */

// Load configuration
if (!defined('SESSION_LIFETIME')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Initialize secure session
 * Call this at the start of every request before using $_SESSION
 * 
 * @return void
 */
if (!function_exists('initSecureSession')) {
    function initSecureSession() {
        // Don't start if already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Get session configuration
        $sessionLifetime = config('session.lifetime', SESSION_LIFETIME);
        $sessionSecure = config('session.secure', isProductionEnvironment());
        $sessionHttpOnly = config('session.httponly', true);
        $sessionSameSite = config('session.samesite', 'Strict');
        
        // Configure session parameters BEFORE starting session
        ini_set('session.cookie_lifetime', $sessionLifetime);
        ini_set('session.cookie_secure', $sessionSecure ? '1' : '0');
        ini_set('session.cookie_httponly', $sessionHttpOnly ? '1' : '0');
        ini_set('session.cookie_samesite', $sessionSameSite);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_path', '/');
        
        // Set session name
        $sessionName = config('session.name', 'PHPSESSID');
        session_name($sessionName);
        
        // Start session
        session_start();
        
        // Regenerate session ID if needed (to prevent session fixation)
        if (!isset($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > SESSION_REGENERATE_INTERVAL) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check session timeout
 * 
 * @param int $timeoutMinutes Timeout in minutes (default: from config)
 * @return bool True if session is valid, false if expired
 */
if (!function_exists('checkSessionTimeout')) {
    function checkSessionTimeout($timeoutMinutes = null) {
        if ($timeoutMinutes === null) {
            $timeoutMinutes = config('session.lifetime', SESSION_LIFETIME) / 60;
        }
        
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $timeoutSeconds = $timeoutMinutes * 60;
        
        if (time() - $_SESSION['last_activity'] > $timeoutSeconds) {
            // Session expired
            destroySession();
            return false;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return true;
    }
}

/**
 * Destroy session securely
 * 
 * @return void
 */
if (!function_exists('destroySession')) {
    function destroySession() {
        // Clear all session variables
        $_SESSION = [];
        
        // Destroy the session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        
        // Destroy the session
        session_destroy();
    }
}

/**
 * Regenerate session ID (call after login or privilege escalation)
 * 
 * @return void
 */
if (!function_exists('regenerateSessionId')) {
    function regenerateSessionId() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }
}

/**
 * Validate session integrity (check for session hijacking indicators)
 * 
 * @return bool True if session appears valid
 */
if (!function_exists('validateSessionIntegrity')) {
    function validateSessionIntegrity() {
        // Check if session exists
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        // Check IP address (can be disabled if users have dynamic IPs)
        if (config('session.validate_ip', false)) {
            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $sessionIp = $_SESSION['ip_address'] ?? null;
            
            if ($sessionIp !== null && $sessionIp !== $currentIp) {
                // IP address changed - possible session hijacking
                destroySession();
                return false;
            }
            
            // Store IP address if not set
            if ($sessionIp === null) {
                $_SESSION['ip_address'] = $currentIp;
            }
        }
        
        // Check user agent (can be disabled if needed)
        if (config('session.validate_user_agent', false)) {
            $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $sessionUserAgent = $_SESSION['user_agent'] ?? null;
            
            if ($sessionUserAgent !== null && $sessionUserAgent !== $currentUserAgent) {
                // User agent changed - possible session hijacking
                destroySession();
                return false;
            }
            
            // Store user agent if not set
            if ($sessionUserAgent === null) {
                $_SESSION['user_agent'] = $currentUserAgent;
            }
        }
        
        return true;
    }
}

// Auto-initialize session when this file is included
initSecureSession();
?>


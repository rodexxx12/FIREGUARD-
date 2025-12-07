<?php
/**
 * Rate Limiting Functions
 * 
 * Provides rate limiting for API endpoints and form submissions
 */

if (!function_exists('checkRateLimit')) {
    /**
     * Check if action is within rate limit
     * @param string $action Action identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return bool True if allowed, false if rate limited
     */
    function checkRateLimit($action, $maxAttempts = 10, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'rate_limit_' . $action;
        
        // Use both session and IP-based tracking
        $sessionKey = $key;
        $ipKey = $key . '_ip_' . md5($ip);
        
        $now = time();
        
        // Check session-based rate limit
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [];
        }
        
        // Remove old entries outside time window
        $_SESSION[$sessionKey] = array_filter($_SESSION[$sessionKey], function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // Check if limit exceeded
        if (count($_SESSION[$sessionKey]) >= $maxAttempts) {
            error_log("Rate limit exceeded: $action from IP: $ip (Session-based)");
            return false;
        }
        
        // Add current attempt
        $_SESSION[$sessionKey][] = $now;
        
        // Also track by IP (optional, for stricter rate limiting)
        // This requires a shared storage (Redis, database, etc.) for production
        // For now, we'll rely on session-based tracking
        
        return true;
    }
}

if (!function_exists('getRateLimitRemaining')) {
    /**
     * Get remaining rate limit attempts
     * @param string $action Action identifier
     * @param int $maxAttempts Maximum attempts allowed
     * @param int $timeWindow Time window in seconds
     * @return int Remaining attempts
     */
    function getRateLimitRemaining($action, $maxAttempts = 10, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            return $maxAttempts;
        }
        
        // Remove old entries
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        return max(0, $maxAttempts - count($_SESSION[$key]));
    }
}

if (!function_exists('getRateLimitResetTime')) {
    /**
     * Get time when rate limit will reset
     * @param string $action Action identifier
     * @param int $timeWindow Time window in seconds
     * @return int Unix timestamp when limit resets
     */
    function getRateLimitResetTime($action, $timeWindow = 300) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        
        if (!isset($_SESSION[$key]) || empty($_SESSION[$key])) {
            return time();
        }
        
        $oldestAttempt = min($_SESSION[$key]);
        return $oldestAttempt + $timeWindow;
    }
}






























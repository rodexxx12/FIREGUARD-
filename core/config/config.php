<?php
/**
 * Configuration Manager
 * 
 * Provides centralized configuration access with support for:
 * - Environment variables (from .env file)
 * - Constants
 * - Dot notation for nested config keys
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * $lifetime = config('session.lifetime', SESSION_LIFETIME);
 */

// Load environment variables first
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

// Load constants
if (!defined('SESSION_LIFETIME')) {
    require_once __DIR__ . '/constants.php';
}

/**
 * Get configuration value with optional default
 * 
 * Supports dot notation for nested keys (e.g., 'session.lifetime')
 * Checks environment variables first, then falls back to default
 * 
 * @param string $key Configuration key (supports dot notation)
 * @param mixed $default Default value if not found
 * @return mixed Configuration value or default
 */
if (!function_exists('config')) {
    function config($key, $default = null) {
        // Handle dot notation (e.g., 'session.lifetime' -> 'SESSION_LIFETIME')
        $envKey = strtoupper(str_replace('.', '_', $key));
        
        // Try environment variable first
        if (function_exists('env')) {
            $envValue = env($envKey);
            if ($envValue !== null) {
                // Convert string booleans to actual booleans
                if (is_string($envValue)) {
                    $lower = strtolower($envValue);
                    if ($lower === 'true' || $lower === '1') {
                        return true;
                    }
                    if ($lower === 'false' || $lower === '0') {
                        return false;
                    }
                }
                return $envValue;
            }
        }
        
        // Try to get from constants if key matches a constant name
        if (defined($envKey)) {
            return constant($envKey);
        }
        
        // Handle specific session config keys
        if (strpos($key, 'session.') === 0) {
            $sessionKey = substr($key, 8); // Remove 'session.' prefix
            
            switch ($sessionKey) {
                case 'lifetime':
                    return env('SESSION_LIFETIME') ?: (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : $default);
                case 'secure':
                    $secure = env('SESSION_SECURE');
                    if ($secure !== null) {
                        return filter_var($secure, FILTER_VALIDATE_BOOLEAN);
                    }
                    return $default;
                case 'httponly':
                    $httponly = env('SESSION_HTTPONLY');
                    if ($httponly !== null) {
                        return filter_var($httponly, FILTER_VALIDATE_BOOLEAN);
                    }
                    return $default !== null ? $default : true;
                case 'samesite':
                    return env('SESSION_SAMESITE') ?: ($default !== null ? $default : 'Strict');
                case 'name':
                    return env('SESSION_NAME') ?: ($default !== null ? $default : 'PHPSESSID');
                case 'validate_ip':
                    $validate = env('SESSION_VALIDATE_IP');
                    if ($validate !== null) {
                        return filter_var($validate, FILTER_VALIDATE_BOOLEAN);
                    }
                    return $default !== null ? $default : false;
                case 'validate_user_agent':
                    $validate = env('SESSION_VALIDATE_USER_AGENT');
                    if ($validate !== null) {
                        return filter_var($validate, FILTER_VALIDATE_BOOLEAN);
                    }
                    return $default !== null ? $default : false;
            }
        }
        
        // Return default if nothing found
        return $default;
    }
}
?>

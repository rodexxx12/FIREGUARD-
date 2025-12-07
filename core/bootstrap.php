<?php
/**
 * Master Bootstrap File
 * 
 * Include this file at the start of EVERY PHP file in your application
 * It automatically loads all security, database, session, and core modules
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/bootstrap.php';
 * 
 * That's it! Everything is loaded and configured.
 */

// Track request start time for basic profiling (does not change app logic)
if (!isset($GLOBALS['APP_REQUEST_START'])) {
    $GLOBALS['APP_REQUEST_START'] = microtime(true);
}

// Prevent direct access
if (!defined('BOOTSTRAP_LOADED')) {
    define('BOOTSTRAP_LOADED', true);
    
    // Set timezone
    date_default_timezone_set('Asia/Manila');
    
    // Load configuration first
    require_once __DIR__ . '/config/config.php';
    
    // Load database connection
    require_once __DIR__ . '/database/database.php';
    
    // Load session handler (must be before headers)
    require_once __DIR__ . '/session/session.php';
    
    // Load security modules
    require_once __DIR__ . '/security/security.php';
    
    // Set security headers (must be before any output)
    setSecurityHeaders();
    
    // Force HTTPS in production (before any output)
    if (isProductionEnvironment()) {
        forceHttps();
    }
    
    // Configure error handling based on environment
    if (isProductionEnvironment() && !isDebugMode()) {
        // Production: Log errors but don't display
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        $logFile = LOG_DIR . 'php_errors.log';
        if (!is_dir(LOG_DIR)) {
            @mkdir(LOG_DIR, 0755, true);
        }
        ini_set('error_log', $logFile);
    } else {
        // Development: Show errors for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
        ini_set('log_errors', '1');
    }
    
    // Load logger (for logging if needed)
    require_once __DIR__ . '/logger/logger.php';
    
    // Log application start (development only)
    if (isDevelopmentEnvironment() && function_exists('logDebug')) {
        logDebug('Application bootstrap loaded', [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]);
    }
    
    // Register a shutdown profiler to log total request time and memory (debug/development only)
    if (!function_exists('profileRequest')) {
        function profileRequest() {
            if (!function_exists('isDebugMode') || !isDebugMode()) {
                return;
            }
            
            $start = $GLOBALS['APP_REQUEST_START'] ?? ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            $durationMs = (microtime(true) - $start) * 1000;
            $memory = memory_get_peak_usage(true);
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            
            $data = [
                'uri' => $uri,
                'duration_ms' => round($durationMs, 2),
                'memory_bytes' => $memory,
            ];
            
            if (function_exists('logDebug')) {
                logDebug('Request profiling', $data);
            } else {
                error_log('Request profiling: ' . json_encode($data));
            }
        }
    }
    
    register_shutdown_function('profileRequest');
}


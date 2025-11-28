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
}


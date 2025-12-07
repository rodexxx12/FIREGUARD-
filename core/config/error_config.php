<?php
/**
 * Centralized Error Configuration
 * 
 * Include this file to set up environment-aware error handling
 * Uses APP_ENV and APP_DEBUG environment variables
 * 
 * Usage:
 * require_once __DIR__ . '/path/to/core/config/error_config.php';
 */

// Prevent direct access
if (!defined('ERROR_CONFIG_LOADED')) {
    define('ERROR_CONFIG_LOADED', true);
    
    // Determine environment
    $isProduction = (getenv('APP_ENV') === 'production' || 
                    (isset($_SERVER['HTTP_HOST']) && 
                     strpos($_SERVER['HTTP_HOST'], 'localhost') === false && 
                     strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false));
    
    // Check debug mode from environment variable
    $debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
    
    // Set error reporting based on environment
    error_reporting(E_ALL);
    
    if ($isProduction && !$debugMode) {
        // Production: Hide errors from users, log to file
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        
        // Set log file location
        $logDir = __DIR__ . '/../../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        ini_set('error_log', $logDir . '/php_errors.log');
    } else {
        // Development: Show errors for debugging
        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        ini_set('log_errors', '1');
    }
    
    // Hide PHP version
    ini_set('expose_php', '0');
}
?>



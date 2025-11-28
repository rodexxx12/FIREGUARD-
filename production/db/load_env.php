<?php
/**
 * Environment Configuration Loader for Production Module
 * 
 * UPDATED: Now integrates with centralized core/env.php if available
 * Falls back to local loading if core not available
 * 
 * This ensures compatibility with both centralized architecture and standalone production module
 */

// Try to use centralized environment loader first
$coreEnvPath = __DIR__ . '/../../core/config/env.php';
if (file_exists($coreEnvPath)) {
    // Use centralized environment loader
    require_once $coreEnvPath;
    
    // The centralized env.php auto-loads from root-level .env file
    // All config.env files have been removed - use centralized .env only
    
    // Mark as loaded
    if (!function_exists('loadEnvironmentConfig')) {
        function loadEnvironmentConfig() {
            // Already loaded by centralized module
            return true;
        }
    }
} else {
    // Fallback to local environment loading if core not available
    if (!function_exists('loadEnvironmentConfig')) {
        function loadEnvironmentConfig() {
            $envFile = __DIR__ . '/config.env';
            
            if (!file_exists($envFile)) {
                // Check if example file exists to provide helpful message
                $exampleFile = __DIR__ . '/config.env.example';
                $message = "Configuration file (config.env) not found in " . __DIR__;
                
                if (file_exists($exampleFile)) {
                    $message .= ". Please copy config.env.example to config.env and configure it.";
                } else {
                    $message .= ". Please create config.env file with your database credentials.";
                }
                
                // In production, log error but don't expose details
                error_log("CRITICAL: " . $message);
                
                // For development, show helpful message
                $appEnv = getenv('APP_ENV') ?: (isset($_ENV['APP_ENV']) ? $_ENV['APP_ENV'] : 'local');
                if ($appEnv !== 'production') {
                    die($message);
                }
                
                // In production, use safe defaults or die gracefully
                die("System configuration error. Please contact administrator.");
            }
            
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    $value = trim($value, '"\'');

                    // Set environment variable if not already set
                    if (!getenv($key)) {
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                    }
                }
            }
            
            return true;
        }
    }
    
    // Auto-load on include (fallback mode)
    loadEnvironmentConfig();
}

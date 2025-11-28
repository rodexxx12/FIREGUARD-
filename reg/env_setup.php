<?php
/**
 * Environment Variables Setup Helper
 * 
 * UPDATED: Now uses centralized root-level .env file ONLY
 * All local config.env files should be removed
 * 
 * Usage:
 * 1. Ensure root-level .env file exists at project root
 * 2. Include this file in your PHP files: require_once 'env_setup.php';
 * 3. Or set environment variables directly on your server
 */

// Try to use centralized environment loader first
$coreEnvPath = __DIR__ . '/../../core/config/env.php';
if (file_exists($coreEnvPath)) {
    // Use centralized environment loader (loads from root-level .env)
    require_once $coreEnvPath;
} else {
    // Fallback: Load root-level .env file directly
    $rootEnvFile = __DIR__ . '/../../.env';
    if (file_exists($rootEnvFile)) {
        $lines = file($rootEnvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE format
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                // Set environment variable if not already set
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    } else {
        error_log("WARNING: Centralized .env file not found at: {$rootEnvFile}");
    }
}

?>


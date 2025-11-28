<?php
/**
 * Environment Variable Loader
 * 
 * Loads environment variables from .env file with validation
 * Prevents multiple loads and provides type-safe getters
 */

if (!function_exists('loadEnvironmentVariables')) {
    /**
     * Load environment variables from .env file
     * 
     * @param string $envPath Path to .env file (default: project root)
     * @return bool Success status
     */
    function loadEnvironmentVariables($envPath = null) {
        static $loaded = false;
        
        if ($loaded) {
            return true; // Already loaded
        }
        
        // Determine .env file path
        if ($envPath === null) {
            // Go up from core/config to project root (core/config -> core -> project root)
            $projectRoot = dirname(__DIR__, 2);
            $envPath = $projectRoot . DIRECTORY_SEPARATOR . '.env';
            
            // Normalize path (resolve any ../ components)
            $envPath = realpath($envPath) ?: $envPath;
            
            // If not found, try alternative locations
            if (!file_exists($envPath)) {
                $alternatives = [
                    dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env',
                    dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . '.env',
                    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env',
                ];
                
                foreach ($alternatives as $altPath) {
                    $normalized = realpath($altPath);
                    if ($normalized && file_exists($normalized)) {
                        $envPath = $normalized;
                        break;
                    }
                }
            }
        } else {
            // Normalize provided path
            $envPath = realpath($envPath) ?: $envPath;
        }
        
        // Check if .env file exists
        if (!file_exists($envPath)) {
            // Only log once to avoid spam
            static $warningLogged = false;
            if (!$warningLogged) {
                error_log("WARNING: .env file not found. Tried: " . $envPath);
                error_log("INFO: Expected location: " . dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env');
                $warningLogged = true;
            }
            return false;
        }
        
        // Read and parse .env file
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            error_log("ERROR: Failed to read .env file: {$envPath}");
            return false;
        }
        
        foreach ($lines as $line) {
            // Skip comments
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove quotes if present
            if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }
            
            // Set environment variable if not already set
            if (!isset($_ENV[$name])) {
                $_ENV[$name] = $value;
                putenv("{$name}={$value}");
            }
        }
        
        $loaded = true;
        return true;
    }
}

if (!function_exists('env')) {
    /**
     * Get environment variable with optional default
     * 
     * @param string $key Environment variable name
     * @param mixed $default Default value if not set
     * @return mixed Environment variable value or default
     */
    function env($key, $default = null) {
        // Ensure environment is loaded
        if (!isset($_ENV[$key]) && getenv($key) === false) {
            loadEnvironmentVariables();
        }
        
        // Try $_ENV first, then getenv()
        $value = $_ENV[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        return $value;
    }
}

if (!function_exists('envBool')) {
    /**
     * Get environment variable as boolean
     * 
     * @param string $key Environment variable name
     * @param bool $default Default value
     * @return bool
     */
    function envBool($key, $default = false) {
        $value = env($key, $default);
        
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower($value);
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        
        return (bool)$value;
    }
}

if (!function_exists('envInt')) {
    /**
     * Get environment variable as integer
     * 
     * @param string $key Environment variable name
     * @param int $default Default value
     * @return int
     */
    function envInt($key, $default = 0) {
        $value = env($key, $default);
        return (int)$value;
    }
}

if (!function_exists('envFloat')) {
    /**
     * Get environment variable as float
     * 
     * @param string $key Environment variable name
     * @param float $default Default value
     * @return float
     */
    function envFloat($key, $default = 0.0) {
        $value = env($key, $default);
        return (float)$value;
    }
}

if (!function_exists('isProductionEnvironment')) {
    /**
     * Check if running in production environment
     * 
     * @return bool
     */
    function isProductionEnvironment() {
        $env = strtolower(env('APP_ENV', 'production'));
        return $env === 'production' || $env === 'prod';
    }
}

if (!function_exists('isDevelopmentEnvironment')) {
    /**
     * Check if running in development environment
     * 
     * @return bool
     */
    function isDevelopmentEnvironment() {
        $env = strtolower(env('APP_ENV', 'production'));
        return $env === 'development' || $env === 'dev' || $env === 'local';
    }
}

// Auto-load environment variables when this file is included
loadEnvironmentVariables();
?>


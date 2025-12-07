<?php
// Load environment variables helper
// UPDATED: Now uses centralized root-level .env file ONLY
if (!function_exists('getEnvVar')) {
    function getEnvVar($key, $default = '') {
        // Try to use centralized environment loader first
        $coreEnvPath = __DIR__ . '/../../../core/config/env.php';
        if (file_exists($coreEnvPath)) {
            require_once $coreEnvPath;
            // Use centralized env() function
            if (function_exists('env')) {
                $value = env($key, $default);
                // If value found, return it
                if ($value !== $default || isset($_ENV[$key]) || getenv($key) !== false) {
                    return $value;
                }
            }
        }
        
        // Fallback: Load root-level .env file directly if variable not found
        $envPath = __DIR__ . '/../../../.env';
        if (file_exists($envPath)) {
            // Check if variable already loaded
            if (!isset($_ENV[$key]) && getenv($key) === false) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos(trim($line), '#') === 0) continue;
                    if (strpos($line, '=') !== false) {
                        list($name, $value) = explode('=', $line, 2);
                        $name = trim($name);
                        $value = trim($value);
                        // Remove quotes if present
                        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                            $value = substr($value, 1, -1);
                        }
                        $_ENV[$name] = $value;
                        putenv("{$name}={$value}");
                    }
                }
            }
        }
        
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

// Get environment variables and ensure they're properly trimmed
$apiKey = trim(getEnvVar('SMS_API_KEY', ''));
$deviceId = trim(getEnvVar('SMS_DEVICE_ID', ''));
$apiUrl = trim(getEnvVar('SMS_API_URL', 'https://sms.pagenet.info/api/v1/sms/send'));

// Remove any quotes that might have been left
if ((substr($apiKey, 0, 1) === '"' && substr($apiKey, -1) === '"') ||
    (substr($apiKey, 0, 1) === "'" && substr($apiKey, -1) === "'")) {
    $apiKey = substr($apiKey, 1, -1);
    $apiKey = trim($apiKey);
}
if ((substr($deviceId, 0, 1) === '"' && substr($deviceId, -1) === '"') ||
    (substr($deviceId, 0, 1) === "'" && substr($deviceId, -1) === "'")) {
    $deviceId = substr($deviceId, 1, -1);
    $deviceId = trim($deviceId);
}
if ((substr($apiUrl, 0, 1) === '"' && substr($apiUrl, -1) === '"') ||
    (substr($apiUrl, 0, 1) === "'" && substr($apiUrl, -1) === "'")) {
    $apiUrl = substr($apiUrl, 1, -1);
    $apiUrl = trim($apiUrl);
}

// Fallback to device/config.php if .env values are not set
if (empty($apiKey) || empty($deviceId)) {
    $deviceConfigPath = __DIR__ . '/../../../device/config.php';
    if (file_exists($deviceConfigPath)) {
        $deviceConfig = require $deviceConfigPath;
        if (empty($apiKey) && !empty($deviceConfig['api_key'])) {
            $apiKey = trim($deviceConfig['api_key']);
            error_log('SMS: Using API key from device/config.php as fallback');
        }
        if (empty($deviceId) && !empty($deviceConfig['device'])) {
            $deviceId = trim($deviceConfig['device']);
            error_log('SMS: Using device ID from device/config.php as fallback');
        }
        if (empty($apiUrl) && !empty($deviceConfig['url'])) {
            $apiUrl = trim($deviceConfig['url']);
        }
    }
}

// Check for configuration errors
$configErrors = [];
$isConfigured = true;

if (empty($apiKey)) {
    $configErrors[] = 'SMS_API_KEY is not configured in .env file or device/config.php';
    $isConfigured = false;
}

if (empty($deviceId)) {
    $configErrors[] = 'SMS_DEVICE_ID is not configured in .env file or device/config.php';
    $isConfigured = false;
}

if (empty($apiUrl)) {
    $configErrors[] = 'SMS_API_URL is not configured in .env file';
    $isConfigured = false;
}

// Log configuration errors for debugging
if (!$isConfigured) {
    error_log('SMS Configuration Error: ' . implode(', ', $configErrors));
}

return [
    'api_key' => $apiKey,
    'device'  => $deviceId,
    'url'     => $apiUrl,
    'is_configured' => $isConfigured,
    'errors' => $configErrors
];

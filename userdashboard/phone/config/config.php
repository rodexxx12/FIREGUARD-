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

return [
    'api_key' => getEnvVar('SMS_API_KEY', ''),
    'device'  => getEnvVar('SMS_DEVICE_ID', ''),
    'url'     => getEnvVar('SMS_API_URL', 'https://sms.pagenet.info/api/v1/sms/send')
];

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
                return env($key, $default);
            }
        }
        
        // Fallback: Load root-level .env file directly
        $envPath = __DIR__ . '/../../../.env';
        if (file_exists($envPath) && empty($_ENV)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $_ENV[trim($name)] = trim($value);
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

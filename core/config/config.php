<?php
/**
 * Main Configuration Loader
 * 
 * Central configuration file that loads all necessary modules
 * Include this file at the start of every PHP file in your application
 */

// Load environment variables first
require_once __DIR__ . '/env.php';

// Load constants
require_once __DIR__ . '/constants.php';

// Set error reporting based on environment
if (isDevelopmentEnvironment()) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    // Ensure LOG_DIR is defined before using it
    if (defined('LOG_DIR')) {
        ini_set('error_log', LOG_DIR . 'php_errors.log');
    } else {
        $logDir = __DIR__ . '/../../logs/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        ini_set('error_log', $logDir . 'php_errors.log');
    }
}

// Ensure log directory exists
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

// Ensure upload directory exists
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}

// Get configuration values from environment
$GLOBALS['app_config'] = [
    'app_name' => env('APP_NAME', APP_NAME),
    'app_env' => env('APP_ENV', 'production'),
    'app_debug' => envBool('APP_DEBUG', false),
    'app_url' => env('APP_URL', ''),
    
    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'name' => env('DB_NAME', ''),
        'user' => env('DB_USER', ''),
        'pass' => env('DB_PASS', ''),
        'charset' => DB_CHARSET,
    ],
    
    'session' => [
        'lifetime' => envInt('SESSION_LIFETIME', SESSION_LIFETIME),
        'secure' => envBool('SESSION_SECURE', isProductionEnvironment()),
        'httponly' => envBool('SESSION_HTTPONLY', true),
        'samesite' => env('SESSION_SAMESITE', 'Strict'),
    ],
    
    'security' => [
        'csrf_token_ttl' => envInt('CSRF_TOKEN_TTL', CSRF_TOKEN_TTL),
        'rate_limit_enabled' => envBool('RATE_LIMIT_ENABLED', true),
    ],
    
    'mail' => [
        'smtp_host' => env('SMTP_HOST', ''),
        'smtp_port' => envInt('SMTP_PORT', 587),
        'smtp_user' => env('SMTP_USER', ''),
        'smtp_pass' => env('SMTP_PASS', ''),
        'from_email' => env('MAIL_FROM_EMAIL', ''),
        'from_name' => env('MAIL_FROM_NAME', APP_NAME),
    ],
];

/**
 * Get configuration value
 * 
 * @param string $key Dot-notation key (e.g., 'db.host')
 * @param mixed $default Default value if not found
 * @return mixed
 */
function config($key, $default = null) {
    $keys = explode('.', $key);
    $value = $GLOBALS['app_config'];
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return $default;
        }
        $value = $value[$k];
    }
    
    return $value;
}

/**
 * Check if application is in debug mode
 * 
 * @return bool
 */
function isDebugMode() {
    return config('app_debug', false) && isDevelopmentEnvironment();
}

/**
 * Get application URL
 * 
 * @param string $path Optional path to append
 * @return string
 */
function appUrl($path = '') {
    $baseUrl = config('app_url', '');
    if (empty($baseUrl)) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . $host;
    }
    
    if (!empty($path)) {
        $baseUrl .= '/' . ltrim($path, '/');
    }
    
    return $baseUrl;
}
?>


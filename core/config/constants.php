<?php
/**
 * Application Constants
 * 
 * Centralized constants for the entire application
 * All constants are wrapped with defined() checks to prevent duplicate definition warnings
 */

// Application Information
if (!defined('APP_NAME')) {
    define('APP_NAME', 'FIREGUARD');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

// Timezone
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Manila');
}
date_default_timezone_set(APP_TIMEZONE);

// Database Constants
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}
if (!defined('DB_COLLATION')) {
    define('DB_COLLATION', 'utf8mb4_unicode_ci');
}

// Security Constants
if (!defined('CSRF_TOKEN_TTL')) {
    define('CSRF_TOKEN_TTL', 3600); // 1 hour
}
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 1800); // 30 minutes
}
if (!defined('SESSION_REGENERATE_INTERVAL')) {
    define('SESSION_REGENERATE_INTERVAL', 300); // 5 minutes
}

// Password Constants
if (!defined('PASSWORD_MIN_LENGTH')) {
    define('PASSWORD_MIN_LENGTH', 8);
}
if (!defined('PASSWORD_REQUIRE_UPPERCASE')) {
    define('PASSWORD_REQUIRE_UPPERCASE', true);
}
if (!defined('PASSWORD_REQUIRE_LOWERCASE')) {
    define('PASSWORD_REQUIRE_LOWERCASE', true);
}
if (!defined('PASSWORD_REQUIRE_NUMBER')) {
    define('PASSWORD_REQUIRE_NUMBER', true);
}
if (!defined('PASSWORD_REQUIRE_SPECIAL')) {
    define('PASSWORD_REQUIRE_SPECIAL', true);
}

// File Upload Constants
if (!defined('MAX_UPLOAD_SIZE')) {
    define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
}
if (!defined('ALLOWED_IMAGE_TYPES')) {
    define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif']);
}
if (!defined('ALLOWED_DOCUMENT_TYPES')) {
    define('ALLOWED_DOCUMENT_TYPES', ['application/pdf']);
}
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/../../uploads/');
}

// Rate Limiting Constants
if (!defined('RATE_LIMIT_LOGIN_ATTEMPTS')) {
    define('RATE_LIMIT_LOGIN_ATTEMPTS', 5);
}
if (!defined('RATE_LIMIT_LOGIN_WINDOW')) {
    define('RATE_LIMIT_LOGIN_WINDOW', 900); // 15 minutes
}
if (!defined('RATE_LIMIT_REGISTRATION_ATTEMPTS')) {
    define('RATE_LIMIT_REGISTRATION_ATTEMPTS', 5);
}
if (!defined('RATE_LIMIT_REGISTRATION_WINDOW')) {
    define('RATE_LIMIT_REGISTRATION_WINDOW', 3600); // 1 hour
}
if (!defined('RATE_LIMIT_API_REQUESTS')) {
    define('RATE_LIMIT_API_REQUESTS', 100);
}
if (!defined('RATE_LIMIT_API_WINDOW')) {
    define('RATE_LIMIT_API_WINDOW', 3600); // 1 hour
}

// Logging Constants
if (!defined('LOG_DIR')) {
    define('LOG_DIR', __DIR__ . '/../../logs/');
}
if (!defined('LOG_LEVEL_DEBUG')) {
    define('LOG_LEVEL_DEBUG', 100);
}
if (!defined('LOG_LEVEL_INFO')) {
    define('LOG_LEVEL_INFO', 200);
}
if (!defined('LOG_LEVEL_WARNING')) {
    define('LOG_LEVEL_WARNING', 300);
}
if (!defined('LOG_LEVEL_ERROR')) {
    define('LOG_LEVEL_ERROR', 400);
}
if (!defined('LOG_LEVEL_CRITICAL')) {
    define('LOG_LEVEL_CRITICAL', 500);
}

// User Roles
if (!defined('ROLE_SUPERADMIN')) {
    define('ROLE_SUPERADMIN', 'superadmin');
}
if (!defined('ROLE_ADMIN')) {
    define('ROLE_ADMIN', 'admin');
}
if (!defined('ROLE_FIREFIGHTER')) {
    define('ROLE_FIREFIGHTER', 'firefighter');
}
if (!defined('ROLE_USER')) {
    define('ROLE_USER', 'user');
}

// Error Codes
if (!defined('ERROR_DATABASE_CONNECTION')) {
    define('ERROR_DATABASE_CONNECTION', 'DB_001');
}
if (!defined('ERROR_AUTHENTICATION_FAILED')) {
    define('ERROR_AUTHENTICATION_FAILED', 'AUTH_001');
}
if (!defined('ERROR_CSRF_TOKEN_INVALID')) {
    define('ERROR_CSRF_TOKEN_INVALID', 'CSRF_001');
}
if (!defined('ERROR_RATE_LIMIT_EXCEEDED')) {
    define('ERROR_RATE_LIMIT_EXCEEDED', 'RATE_001');
}
if (!defined('ERROR_VALIDATION_FAILED')) {
    define('ERROR_VALIDATION_FAILED', 'VAL_001');
}
if (!defined('ERROR_UNAUTHORIZED')) {
    define('ERROR_UNAUTHORIZED', 'AUTH_002');
}
if (!defined('ERROR_PERMISSION_DENIED')) {
    define('ERROR_PERMISSION_DENIED', 'AUTH_003');
}

// Success Messages
if (!defined('SUCCESS_LOGIN')) {
    define('SUCCESS_LOGIN', 'Login successful');
}
if (!defined('SUCCESS_LOGOUT')) {
    define('SUCCESS_LOGOUT', 'Logout successful');
}
if (!defined('SUCCESS_REGISTRATION')) {
    define('SUCCESS_REGISTRATION', 'Registration successful');
}
if (!defined('SUCCESS_UPDATE')) {
    define('SUCCESS_UPDATE', 'Update successful');
}
if (!defined('SUCCESS_DELETE')) {
    define('SUCCESS_DELETE', 'Delete successful');
}
?>


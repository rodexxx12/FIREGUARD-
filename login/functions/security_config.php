<?php
/**
 * Security Configuration File
 * 
 * This file contains all security-related configuration constants
 * and settings for the login system.
 */

// Session Security Configuration
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes
define('SESSION_REGENERATE_INTERVAL', 5 * 60); // Regenerate session ID every 5 minutes
define('SESSION_COOKIE_LIFETIME', 0); // Session cookie expires when browser closes
define('SESSION_COOKIE_SECURE', true); // Only send over HTTPS
define('SESSION_COOKIE_HTTPONLY', true); // Prevent JavaScript access
define('SESSION_COOKIE_SAMESITE', 'Strict'); // CSRF protection

// Rate Limiting Configuration
define('RATE_LIMIT_WINDOW', 60); // 1 minute window
define('RATE_LIMIT_MAX_REQUESTS', 10); // Max requests per window
define('RATE_LIMIT_MAX_LOGIN_ATTEMPTS', 5); // Max login attempts per window

// Brute Force Protection Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutes in seconds
define('EXTENDED_LOCKOUT_TIME', 60 * 60); // 1 hour for repeated violations
define('PROGRESSIVE_DELAY_BASE', 2); // Base delay in seconds
define('MAX_PROGRESSIVE_DELAY', 300); // Max delay: 5 minutes

// Password Security Configuration
define('MIN_PASSWORD_LENGTH', 8);
define('MAX_PASSWORD_LENGTH', 128);
define('PASSWORD_REQUIRE_UPPERCASE', false);
define('PASSWORD_REQUIRE_LOWERCASE', false);
define('PASSWORD_REQUIRE_NUMBERS', false);
define('PASSWORD_REQUIRE_SYMBOLS', false);

// Username Security Configuration
define('MIN_USERNAME_LENGTH', 3);
define('MAX_USERNAME_LENGTH', 30);
define('USERNAME_ALLOWED_CHARS', '/^[a-zA-Z0-9_]+$/');

// Remember Me Configuration
define('REMEMBER_ME_EXPIRE_DAYS', 30);
define('REMEMBER_TOKEN_LENGTH', 32);

// Security Headers Configuration
define('ENABLE_SECURITY_HEADERS', true);
define('ENABLE_CSP', true);
define('ENABLE_HSTS', true);

// Logging Configuration
define('LOG_SECURITY_EVENTS', true);
define('LOG_FAILED_LOGINS', true);
define('LOG_SQL_INJECTION_ATTEMPTS', true);
define('LOG_SESSION_EVENTS', true);

// Database Security Configuration
define('DB_USE_PREPARED_STATEMENTS', true);
define('DB_ESCAPE_ALL_INPUTS', true);
define('DB_LOG_QUERIES', false); // Set to true for debugging

// IP Security Configuration
define('ENABLE_IP_VALIDATION', true);
define('ENABLE_PROXY_DETECTION', true);
define('BLOCK_PRIVATE_IPS', false); // Set to true in production

// CSRF Protection Configuration
define('CSRF_TOKEN_LENGTH', 32);
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

// File Upload Security (if applicable)
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);
define('UPLOAD_PATH', '/secure/uploads/');

// Email Security Configuration
define('EMAIL_RATE_LIMIT', 5); // Max emails per hour per IP
define('EMAIL_RATE_WINDOW', 3600); // 1 hour window

// Security Event Types
define('SECURITY_EVENT_TYPES', [
    'failed_login',
    'successful_login',
    'sql_injection_attempt',
    'session_timeout',
    'session_integrity_failure',
    'user_logout',
    'inactive_account_login',
    'unavailable_account_login',
    'rate_limit_exceeded',
    'csrf_token_invalid',
    'suspicious_activity'
]);

// Security Levels
define('SECURITY_LEVEL_LOW', 1);
define('SECURITY_LEVEL_MEDIUM', 2);
define('SECURITY_LEVEL_HIGH', 3);
define('SECURITY_LEVEL_CRITICAL', 4);

// Current Security Level (adjust based on environment)
define('CURRENT_SECURITY_LEVEL', SECURITY_LEVEL_MEDIUM);

// Security Monitoring Configuration
define('MONITOR_LOGIN_PATTERNS', true);
define('MONITOR_SESSION_ANOMALIES', true);
define('MONITOR_IP_REPUTATION', false); // Requires external service
define('ALERT_ON_MULTIPLE_FAILURES', true);
define('ALERT_THRESHOLD', 10); // Alert after 10 failed attempts from same IP

// Backup and Recovery Configuration
define('SECURITY_BACKUP_ENABLED', true);
define('SECURITY_BACKUP_INTERVAL', 24 * 60 * 60); // 24 hours
define('SECURITY_BACKUP_RETENTION', 30 * 24 * 60 * 60); // 30 days

// Development vs Production Settings
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    // Relaxed security for development
    define('SESSION_TIMEOUT', 2 * 60 * 60); // 2 hours
    define('RATE_LIMIT_MAX_REQUESTS', 100);
    define('MAX_LOGIN_ATTEMPTS', 20);
    define('LOCKOUT_TIME', 5 * 60); // 5 minutes
    define('LOG_SECURITY_EVENTS', false);
} elseif (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
    // Strict security for production
    define('SESSION_TIMEOUT', 15 * 60); // 15 minutes
    define('RATE_LIMIT_MAX_REQUESTS', 5);
    define('MAX_LOGIN_ATTEMPTS', 3);
    define('LOCKOUT_TIME', 30 * 60); // 30 minutes
    define('LOG_SECURITY_EVENTS', true);
    define('ENABLE_HSTS', true);
    define('SESSION_COOKIE_SECURE', true);
}

<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the testing environment before running tests
 */

declare(strict_types=1);

// Load PHPUnit stub if PHPUnit is not installed
if (!class_exists('PHPUnit\Framework\TestCase')) {
    require_once __DIR__ . '/phpunit-stub.php';
}

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Set timezone
date_default_timezone_set('UTC');

// Define test environment
define('APP_ENV', 'testing');
define('APP_DEBUG', false);
define('TESTING', true);

// Set base path
define('BASE_PATH', dirname(__DIR__));

// Load environment variables (if .env.testing exists)
$envFile = BASE_PATH . '/.env.testing';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load core configuration
require_once BASE_PATH . '/core/config/config.php';
require_once BASE_PATH . '/core/config/constants.php';

// Load core modules for testing
require_once BASE_PATH . '/core/database/database.php';
require_once BASE_PATH . '/core/security/input_sanitizer.php';
require_once BASE_PATH . '/core/security/csrf.php';
require_once BASE_PATH . '/core/security/xss.php';
require_once BASE_PATH . '/core/auth/authentication.php';
require_once BASE_PATH . '/core/validation/validator.php';
require_once BASE_PATH . '/core/rate_limit/rate_limiter.php';


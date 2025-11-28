<?php
/**
 * PHPUnit Bootstrap File
 * 
 * Sets up the testing environment before running tests
 */

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test environment
define('APP_ENV', 'testing');
define('TESTING', true);

// Set timezone
date_default_timezone_set('Asia/Manila');

// Load composer autoloader if available
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// Load core configuration
require_once __DIR__ . '/../core/config/config.php';

// Mock database for testing (can be overridden in tests)
if (!function_exists('getDatabaseConnection')) {
    require_once __DIR__ . '/../core/database/database.php';
}

// Load test helper functions
require_once __DIR__ . '/helpers/TestHelpers.php';

// Set up test database if needed
// You can override this in specific test files
if (getenv('DB_TEST_NAME')) {
    // Test database configuration can be set via environment variables
}


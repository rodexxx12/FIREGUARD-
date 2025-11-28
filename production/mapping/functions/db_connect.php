<?php
/**
 * Database Connection for Mapping Module
 * SECURITY: Production-ready error handling
 */

// Set error reporting based on environment
$appEnv = getenv('APP_ENV') ?: 'production';
if ($appEnv === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Include the centralized database connection
require_once __DIR__ . '/../../../db/db.php';

// This file now uses the centralized database connection
// The getDatabaseConnection() function is available from the main db/db.php file
?>
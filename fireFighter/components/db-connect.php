<?php
/**
 * Database Connection Include File
 * Include this file in any component that needs database access
 * 
 * Usage:
 * require_once('db-connect.php');
 * 
 * Available functions:
 * - getUserProfile($username)
 * - updateUserProfile($username, $data)
 * - checkUserExists($username)
 * - getUserSession()
 * - executeQuery($sql, $params)
 * - fetchSingle($sql, $params)
 * - fetchAll($sql, $params)
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration and helper functions
// Use multiple path attempts to ensure compatibility
$db_paths = [
    '../db/db.php',                    // From components directory
    '../../db/db.php',                 // From subdirectories of components
    dirname(__DIR__) . '/db/db.php',   // Absolute path from components
    __DIR__ . '/../db/db.php'          // Alternative absolute path
];

$db_included = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $db_included = true;
        break;
    }
}

if (!$db_included) {
    die('Database configuration file not found. Please check the file paths.');
}

// Optional: Add any component-specific database operations here
// Example: Get current user data for components
$currentUser = null;
if (isset($_SESSION['username'])) {
    $currentUser = getUserProfile($_SESSION['username']);
}

// Optional: Add security checks
if (!isset($_SESSION['username']) && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    // Redirect to login if not authenticated (uncomment if needed)
    // header('Location: ../../login/index.php');
    // exit();
}
?>

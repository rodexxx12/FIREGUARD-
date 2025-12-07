<?php
// Include the main database connection from production/db/db.php
// This path goes: functions -> profile -> production -> db/db.php
require_once __DIR__ . '/../../db/db.php';

// Verify that getDatabaseConnection exists
if (!function_exists('getDatabaseConnection')) {
    error_log("ERROR: getDatabaseConnection() function not found. Check database connection file path.");
    die("Database connection error. Please contact administrator.");
}

// Alias the main database connection function for profile use
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return getDatabaseConnection();
    }
}
?> 
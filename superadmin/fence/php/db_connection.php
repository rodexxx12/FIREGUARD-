<?php
// Security: Use centralized database connection with environment variables
// This file is included for backward compatibility
require_once __DIR__ . '/../../../db/db.php';

// Ensure we use the secure centralized connection
if (!function_exists('getDatabaseConnection')) {
    error_log("ERROR: getDatabaseConnection() not found. Check db/db.php inclusion.");
}
?> 
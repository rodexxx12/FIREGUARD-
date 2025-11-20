<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the centralized database connection
require_once __DIR__ . '/../../../db/db.php';

// This file now uses the centralized database connection
// The getDatabaseConnection() function is available from the main db/db.php file
?>
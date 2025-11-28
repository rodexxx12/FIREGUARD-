<?php
// Security: Use centralized database connection with environment variables
// Never hardcode credentials in source files
require_once __DIR__ . '/../../db/db.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    error_log("Database connection failed in superadmin_registration: " . $e->getMessage());
    die("System temporarily unavailable. Please contact administrator.");
}
?>

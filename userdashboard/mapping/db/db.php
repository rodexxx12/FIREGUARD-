<?php
// Include the centralized database configuration
require_once __DIR__ . '/../../db/db.php';

// Use the centralized database connection function
function getMappingDBConnection() {
    return getDatabaseConnection();
}

// For backward compatibility, create a global $conn variable
$conn = getDatabaseConnection();
?>

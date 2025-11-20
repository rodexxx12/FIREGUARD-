<?php
// Include the main database connection from db/db.php
require_once __DIR__ . '/../../db/db.php';

// Alias the main database connection function for profile use
function getDBConnection() {
    return getDatabaseConnection();
}
?> 
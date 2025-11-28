<?php
// Suppress error output for JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
if (!ob_get_level()) {
    ob_start();
}

require_once 'common/database_utils.php';

try {
    // Query to get offline devices (devices that haven't reported in the last 30 minutes)
    $query = "SELECT COUNT(*) as offline_devices 
              FROM devices 
              WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE) 
              OR last_activity IS NULL
              OR status = 'offline'";
    
    $result = DatabaseUtils::executeSingleQuery($query);
    
    DatabaseUtils::sendResponse(true, ['offline_devices' => (int)$result['offline_devices']]);
    
} catch (Exception $e) {
    DatabaseUtils::sendResponse(false, ['offline_devices' => 0], 'Failed to load offline devices', $e->getMessage());
}
?>

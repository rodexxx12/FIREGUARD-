<?php
// Environment-aware error handling for JSON responses
$isProduction = (getenv('APP_ENV') === 'production');
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', ($isProduction && !$debugMode) ? '0' : '1');
ini_set('log_errors', '1');

// Start output buffering to catch any accidental output
if (!ob_get_level()) {
    ob_start();
}

require_once 'common/database_utils.php';

try {
    // Query to get recent fire incidents (last 24 hours)
    $query = "SELECT COUNT(*) as recent_incidents 
              FROM fire_data 
              WHERE status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire') 
              AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $result = DatabaseUtils::executeSingleQuery($query);
    
    DatabaseUtils::sendResponse(true, ['recent_incidents' => (int)$result['recent_incidents']]);
    
} catch (Exception $e) {
    DatabaseUtils::sendResponse(false, ['recent_incidents' => 0], 'Failed to load recent incidents', $e->getMessage());
}
?>

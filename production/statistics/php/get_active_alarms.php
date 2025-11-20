<?php
require_once 'common/database_utils.php';

try {
    // Query to get active fire alarms (last 24 hours)
    $query = "SELECT COUNT(*) as active_alarms 
              FROM fire_data 
              WHERE status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire') 
              AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
    
    $result = DatabaseUtils::executeSingleQuery($query);
    
    DatabaseUtils::sendResponse(true, ['active_alarms' => (int)$result['active_alarms']]);
    
} catch (Exception $e) {
    DatabaseUtils::sendResponse(false, ['active_alarms' => 0], 'Failed to load active alarms', $e->getMessage());
}
?>

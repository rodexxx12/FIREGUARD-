<?php
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

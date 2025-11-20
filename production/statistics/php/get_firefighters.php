<?php
require_once 'common/database_utils.php';

try {
    // Get all firefighters with their basic information
    $sql = "SELECT 
                id,
                name,
                badge_number,
                rank,
                specialization
            FROM firefighters 
            WHERE availability = 1
            ORDER BY name ASC";
    
    $firefighters = DatabaseUtils::executeQuery($sql);
    
    DatabaseUtils::sendResponse(true, ['firefighters' => $firefighters], 'Firefighters loaded successfully');
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load firefighters', $e->getMessage());
}
?>

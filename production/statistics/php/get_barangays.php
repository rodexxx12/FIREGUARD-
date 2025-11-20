<?php
require_once 'common/database_utils.php';

// Start session to get user_id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get all barangays from the barangay table
    // This allows users to filter by any barangay, not just those with fire_data
    $sql = "SELECT 
                id, 
                barangay_name,
                latitude,
                longitude,
                ir_number
            FROM barangay 
            ORDER BY barangay_name ASC";
    
    $barangays = DatabaseUtils::executeQuery($sql, []);
    
    // Format the response to match expected structure
    $formattedBarangays = [];
    foreach ($barangays as $barangay) {
        $formattedBarangays[] = [
            'id' => $barangay['id'],
            'barangay_name' => $barangay['barangay_name'],
            'latitude' => $barangay['latitude'] ?? null,
            'longitude' => $barangay['longitude'] ?? null,
            'ir_number' => $barangay['ir_number'] ?? null
        ];
    }
    
    DatabaseUtils::sendResponse(true, ['barangays' => $formattedBarangays], 'Barangays loaded successfully');
    
} catch (Exception $e) {
    error_log("Get Barangays Error: " . $e->getMessage());
    error_log("Get Barangays Trace: " . $e->getTraceAsString());
    DatabaseUtils::sendError('Failed to load barangays', $e->getMessage());
}
?>

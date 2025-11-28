<?php
// Suppress error output for JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Start output buffering to catch any accidental output
if (!ob_get_level()) {
    ob_start();
}

// Register error handler to catch any errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_level() > 0) {
            ob_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error',
            'error' => $error['message']
        ]);
        exit;
    }
});

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
} catch (Throwable $e) {
    error_log("Get Barangays Error: " . $e->getMessage());
    error_log("Get Barangays Trace: " . $e->getTraceAsString());
    DatabaseUtils::sendError('Failed to load barangays', $e->getMessage());
}
?>

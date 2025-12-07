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
                longitude
            FROM barangay 
            ORDER BY barangay_name ASC";
    
    $barangays = DatabaseUtils::executeQuery($sql, []);
    
    // Ensure results is an array
    if (!is_array($barangays)) {
        $barangays = [];
    }
    
    // Format the response to match expected structure
    $formattedBarangays = [];
    foreach ($barangays as $barangay) {
        $formattedBarangays[] = [
            'id' => (int)($barangay['id'] ?? 0),
            'barangay_name' => $barangay['barangay_name'] ?? 'Unknown',
            'latitude' => $barangay['latitude'] ?? null,
            'longitude' => $barangay['longitude'] ?? null
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

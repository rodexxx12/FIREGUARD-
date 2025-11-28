<?php
require_once 'common/database_utils.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userRole = $_SESSION['user_role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array(strtolower($userRole), ['admin', 'superadmin', 'super_admin'], true)) {
    http_response_code(403);
    DatabaseUtils::sendError('Unauthorized access.');
}

try {
    // Test basic connectivity
    $testResult = DatabaseUtils::executeSingleQuery("SELECT 1 as test");
    
    // Check table counts
    $fireDataCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM fire_data")['count'];
    $barangayCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM barangay")['count'];
    $devicesCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM devices")['count'];
    $buildingsCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as count FROM buildings")['count'];
    
    DatabaseUtils::sendResponse(true, [
        'database_connection' => $testResult['test'] == 1 ? 'OK' : 'FAILED',
        'tables' => [
            'fire_data' => $fireDataCount > 0,
            'barangay' => $barangayCount > 0,
            'devices' => $devicesCount > 0,
            'buildings' => $buildingsCount > 0
        ]
    ], 'System health check completed');
    
} catch (Exception $e) {
    DatabaseUtils::sendError('System health check failed', $e->getMessage());
}
?>

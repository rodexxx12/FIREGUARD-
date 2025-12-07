<?php
// Environment-aware error handling for JSON responses
$isProduction = (getenv('APP_ENV') === 'production');
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', ($isProduction && !$debugMode) ? '0' : '1');
ini_set('log_errors', '1');

require_once 'common/database_utils.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id'])) {
    DatabaseUtils::sendError('Unauthorized access.');
}

try {
    // Get database connection
    $conn = DatabaseUtils::getConnection();
    
    // Get total users count - using prepared statement
    $usersStmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE status = 'Active'");
    $usersStmt->execute();
    $totalUsers = $usersStmt->fetch()['total_users'];

    // Get total buildings count - using prepared statement
    $buildingsStmt = $conn->prepare("SELECT COUNT(*) as total_buildings FROM buildings");
    $buildingsStmt->execute();
    $totalBuildings = $buildingsStmt->fetch()['total_buildings'];

    // Get total acknowledgments count (fire incidents) - using prepared statement
    $acknowledgmentsStmt = $conn->prepare("SELECT COUNT(*) as total_acknowledgments FROM acknowledgments");
    $acknowledgmentsStmt->execute();
    $totalAcknowledgments = $acknowledgmentsStmt->fetch()['total_acknowledgments'];

    // Get total active devices count - using prepared statement
    $devicesStmt = $conn->prepare("SELECT COUNT(*) as total_devices FROM devices WHERE is_active = ? AND status = ?");
    $devicesStmt->execute([1, 'online']);
    $totalDevices = $devicesStmt->fetch()['total_devices'];

    DatabaseUtils::sendResponse(true, [
        'total_users' => (int)$totalUsers,
        'total_buildings' => (int)$totalBuildings,
        'total_acknowledgments' => (int)$totalAcknowledgments,
        'total_devices' => (int)$totalDevices
    ], 'Summary statistics loaded successfully');
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Error fetching statistics', $e->getMessage());
}
?>
<?php
// Suppress error output for JSON responses
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

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
    
    // Get total users count
    $usersQuery = "SELECT COUNT(*) as total_users FROM users WHERE status = 'Active'";
    $usersResult = $conn->query($usersQuery);
    $totalUsers = $usersResult->fetch()['total_users'];

    // Get total buildings count
    $buildingsQuery = "SELECT COUNT(*) as total_buildings FROM buildings";
    $buildingsResult = $conn->query($buildingsQuery);
    $totalBuildings = $buildingsResult->fetch()['total_buildings'];

    // Get total acknowledgments count (fire incidents)
    $acknowledgmentsQuery = "SELECT COUNT(*) as total_acknowledgments FROM acknowledgments";
    $acknowledgmentsResult = $conn->query($acknowledgmentsQuery);
    $totalAcknowledgments = $acknowledgmentsResult->fetch()['total_acknowledgments'];

    // Get total active devices count
    $devicesQuery = "SELECT COUNT(*) as total_devices FROM devices WHERE is_active = 1 AND status = 'online'";
    $devicesResult = $conn->query($devicesQuery);
    $totalDevices = $devicesResult->fetch()['total_devices'];

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
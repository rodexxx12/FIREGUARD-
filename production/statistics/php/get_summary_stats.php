<?php
header('Content-Type: application/json');

// Include database connection
require_once '../../../db/db.php';

try {
    // Get database connection
    $conn = getDatabaseConnection();
    
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

    // Return the statistics
    echo json_encode([
        'success' => true,
        'total_users' => $totalUsers,
        'total_buildings' => $totalBuildings,
        'total_acknowledgments' => $totalAcknowledgments,
        'total_devices' => $totalDevices
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching statistics: ' . $e->getMessage()
    ]);
}
?>
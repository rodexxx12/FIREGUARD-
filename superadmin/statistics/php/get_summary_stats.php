<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

try {
    $conn = getDatabaseConnection();
    
    // Get summary statistics
    $summaryData = [];
    
    // Total fire alarms (fire_data with status indicating fire)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as total_alarms 
        FROM fire_data 
        WHERE status IN ('Fire Detected', 'Fire Alert', 'Critical Fire') 
        OR ml_prediction = 1
    ");
    $stmt->execute();
    $summaryData['total_alarms'] = $stmt->fetch()['total_alarms'];
    
    // Total active devices (both user devices and admin devices)
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM devices WHERE is_active = 1) + 
            (SELECT COUNT(*) FROM admin_devices WHERE status = 'approved') as total_devices
    ");
    $stmt->execute();
    $summaryData['total_devices'] = $stmt->fetch()['total_devices'];
    
    // Total buildings
    $stmt = $conn->prepare("SELECT COUNT(*) as total_buildings FROM buildings");
    $stmt->execute();
    $summaryData['total_buildings'] = $stmt->fetch()['total_buildings'];
    
    // Total users
    $stmt = $conn->prepare("SELECT COUNT(*) as total_users FROM users WHERE status = 'Active'");
    $stmt->execute();
    $summaryData['total_users'] = $stmt->fetch()['total_users'];
    
    echo json_encode([
        'success' => true,
        'data' => $summaryData
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_summary_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch summary statistics'
    ]);
}
?>

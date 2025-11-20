<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

require_once __DIR__ . '/../functions/database.php';

try {
    $user_id = $_SESSION['user_id'];
    $pdo = Database::getConnection();
    
    // Get the latest fire data for all devices owned by this user
    $sql = "SELECT fd.*, d.device_name, d.serial_number, b.building_name, b.building_type
            FROM fire_data fd
            JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings b ON d.building_id = b.id
            WHERE d.user_id = ?
            ORDER BY fd.timestamp DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $fire_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count active alerts (flame detected = 1)
    $active_alerts = array_filter($fire_data, function($data) {
        return $data['flame_detected'] == 1;
    });
    
    // Get latest reading for each device
    $latest_readings = [];
    $device_ids = array_unique(array_column($fire_data, 'device_id'));
    
    foreach ($device_ids as $device_id) {
        $device_data = array_filter($fire_data, function($data) use ($device_id) {
            return $data['device_id'] == $device_id;
        });
        
        if (!empty($device_data)) {
            $latest_readings[] = array_values($device_data)[0];
        }
    }
    
    echo json_encode([
        'success' => true,
        'active_alerts' => count($active_alerts),
        'total_devices' => count($device_ids),
        'latest_readings' => $latest_readings,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 
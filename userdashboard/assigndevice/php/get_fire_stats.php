<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include database connection directly
require_once __DIR__ . '/../db/db.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'User not authenticated'
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    
    // Get database connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Get comprehensive fire incident statistics
    $fireStatsQuery = "
        SELECT 
            COUNT(*) as total_fire_incidents,
            COUNT(CASE WHEN ml_prediction = 1 THEN 1 END) as ml_fire_predictions,
            COUNT(CASE WHEN flame_detected = 1 THEN 1 END) as flame_detections,
            COUNT(CASE WHEN smoke > 500 THEN 1 END) as high_smoke_readings,
            COUNT(CASE WHEN temp > 80 THEN 1 END) as high_temp_readings,
            COUNT(CASE WHEN heat > 100 THEN 1 END) as high_heat_readings,
            COUNT(CASE WHEN acknowledged_at_time IS NOT NULL THEN 1 END) as acknowledged_incidents,
            COUNT(CASE WHEN notified = 1 THEN 1 END) as notified_incidents,
            AVG(ml_confidence) as avg_ml_confidence,
            AVG(smoke) as avg_smoke_level,
            AVG(temp) as avg_temperature,
            AVG(heat) as avg_heat_level
        FROM fire_data
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND user_id = ?
    ";
    
    $fireStatsStmt = $pdo->prepare($fireStatsQuery);
    $fireStatsStmt->execute([$user_id]);
    $fireStats = $fireStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get device statistics
    $deviceStatsQuery = "
        SELECT 
            COUNT(*) as total_devices,
            COUNT(CASE WHEN status = 'online' THEN 1 END) as online_devices,
            COUNT(CASE WHEN status = 'offline' THEN 1 END) as offline_devices,
            COUNT(CASE WHEN status = 'faulty' THEN 1 END) as faulty_devices,
            COUNT(CASE WHEN building_id IS NOT NULL THEN 1 END) as assigned_devices,
            COUNT(CASE WHEN building_id IS NULL THEN 1 END) as unassigned_devices
        FROM devices
        WHERE user_id = ?
    ";
    
    $deviceStatsStmt = $pdo->prepare($deviceStatsQuery);
    $deviceStatsStmt->execute([$user_id]);
    $deviceStats = $deviceStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get building statistics
    $buildingStatsQuery = "
        SELECT 
            COUNT(*) as total_buildings,
            COUNT(CASE WHEN has_fire_alarm = 1 THEN 1 END) as buildings_with_alarms,
            COUNT(CASE WHEN has_sprinkler_system = 1 THEN 1 END) as buildings_with_sprinklers,
            COUNT(CASE WHEN has_fire_extinguishers = 1 THEN 1 END) as buildings_with_extinguishers,
            COUNT(CASE WHEN has_emergency_exits = 1 THEN 1 END) as buildings_with_exits,
            COUNT(CASE WHEN has_fire_escape = 1 THEN 1 END) as buildings_with_escape
        FROM buildings
        WHERE user_id = ?
    ";
    
    $buildingStatsStmt = $pdo->prepare($buildingStatsQuery);
    $buildingStatsStmt->execute([$user_id]);
    $buildingStats = $buildingStatsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent fire incidents (last 10)
    $recentIncidentsQuery = "
        SELECT 
            fd.id,
            fd.status,
            fd.smoke,
            fd.temp,
            fd.heat,
            fd.flame_detected,
            fd.ml_confidence,
            fd.ml_prediction,
            fd.timestamp,
            fd.notified,
            fd.acknowledged_at_time,
            d.device_name,
            d.device_number,
            b.building_name,
            b.building_type,
            b.address
        FROM fire_data fd
        LEFT JOIN devices d ON fd.device_id = d.device_id
        LEFT JOIN buildings b ON fd.building_id = b.id
        WHERE fd.user_id = ?
        ORDER BY fd.timestamp DESC
        LIMIT 10
    ";
    
    $recentIncidentsStmt = $pdo->prepare($recentIncidentsQuery);
    $recentIncidentsStmt->execute([$user_id]);
    $recentIncidents = $recentIncidentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get fire incidents by day (last 30 days for calendar)
    $calendarQuery = "
        SELECT 
            DATE(timestamp) as incident_date,
            COUNT(*) as incident_count,
            COUNT(CASE WHEN ml_prediction = 1 THEN 1 END) as fire_predictions,
            COUNT(CASE WHEN flame_detected = 1 THEN 1 END) as flame_detections,
            MAX(ml_confidence) as max_confidence,
            AVG(smoke) as avg_smoke,
            AVG(temp) as avg_temp,
            AVG(heat) as avg_heat
        FROM fire_data
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND user_id = ?
        GROUP BY DATE(timestamp)
        ORDER BY incident_date DESC
    ";
    
    $calendarStmt = $pdo->prepare($calendarQuery);
    $calendarStmt->execute([$user_id]);
    $calendarData = $calendarStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get building-wise fire incident counts
    $buildingIncidentsQuery = "
        SELECT 
            b.building_name,
            b.building_type,
            b.address,
            COUNT(fd.id) as incident_count,
            COUNT(CASE WHEN fd.ml_prediction = 1 THEN 1 END) as fire_predictions,
            COUNT(CASE WHEN fd.flame_detected = 1 THEN 1 END) as flame_detections,
            MAX(fd.ml_confidence) as max_confidence,
            AVG(fd.smoke) as avg_smoke,
            AVG(fd.temp) as avg_temp,
            AVG(fd.heat) as avg_heat
        FROM fire_data fd
        LEFT JOIN buildings b ON fd.building_id = b.id
        WHERE fd.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND fd.user_id = ?
        AND b.id IS NOT NULL
        GROUP BY b.id, b.building_name, b.building_type, b.address
        ORDER BY incident_count DESC
        LIMIT 5
    ";
    
    $buildingIncidentsStmt = $pdo->prepare($buildingIncidentsQuery);
    $buildingIncidentsStmt->execute([$user_id]);
    $buildingIncidents = $buildingIncidentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get device-wise fire incident counts
    $deviceIncidentsQuery = "
        SELECT 
            d.device_name,
            d.device_number,
            d.status,
            COUNT(fd.id) as incident_count,
            COUNT(CASE WHEN fd.ml_prediction = 1 THEN 1 END) as fire_predictions,
            COUNT(CASE WHEN fd.flame_detected = 1 THEN 1 END) as flame_detections,
            MAX(fd.ml_confidence) as max_confidence,
            AVG(fd.smoke) as avg_smoke,
            AVG(fd.temp) as avg_temp,
            AVG(fd.heat) as avg_heat
        FROM fire_data fd
        LEFT JOIN devices d ON fd.device_id = d.device_id
        WHERE fd.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND fd.user_id = ?
        AND d.device_id IS NOT NULL
        GROUP BY d.device_id, d.device_name, d.device_number, d.status
        ORDER BY incident_count DESC
        LIMIT 5
    ";
    
    $deviceIncidentsStmt = $pdo->prepare($deviceIncidentsQuery);
    $deviceIncidentsStmt->execute([$user_id]);
    $deviceIncidents = $deviceIncidentsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'fire_stats' => [
            'total_incidents' => (int)$fireStats['total_fire_incidents'],
            'ml_predictions' => (int)$fireStats['ml_fire_predictions'],
            'flame_detections' => (int)$fireStats['flame_detections'],
            'high_smoke_readings' => (int)$fireStats['high_smoke_readings'],
            'high_temp_readings' => (int)$fireStats['high_temp_readings'],
            'high_heat_readings' => (int)$fireStats['high_heat_readings'],
            'acknowledged_incidents' => (int)$fireStats['acknowledged_incidents'],
            'notified_incidents' => (int)$fireStats['notified_incidents'],
            'avg_ml_confidence' => round((float)$fireStats['avg_ml_confidence'], 2),
            'avg_smoke_level' => round((float)$fireStats['avg_smoke_level'], 1),
            'avg_temperature' => round((float)$fireStats['avg_temperature'], 1),
            'avg_heat_level' => round((float)$fireStats['avg_heat_level'], 1)
        ],
        'device_stats' => [
            'total_devices' => (int)$deviceStats['total_devices'],
            'online_devices' => (int)$deviceStats['online_devices'],
            'offline_devices' => (int)$deviceStats['offline_devices'],
            'faulty_devices' => (int)$deviceStats['faulty_devices'],
            'assigned_devices' => (int)$deviceStats['assigned_devices'],
            'unassigned_devices' => (int)$deviceStats['unassigned_devices']
        ],
        'building_stats' => [
            'total_buildings' => (int)$buildingStats['total_buildings'],
            'buildings_with_alarms' => (int)$buildingStats['buildings_with_alarms'],
            'buildings_with_sprinklers' => (int)$buildingStats['buildings_with_sprinklers'],
            'buildings_with_extinguishers' => (int)$buildingStats['buildings_with_extinguishers'],
            'buildings_with_exits' => (int)$buildingStats['buildings_with_exits'],
            'buildings_with_escape' => (int)$buildingStats['buildings_with_escape']
        ],
        'recent_incidents' => $recentIncidents,
        'calendar_data' => $calendarData,
        'building_incidents' => $buildingIncidents,
        'device_incidents' => $deviceIncidents
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching fire incident statistics: ' . $e->getMessage()
    ]);
}
?>

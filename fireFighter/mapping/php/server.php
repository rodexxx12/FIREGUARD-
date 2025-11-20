<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Include the database connection function
require_once '../functions/database_connection.php';

try {
    $conn = getDatabaseConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

try {
    // Get fire data with GPS coordinates prioritized
    $sql = "
    SELECT 
        f.id,
        f.building_id,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.status,
        f.timestamp,
        f.gps_latitude,
        f.gps_longitude,
        f.geo_lat,
        f.geo_long,
        -- Prioritize GPS coordinates, then fall back to geo_lat/geo_long
        COALESCE(f.gps_latitude, f.geo_lat) AS latitude,
        COALESCE(f.gps_longitude, f.geo_long) AS longitude,
        b.building_name,
        b.building_type,
        b.address
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    WHERE f.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    AND (f.gps_latitude IS NOT NULL OR f.gps_longitude IS NOT NULL OR f.geo_lat IS NOT NULL OR f.geo_long IS NOT NULL)
    ORDER BY f.timestamp DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $fireData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts for different statuses
    $countSql = "
    SELECT 
        status,
        COUNT(*) as count
    FROM fire_data 
    WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY status
    ";
    
    $countStmt = $conn->prepare($countSql);
    $countStmt->execute();
    $counts = $countStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent alerts
    $alertSql = "
    SELECT 
        f.id,
        f.temp,
        f.smoke,
        f.flame_detected,
        f.timestamp,
        b.building_type
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    WHERE f.status IN ('Emergency', 'Pre-Dispatch', 'Monitoring')
    AND f.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY f.timestamp DESC
    LIMIT 10
    ";
    
    $alertStmt = $conn->prepare($alertSql);
    $alertStmt->execute();
    $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format counts into associative array
    $countsArray = [
        'SAFE' => 0,
        'MONITORING' => 0,
        'PRE-DISPATCH' => 0,
        'EMERGENCY' => 0
    ];
    
    foreach ($counts as $count) {
        $status = strtoupper($count['status']);
        if (isset($countsArray[$status])) {
            $countsArray[$status] = (int)$count['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $fireData,
        'counts' => $countsArray,
        'alerts' => $alerts
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database query failed', 
        'error' => $e->getMessage()
    ]);
}
?> 
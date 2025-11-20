<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../functions/database_connection.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

try {
    $sql = "
        SELECT
            fd.id AS fire_data_id,
            fd.status,
            fd.timestamp,
            fd.acknowledged_at_time,
            fd.temp,
            fd.smoke,
            fd.heat,
            fd.flame_detected,
            fd.device_id,
            fd.building_id,
            fd.ml_confidence,
            fd.ml_prediction,
            fd.ai_prediction,
            -- Prioritize GPS coordinates from fire_data (gps_latitude, gps_longitude)
            -- Then fall back to geo_lat/geo_long, but NOT building coordinates
            COALESCE(fd.gps_latitude, fd.geo_lat) AS latitude,
            COALESCE(fd.gps_longitude, fd.geo_long) AS longitude,
            fd.gps_latitude,
            fd.gps_longitude,
            fd.geo_lat AS fire_data_latitude,
            fd.geo_long AS fire_data_longitude,
            b.building_name,
            b.address,
            d.device_name,
            d.device_number,
            d.serial_number,
            -- Calculate effective timestamp: use date from timestamp + acknowledged_at_time
            CASE 
                WHEN fd.acknowledged_at_time IS NOT NULL
                THEN CONCAT(DATE(fd.timestamp), ' ', fd.acknowledged_at_time)
                ELSE fd.timestamp
            END AS effective_timestamp
        FROM fire_data fd
        LEFT JOIN buildings b ON fd.building_id = b.id
        LEFT JOIN devices d ON fd.device_id = d.device_id
        WHERE UPPER(fd.status) = 'ACKNOWLEDGED'
        AND (
            fd.gps_latitude IS NOT NULL OR
            fd.gps_longitude IS NOT NULL OR
            fd.geo_lat IS NOT NULL OR
            fd.geo_long IS NOT NULL
        )
        ORDER BY 
            -- Order by date + acknowledged_at_time for most recent acknowledgment
            CASE 
                WHEN fd.acknowledged_at_time IS NOT NULL
                THEN CONCAT(DATE(fd.timestamp), ' ', fd.acknowledged_at_time)
                ELSE fd.timestamp
            END DESC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $acknowledged = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acknowledged) {
        echo json_encode([
            'success' => false,
            'message' => 'No fire_data with ACKNOWLEDGED status found'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => $acknowledged
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database query failed',
        'details' => $e->getMessage()
    ]);
}
?>


<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Environment-aware error handling
$isProduction = (getenv('APP_ENV') === 'production');
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', ($isProduction && !$debugMode) ? '0' : '1');

require_once '../functions/db_connect.php';
require_once '../functions/get_emergency_buildings.php';

try {
    $pdo = getDatabaseConnection();
    
    // Get the single most recent ACKNOWLEDGED status only
    $sql = "
    SELECT
        f.id as fire_data_id,
        f.status,
        f.building_type,
        f.timestamp,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.notified,
        f.user_id,
        -- Get GPS coordinates from fire_data table (prioritize fire_data's own GPS fields first)
        -- Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table
        COALESCE(
            f.gps_latitude, 
            f.geo_lat,
            g.latitude
        ) as latitude,
        COALESCE(
            f.gps_longitude, 
            f.geo_long,
            g.longitude
        ) as longitude,
        COALESCE(
            f.gps_latitude, 
            f.geo_lat,
            g.latitude
        ) as geo_lat,
        COALESCE(
            f.gps_longitude, 
            f.geo_long,
            g.longitude
        ) as geo_long,
        COALESCE(f.gps_altitude, g.altitude) as altitude,
        f.gps_latitude,
        f.gps_longitude,
        f.gps_altitude,
        f.device_id,
        f.building_id,
        f.ml_confidence,
        f.ml_prediction,
        f.ml_fire_probability,
        f.ai_prediction,
        f.acknowledged_at_time,
        f.ml_timestamp,
        f.barangay_id,
        d.device_name,
        d.device_number,
        d.serial_number,
        d.status as device_status,
        b.id as building_id_join,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM fire_data f
    LEFT JOIN devices d ON f.device_id = d.device_id
    LEFT JOIN buildings b ON f.building_id = b.id
    LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, STR_TO_DATE(f.timestamp, '%Y-%m-%d %H:%i:%s'))) <= 300
        AND g.latitude IS NOT NULL 
        AND g.longitude IS NOT NULL
        AND g.latitude != 0
        AND g.longitude != 0
        AND g.id = (
            SELECT g2.id
            FROM gps_data g2
            WHERE g2.latitude IS NOT NULL 
            AND g2.longitude IS NOT NULL
            AND g2.latitude != 0
            AND g2.longitude != 0
            AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, STR_TO_DATE(f.timestamp, '%Y-%m-%d %H:%i:%s'))) <= 300
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, STR_TO_DATE(f.timestamp, '%Y-%m-%d %H:%i:%s'))) ASC
            LIMIT 1
        )
    WHERE UPPER(f.status) = 'ACKNOWLEDGED'
    AND (
        f.gps_latitude IS NOT NULL OR 
        f.geo_lat IS NOT NULL OR 
        g.latitude IS NOT NULL
    )
    ORDER BY STR_TO_DATE(f.timestamp, '%Y-%m-%d %H:%i:%s') DESC
    LIMIT 1;
    ";

    $stmt = $pdo->query($sql);
    $acknowledgedDevice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$acknowledgedDevice) {
        echo json_encode([
            'success' => false,
            'message' => 'No fire_data with ACKNOWLEDGED status found',
            'data' => null
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Most recent ACKNOWLEDGED fire_data found',
        'data' => $acknowledgedDevice
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => null
    ]);
    exit;
}
?>











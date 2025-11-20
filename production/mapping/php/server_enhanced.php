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
require_once '../functions/db_connect.php';

// Get database connection
try {
    $conn = getDatabaseConnection();
    
    if (!$conn) {
        throw new Exception('Database connection failed');
    }
    
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    // Check if tables exist
    $tableCheckSql = "SHOW TABLES LIKE 'fire_data'";
    $tableStmt = $conn->prepare($tableCheckSql);
    $tableStmt->execute();
    $fireDataTableExists = $tableStmt->rowCount() > 0;
    
    $tableCheckSql2 = "SHOW TABLES LIKE 'buildings'";
    $tableStmt2 = $conn->prepare($tableCheckSql2);
    $tableStmt2->execute();
    $buildingsTableExists = $tableStmt2->rowCount() > 0;
    
    if (!$fireDataTableExists) {
        // Return sample data if fire_data table doesn't exist
        echo json_encode([
            'success' => true,
            'data' => getSampleFireData(),
            'counts' => [
                'SAFE' => 2,
                'MONITORING' => 1,
                'PRE-DISPATCH' => 0,
                'EMERGENCY' => 1
            ],
            'alerts' => [
                [
                    'id' => 1,
                    'temp' => 85,
                    'smoke' => 150,
                    'flame_detected' => 1,
                    'timestamp' => date('Y-m-d H:i:s'),
                    'building_type' => 'Commercial'
                ]
            ],
            'message' => 'Using sample data - fire_data table not found'
        ]);
        exit;
    }

    // Get fire data with building and device information
    // Use GPS coordinates directly from fire_data table (prioritize fire_data's own GPS fields)
    // Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
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
        f.device_id,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        f.acknowledged_at_time,
        b.building_name,
        b.building_type,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        d.device_name,
        d.device_number,
        d.status as device_status,
        g.ph_time as gps_time
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    LEFT JOIN devices d ON f.device_id = d.device_id
    LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
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
            AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
            LIMIT 1
        )
    WHERE f.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY f.timestamp DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $fireData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no recent data, get all data
    if (empty($fireData)) {
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
            f.device_id,
            f.ml_confidence,
            f.ml_prediction,
            f.ai_prediction,
            f.acknowledged_at_time,
            b.building_name,
            b.building_type,
            b.address,
            b.latitude as building_lat,
            b.longitude as building_lng,
            d.device_name,
            d.device_number,
            d.status as device_status,
            g.ph_time as gps_time
        FROM fire_data f
        LEFT JOIN buildings b ON f.building_id = b.id
        LEFT JOIN devices d ON f.device_id = d.device_id
        LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
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
                AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
                LIMIT 1
            )
        ORDER BY f.timestamp DESC
        LIMIT 50
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $fireData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // If still no data, use sample data
    if (empty($fireData)) {
        $fireData = getSampleFireData();
    }
    
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
    
    // If no counts, calculate from fire data
    if (array_sum($countsArray) === 0) {
        foreach ($fireData as $fire) {
            $status = strtoupper($fire['status']);
            if (isset($countsArray[$status])) {
                $countsArray[$status]++;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $fireData,
        'counts' => $countsArray,
        'alerts' => $alerts,
        'message' => 'Fire data retrieved successfully'
    ]);
    
} catch (PDOException $e) {
    // Return sample data on database error
    echo json_encode([
        'success' => true,
        'data' => getSampleFireData(),
        'counts' => [
            'SAFE' => 2,
            'MONITORING' => 1,
            'PRE-DISPATCH' => 0,
            'EMERGENCY' => 1
        ],
        'alerts' => [
            [
                'id' => 1,
                'temp' => 85,
                'smoke' => 150,
                'flame_detected' => 1,
                'timestamp' => date('Y-m-d H:i:s'),
                'building_type' => 'Commercial'
            ]
        ],
        'message' => 'Database error - using sample data: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Return sample data on any other error
    echo json_encode([
        'success' => true,
        'data' => getSampleFireData(),
        'counts' => [
            'SAFE' => 2,
            'MONITORING' => 1,
            'PRE-DISPATCH' => 0,
            'EMERGENCY' => 1
        ],
        'alerts' => [
            [
                'id' => 1,
                'temp' => 85,
                'smoke' => 150,
                'flame_detected' => 1,
                'timestamp' => date('Y-m-d H:i:s'),
                'building_type' => 'Commercial'
            ]
        ],
        'message' => 'System error - using sample data: ' . $e->getMessage()
    ]);
}

// Function to generate sample fire data
function getSampleFireData() {
    return [
        [
            'id' => 1,
            'building_id' => 1,
            'temp' => 75,
            'smoke' => 25,
            'heat' => 30,
            'flame_detected' => 0,
            'status' => 'SAFE',
            'timestamp' => date('Y-m-d H:i:s'),
            'geo_lat' => 10.525467693871333,
            'geo_long' => 122.84123838118607,
            'building_name' => 'Sample Building 1',
            'building_type' => 'Commercial',
            'address' => '123 Main Street, Bago City'
        ],
        [
            'id' => 2,
            'building_id' => 2,
            'temp' => 85,
            'smoke' => 150,
            'heat' => 180,
            'flame_detected' => 1,
            'status' => 'EMERGENCY',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
            'geo_lat' => 10.530000000000000,
            'geo_long' => 122.845000000000000,
            'building_name' => 'Sample Building 2',
            'building_type' => 'Residential',
            'address' => '456 Oak Avenue, Bago City'
        ],
        [
            'id' => 3,
            'building_id' => 3,
            'temp' => 65,
            'smoke' => 45,
            'heat' => 50,
            'flame_detected' => 0,
            'status' => 'MONITORING',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-15 minutes')),
            'geo_lat' => 10.520000000000000,
            'geo_long' => 122.840000000000000,
            'building_name' => 'Sample Building 3',
            'building_type' => 'Industrial',
            'address' => '789 Industrial Road, Bago City'
        ],
        [
            'id' => 4,
            'building_id' => 4,
            'temp' => 70,
            'smoke' => 20,
            'heat' => 25,
            'flame_detected' => 0,
            'status' => 'SAFE',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
            'geo_lat' => 10.535000000000000,
            'geo_long' => 122.850000000000000,
            'building_name' => 'Sample Building 4',
            'building_type' => 'Educational',
            'address' => '321 School Street, Bago City'
        ]
    ];
}
?> 
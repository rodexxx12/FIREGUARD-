<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../functions/device_location_validator.php';

// Check if user_id is set in session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get the latest fire_data record for the user
    // Use only gps_latitude and gps_longitude from fire_data table
    $sql = "
    SELECT 
        id,
        status,
        building_type,
        smoke,
        temp,
        heat,
        flame_detected,
        timestamp,
        user_id,
        building_id,
        gps_latitude,
        gps_longitude,
        gps_altitude,
        device_id,
        ml_confidence,
        ml_prediction,
        ml_fire_probability,
        ai_prediction,
        ml_timestamp,
        acknowledged_at_time,
        barangay_id
    FROM fire_data 
    WHERE user_id = :user_id 
    AND gps_latitude IS NOT NULL 
    AND gps_longitude IS NOT NULL
    ORDER BY timestamp DESC 
    LIMIT 1
    ";

    $pdo = getMappingDBConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $fireData = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fireData === false || empty($fireData)) {
        echo json_encode([
            'success' => false, 
            'error' => 'No fire data found for this user',
            'message' => 'No fire data with GPS coordinates found for your account.'
        ]);
        exit;
    }

    // Use GPS coordinates from fire_data table
    $latitude = (float)$fireData['gps_latitude'];
    $longitude = (float)$fireData['gps_longitude'];
    $device_id = $fireData['device_id'];

    // Validate GPS coordinates
    if (empty($latitude) || empty($longitude) || !is_numeric($latitude) || !is_numeric($longitude)) {
        echo json_encode([
            'success' => false, 
            'error' => 'No valid GPS coordinates found',
            'message' => 'Fire data found but no valid GPS coordinates available.'
        ]);
        exit;
    }

    // Validate that device is within a building radius
    if ($device_id) {
        // Get all buildings for this user with coordinates and radius
        $buildingsSql = "SELECT b.id, b.latitude, b.longitude, b.building_name,
                                COALESCE(ba.radius, 100.00) as radius
                         FROM buildings b
                         LEFT JOIN building_areas ba ON ba.building_id = b.id
                         WHERE b.user_id = ?
                         AND b.latitude IS NOT NULL 
                         AND b.longitude IS NOT NULL";
        
        $buildingsStmt = $pdo->prepare($buildingsSql);
        $buildingsStmt->execute([$userId]);
        $buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deviceWithinRadius = false;
        $matchedBuilding = null;
        
        if (!empty($buildings)) {
            // Check if device is within any building radius
            foreach ($buildings as $building) {
                $buildingLat = (float)$building['latitude'];
                $buildingLon = (float)$building['longitude'];
                $radius = (float)$building['radius'];
                
                $validation = isDeviceInBuildingRadius($latitude, $longitude, $buildingLat, $buildingLon, $radius);
                
                if ($validation['inside']) {
                    $deviceWithinRadius = true;
                    $matchedBuilding = $building;
                    break; // Found a match, no need to check further
                }
            }
        }
        
        // If device is not within any building radius, return error
        if (!$deviceWithinRadius) {
            echo json_encode([
                'success' => false,
                'error' => 'Device not within building radius',
                'message' => 'Device must be inside a building radius area to be located. Please move the device within a building\'s radius (100 meters) and try again.',
                'device_id' => $device_id,
                'gps_latitude' => $latitude,
                'gps_longitude' => $longitude
            ]);
            exit;
        }
    }

    // Return the fire data with coordinates
    $response = [
        'success' => true,
        'data' => [
            'id' => $fireData['id'],
            'status' => $fireData['status'],
            'building_type' => $fireData['building_type'],
            'smoke' => $fireData['smoke'],
            'temp' => $fireData['temp'],
            'heat' => $fireData['heat'],
            'flame_detected' => $fireData['flame_detected'],
            'timestamp' => $fireData['timestamp'],
            'user_id' => $fireData['user_id'],
            'building_id' => $fireData['building_id'],
            'device_id' => $fireData['device_id'],
            'latitude' => $latitude,
            'longitude' => $longitude,
            'gps_altitude' => $fireData['gps_altitude'],
            'ml_confidence' => $fireData['ml_confidence'],
            'ml_prediction' => $fireData['ml_prediction'],
            'ml_fire_probability' => $fireData['ml_fire_probability'],
            'ai_prediction' => $fireData['ai_prediction'],
            'ml_timestamp' => $fireData['ml_timestamp'],
            'acknowledged_at_time' => $fireData['acknowledged_at_time'],
            'barangay_id' => $fireData['barangay_id']
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in get_latest_fire_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Database query failed', 
        'message' => 'An error occurred while fetching fire data'
    ]);
}
?>


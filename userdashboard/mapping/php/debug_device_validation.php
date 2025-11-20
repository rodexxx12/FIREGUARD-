<?php
/**
 * Debug Script for Device Validation
 * 
 * This script helps diagnose why device.building_id is not being updated.
 */

session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../functions/device_location_validator.php';

header('Content-Type: application/json');

// Allow GET or POST
$device_id = $_GET['device_id'] ?? $_POST['device_id'] ?? null;
$building_id = $_GET['building_id'] ?? $_POST['building_id'] ?? null;

if (!$device_id) {
    echo json_encode([
        'error' => 'device_id is required',
        'usage' => '?device_id=1&building_id=2 or use POST'
    ]);
    exit;
}

$pdo = getMappingDBConnection();
$user_id = $_SESSION['user_id'] ?? null;

$debug = [
    'device_id' => $device_id,
    'building_id' => $building_id,
    'user_id' => $user_id,
    'checks' => []
];

// Check 1: Device exists and belongs to user
$deviceCheck = $pdo->prepare("SELECT device_id, user_id, device_name, building_id FROM devices WHERE device_id = ?");
$deviceCheck->execute([$device_id]);
$deviceData = $deviceCheck->fetch(PDO::FETCH_ASSOC);

if (!$deviceData) {
    echo json_encode([
        'error' => 'Device not found',
        'device_id' => $device_id
    ]);
    exit;
}

$debug['checks']['device_exists'] = true;
$debug['device_data'] = $deviceData;

if ($user_id && $deviceData['user_id'] != $user_id) {
    echo json_encode([
        'error' => 'Device does not belong to user',
        'device_user_id' => $deviceData['user_id'],
        'session_user_id' => $user_id
    ]);
    exit;
}

// Check 2: Device has GPS data in fire_data
$gpsCheck = $pdo->prepare("SELECT gps_latitude, gps_longitude, timestamp 
                           FROM fire_data 
                           WHERE device_id = ? 
                           AND gps_latitude IS NOT NULL 
                           AND gps_longitude IS NOT NULL
                           ORDER BY timestamp DESC 
                           LIMIT 1");
$gpsCheck->execute([$device_id]);
$gpsData = $gpsCheck->fetch(PDO::FETCH_ASSOC);

if (!$gpsData) {
    echo json_encode([
        'error' => 'Device has no GPS data in fire_data table',
        'device_id' => $device_id,
        'debug' => $debug
    ]);
    exit;
}

$debug['checks']['has_gps_data'] = true;
$debug['gps_data'] = $gpsData;

// Check 3: Get user's buildings
$user_id_for_buildings = $user_id ?? $deviceData['user_id'];
$buildingsCheck = $pdo->prepare("SELECT b.id, b.building_name, b.latitude, b.longitude, b.device_id as current_building_device_id,
                                         COALESCE(ba.radius, 100.00) as radius
                                  FROM buildings b
                                  LEFT JOIN building_areas ba ON ba.building_id = b.id
                                  WHERE b.user_id = ?
                                  AND b.latitude IS NOT NULL 
                                  AND b.longitude IS NOT NULL");
$buildingsCheck->execute([$user_id_for_buildings]);
$buildings = $buildingsCheck->fetchAll(PDO::FETCH_ASSOC);

if (empty($buildings)) {
    echo json_encode([
        'error' => 'No buildings with coordinates found for user',
        'user_id' => $user_id_for_buildings,
        'debug' => $debug
    ]);
    exit;
}

$debug['checks']['has_buildings'] = true;
$debug['buildings_count'] = count($buildings);

// Check 4: Calculate distances
$deviceLat = (float)$gpsData['gps_latitude'];
$deviceLon = (float)$gpsData['gps_longitude'];

$distances = [];
foreach ($buildings as $building) {
    $buildingLat = (float)$building['latitude'];
    $buildingLon = (float)$building['longitude'];
    $radius = (float)$building['radius'];
    
    require_once __DIR__ . '/../functions/device_location_validator.php';
    $validation = isDeviceInBuildingRadius($deviceLat, $deviceLon, $buildingLat, $buildingLon, $radius);
    
    $distances[] = [
        'building_id' => $building['id'],
        'building_name' => $building['building_name'],
        'distance' => round($validation['distance'], 2),
        'radius' => $radius,
        'inside' => $validation['inside']
    ];
}

$debug['distances'] = $distances;

// Check 5: If building_id provided, validate against it
if ($building_id) {
    $targetBuilding = null;
    foreach ($buildings as $building) {
        if ($building['id'] == $building_id) {
            $targetBuilding = $building;
            break;
        }
    }
    
    if (!$targetBuilding) {
        echo json_encode([
            'error' => 'Building not found or does not belong to user',
            'building_id' => $building_id,
            'debug' => $debug
        ]);
        exit;
    }
    
    $targetBuildingLat = (float)$targetBuilding['latitude'];
    $targetBuildingLon = (float)$targetBuilding['longitude'];
    $targetRadius = (float)$targetBuilding['radius'];
    
    $validation = isDeviceInBuildingRadius($deviceLat, $deviceLon, $targetBuildingLat, $targetBuildingLon, $targetRadius);
    
    if ($validation['inside']) {
        // Try to update
        $result = validateAndUpdateDeviceLocation($device_id, $building_id, $pdo, $user_id_for_buildings);
        
        // Verify update
        $verifyDevice = $pdo->prepare("SELECT building_id FROM devices WHERE device_id = ?");
        $verifyDevice->execute([$device_id]);
        $updatedDevice = $verifyDevice->fetch(PDO::FETCH_ASSOC);
        
        $verifyBuilding = $pdo->prepare("SELECT device_id FROM buildings WHERE id = ?");
        $verifyBuilding->execute([$building_id]);
        $updatedBuilding = $verifyBuilding->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => $result['success'],
            'message' => $result['message'],
            'validation_result' => $result,
            'verification' => [
                'devices.building_id' => $updatedDevice['building_id'],
                'buildings.device_id' => $updatedBuilding['device_id']
            ],
            'debug' => $debug
        ]);
    } else {
        echo json_encode([
            'error' => 'Device is outside building radius',
            'distance' => round($validation['distance'], 2),
            'radius' => $targetRadius,
            'debug' => $debug
        ]);
    }
} else {
    // Auto-validate
    $result = autoValidateDeviceLocation($device_id, $pdo);
    
    // Verify update
    if ($result['success']) {
        $verifyDevice = $pdo->prepare("SELECT building_id FROM devices WHERE device_id = ?");
        $verifyDevice->execute([$device_id]);
        $updatedDevice = $verifyDevice->fetch(PDO::FETCH_ASSOC);
        
        $verifyBuilding = $pdo->prepare("SELECT device_id FROM buildings WHERE id = ?");
        $verifyBuilding->execute([$result['building_id']]);
        $updatedBuilding = $verifyBuilding->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'validation_result' => $result,
            'verification' => [
                'devices.building_id' => $updatedDevice['building_id'],
                'buildings.device_id' => $updatedBuilding['device_id']
            ],
            'debug' => $debug
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['message'],
            'debug' => $debug
        ]);
    }
}
?>


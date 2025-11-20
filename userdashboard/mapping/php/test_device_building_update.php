<?php
/**
 * Simple Test Script for Device Building Update
 * 
 * Run this script to test if device.building_id gets updated.
 * Usage: http://your-domain/userdashboard/mapping/php/test_device_building_update.php?device_id=1
 */

session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../functions/device_location_validator.php';

$device_id = $_GET['device_id'] ?? 1; // Default to device_id 1 for testing

$pdo = getMappingDBConnection();

echo "<h2>Testing Device Building Update</h2>";
echo "<p>Device ID: <strong>{$device_id}</strong></p>";

// Get device info
$deviceStmt = $pdo->prepare("SELECT device_id, user_id, device_name, building_id FROM devices WHERE device_id = ?");
$deviceStmt->execute([$device_id]);
$device = $deviceStmt->fetch(PDO::FETCH_ASSOC);

if (!$device) {
    die("<p style='color:red;'>Device not found!</p>");
}

echo "<h3>Current Device Status:</h3>";
echo "<pre>";
print_r($device);
echo "</pre>";

// Check GPS data
$gpsStmt = $pdo->prepare("SELECT gps_latitude, gps_longitude, timestamp 
                          FROM fire_data 
                          WHERE device_id = ? 
                          AND gps_latitude IS NOT NULL 
                          AND gps_longitude IS NOT NULL
                          ORDER BY timestamp DESC 
                          LIMIT 1");
$gpsStmt->execute([$device_id]);
$gps = $gpsStmt->fetch(PDO::FETCH_ASSOC);

if (!$gps) {
    die("<p style='color:red;'>Device has no GPS data in fire_data table!</p>");
}

echo "<h3>Latest GPS Data:</h3>";
echo "<pre>";
print_r($gps);
echo "</pre>";

// Get buildings for this user
$buildingsStmt = $pdo->prepare("SELECT b.id, b.building_name, b.latitude, b.longitude, b.device_id as current_building_device_id,
                                        COALESCE(ba.radius, 100.00) as radius
                                 FROM buildings b
                                 LEFT JOIN building_areas ba ON ba.building_id = b.id
                                 WHERE b.user_id = ?
                                 AND b.latitude IS NOT NULL 
                                 AND b.longitude IS NOT NULL");
$buildingsStmt->execute([$device['user_id']]);
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($buildings)) {
    die("<p style='color:red;'>No buildings with coordinates found for user_id {$device['user_id']}!</p>");
}

echo "<h3>Buildings for User ID {$device['user_id']}:</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Building ID</th><th>Name</th><th>Latitude</th><th>Longitude</th><th>Radius (m)</th><th>Distance (m)</th><th>Inside?</th></tr>";

$deviceLat = (float)$gps['gps_latitude'];
$deviceLon = (float)$gps['gps_longitude'];
$closestBuilding = null;
$minDistance = null;

foreach ($buildings as $building) {
    $buildingLat = (float)$building['latitude'];
    $buildingLon = (float)$building['longitude'];
    $radius = (float)$building['radius'];
    
    $validation = isDeviceInBuildingRadius($deviceLat, $deviceLon, $buildingLat, $buildingLon, $radius);
    $distance = round($validation['distance'], 2);
    $inside = $validation['inside'] ? 'YES' : 'NO';
    
    if ($validation['inside'] && ($closestBuilding === null || $distance < $minDistance)) {
        $closestBuilding = $building;
        $minDistance = $distance;
    }
    
    echo "<tr>";
    echo "<td>{$building['id']}</td>";
    echo "<td>{$building['building_name']}</td>";
    echo "<td>{$buildingLat}</td>";
    echo "<td>{$buildingLon}</td>";
    echo "<td>{$radius}</td>";
    echo "<td>{$distance}</td>";
    echo "<td style='color:" . ($validation['inside'] ? 'green' : 'red') . ";'><strong>{$inside}</strong></td>";
    echo "</tr>";
}

echo "</table>";

if ($closestBuilding) {
    echo "<h3 style='color:green;'>Device is within building radius!</h3>";
    echo "<p>Closest building: <strong>{$closestBuilding['building_name']}</strong> (ID: {$closestBuilding['id']})</p>";
    echo "<p>Distance: <strong>{$minDistance} meters</strong> (within radius of {$closestBuilding['radius']} meters)</p>";
    
    echo "<h3>Attempting to update...</h3>";
    
    // Try auto-validation
    $result = autoValidateDeviceLocation($device_id, $pdo);
    
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    // Verify update
    $verifyDevice = $pdo->prepare("SELECT building_id FROM devices WHERE device_id = ?");
    $verifyDevice->execute([$device_id]);
    $updatedDevice = $verifyDevice->fetch(PDO::FETCH_ASSOC);
    
    $verifyBuilding = $pdo->prepare("SELECT device_id FROM buildings WHERE id = ?");
    $verifyBuilding->execute([$closestBuilding['id']]);
    $updatedBuilding = $verifyBuilding->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Verification:</h3>";
    echo "<p>devices.building_id: <strong>" . ($updatedDevice['building_id'] ?? 'NULL') . "</strong></p>";
    echo "<p>buildings.device_id: <strong>" . ($updatedBuilding['device_id'] ?? 'NULL') . "</strong></p>";
    
    if ($updatedDevice['building_id'] == $closestBuilding['id']) {
        echo "<p style='color:green;'><strong>✓ SUCCESS: devices.building_id was updated!</strong></p>";
    } else {
        echo "<p style='color:red;'><strong>✗ FAILED: devices.building_id was NOT updated!</strong></p>";
        echo "<p>Expected: {$closestBuilding['id']}, Got: " . ($updatedDevice['building_id'] ?? 'NULL') . "</p>";
    }
} else {
    echo "<h3 style='color:red;'>Device is NOT within any building radius!</h3>";
    echo "<p>The device must be within a building's radius to be assigned.</p>";
}

echo "<hr>";
echo "<p><a href='?device_id=" . ($device_id + 1) . "'>Test Next Device</a> | <a href='?device_id={$device_id}'>Refresh</a></p>";
?>


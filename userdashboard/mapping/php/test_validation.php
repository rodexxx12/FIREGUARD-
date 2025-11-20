<?php
/**
 * Test Script for Device Validation
 * 
 * This script demonstrates how to test the device validation functionality.
 * Run this after ensuring the migration has been completed.
 */

session_start();
require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../functions/device_location_validator.php';

// Check if user is logged in (for testing, you can comment this out)
if (!isset($_SESSION['user_id'])) {
    die("Please log in first, or modify this script to set a test user_id\n");
}

$pdo = getMappingDBConnection();
$user_id = $_SESSION['user_id'];

echo "=== Device Validation Test ===\n\n";

// Test 1: Get user's devices
echo "Test 1: Getting user's devices...\n";
$devicesStmt = $pdo->prepare("SELECT device_id, device_name, status FROM devices WHERE user_id = ? LIMIT 5");
$devicesStmt->execute([$user_id]);
$devices = $devicesStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($devices)) {
    echo "No devices found for user. Please create a device first.\n";
    exit;
}

echo "Found " . count($devices) . " device(s):\n";
foreach ($devices as $device) {
    echo "  - Device ID: {$device['device_id']}, Name: {$device['device_name']}\n";
}

// Test 2: Get user's buildings
echo "\nTest 2: Getting user's buildings...\n";
$buildingsStmt = $pdo->prepare("SELECT b.id, b.building_name, b.latitude, b.longitude, 
                                        COALESCE(ba.radius, 100.00) as radius
                                 FROM buildings b
                                 LEFT JOIN building_areas ba ON ba.building_id = b.id
                                 WHERE b.user_id = ? 
                                 AND b.latitude IS NOT NULL 
                                 AND b.longitude IS NOT NULL
                                 LIMIT 5");
$buildingsStmt->execute([$user_id]);
$buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($buildings)) {
    echo "No buildings with coordinates found. Please create a building with coordinates first.\n";
    exit;
}

echo "Found " . count($buildings) . " building(s) with coordinates:\n";
foreach ($buildings as $building) {
    echo "  - Building ID: {$building['id']}, Name: {$building['building_name']}, Radius: {$building['radius']}m\n";
}

// Test 3: Check device GPS data
echo "\nTest 3: Checking device GPS data...\n";
$device_id = $devices[0]['device_id'];
$gpsStmt = $pdo->prepare("SELECT gps_latitude, gps_longitude, timestamp 
                           FROM fire_data 
                           WHERE device_id = ? 
                           AND gps_latitude IS NOT NULL 
                           AND gps_longitude IS NOT NULL
                           ORDER BY timestamp DESC 
                           LIMIT 1");
$gpsStmt->execute([$device_id]);
$gpsData = $gpsStmt->fetch(PDO::FETCH_ASSOC);

if (!$gpsData) {
    echo "Device {$device_id} has no GPS data in fire_data table.\n";
    echo "Please ensure the device has sent GPS coordinates.\n";
    exit;
}

echo "Device {$device_id} latest GPS: Lat {$gpsData['gps_latitude']}, Lon {$gpsData['gps_longitude']}\n";
echo "Timestamp: {$gpsData['timestamp']}\n";

// Test 4: Test validation
echo "\nTest 4: Testing device validation...\n";
$building_id = $buildings[0]['id'];
echo "Attempting to validate device {$device_id} against building {$building_id}...\n";

$result = validateAndUpdateDeviceLocation($device_id, $building_id, $pdo, $user_id);

if ($result['success']) {
    echo "✓ SUCCESS: Device is within building radius!\n";
    echo "  Distance: {$result['distance']} meters\n";
    echo "  Building radius: {$result['radius']} meters\n";
    echo "  Building device_id has been updated to: {$result['device_id']}\n";
    
    // Verify both tables were updated
    $verifyBuilding = $pdo->prepare("SELECT device_id FROM buildings WHERE id = ?");
    $verifyBuilding->execute([$building_id]);
    $buildingData = $verifyBuilding->fetch(PDO::FETCH_ASSOC);
    
    $verifyDevice = $pdo->prepare("SELECT building_id FROM devices WHERE device_id = ?");
    $verifyDevice->execute([$device_id]);
    $deviceData = $verifyDevice->fetch(PDO::FETCH_ASSOC);
    
    echo "  Verification:\n";
    echo "    - buildings.device_id: " . ($buildingData['device_id'] ?? 'NULL') . "\n";
    echo "    - devices.building_id: " . ($deviceData['building_id'] ?? 'NULL') . "\n";
} else {
    echo "✗ FAILED: {$result['message']}\n";
    if (isset($result['distance'])) {
        echo "  Distance: {$result['distance']} meters\n";
        echo "  Required: Within {$result['radius']} meters\n";
    }
}

// Test 5: Auto-validation
echo "\nTest 5: Testing auto-validation...\n";
echo "Attempting to auto-validate device {$device_id}...\n";

$autoResult = autoValidateDeviceLocation($device_id, $pdo);

if ($autoResult['success']) {
    echo "✓ SUCCESS: Device auto-assigned to building!\n";
    echo "  Building ID: {$autoResult['building_id']}\n";
    echo "  Building Name: " . ($autoResult['building_name'] ?? 'N/A') . "\n";
    echo "  Distance: {$autoResult['distance']} meters\n";
    echo "  Radius: {$autoResult['radius']} meters\n";
    
    // Verify both tables were updated
    $verifyBuilding = $pdo->prepare("SELECT device_id FROM buildings WHERE id = ?");
    $verifyBuilding->execute([$autoResult['building_id']]);
    $buildingData = $verifyBuilding->fetch(PDO::FETCH_ASSOC);
    
    $verifyDevice = $pdo->prepare("SELECT building_id FROM devices WHERE device_id = ?");
    $verifyDevice->execute([$device_id]);
    $deviceData = $verifyDevice->fetch(PDO::FETCH_ASSOC);
    
    echo "  Verification:\n";
    echo "    - buildings.device_id: " . ($buildingData['device_id'] ?? 'NULL') . "\n";
    echo "    - devices.building_id: " . ($deviceData['building_id'] ?? 'NULL') . "\n";
} else {
    echo "✗ FAILED: {$autoResult['message']}\n";
}

echo "\n=== Test Complete ===\n";
?>


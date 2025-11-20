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

require_once '../functions/db_connect.php';
require_once '../functions/get_device_location.php';

try {
    $pdo = getDatabaseConnection();
    
    $deviceId = isset($_GET['device_id']) ? (int)$_GET['device_id'] : null;
    $buildingId = isset($_GET['building_id']) ? (int)$_GET['building_id'] : null;
    $emergencyOnly = isset($_GET['emergency_only']) ? filter_var($_GET['emergency_only'], FILTER_VALIDATE_BOOLEAN) : false;
    
    if ($deviceId) {
        // Get specific device location
        $deviceLocation = getDeviceLocation($pdo, $deviceId);
        
        if (!$deviceLocation) {
            echo json_encode([
                'success' => false,
                'message' => 'Device not found or no location data available',
                'data' => null
            ]);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Device location retrieved successfully',
            'data' => $deviceLocation
        ]);
        
    } elseif ($buildingId) {
        // Get all devices in a specific building
        $devices = getDeviceLocationByBuilding($pdo, $buildingId);
        
        echo json_encode([
            'success' => true,
            'message' => 'Building devices retrieved successfully',
            'data' => $devices,
            'count' => count($devices)
        ]);
        
    } elseif ($emergencyOnly) {
        // Get only devices with emergency status
        $emergencyDevices = getEmergencyDevices($pdo);
        
        echo json_encode([
            'success' => true,
            'message' => 'Emergency devices retrieved successfully',
            'data' => $emergencyDevices,
            'count' => count($emergencyDevices)
        ]);
        
    } else {
        // Get all active devices
        $allDevices = getAllActiveDevices($pdo);
        
        echo json_encode([
            'success' => true,
            'message' => 'All active devices retrieved successfully',
            'data' => $allDevices,
            'count' => count($allDevices)
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => null
    ]);
    exit;
}
?>

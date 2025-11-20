<?php
/**
 * Validate Device Location and Update Building device_id
 * 
 * This script validates if a device is within a building's radius
 * and updates the building's device_id if the device is inside.
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db/db.php';
require_once __DIR__ . '/../functions/device_location_validator.php';

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = getMappingDBConnection();
        
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Get parameters from POST or JSON
        $device_id = $input['device_id'] ?? $_POST['device_id'] ?? null;
        $building_id = $input['building_id'] ?? $_POST['building_id'] ?? null;
        
        if (!$device_id || !$building_id) {
            echo json_encode([
                'success' => false,
                'error' => 'Missing required parameters: device_id and building_id are required'
            ]);
            exit;
        }
        
        // Validate device belongs to user
        $deviceCheck = $pdo->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
        $deviceCheck->execute([$device_id, $_SESSION['user_id']]);
        if ($deviceCheck->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Device not found or does not belong to you'
            ]);
            exit;
        }
        
        // Validate building belongs to user
        $buildingCheck = $pdo->prepare("SELECT id FROM buildings WHERE id = ? AND user_id = ?");
        $buildingCheck->execute([$building_id, $_SESSION['user_id']]);
        if ($buildingCheck->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Building not found or does not belong to you'
            ]);
            exit;
        }
        
        // Check if device_id column exists in buildings table
        $checkColumn = $pdo->query("SHOW COLUMNS FROM buildings LIKE 'device_id'");
        if ($checkColumn->rowCount() === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Database migration required. Please run migrate_add_device_id.php to add device_id column to buildings table.'
            ]);
            exit;
        }
        
        // Validate device location and update building
        $result = validateAndUpdateDeviceLocation($device_id, $building_id, $pdo, $_SESSION['user_id']);
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log("Error in validate_device_location.php: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Only POST requests are allowed'
    ]);
}
?>


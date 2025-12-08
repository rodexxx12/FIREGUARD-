<?php
// Turn off error display but keep error logging
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start output buffering to catch any unwanted output
ob_start();

session_start();

// Check if user is logged in
if (!isset($_SESSION['superadmin_id'])) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Include required files
require_once __DIR__ . '/functions/database.php';
require_once __DIR__ . '/functions/device_operations.php';
require_once __DIR__ . '/functions/validation.php';

// Clear any output buffers before sending JSON
while (ob_get_level()) {
    ob_end_clean();
}

// Set JSON content type
header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    // Handle get_next_numbers action
    if ($action === 'get_next_numbers') {
        try {
            $deviceOperations = new DeviceOperations();
            $pdo = Database::getConnection();
            
            // Generate next device number
            $next_device_number = DeviceValidation::generateDeviceNumber($pdo);
            
            // Generate next serial number
            $next_serial_number = DeviceValidation::generateSerialNumber($pdo);
            
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'next_device_number' => $next_device_number,
                'next_serial_number' => $next_serial_number
            ]);
            exit;
        } catch (Exception $e) {
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error generating numbers: ' . $e->getMessage()
            ]);
            exit;
        }
    }

    // Handle device addition
    if (isset($_POST['device_number']) && isset($_POST['serial_number'])) {
        $deviceOperations = new DeviceOperations();
        
        // Prepare data for adding device
        $data = [
            'device_number' => $_POST['device_number'],
            'serial_number' => $_POST['serial_number'],
            'status' => isset($_POST['status']) ? $_POST['status'] : 'approved'
        ];
        
        try {
            // Add device (this method throws exceptions on error)
            $result = $deviceOperations->addDevice($data);
            
            // Get the device ID from the database
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT admin_device_id FROM admin_devices WHERE device_number = ? AND serial_number = ? ORDER BY admin_device_id DESC LIMIT 1");
            $stmt->execute([$data['device_number'], $data['serial_number']]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'message' => $result['message'],
                'device_id' => $device ? $device['admin_device_id'] : null,
                'device_number' => $result['device_number'],
                'serial_number' => $result['serial_number'],
                'status' => $data['status']
            ]);
        } catch (Exception $e) {
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
        exit;
    }

    // If no valid action or data provided
    throw new Exception('Invalid request');

} catch (Exception $e) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Log the error for debugging
    error_log("add_device.php Error: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}









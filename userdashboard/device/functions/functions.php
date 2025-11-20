<?php
session_start();

// Include all handlers
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/handlers/auth_handler.php';
require_once __DIR__ . '/handlers/device_validator.php';
require_once __DIR__ . '/handlers/device_crud.php';
require_once __DIR__ . '/handlers/device_status.php';
require_once __DIR__ . '/handlers/statistics_handler.php';

// Initialize handlers
$authHandler = new AuthHandler();
$deviceValidator = new DeviceValidator();
$deviceCRUD = new DeviceCRUD();
$deviceStatus = new DeviceStatus();
$statisticsHandler = new StatisticsHandler();

// Handle login
if (isset($_POST['action']) && $_POST['action'] == 'login') {
    $result = $authHandler->handleLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
    echo json_encode($result);
    exit;
}

// Handle all other AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (!$authHandler->isAuthenticated()) {
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please login.']);
        exit;
    }
    
    $user_id = $authHandler->getUserId();
    
    // Validate device
    if (isset($_POST['action']) && $_POST['action'] == 'validate_device') {
        $result = $deviceValidator->validateDevice(
            $_POST['device_number'] ?? '', 
            $_POST['serial_number'] ?? ''
        );
        echo json_encode($result);
        exit;
    }
    
    // Add new device
    if (isset($_POST['action']) && $_POST['action'] == 'add_device') {
        $result = $deviceCRUD->addDevice(
            $user_id,
            $_POST['device_name'] ?? '',
            $_POST['device_number'] ?? '',
            $_POST['serial_number'] ?? ''
        );
        echo json_encode($result);
        exit;
    }
    
    
    // Toggle device active status
    if (isset($_POST['action']) && $_POST['action'] == 'toggle_device_active') {
        $result = $deviceStatus->toggleDeviceActive(
            $user_id,
            $_POST['device_id'] ?? ''
        );
        echo json_encode($result);
        exit;
    }
    
    // Delete device
    if (isset($_POST['action']) && $_POST['action'] == 'delete_device') {
        $result = $deviceCRUD->deleteDevice(
            $user_id,
            $_POST['device_id'] ?? ''
        );
        echo json_encode($result);
        exit;
    }
    
    // Get device details for edit
    if (isset($_POST['action']) && $_POST['action'] == 'get_device_details') {
        $result = $deviceCRUD->getDeviceDetails(
            $user_id,
            $_POST['device_id'] ?? ''
        );
        echo json_encode($result);
        exit;
    }
    
    // Update device details
    if (isset($_POST['action']) && $_POST['action'] == 'update_device') {
        $result = $deviceCRUD->updateDevice(
            $user_id,
            $_POST['device_id'] ?? '',
            $_POST['device_name'] ?? '',
            $_POST['device_number'] ?? '',
            $_POST['serial_number'] ?? ''
        );
        echo json_encode($result);
        exit;
    }
    
    // Get all devices for the user
    if (isset($_GET['action']) && $_GET['action'] == 'get_devices') {
        $result = $deviceCRUD->getAllDevices($user_id);
        echo json_encode($result);
        exit;
    }
    
    // Get statistics
    if (isset($_GET['action']) && $_GET['action'] == 'get_statistics') {
        $result = $statisticsHandler->getStatistics($user_id);
        echo json_encode($result);
        exit;
    }
    
    // Get available devices (not assigned to any user)
    if (isset($_GET['action']) && $_GET['action'] == 'get_available_devices') {
        $result = $deviceCRUD->getAvailableDevices();
        echo json_encode($result);
        exit;
    }
}

// Handle regular page requests
?>
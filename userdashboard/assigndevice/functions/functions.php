<?php
// Include all component files
require_once 'database.php';
require_once 'auth.php';
require_once 'device_manager.php';
require_once 'fire_data_manager.php';
require_once 'form_handler.php';

// Check authentication
Auth::requireLogin();

// Get database connection
$pdo = Database::getConnection();

// Get user information
$user_id = Auth::getUserId();
$user = Auth::getUserInfo($pdo, $user_id);

// Initialize managers
$deviceManager = new DeviceManager($pdo, $user_id);
$fireDataManager = new FireDataManager($pdo);
$formHandler = new FormHandler($deviceManager);

// Handle form submissions
$formHandler->handleFormSubmission();

// Pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get user's devices (both assigned and unassigned) with pagination
$devices = $deviceManager->getUserDevices($offset, $per_page);
$total_devices = $deviceManager->getUserDevicesCount();
$total_pages = ceil($total_devices / $per_page);

// Get user's buildings
$buildings = $deviceManager->getUserBuildings();

// Get all fire data for user's devices
$device_ids = array_column($devices, 'device_id');
$fire_data = $fireDataManager->getFireData($device_ids);

// Prepare data for map
$map_data = $fireDataManager->prepareMapData($buildings, $devices, $fire_data);
?>
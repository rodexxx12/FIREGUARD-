<?php
// Include all component files
require_once 'database.php';
require_once 'validation.php';
require_once 'device_operations.php';
require_once 'activity_logger.php';
require_once 'statistics.php';

// Initialize database connection
$pdo = Database::getConnection();

// Initialize device operations
$deviceOperations = new DeviceOperations();

// Handle regular form submissions (for search)
// Read filters and pagination
$search_term = '';
$status_filter = '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'search') {
        $search_term = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';
    }
    if (isset($_POST['status_filter'])) {
        $status_filter = trim($_POST['status_filter']);
    }
}

// Allow GET to persist filters when navigating pages
if (isset($_GET['search_term'])) {
    $search_term = trim($_GET['search_term']);
}
if (isset($_GET['status'])) {
    $status_filter = trim($_GET['status']);
}

list($devices, $total_devices_count) = $deviceOperations->getDevicesPaginated($page, $per_page, $search_term, $status_filter);

$total_pages = (int)ceil($total_devices_count / $per_page);

// Get device by ID for editing
$editDevice = null;
if (isset($_GET['edit'])) {
    $editDevice = $deviceOperations->getDeviceById($_GET['edit']);
}

// Initialize statistics
$statistics = new DeviceStatistics();
$allStats = $statistics->getAllStatistics();

// Extract statistics data
$statusStats = $allStats['statusStats'];
$totalDevices = $allStats['totalDevices'];
$recentlyAdded = $allStats['recentlyAdded'];
$deviceAgeStats = $allStats['deviceAgeStats'];
$monthlyChartData = $allStats['monthlyChartData'];
$statusChartData = $allStats['statusChartData'];

// Prepare data for charts (maintaining original variable names for compatibility)
$monthlyLabels = $monthlyChartData['labels'];
$monthlyData = $monthlyChartData['data'];
$statusChangeData = $statusChartData;
?>

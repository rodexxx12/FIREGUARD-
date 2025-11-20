<?php
require_once __DIR__ . '../../../functions/functions.php'; 
// require_once __DIR__ . '../../../../alarm/alarm.php';

// Initialize database connection
$pdo = getDatabaseConnection();

// Get required data
$counts = getStatusCounts($pdo);
$alerts = getRecentAlerts($pdo);
$buildings = getBuildings($pdo);
$buildingAreas = getBuildingAreas($pdo);
$data = getLatestFireData($pdo);

// Ensure buildings is always an array
if (!isset($buildings) || !is_array($buildings)) {
    $buildings = [];
}

// Ensure buildingAreas is always an array
if (!isset($buildingAreas) || !is_array($buildingAreas)) {
    $buildingAreas = [];
}
?> 
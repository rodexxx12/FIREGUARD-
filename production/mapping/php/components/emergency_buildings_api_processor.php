<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);  // Ensure errors are visible for debugging
error_reporting(E_ALL); // Log all errors

require_once '../functions/db_connect.php';
require_once '../functions/get_emergency_buildings.php';

try {
    $pdo = getDatabaseConnection();
    $buildings = getEmergencyBuildings($pdo);

    if (empty($buildings)) {
        echo json_encode(['message' => 'No emergency buildings found']);
        exit;
    }

    echo json_encode($buildings);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?> 
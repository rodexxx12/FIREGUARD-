<?php
header('Content-Type: application/json');

require_once '../functions/db_connect.php';
require_once '../functions/get_building_by_id.php';

try {
    $pdo = getDatabaseConnection();
    $buildingId = $_GET['id'] ?? 0;
    
    $result = getBuildingById($pdo, $buildingId);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 
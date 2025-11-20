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
require_once '../functions/get_emergency_buildings.php';

try {
    $pdo = getDatabaseConnection();
    
    // Use the function to get the single most recent status (EMERGENCY OR ACKNOWLEDGED)
    $criticalBuilding = getMostRecentCriticalBuilding($pdo);

    if (!$criticalBuilding) {
        echo json_encode([
            'success' => false,
            'message' => 'No fire_data with EMERGENCY or ACKNOWLEDGED status found',
            'data' => null
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Most recent critical fire_data found: ' . $criticalBuilding['status'],
        'data' => $criticalBuilding
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'data' => null
    ]);
    exit;
}
?>

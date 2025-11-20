<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include('../../db/db.php');

header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    
    // Get latitude and longitude from request
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
    $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
    
    if ($lat === null || $lng === null) {
        // Return all barangays if no coordinates provided
        $stmt = $conn->prepare("SELECT id, ir_number, barangay_name, latitude, longitude FROM barangay ORDER BY barangay_name");
        $stmt->execute();
        $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'barangays' => $barangays
        ]);
        exit;
    }
    
    // Find the closest barangay to the given coordinates
    $stmt = $conn->prepare("
        SELECT id, ir_number, barangay_name, latitude, longitude,
        (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance
        FROM barangay 
        ORDER BY distance 
        LIMIT 10
    ");
    $stmt->execute([$lat, $lng, $lat]);
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'status' => 'success',
        'barangays' => $barangays
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching barangays: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch barangays'
    ]);
}
?>

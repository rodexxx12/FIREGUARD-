<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db/db.php';

try {
    $pdo = getMappingDBConnection();
    
    $building_id = $_GET['building_id'] ?? null;
    
    if (!$building_id) {
        throw new Exception('building_id parameter is required');
    }
    
    // Verify building belongs to user
    $stmt = $pdo->prepare("SELECT id FROM buildings WHERE id = ? AND user_id = ?");
    $stmt->execute([$building_id, $_SESSION['user_id']]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$building) {
        throw new Exception('Building not found or access denied');
    }
    
    // Get building area
    $stmt = $pdo->prepare("SELECT * FROM building_areas WHERE building_id = ?");
    $stmt->execute([$building_id]);
    $area = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($area) {
        // Decode JSON boundary coordinates if present
        if ($area['boundary_coordinates']) {
            $area['boundary_coordinates'] = json_decode($area['boundary_coordinates'], true);
        }
        
        echo json_encode([
            'success' => true,
            'area' => $area
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No area data found for this building'
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>


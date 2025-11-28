<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security includes
require_once __DIR__ . '/../../includes/session_config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/escape.php';

header('Content-Type: application/json');

try {
    // Require authentication
    $userId = requireAuthentication();
    
    // Use centralized database connection
    require_once __DIR__ . '/../db/db.php';
    $pdo = getMappingDBConnection();

    // Validate and sanitize input
    $buildingId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);
    
    if (!$buildingId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid building ID'
        ]);
        exit;
    }
    
    // Verify user owns this building
    require_once __DIR__ . '/../../includes/auth.php';
    if (!requireUserOwnership($pdo, 'buildings', $buildingId, $userId)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied'
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ? AND user_id = ?");
    $stmt->execute([$buildingId, $userId]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($building) {
        // Escape output for XSS prevention
        $safeBuilding = [];
        foreach ($building as $key => $value) {
            if (is_string($value)) {
                $safeBuilding[$key] = escapeHtml($value);
            } else {
                $safeBuilding[$key] = $value;
            }
        }
        
        echo json_encode([
            'success' => true,
            'building' => $safeBuilding
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Building not found'
        ]);
    }
} catch (PDOException $e) {
    error_log("Database error in get_building.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}
?>
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
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    $building_id = $input['building_id'] ?? null;
    $center_latitude = $input['center_latitude'] ?? null;
    $center_longitude = $input['center_longitude'] ?? null;
    $radius = $input['radius'] ?? 100.00;
    $boundary_coordinates = $input['boundary_coordinates'] ?? null;
    
    // Validate required fields
    if (!$building_id || !$center_latitude || !$center_longitude) {
        throw new Exception('Missing required fields: building_id, center_latitude, center_longitude');
    }
    
    // Verify building belongs to user
    $stmt = $pdo->prepare("SELECT id FROM buildings WHERE id = ? AND user_id = ?");
    $stmt->execute([$building_id, $_SESSION['user_id']]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$building) {
        throw new Exception('Building not found or access denied');
    }
    
    // Check if area already exists for this building
    $stmt = $pdo->prepare("SELECT id FROM building_areas WHERE building_id = ?");
    $stmt->execute([$building_id]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update existing area
        $sql = "UPDATE building_areas 
                SET center_latitude = ?, 
                    center_longitude = ?, 
                    radius = ?, 
                    boundary_coordinates = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE building_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $center_latitude,
            $center_longitude,
            $radius,
            $boundary_coordinates ? json_encode($boundary_coordinates) : null,
            $building_id
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Building area updated successfully',
            'area_id' => $existing['id']
        ]);
    } else {
        // Insert new area
        $sql = "INSERT INTO building_areas 
                (building_id, center_latitude, center_longitude, radius, boundary_coordinates) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $building_id,
            $center_latitude,
            $center_longitude,
            $radius,
            $boundary_coordinates ? json_encode($boundary_coordinates) : null
        ]);
        
        $area_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Building area saved successfully',
            'area_id' => $area_id
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


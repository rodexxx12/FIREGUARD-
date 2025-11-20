<?php
header('Content-Type: application/json');

try {
    // Use centralized database connection
    require_once __DIR__ . '/../db/db.php';
    $pdo = getMappingDBConnection();

    $buildingId = $_GET['id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT * FROM buildings WHERE id = ?");
    $stmt->execute([$buildingId]);
    $building = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($building) {
        echo json_encode([
            'success' => true,
            'building' => $building
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Building not found'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
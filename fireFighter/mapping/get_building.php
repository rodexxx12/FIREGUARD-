<?php
header('Content-Type: application/json');

// Include the database connection function
require_once '../functions/database_connection.php';

try {
    $pdo = getDatabaseConnection();

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
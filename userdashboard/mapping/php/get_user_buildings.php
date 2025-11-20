<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Use centralized database connection
    require_once __DIR__ . '/../db/db.php';
    $pdo = getMappingDBConnection();

    $sql = "SELECT id, building_name, building_type, address, latitude, longitude FROM buildings 
            WHERE user_id = :user_id AND latitude IS NOT NULL AND longitude IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($buildings);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>

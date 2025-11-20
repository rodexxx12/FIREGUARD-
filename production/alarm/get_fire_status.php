<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../db/db.php');
session_start();

try {
    // Connect with PDO
    $pdo = getDatabaseConnection();

    // Get user ID from session or GET
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $requested_user_id = $_GET['user_id'] ?? $current_user_id;
    $building_id = $_GET['building_id'] ?? null;

    // Build SQL
    $sql = "SELECT status, building_type, smoke, temp, heat, flame_detected, user_id, building_id 
            FROM fire_data 
            WHERE user_id = ?";
    $params = [$requested_user_id];

    if ($building_id !== null) {
        $sql .= " AND building_id = ?";
        $params[] = $building_id;
    }

    $sql .= " ORDER BY timestamp DESC LIMIT 1";

    // Execute
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'status' => $row['status'] ?? 'SAFE',
        'building_type' => $row['building_type'] ?? '',
        'smoke' => $row['smoke'] ?? 0,
        'temp' => $row['temp'] ?? 0,
        'heat' => $row['heat'] ?? 0,
        'flame_detected' => $row['flame_detected'] ?? 0,
        'user_id' => $row['user_id'] ?? $requested_user_id,
        'building_id' => $row['building_id'] ?? $building_id
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch (PDOException $e) {
    error_log("🔥 DB Error: " . $e->getMessage());

    echo json_encode([
        'status' => 'SAFE',
        'building_type' => '',
        'smoke' => 0,
        'temp' => 0,
        'heat' => 0,
        'flame_detected' => 0,
        'user_id' => $requested_user_id,
        'building_id' => $building_id
    ]);
}
?>

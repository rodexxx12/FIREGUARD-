<?php
/**
 * Get Fire Status API - Secure Version
 */

// Use centralized error handling
require_once __DIR__ . '/../../core/error_handler.php';
initializeErrorHandling(__DIR__ . '/../../logs/production_errors.log');

include('../../db/db.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Connect with PDO
    $pdo = getDatabaseConnection();

    // Get latest fire_data regardless of user or building
    $sql = "SELECT status, building_type, smoke, temp, heat, flame_detected, user_id 
            FROM fire_data 
            ORDER BY timestamp DESC LIMIT 1";

    // Execute
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    // Prepare response
    $response = [
        'status' => $row['status'] ?? 'SAFE',
        'building_type' => $row['building_type'] ?? '',
        'smoke' => $row['smoke'] ?? 0,
        'temp' => $row['temp'] ?? 0,
        'heat' => $row['heat'] ?? 0,
        'flame_detected' => $row['flame_detected'] ?? 0,
        'user_id' => $row['user_id'] ?? 0
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
        'user_id' => 0
    ]);
}
?>

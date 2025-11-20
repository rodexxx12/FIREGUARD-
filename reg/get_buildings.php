<?php
header('Content-Type: application/json');
require_once 'db_config.php';

try {
    $conn = getDatabaseConnection();
    $stmt = $conn->query("SELECT id, building_name, building_type, latitude, longitude FROM buildings WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($buildings);
} catch (Exception $e) {
    echo json_encode([]);
} 
<?php
session_start();
header('Content-Type: application/json');

// Include database connection
require_once '../../db/db.php';

try {
    // Get fire status data
    $query = "SELECT 
                f.id,
                f.smoke_level,
                f.temperature,
                f.heat_level,
                f.status,
                f.timestamp,
                b.building_name,
                b.address,
                b.latitude,
                b.longitude
              FROM fire_data f
              LEFT JOIN buildings b ON f.building_id = b.id
              WHERE f.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
              ORDER BY f.timestamp DESC";
    
    $stmt = getDatabaseConnection()->prepare($query);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count by status
    $counts = [
        'SAFE' => 0,
        'MONITORING' => 0,
        'ACKNOWLEDGED' => 0,
        'EMERGENCY' => 0
    ];
    
    foreach ($result as $row) {
        $status = strtoupper($row['status']);
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'counts' => $counts,
        'total' => count($result)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 
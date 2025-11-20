<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../db/db.php';

// Check if user_id is set in session
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Modified query to find the most recent EMERGENCY or ACKNOWLEDGED status for the user
    // This will find the latest status that is either EMERGENCY or ACKNOWLEDGED, even if it's not the most recent overall status
    // The query specifically filters for these two statuses and finds the most recent occurrence of either one
    $sql = "
    SELECT
        b.id,
        b.building_name,
        b.address,
        b.latitude,
        b.longitude,
        f.status,
        f.timestamp
    FROM buildings b
    JOIN (
        SELECT f1.*
        FROM fire_data f1
        INNER JOIN (
            SELECT building_id, MAX(timestamp) AS latest_emergency_time
            FROM fire_data
            WHERE building_id IS NOT NULL 
            AND status IN ('EMERGENCY', 'ACKNOWLEDGED')
            GROUP BY building_id
        ) f2 ON f1.building_id = f2.building_id AND f1.timestamp = f2.latest_emergency_time
    ) f ON b.id = f.building_id
    WHERE b.user_id = :user_id  -- Filter by the logged-in user
    AND f.status IN ('EMERGENCY', 'ACKNOWLEDGED')  -- Only include EMERGENCY or ACKNOWLEDGED statuses
    ORDER BY f.timestamp DESC;
    ";

    $stmt = getMappingDBConnection()->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($buildings === false) {
        echo json_encode(['error' => 'Failed to fetch buildings']);
    } else {
        echo json_encode($buildings);
    }
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['error' => 'Database query failed', 'details' => 'An error occurred']);
}
?>
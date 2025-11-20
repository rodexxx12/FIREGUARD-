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
    // Get fire_data records with EMERGENCY or ACKNOWLEDGED status
    // Use only gps_latitude and gps_longitude from fire_data table
    // Case-insensitive status matching to handle different case variations
    $sql = "
    SELECT 
        id,
        status,
        building_type,
        smoke,
        temp,
        heat,
        flame_detected,
        timestamp,
        user_id,
        building_id,
        gps_latitude,
        gps_longitude,
        gps_altitude,
        device_id,
        ml_confidence,
        ml_prediction,
        ml_fire_probability,
        ai_prediction,
        ml_timestamp,
        acknowledged_at_time,
        barangay_id
    FROM fire_data 
    WHERE user_id = :user_id 
    AND UPPER(status) IN ('EMERGENCY', 'ACKNOWLEDGED', 'PRE-DISPATCH', 'PRE DISPATCH')
    AND gps_latitude IS NOT NULL 
    AND gps_longitude IS NOT NULL
    ORDER BY timestamp DESC
    ";

    $stmt = getMappingDBConnection()->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $fireDataRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($fireDataRecords === false) {
        echo json_encode(['error' => 'Failed to fetch fire data']);
        exit;
    }

    // Format the response with latitude and longitude from GPS coordinates
    $formattedData = [];
    foreach ($fireDataRecords as $record) {
        // Validate GPS coordinates before adding
        if (!empty($record['gps_latitude']) && !empty($record['gps_longitude'])) {
            $formattedData[] = [
                'id' => $record['id'],
                'status' => $record['status'],
                'building_type' => $record['building_type'],
                'smoke' => $record['smoke'],
                'temp' => $record['temp'],
                'heat' => $record['heat'],
                'flame_detected' => $record['flame_detected'],
                'timestamp' => $record['timestamp'],
                'user_id' => $record['user_id'],
                'building_id' => $record['building_id'],
                'device_id' => $record['device_id'],
                'latitude' => (float)$record['gps_latitude'],
                'longitude' => (float)$record['gps_longitude'],
                'gps_altitude' => $record['gps_altitude'],
                'ml_confidence' => $record['ml_confidence'],
                'ml_prediction' => $record['ml_prediction'],
                'ml_fire_probability' => $record['ml_fire_probability'],
                'ai_prediction' => $record['ai_prediction'],
                'ml_timestamp' => $record['ml_timestamp'],
                'acknowledged_at_time' => $record['acknowledged_at_time'],
                'barangay_id' => $record['barangay_id']
            ];
        }
    }

    // Always return an array, even if empty
    echo json_encode($formattedData);
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error in get_emergency_fire_data.php: " . $e->getMessage());
    echo json_encode(['error' => 'Database query failed', 'details' => 'An error occurred']);
}
?>


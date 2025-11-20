<?php
/**
 * Get Latest Fire Data for Marquee Display
 * Returns the most recent fire_data entry with related information
 */

header('Content-Type: application/json');

// Include database connection
require_once '../functions/database_connection.php';

try {
    $pdo = getDatabaseConnection();
    
    // Fetch the latest fire_data with related information
    $stmt = $pdo->prepare("
        SELECT 
            fd.id,
            fd.status,
            fd.building_type,
            fd.smoke,
            fd.temp,
            fd.heat,
            fd.flame_detected,
            fd.timestamp,
            fd.ml_confidence,
            fd.ml_prediction,
            fd.ml_fire_probability,
            fd.ai_prediction,
            fd.gps_latitude,
            fd.gps_longitude,
            fd.geo_lat,
            fd.geo_long,
            COALESCE(b.building_name, 'Unknown Building') as building_name,
            COALESCE(b.address, 'Unknown Address') as address,
            COALESCE(d.device_name, CONCAT('Device #', fd.device_id)) as device_name,
            COALESCE(br.barangay_name, 'Unknown Barangay') as barangay_name
        FROM fire_data fd
        LEFT JOIN buildings b ON fd.building_id = b.id
        LEFT JOIN devices d ON fd.device_id = d.device_id
        LEFT JOIN barangay br ON COALESCE(fd.barangay_id, b.barangay_id, d.barangay_id) = br.id
        ORDER BY fd.timestamp DESC, fd.id DESC
        LIMIT 1
    ");
    
    $stmt->execute();
    $latestData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($latestData) {
        // Format the timestamp for display
        $timestamp = $latestData['timestamp'];
        if (is_string($timestamp)) {
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $timestamp);
            if (!$date) {
                $date = new DateTime($timestamp);
            }
            $latestData['formatted_timestamp'] = $date->format('M d, Y h:i A');
        } else {
            $latestData['formatted_timestamp'] = date('M d, Y h:i A', strtotime($timestamp));
        }
        
        echo json_encode([
            'success' => true,
            'data' => $latestData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No fire data found'
        ]);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>


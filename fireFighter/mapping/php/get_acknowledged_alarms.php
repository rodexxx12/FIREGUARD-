<?php
// Database connection
require_once('../../../db/db.php');

try {
    // Get database connection using the function
    $conn = getDatabaseConnection();
    
    // First, let's check if we have any ACKNOWLEDGED alarms at all
    $checkQuery = "SELECT COUNT(*) as count FROM fire_data WHERE status = 'ACKNOWLEDGED'";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute();
    $countResult = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($countResult['count'] == 0) {
        // No ACKNOWLEDGED alarms found
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'No ACKNOWLEDGED alarms found in the system'
        ]);
        exit;
    }
    
    // Query to get the most recent 5 ACKNOWLEDGED alarms with related data
    // Order by acknowledged_at_time (combined with date from timestamp) for most recent acknowledgment
    $query = "
        SELECT 
            fd.id,
            fd.status,
            fd.timestamp,
            fd.acknowledged_at_time,
            fd.smoke,
            fd.temp,
            fd.heat,
            fd.flame_detected,
            fd.building_type,
            d.device_name,
            d.device_number,
            d.serial_number,
            d.status as device_status,
            b.contact_person,
            b.contact_number,
            b.total_floors,
            u.fullname as user_fullname,
            u.contact_number as user_contact,
            -- Calculate effective timestamp: use date from timestamp + acknowledged_at_time
            CASE 
                WHEN fd.acknowledged_at_time IS NOT NULL
                THEN CONCAT(DATE(fd.timestamp), ' ', fd.acknowledged_at_time)
                ELSE fd.timestamp
            END AS effective_timestamp
        FROM fire_data fd
        LEFT JOIN devices d ON fd.device_id = d.device_id
        LEFT JOIN buildings b ON d.building_id = b.id
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE fd.status = 'ACKNOWLEDGED'
        ORDER BY 
            -- Order by date + acknowledged_at_time for most recent acknowledgment
            CASE 
                WHEN fd.acknowledged_at_time IS NOT NULL
                THEN CONCAT(DATE(fd.timestamp), ' ', fd.acknowledged_at_time)
                ELSE fd.timestamp
            END DESC
        LIMIT 5
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $acknowledged_alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $acknowledged_alarms,
        'total_found' => $countResult['count']
    ]);
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'details' => 'Check database connection and table structure'
    ]);
}
?>

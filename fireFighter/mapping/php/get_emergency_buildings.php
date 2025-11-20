<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);  // Ensure errors are visible for debugging
error_reporting(E_ALL); // Log all errors

// Include the database connection function
require_once '../functions/database_connection.php';

try {
    $conn = getDatabaseConnection();
} catch (Exception $e) {
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// First, let's check if we have any data at all
try {
    // Check if buildings table has data
    $stmt = $conn->query("SELECT COUNT(*) as count FROM buildings");
    $buildingCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if fire_data table has data
    $stmt = $conn->query("SELECT COUNT(*) as count FROM fire_data");
    $fireDataCount = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no data in either table, return appropriate message
    if ($buildingCount['count'] == 0) {
        echo json_encode(['message' => 'No buildings found in database']);
        exit;
    }
    
    if ($fireDataCount['count'] == 0) {
        echo json_encode(['message' => 'No fire data found in database']);
        exit;
    }
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database check failed', 'details' => $e->getMessage()]);
    exit;
}

// Now try the main query
// Updated to use acknowledged_at_time for ACKNOWLEDGED status
$sql = "
SELECT
    b.id,
    b.building_name,
    b.address,
    b.latitude,
    b.longitude,
    f.id AS fire_data_id,
    f.status,
    f.timestamp,
    f.acknowledged_at_time,
    COALESCE(r.response_count, 0) AS response_count,
    CASE WHEN COALESCE(r.response_count, 0) > 0 THEN 1 ELSE 0 END AS has_response,
    -- Calculate effective timestamp: for ACKNOWLEDGED, use date from timestamp + acknowledged_at_time
    CASE 
        WHEN UPPER(f.status) = 'ACKNOWLEDGED' AND f.acknowledged_at_time IS NOT NULL
        THEN CONCAT(DATE(f.timestamp), ' ', f.acknowledged_at_time)
        ELSE f.timestamp
    END AS effective_timestamp
FROM buildings b
JOIN (
    SELECT f1.*
    FROM fire_data f1
    INNER JOIN (
        SELECT 
            building_id,
            MAX(
                CASE 
                    WHEN UPPER(status) = 'ACKNOWLEDGED' AND acknowledged_at_time IS NOT NULL
                    THEN CONCAT(DATE(timestamp), ' ', acknowledged_at_time)
                    ELSE timestamp
                END
            ) AS latest_time
        FROM fire_data
        WHERE building_id IS NOT NULL
        AND UPPER(status) IN ('EMERGENCY', 'ACKNOWLEDGED', 'PRE-DISPATCH')
        GROUP BY building_id
    ) f2 ON f1.building_id = f2.building_id 
        AND (
            CASE 
                WHEN UPPER(f1.status) = 'ACKNOWLEDGED' AND f1.acknowledged_at_time IS NOT NULL
                THEN CONCAT(DATE(f1.timestamp), ' ', f1.acknowledged_at_time)
                ELSE f1.timestamp
            END
        ) = f2.latest_time
) f ON b.id = f.building_id
LEFT JOIN (
    SELECT fire_data_id, COUNT(*) AS response_count
    FROM responses
    GROUP BY fire_data_id
) r ON r.fire_data_id = f.id
WHERE b.latitude IS NOT NULL AND b.longitude IS NOT NULL
AND UPPER(f.status) IN ('EMERGENCY', 'ACKNOWLEDGED', 'PRE-DISPATCH')
ORDER BY 
    -- Prioritize EMERGENCY over ACKNOWLEDGED
    CASE WHEN UPPER(f.status) = 'EMERGENCY' THEN 0 
         WHEN UPPER(f.status) = 'PRE-DISPATCH' THEN 1
         ELSE 2 END,
    -- Order by effective timestamp
    CASE 
        WHEN UPPER(f.status) = 'ACKNOWLEDGED' AND f.acknowledged_at_time IS NOT NULL
        THEN CONCAT(DATE(f.timestamp), ' ', f.acknowledged_at_time)
        ELSE f.timestamp
    END DESC;
";

try {
    $stmt = $conn->query($sql);
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($buildings)) {
        // Let's get some debugging info
        $debugSql = "
        SELECT 
            COUNT(DISTINCT b.id) as total_buildings,
            COUNT(DISTINCT f.building_id) as buildings_with_fire_data,
            COUNT(DISTINCT CASE WHEN b.latitude IS NOT NULL AND b.longitude IS NOT NULL THEN b.id END) as buildings_with_coords
        FROM buildings b
        LEFT JOIN fire_data f ON b.id = f.building_id
        ";
        
        $debugStmt = $conn->query($debugSql);
        $debugInfo = $debugStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'message' => 'No emergency buildings found',
            'debug_info' => $debugInfo,
            'total_buildings' => $buildingCount['count'],
            'total_fire_data' => $fireDataCount['count']
        ]);
        exit;
    }

    echo json_encode($buildings);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database query failed', 'details' => $e->getMessage()]);
    exit;
}
?>

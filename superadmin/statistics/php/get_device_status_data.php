<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

try {
    $conn = getDatabaseConnection();
    
    // Get filter parameters
    $deviceType = $_GET['device_type'] ?? 'all';
    $location = $_GET['location'] ?? 'all';
    
    // Build query based on device type filter
    if ($deviceType === 'user') {
        $sql = "
            SELECT 
                CASE 
                    WHEN status = 'online' THEN 'Online'
                    WHEN status = 'offline' THEN 'Offline'
                    WHEN status = 'faulty' THEN 'Faulty'
                    ELSE 'Unknown'
                END as status_label,
                COUNT(*) as count
            FROM devices 
            " . ($location !== 'all' ? "WHERE location = ?" : "") . "
            GROUP BY status
            ORDER BY 
                CASE status
                    WHEN 'online' THEN 1
                    WHEN 'offline' THEN 2
                    WHEN 'faulty' THEN 3
                    ELSE 4
                END
        ";
    } elseif ($deviceType === 'admin') {
        $sql = "
            SELECT 
                CASE 
                    WHEN status = 'online' THEN 'Online'
                    WHEN status = 'offline' THEN 'Offline'
                    WHEN status = 'faulty' THEN 'Faulty'
                    ELSE 'Unknown'
                END as status_label,
                COUNT(*) as count
            FROM admin_devices 
            " . ($location !== 'all' ? "WHERE location = ?" : "") . "
            GROUP BY status
            ORDER BY 
                CASE status
                    WHEN 'online' THEN 1
                    WHEN 'offline' THEN 2
                    WHEN 'faulty' THEN 3
                    ELSE 4
                END
        ";
    } else {
        // All devices - combine both tables
        $sql = "
            SELECT 
                CASE 
                    WHEN status = 'online' THEN 'Online'
                    WHEN status = 'offline' THEN 'Offline'
                    WHEN status = 'faulty' THEN 'Faulty'
                    ELSE 'Unknown'
                END as status_label,
                SUM(count) as count
            FROM (
                SELECT status, COUNT(*) as count FROM devices " . ($location !== 'all' ? "WHERE location = ?" : "") . " GROUP BY status
                UNION ALL
                SELECT status, COUNT(*) as count FROM admin_devices " . ($location !== 'all' ? "WHERE location = ?" : "") . " GROUP BY status
            ) as combined
            GROUP BY status
            ORDER BY 
                CASE status
                    WHEN 'online' THEN 1
                    WHEN 'offline' THEN 2
                    WHEN 'faulty' THEN 3
                    ELSE 4
                END
        ";
    }
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters if location filter is applied
    if ($location !== 'all') {
        if ($deviceType === 'all') {
            $stmt->bindParam(1, $location);
            $stmt->bindParam(2, $location);
        } else {
            $stmt->bindParam(1, $location);
        }
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        $labels[] = $row['status_label'];
        $values[] = (int)$row['count'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'values' => $values
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_device_status_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch device status data'
    ]);
}
?>

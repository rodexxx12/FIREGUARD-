<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

try {
    $conn = getDatabaseConnection();
    
    // Get filter parameters
    $dateRange = $_GET['date_range'] ?? '7';
    $severity = $_GET['severity'] ?? 'all';
    $location = $_GET['location'] ?? 'all';
    
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    
    // Date range filter
    $whereConditions[] = "DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
    $params[] = $dateRange;
    
    // Severity filter
    if ($severity !== 'all') {
        if ($severity === 'detected') {
            $whereConditions[] = "status = 'Fire Detected'";
        } elseif ($severity === 'alert') {
            $whereConditions[] = "status = 'Fire Alert'";
        } elseif ($severity === 'critical') {
            $whereConditions[] = "status = 'Critical Fire'";
        }
    } else {
        $whereConditions[] = "(status IN ('Fire Detected', 'Fire Alert', 'Critical Fire') OR ml_prediction = 1 OR flame_detected = 1)";
    }
    
    // Location filter (assuming there's a location field or related table)
    if ($location !== 'all') {
        $whereConditions[] = "location = ?";
        $params[] = $location;
    }
    
    // Get fire incidents with filters
    $sql = "
        SELECT 
            DATE(timestamp) as incident_date,
            COUNT(*) as incident_count
        FROM fire_data 
        WHERE " . implode(' AND ', $whereConditions) . "
        GROUP BY DATE(timestamp)
        ORDER BY incident_date ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Generate labels for the specified date range
    $labels = [];
    $values = [];
    
    for ($i = $dateRange - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M j', strtotime($date));
        
        // Find count for this date
        $count = 0;
        foreach ($results as $row) {
            if ($row['incident_date'] === $date) {
                $count = (int)$row['incident_count'];
                break;
            }
        }
        $values[] = $count;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'labels' => $labels,
            'values' => $values
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_fire_incidents_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch fire incidents data'
    ]);
}
?>

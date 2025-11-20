<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

try {
    $conn = getDatabaseConnection();
    
    // Get filter parameters
    $dateRange = $_GET['date_range'] ?? '6';
    $status = $_GET['status'] ?? 'all';
    
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    
    // Date range filter
    $whereConditions[] = "registration_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)";
    $params[] = $dateRange;
    
    // Status filter
    if ($status !== 'all') {
        $whereConditions[] = "status = ?";
        $params[] = $status;
    }
    
    // Get user registration trends with filters
    $sql = "
        SELECT 
            DATE_FORMAT(registration_date, '%Y-%m') as month,
            COUNT(*) as registration_count
        FROM users 
        WHERE " . implode(' AND ', $whereConditions) . "
        GROUP BY DATE_FORMAT(registration_date, '%Y-%m')
        ORDER BY month ASC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    // Generate labels for the specified time period
    $labels = [];
    $values = [];
    
    for ($i = $dateRange - 1; $i >= 0; $i--) {
        $month = date('Y-m', strtotime("-$i months"));
        $labels[] = date('M Y', strtotime($month));
        
        // Find count for this month
        $count = 0;
        foreach ($results as $row) {
            if ($row['month'] === $month) {
                $count = (int)$row['registration_count'];
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
    error_log("Error in get_user_registration_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch user registration data'
    ]);
}
?>

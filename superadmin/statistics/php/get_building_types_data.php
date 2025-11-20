<?php
header('Content-Type: application/json');
require_once '../../../db/db.php';

try {
    $conn = getDatabaseConnection();
    
    // Get filter parameters
    $barangay = $_GET['barangay'] ?? 'all';
    $floors = $_GET['floors'] ?? 'all';
    $limit = $_GET['limit'] ?? '8';
    
    // Build WHERE conditions
    $whereConditions = [];
    $params = [];
    
    // Barangay filter
    if ($barangay !== 'all') {
        $whereConditions[] = "b.barangay_id = ?";
        $params[] = $barangay;
    }
    
    // Floors filter
    if ($floors !== 'all') {
        switch ($floors) {
            case '1':
                $whereConditions[] = "b.total_floors = 1";
                break;
            case '2-5':
                $whereConditions[] = "b.total_floors BETWEEN 2 AND 5";
                break;
            case '6-10':
                $whereConditions[] = "b.total_floors BETWEEN 6 AND 10";
                break;
            case '10+':
                $whereConditions[] = "b.total_floors > 10";
                break;
        }
    }
    
    // Build the query with JOIN to get barangay names
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(' AND ', $whereConditions) : "";
    
    $sql = "
        SELECT 
            b.building_type,
            COUNT(*) as count
        FROM buildings b
        LEFT JOIN barangay br ON b.barangay_id = br.id
        $whereClause
        GROUP BY b.building_type
        ORDER BY count DESC
        LIMIT ?
    ";
    
    $params[] = $limit;
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll();
    
    $labels = [];
    $values = [];
    
    foreach ($results as $row) {
        $labels[] = ucwords(str_replace('_', ' ', $row['building_type']));
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
    error_log("Error in get_building_types_data.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch building types data'
    ]);
}
?>

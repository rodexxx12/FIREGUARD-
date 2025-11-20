<?php
require_once 'common/database_utils.php';

try {
    // Get filter parameters
    $startDate = trim($_GET['start_date'] ?? '');
    $endDate = trim($_GET['end_date'] ?? '');
    $barangay = trim($_GET['barangay'] ?? '');
    $mlConfidence = trim($_GET['ml_confidence'] ?? '');
    $mlPrediction = trim($_GET['ml_prediction'] ?? '');
    
    // Check if we have any fire_data at all
    $totalCount = DatabaseUtils::executeSingleQuery("SELECT COUNT(*) as total_count FROM fire_data WHERE timestamp IS NOT NULL")['total_count'];
    
    if ($totalCount == 0) {
        DatabaseUtils::sendResponse(true, [
            'labels' => ['No Fire Data Available'],
            'data' => [0]
        ], 'No fire data found in the system', 'Total fire_data records: 0');
        exit;
    }
    
    // Build optimized query using common utilities
    $sql = "SELECT 
                COALESCE(bld_barangay.barangay_name, fd_barangay.barangay_name, 'Unknown') as barangay_name,
                COALESCE(fd.building_type, 'Unknown') as building_type,
                COUNT(*) as incident_count
            FROM fire_data fd
            LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
            LEFT JOIN barangay bld_barangay ON bld.barangay_id = bld_barangay.id
            LEFT JOIN barangay fd_barangay ON fd.barangay_id = fd_barangay.id
            WHERE fd.timestamp IS NOT NULL
            AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire')";
    
    $params = [];
    
    // Use common filter builders
    DatabaseUtils::buildBarangayFilter($barangay, $sql, $params);
    DatabaseUtils::buildDateFilters($startDate, $endDate, $sql, $params);
    
    // Add ML filters
    if (!empty($mlConfidence) && is_numeric($mlConfidence)) {
        $sql .= " AND fd.ml_confidence >= :ml_confidence";
        $params[':ml_confidence'] = (float)$mlConfidence;
    }
    
    if (!empty($mlPrediction)) {
        $sql .= " AND fd.ml_prediction = :ml_prediction";
        $params[':ml_prediction'] = $mlPrediction;
    }
    
    $sql .= " GROUP BY COALESCE(bld_barangay.barangay_name, fd_barangay.barangay_name, 'Unknown'), COALESCE(fd.building_type, 'Unknown') 
              ORDER BY incident_count DESC";
    
    $results = DatabaseUtils::executeQuery($sql, $params);
    
    // Process data for chart - optimized
    $chartData = [
        'labels' => [],
        'data' => []
    ];
    
    foreach ($results as $row) {
        $chartData['labels'][] = $row['barangay_name'] . ' - ' . ucfirst($row['building_type']);
        $chartData['data'][] = (int)$row['incident_count'];
    }
    
    // If no data, show message
    if (empty($chartData['labels'])) {
        $chartData = [
            'labels' => ['No Fire Data Found'],
            'data' => [0]
        ];
    }
    
    DatabaseUtils::sendResponse(true, $chartData, 'Fire Data Analysis loaded successfully', [
        'total_records' => $totalCount,
        'filtered_results' => count($results),
        'date_range' => ['start' => $startDate, 'end' => $endDate],
        'filters' => [
            'barangay' => $barangay,
            'ml_confidence' => $mlConfidence,
            'ml_prediction' => $mlPrediction
        ],
        'query_params' => $params
    ]);
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load fire incident statistics: ' . $e->getMessage(), [
        'file' => __FILE__,
        'line' => $e->getLine(),
        'barangay' => $barangay ?? 'not set',
        'start_date' => $startDate ?? 'not set',
        'end_date' => $endDate ?? 'not set'
    ]);
}
?>

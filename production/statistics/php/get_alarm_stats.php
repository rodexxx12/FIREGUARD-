<?php
require_once 'common/database_utils.php';

try {
    // Get filter parameters
    $status = $_GET['status'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $barangay = $_GET['barangay'] ?? '';
    
    // Build optimized query using common utilities
    $sql = "SELECT 
                DATE(fd.timestamp) as date,
                fd.status,
                COUNT(*) as count
            FROM fire_data fd
            " . DatabaseUtils::getFireDataJoins() . "
            WHERE fd.timestamp IS NOT NULL";
    
    $params = [];
    
    // Add status filter
    if (!empty($status)) {
        $sql .= " AND fd.status = :status";
        $params[':status'] = $status;
    }
    
    // Use common filter builders
    DatabaseUtils::buildBarangayFilter($barangay, $sql, $params);
    DatabaseUtils::buildDateFilters($startDate, $endDate, $sql, $params);
    
    $sql .= " GROUP BY DATE(fd.timestamp), fd.status ORDER BY date ASC";
    
    $results = DatabaseUtils::executeQuery($sql, $params);
    
    // Process data for chart - optimized
    $dates = array_unique(array_column($results, 'date'));
    sort($dates);
    
    // Initialize data arrays
    $fireData = $normalData = $warningData = array_fill(0, count($dates), 0);
    
    // Fill data efficiently
    foreach ($results as $row) {
        $dateIndex = array_search($row['date'], $dates);
        $count = (int)$row['count'];
        
        switch (strtoupper($row['status'])) {
            case 'EMERGENCY':
            case 'FIRE':
                $fireData[$dateIndex] = $count;
                break;
            case 'NORMAL':
                $normalData[$dateIndex] = $count;
                break;
            case 'ACKNOWLEDGED':
            case 'WARNING':
                $warningData[$dateIndex] = $count;
                break;
        }
    }
    
    $chartData = [
        'labels' => array_map(function($date) { return date('M j', strtotime($date)); }, $dates),
        'fire_data' => DatabaseUtils::sanitizeData($fireData),
        'normal_data' => DatabaseUtils::sanitizeData($normalData),
        'warning_data' => DatabaseUtils::sanitizeData($warningData)
    ];
    
    DatabaseUtils::sendResponse(true, $chartData, 'Alarm statistics loaded successfully', [
        'total_records' => count($results),
        'date_range' => ['start' => $startDate, 'end' => $endDate],
        'filters' => ['status' => $status, 'barangay' => $barangay],
        'unique_dates' => count($dates)
    ]);
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load alarm statistics', $e->getMessage());
}
?>

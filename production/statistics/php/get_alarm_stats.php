<?php
// Environment-aware error handling for JSON responses
$isProduction = (getenv('APP_ENV') === 'production');
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
error_reporting(E_ALL);
ini_set('display_errors', ($isProduction && !$debugMode) ? '0' : '1');
ini_set('log_errors', '1');

// Start output buffering to catch any accidental output
if (!ob_get_level()) {
    ob_start();
}

require_once 'common/database_utils.php';

try {
    // Get filter parameters with validation
    $statusRaw = $_GET['status'] ?? null;
    $status = DatabaseUtils::sanitizeEnum($statusRaw, ['EMERGENCY', 'FIRE', 'NORMAL', 'ACKNOWLEDGED', 'WARNING']);
    if ($statusRaw && !$status) {
        DatabaseUtils::sendError('Invalid status filter value');
    }

    $startDateRaw = $_GET['start_date'] ?? null;
    $startDate = DatabaseUtils::sanitizeDate($startDateRaw);
    if ($startDateRaw && !$startDate) {
        DatabaseUtils::sendError('Invalid start date. Use YYYY-MM-DD format.');
    }

    $endDateRaw = $_GET['end_date'] ?? null;
    $endDate = DatabaseUtils::sanitizeDate($endDateRaw);
    if ($endDateRaw && !$endDate) {
        DatabaseUtils::sendError('Invalid end date. Use YYYY-MM-DD format.');
    }

    $barangayRaw = $_GET['barangay'] ?? null;
    $barangay = DatabaseUtils::sanitizeInt($barangayRaw, 1);
    if ($barangayRaw !== null && $barangay === null) {
        DatabaseUtils::sendError('Invalid barangay identifier.');
    }
    
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
    $dates = array_values(array_unique(array_column($results, 'date')));
    sort($dates);
    $dateIndexMap = array_flip($dates);
    
    // Initialize data arrays
    $fireData = $normalData = $warningData = array_fill(0, count($dates), 0);
    
    // Fill data efficiently
    foreach ($results as $row) {
        if (!isset($dateIndexMap[$row['date']])) {
            continue;
        }
        $dateIndex = $dateIndexMap[$row['date']];
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

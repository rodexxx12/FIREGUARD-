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

    $firefighterIdRaw = $_GET['firefighter_id'] ?? null;
    $firefighterId = DatabaseUtils::sanitizeInt($firefighterIdRaw, 1);
    if ($firefighterIdRaw !== null && $firefighterId === null) {
        DatabaseUtils::sendError('Invalid firefighter identifier.');
    }

    $barangayRaw = $_GET['barangay'] ?? null;
    $barangay = DatabaseUtils::sanitizeInt($barangayRaw, 1);
    if ($barangayRaw !== null && $barangay === null) {
        DatabaseUtils::sendError('Invalid barangay identifier.');
    }
    
    // Build optimized query using common utilities
    $sql = "SELECT 
                DATE(r.timestamp) as response_date,
                r.response_type,
                COUNT(*) as response_count,
                AVG(TIMESTAMPDIFF(MINUTE, fd.timestamp, r.timestamp)) as avg_response_time
            FROM responses r
            LEFT JOIN fire_data fd ON r.fire_data_id = fd.id
            LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
            LEFT JOIN barangay b ON (fd.barangay_id = b.id OR bld.barangay_id = b.id)
            WHERE 1=1";
    
    $params = [];
    $whereConditions = [];
    
    // Add date filters
    if (!empty($startDate)) {
        $whereConditions[] = "r.timestamp >= :start_date";
        $params[':start_date'] = "{$startDate} 00:00:00";
    }
    
    if (!empty($endDate)) {
        $whereConditions[] = "r.timestamp <= :end_date";
        $params[':end_date'] = "{$endDate} 23:59:59";
    }
    
    // Add firefighter filter
    if (!empty($firefighterId)) {
        $whereConditions[] = "r.firefighter_id = :firefighter_id";
        $params[':firefighter_id'] = $firefighterId;
    }
    
    // Use common barangay filter
    DatabaseUtils::buildBarangayFilter($barangay, $sql, $params);
    
    if (!empty($whereConditions)) {
        $sql .= " AND " . implode(" AND ", $whereConditions);
    }
    
    $sql .= " GROUP BY DATE(r.timestamp), r.response_type ORDER BY response_date ASC";
    
    $results = DatabaseUtils::executeQuery($sql, $params);
    
    // Process data for chart - optimized
    $dateData = [];
    foreach ($results as $row) {
        $date = $row['response_date'];
        if (!isset($dateData[$date])) {
            $dateData[$date] = [
                'response_time' => 0,
                'response_count' => 0,
                'count' => 0
            ];
        }
        
        $dateData[$date]['response_time'] += $row['avg_response_time'];
        $dateData[$date]['response_count'] += $row['response_count'];
        $dateData[$date]['count']++;
    }
    
    $chartData = [
        'labels' => [],
        'response_times' => [],
        'response_counts' => []
    ];
    
    // Calculate averages and format data
    foreach ($dateData as $date => $data) {
        $chartData['labels'][] = date('M j', strtotime($date));
        $chartData['response_times'][] = max(0, round($data['response_time'] / $data['count'], 1));
        $chartData['response_counts'][] = max(0, $data['response_count']);
    }
    
    // If no data, show message
    if (empty($chartData['labels'])) {
        $chartData = [
            'labels' => ['No Response Data Available'],
            'response_times' => [0],
            'response_counts' => [0]
        ];
    }
    
    DatabaseUtils::sendResponse(true, $chartData, 'Response statistics loaded successfully', [
        'total_responses' => count($results),
        'date_range' => ['start' => $startDate, 'end' => $endDate],
        'filters' => [
            'firefighter_id' => $firefighterId,
            'barangay' => $barangay
        ],
        'unique_dates' => count($dateData)
    ]);
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load response statistics', $e->getMessage());
}
?>

<?php
require_once 'common/database_utils.php';

// Start session to get user_id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get filter parameters
    $barangay = $_GET['barangay'] ?? '';
    $month = $_GET['month'] ?? '';
    $year = $_GET['year'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $deviceStatus = $_GET['device_status'] ?? ''; // Optional: filter by device status (online/offline/faulty)
    
    // Get user_id from session (optional - if not set, show all data)
    $userId = $_SESSION['user_id'] ?? null;
    
    // Build query to get heat data from fire_data table
    // Directly use fire_data.barangay_id since it has a foreign key constraint
    $params = [];
    
    // Build WHERE conditions for fire_data
    $whereConditions = [];
    
    // Only filter by user_id if it exists in session
    if ($userId) {
        $whereConditions[] = "fd.user_id = :user_id";
        $params[':user_id'] = $userId;
    }
    
    // Allow 0 values for heat/temp/smoke (they are valid readings)
    // Only exclude NULL values - be more lenient
    $whereConditions[] = "fd.barangay_id IS NOT NULL"; // Ensure barangay_id exists
    // Only require heat to be not null (most important for heat analysis)
    $whereConditions[] = "fd.heat IS NOT NULL";
    
    // Add date filters - handle VARCHAR timestamp
    // timestamp is VARCHAR(50), so we need to handle it as string
    if (!empty($month)) {
        // Extract month from timestamp string (format: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS)
        $whereConditions[] = "SUBSTRING(fd.timestamp, 6, 2) = :month";
        $params[':month'] = str_pad($month, 2, '0', STR_PAD_LEFT);
    }
    if (!empty($year)) {
        // Extract year from timestamp string (first 4 characters)
        $whereConditions[] = "SUBSTRING(fd.timestamp, 1, 4) = :year";
        $params[':year'] = $year;
    }
    if (!empty($startDate)) {
        // Compare date part of timestamp string
        $whereConditions[] = "SUBSTRING(fd.timestamp, 1, 10) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if (!empty($endDate)) {
        // Compare date part of timestamp string
        $whereConditions[] = "SUBSTRING(fd.timestamp, 1, 10) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    // Query directly from fire_data table using barangay_id foreign key
    $sql = "SELECT 
                b.id,
                b.barangay_name,
                b.latitude,
                b.longitude,
                b.ir_number,
                AVG(CAST(fd.heat AS DECIMAL(10,2))) as avg_heat,
                COUNT(fd.id) as total_readings,
                MAX(CAST(fd.heat AS DECIMAL(10,2))) as max_heat,
                MIN(CAST(fd.heat AS DECIMAL(10,2))) as min_heat,
                AVG(CAST(fd.temp AS DECIMAL(10,2))) as avg_temp,
                AVG(CAST(fd.smoke AS DECIMAL(10,2))) as avg_smoke,
                COUNT(DISTINCT fd.device_id) as device_count
            FROM fire_data fd
            INNER JOIN barangay b ON fd.barangay_id = b.id
            LEFT JOIN devices d ON fd.device_id = d.device_id";
    
    // Add device status filter (optional)
    if (!empty($deviceStatus) && in_array($deviceStatus, ['online', 'offline', 'faulty'])) {
        $whereConditions[] = "d.status = :device_status";
        $params[':device_status'] = $deviceStatus;
    }
    
    // Add barangay filter
    if (!empty($barangay)) {
        $whereConditions[] = "b.id = :barangay";
        $params[':barangay'] = $barangay;
    }
    
    // Add WHERE clause only if we have conditions
    if (!empty($whereConditions)) {
        $sql .= " WHERE " . implode(" AND ", $whereConditions);
    }
    
    // Group by barangay - shows all barangays with fire_data for this user
    $sql .= " GROUP BY b.id, b.barangay_name, b.latitude, b.longitude, b.ir_number 
              ORDER BY avg_heat DESC, b.barangay_name ASC";
    
    // Log query for debugging (remove in production if needed)
    error_log("Barangay Stats Query: " . $sql);
    error_log("Barangay Stats Params: " . json_encode($params));
    
    try {
        $results = DatabaseUtils::executeQuery($sql, $params);
        error_log("Barangay Stats Results Count: " . count($results));
        
        // If no results, try a simpler query to see if any data exists
        if (empty($results)) {
            $testSql = "SELECT COUNT(*) as total FROM fire_data WHERE barangay_id IS NOT NULL";
            $testResults = DatabaseUtils::executeQuery($testSql, []);
            error_log("Total fire_data records with barangay_id: " . json_encode($testResults));
        }
    } catch (Exception $queryError) {
        error_log("Query execution error: " . $queryError->getMessage());
        throw $queryError;
    }
    
    // Process data for chart - optimized
    $chartData = [
        'labels' => [],
        'heat_data' => [],
        'total_readings' => [],
        'max_heat' => [],
        'min_heat' => [],
        'avg_temp' => [],
        'avg_smoke' => []
    ];
    
    foreach ($results as $row) {
        // Ensure we have valid data
        if (!empty($row['barangay_name'])) {
            $chartData['labels'][] = $row['barangay_name'];
            $chartData['heat_data'][] = round((float)($row['avg_heat'] ?? 0), 1);
            $chartData['total_readings'][] = (int)($row['total_readings'] ?? 0);
            $chartData['max_heat'][] = round((float)($row['max_heat'] ?? 0), 1);
            $chartData['min_heat'][] = round((float)($row['min_heat'] ?? 0), 1);
            $chartData['avg_temp'][] = round((float)($row['avg_temp'] ?? 0), 1);
            $chartData['avg_smoke'][] = round((float)($row['avg_smoke'] ?? 0), 1);
        }
    }
    
    // If no data, show message
    if (empty($chartData['labels'])) {
        $chartData = [
            'labels' => ['No Data Available'],
            'heat_data' => [0],
            'total_readings' => [0],
            'max_heat' => [0],
            'min_heat' => [0],
            'avg_temp' => [0],
            'avg_smoke' => [0]
        ];
    }
    
    DatabaseUtils::sendResponse(true, $chartData, 'Barangay statistics loaded successfully', [
        'total_barangays' => count($results),
        'user_id' => $userId,
        'filters' => [
            'barangay' => $barangay,
            'month' => $month,
            'year' => $year,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'device_status' => $deviceStatus
        ],
        'query_params' => $params
    ]);
    
} catch (Exception $e) {
    error_log("Barangay Stats Error: " . $e->getMessage());
    error_log("Barangay Stats Trace: " . $e->getTraceAsString());
    DatabaseUtils::sendError('Failed to load barangay statistics', $e->getMessage());
}
?>

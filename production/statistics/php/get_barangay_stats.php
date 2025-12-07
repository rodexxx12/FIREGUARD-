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

// Start session to get user_id
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Get filter parameters with validation
    $barangayRaw = $_GET['barangay'] ?? null;
    $barangay = DatabaseUtils::sanitizeInt($barangayRaw, 1);
    if ($barangayRaw !== null && $barangay === null) {
        DatabaseUtils::sendError('Invalid barangay identifier.');
    }

    $monthRaw = $_GET['month'] ?? null;
    $month = (isset($monthRaw) && preg_match('/^(0?[1-9]|1[0-2])$/', $monthRaw)) ? str_pad($monthRaw, 2, '0', STR_PAD_LEFT) : null;
    if ($monthRaw && !$month) {
        DatabaseUtils::sendError('Invalid month filter. Use 1-12.');
    }

    $yearRaw = $_GET['year'] ?? null;
    $year = (isset($yearRaw) && preg_match('/^\d{4}$/', $yearRaw)) ? $yearRaw : null;
    if ($yearRaw && !$year) {
        DatabaseUtils::sendError('Invalid year filter. Use YYYY.');
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

    $deviceStatusRaw = $_GET['device_status'] ?? null; // Optional: filter by device status
    $deviceStatus = DatabaseUtils::sanitizeEnum($deviceStatusRaw, ['online', 'offline', 'faulty']);
    if ($deviceStatusRaw && !$deviceStatus) {
        DatabaseUtils::sendError('Invalid device status filter.');
    }
    
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
    
    $timestampExpression = "COALESCE(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s'), STR_TO_DATE(fd.timestamp, '%Y-%m-%d'))";

    // Allow 0 values for heat/temp/smoke (they are valid readings)
    $whereConditions[] = "fd.barangay_id IS NOT NULL"; // Ensure barangay_id exists
    $whereConditions[] = "fd.heat IS NOT NULL";
    $whereConditions[] = "{$timestampExpression} IS NOT NULL";
    
    // Add date filters against parsed timestamp
    if (!empty($month)) {
        $whereConditions[] = "MONTH({$timestampExpression}) = :month";
        $params[':month'] = (int)$month;
    }
    if (!empty($year)) {
        $whereConditions[] = "YEAR({$timestampExpression}) = :year";
        $params[':year'] = (int)$year;
    }
    if (!empty($startDate)) {
        $whereConditions[] = "{$timestampExpression} >= :start_date";
        $params[':start_date'] = "{$startDate} 00:00:00";
    }
    if (!empty($endDate)) {
        $whereConditions[] = "{$timestampExpression} <= :end_date";
        $params[':end_date'] = "{$endDate} 23:59:59";
    }
    
    // Query directly from fire_data table using barangay_id foreign key
    $sql = "SELECT 
                b.id,
                b.barangay_name,
                b.latitude,
                b.longitude,
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
    if (!empty($deviceStatus)) {
        $whereConditions[] = "d.status = :device_status";
        $params[':device_status'] = strtolower($deviceStatus);
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
    $sql .= " GROUP BY b.id, b.barangay_name, b.latitude, b.longitude 
              ORDER BY avg_heat DESC, b.barangay_name ASC";
    
    if (DatabaseUtils::isDebugEnabled()) {
        error_log("Barangay Stats Query: " . $sql);
        error_log("Barangay Stats Params: " . json_encode($params));
    }
    
    try {
        $results = DatabaseUtils::executeQuery($sql, $params);
        
        // Ensure results is an array
        if (!is_array($results)) {
            $results = [];
        }
        
        error_log("Barangay Stats Results Count: " . count($results));
        
        // If no results, that's okay - we'll show empty data
        // Don't throw an error for empty results
    } catch (Exception $queryError) {
        error_log("Query execution error: " . $queryError->getMessage());
        error_log("Query: " . $sql);
        error_log("Params: " . json_encode($params));
        error_log("Stack trace: " . $queryError->getTraceAsString());
        throw $queryError;
    } catch (Throwable $queryError) {
        error_log("Query execution fatal error: " . $queryError->getMessage());
        error_log("Query: " . $sql);
        error_log("Params: " . json_encode($params));
        error_log("Stack trace: " . $queryError->getTraceAsString());
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
    
    // Safely process results
    if (is_array($results) && !empty($results)) {
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
} catch (Throwable $e) {
    error_log("Barangay Stats Fatal Error: " . $e->getMessage());
    error_log("Barangay Stats Trace: " . $e->getTraceAsString());
    DatabaseUtils::sendError('Failed to load barangay statistics', $e->getMessage());
}

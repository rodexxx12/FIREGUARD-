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

    $barangayRaw = $_GET['barangay'] ?? null;
    $barangay = DatabaseUtils::sanitizeInt($barangayRaw, 1);
    if ($barangayRaw !== null && $barangay === null) {
        DatabaseUtils::sendError('Invalid barangay identifier.');
    }

    $mlConfidenceRaw = $_GET['ml_confidence'] ?? null;
    $mlConfidence = DatabaseUtils::sanitizeInt($mlConfidenceRaw, 0, 100);
    if ($mlConfidenceRaw !== null && $mlConfidence === null) {
        DatabaseUtils::sendError('Invalid ML confidence. Provide a number between 0 and 100.');
    }

    $mlPredictionRaw = $_GET['ml_prediction'] ?? null;
    $mlPrediction = DatabaseUtils::sanitizeEnum($mlPredictionRaw, ['0', '1']);
    if ($mlPredictionRaw && $mlPrediction === null) {
        DatabaseUtils::sendError('Invalid ML prediction filter.');
    }
    
    $cachePayload = DatabaseUtils::remember(
        'stats:incidents',
        [
            'start' => $startDate,
            'end' => $endDate,
            'barangay' => $barangay,
            'ml_confidence' => $mlConfidence,
            'ml_prediction' => $mlPrediction
        ],
        function () use ($barangay, $startDate, $endDate, $mlConfidence, $mlPrediction) {
            $totalCount = DatabaseUtils::executeSingleQuery(
                "SELECT COUNT(*) as total_count FROM fire_data WHERE timestamp IS NOT NULL"
            )['total_count'];

            if ($totalCount == 0) {
                return [
                    'chartData' => [
                        'labels' => ['No Fire Data Available'],
                        'data' => [0]
                    ],
                    'meta' => [
                        'total_records' => 0,
                        'filtered_results' => 0,
                        'date_range' => ['start' => $startDate, 'end' => $endDate],
                        'filters' => [
                            'barangay' => $barangay,
                            'ml_confidence' => $mlConfidence,
                            'ml_prediction' => $mlPrediction
                        ],
                        'query_params' => []
                    ]
                ];
            }

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
            DatabaseUtils::buildBarangayFilter($barangay, $sql, $params);
            DatabaseUtils::buildDateFilters($startDate, $endDate, $sql, $params);

            if ($mlConfidence !== null) {
                $sql .= " AND fd.ml_confidence >= :ml_confidence";
                $params[':ml_confidence'] = (float)$mlConfidence;
            }

            if ($mlPrediction !== null) {
                $sql .= " AND fd.ml_prediction = :ml_prediction";
                $params[':ml_prediction'] = (int)$mlPrediction;
            }

            $sql .= " GROUP BY COALESCE(bld_barangay.barangay_name, fd_barangay.barangay_name, 'Unknown'), COALESCE(fd.building_type, 'Unknown') 
                      ORDER BY incident_count DESC";

            $results = DatabaseUtils::executeQuery($sql, $params);

            $chartData = [
                'labels' => [],
                'data' => []
            ];

            foreach ($results as $row) {
                $chartData['labels'][] = $row['barangay_name'] . ' - ' . ucfirst($row['building_type']);
                $chartData['data'][] = (int)$row['incident_count'];
            }

            if (empty($chartData['labels'])) {
                $chartData = [
                    'labels' => ['No Fire Data Found'],
                    'data' => [0]
                ];
            }

            return [
                'chartData' => $chartData,
                'meta' => [
                    'total_records' => $totalCount,
                    'filtered_results' => count($results),
                    'date_range' => ['start' => $startDate, 'end' => $endDate],
                    'filters' => [
                        'barangay' => $barangay,
                        'ml_confidence' => $mlConfidence,
                        'ml_prediction' => $mlPrediction
                    ],
                    'query_params' => $params
                ]
            ];
        },
        120
    );

    DatabaseUtils::sendResponse(
        true,
        $cachePayload['chartData'],
        'Fire Data Analysis loaded successfully',
        $cachePayload['meta']
    );
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load fire incident statistics', $e->getMessage());
}
?>

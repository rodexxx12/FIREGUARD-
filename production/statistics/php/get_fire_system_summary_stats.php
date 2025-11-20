<?php
require_once 'common/database_utils.php';

try {
    // Get filter parameters
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $barangay = $_GET['barangay'] ?? '';
    
    $summaryData = [];
    
    // 1. ALARM STATISTICS - Monthly count of emergency alarms
    $alarmSql = "SELECT 
                    fd.status,
                    COUNT(*) as count
                FROM fire_data fd
                " . DatabaseUtils::getFireDataJoins() . "
                WHERE fd.timestamp IS NOT NULL
                " . DatabaseUtils::getCurrentMonthYearFilter();
    
    $alarmParams = [];
    DatabaseUtils::buildBarangayFilter($barangay, $alarmSql, $alarmParams);
    $alarmSql .= " GROUP BY fd.status";
    
    $alarmResults = DatabaseUtils::executeQuery($alarmSql, $alarmParams);
    
    $totalAlarms = $emergencyAlarms = $warningAlarms = $normalAlarms = 0;
    
    foreach ($alarmResults as $row) {
        $totalAlarms += $row['count'];
        switch (strtoupper($row['status'])) {
            case 'EMERGENCY':
            case 'FIRE':
                $emergencyAlarms += $row['count'];
                break;
            case 'WARNING':
            case 'ACKNOWLEDGED':
                $warningAlarms += $row['count'];
                break;
            case 'NORMAL':
                $normalAlarms += $row['count'];
                break;
        }
    }
    
    $summaryData['alarm_stats'] = [
        'total_alarms' => $totalAlarms,
        'emergency_alarms' => $emergencyAlarms,
        'warning_alarms' => $warningAlarms,
        'normal_alarms' => $normalAlarms
    ];
    
    // 2. BARANGAY HEAT ANALYSIS - Monthly average heat levels
    $heatSql = "SELECT 
                    COUNT(DISTINCT b.id) as total_barangays,
                    AVG(fd.heat) as avg_heat_level,
                    MAX(fd.heat) as max_heat_level,
                    COUNT(fd.id) as total_readings
                FROM barangay b
                " . DatabaseUtils::getBarangayJoins() . "
                WHERE fd.timestamp IS NOT NULL
                " . DatabaseUtils::getCurrentMonthYearFilter();
    
    $heatParams = [];
    if (!empty($barangay)) {
        $heatSql .= " AND b.id = :barangay";
        $heatParams[':barangay'] = $barangay;
    }
    
    $heatResult = DatabaseUtils::executeSingleQuery($heatSql, $heatParams);
    
    $summaryData['heat_analysis'] = [
        'total_barangays' => (int)$heatResult['total_barangays'],
        'avg_heat_level' => round($heatResult['avg_heat_level'] ?? 0, 1),
        'max_heat_level' => round($heatResult['max_heat_level'] ?? 0, 1),
        'total_readings' => (int)$heatResult['total_readings']
    ];
    
    // 3. FIRE DATA ANALYSIS - Monthly fire incidents
    $fireSql = "SELECT 
                    COUNT(*) as total_fire_incidents,
                    COUNT(DISTINCT COALESCE(fd.building_id, d.building_id)) as affected_buildings,
                    COUNT(DISTINCT COALESCE(bld_barangay.id, fd_barangay.id)) as affected_barangays
                FROM fire_data fd
                LEFT JOIN devices d ON fd.device_id = d.device_id
                LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
                LEFT JOIN barangay bld_barangay ON bld.barangay_id = bld_barangay.id
                LEFT JOIN barangay fd_barangay ON fd.barangay_id = fd_barangay.id
                WHERE fd.timestamp IS NOT NULL
                AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire')
                " . DatabaseUtils::getCurrentMonthYearFilter();
    
    $fireParams = [];
    DatabaseUtils::buildBarangayFilter($barangay, $fireSql, $fireParams);
    
    $fireResult = DatabaseUtils::executeSingleQuery($fireSql, $fireParams);
    
    $summaryData['fire_data_analysis'] = [
        'total_fire_incidents' => (int)$fireResult['total_fire_incidents'],
        'affected_buildings' => (int)$fireResult['affected_buildings'],
        'affected_barangays' => (int)$fireResult['affected_barangays']
    ];
    
    // 4. RESPONSE DATA - Monthly response statistics
    $responseSql = "SELECT 
                        COUNT(*) as total_responses,
                        COUNT(DISTINCT r.firefighter_id) as active_firefighters,
                        AVG(TIMESTAMPDIFF(MINUTE, fd.timestamp, r.timestamp)) as avg_response_time,
                        COUNT(DISTINCT DATE(r.timestamp)) as response_days
                    FROM responses r
                    LEFT JOIN fire_data fd ON r.fire_data_id = fd.id
                    LEFT JOIN devices d ON fd.device_id = d.device_id
                    LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
                    LEFT JOIN barangay b ON (fd.barangay_id = b.id OR bld.barangay_id = b.id)
                    WHERE MONTH(r.timestamp) = MONTH(CURRENT_DATE())
                    AND YEAR(r.timestamp) = YEAR(CURRENT_DATE())";
    
    $responseParams = [];
    DatabaseUtils::buildBarangayFilter($barangay, $responseSql, $responseParams);
    
    $responseResult = DatabaseUtils::executeSingleQuery($responseSql, $responseParams);
    
    $summaryData['response_data'] = [
        'total_responses' => (int)$responseResult['total_responses'],
        'active_firefighters' => (int)$responseResult['active_firefighters'],
        'avg_response_time' => round($responseResult['avg_response_time'] ?? 0, 1),
        'response_days' => (int)$responseResult['response_days']
    ];
    
    // Get current month name
    $currentMonth = date('F Y');
    
    DatabaseUtils::sendResponse(true, $summaryData, 'Fire system monthly summary statistics loaded successfully', [
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'barangay' => $barangay
        ],
        'alarm_stats' => $summaryData['alarm_stats'],
        'heat_analysis' => $summaryData['heat_analysis'],
        'fire_data_analysis' => $summaryData['fire_data_analysis'],
        'response_data' => $summaryData['response_data'],
        'current_month' => $currentMonth
    ]);
    
} catch (Exception $e) {
    DatabaseUtils::sendError('Failed to load fire system summary statistics', $e->getMessage());
}
?>

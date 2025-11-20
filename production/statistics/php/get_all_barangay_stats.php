<?php
require_once '../../../db/db.php';

header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    
    // Get comprehensive statistics for all barangays
    $sql = "SELECT 
                b.id,
                b.barangay_name,
                
                -- Fire Data Statistics
                COUNT(DISTINCT fd.id) as total_fire_readings,
                COUNT(DISTINCT CASE WHEN fd.status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire') THEN fd.id END) as fire_incidents,
                COUNT(DISTINCT CASE WHEN fd.status = 'NORMAL' THEN fd.id END) as normal_readings,
                COUNT(DISTINCT CASE WHEN fd.status = 'ACKNOWLEDGED' THEN fd.id END) as warning_readings,
                
                -- Environmental Statistics
                AVG(fd.heat) as avg_heat_index,
                MAX(fd.heat) as max_heat_index,
                MIN(fd.heat) as min_heat_index,
                AVG(fd.temp) as avg_temperature,
                AVG(fd.smoke) as avg_smoke_level,
                
                -- Device Statistics
                COUNT(DISTINCT fd.device_id) as total_devices,
                COUNT(DISTINCT CASE WHEN d.status = 'online' THEN fd.device_id END) as online_devices,
                COUNT(DISTINCT CASE WHEN d.status = 'offline' THEN fd.device_id END) as offline_devices,
                
                -- Building Statistics
                COUNT(DISTINCT COALESCE(fd.building_id, d.building_id)) as total_buildings,
                COUNT(DISTINCT CASE WHEN bld.building_type = 'residential' THEN COALESCE(fd.building_id, d.building_id) END) as residential_buildings,
                COUNT(DISTINCT CASE WHEN bld.building_type = 'commercial' THEN COALESCE(fd.building_id, d.building_id) END) as commercial_buildings,
                COUNT(DISTINCT CASE WHEN bld.building_type = 'industrial' THEN COALESCE(fd.building_id, d.building_id) END) as industrial_buildings,
                
                -- Response Statistics
                COUNT(DISTINCT r.id) as total_responses,
                AVG(r.response_time) as avg_response_time,
                MIN(r.response_time) as min_response_time,
                MAX(r.response_time) as max_response_time,
                
                -- Recent Activity (last 30 days)
                COUNT(DISTINCT CASE WHEN fd.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN fd.id END) as recent_readings,
                COUNT(DISTINCT CASE WHEN fd.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED', 'fire') THEN fd.id END) as recent_incidents,
                
                -- Last Activity
                MAX(fd.timestamp) as last_activity,
                MAX(r.created_at) as last_response
                
            FROM barangay b
            LEFT JOIN fire_data fd ON (
                fd.barangay_id = b.id OR 
                EXISTS (
                    SELECT 1 FROM devices d 
                    LEFT JOIN buildings bld ON d.building_id = bld.id 
                    WHERE d.device_id = fd.device_id AND bld.barangay_id = b.id
                )
            )
            LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings bld ON (fd.building_id = bld.id OR d.building_id = bld.id)
            LEFT JOIN responses r ON r.barangay_id = b.id
            
            WHERE EXISTS (
                SELECT 1 FROM buildings bld2 WHERE bld2.barangay_id = b.id
            ) OR EXISTS (
                SELECT 1 FROM fire_data fd2 WHERE fd2.barangay_id = b.id
            ) OR EXISTS (
                SELECT 1 FROM devices d2 
                LEFT JOIN buildings bld3 ON d2.building_id = bld3.id 
                WHERE bld3.barangay_id = b.id
            )
            
            GROUP BY b.id, b.barangay_name
            ORDER BY fire_incidents DESC, total_fire_readings DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    // Process data for detailed display
    $barangayStats = [];
    
    foreach ($results as $row) {
        $barangayStats[] = [
            'id' => (int)$row['id'],
            'name' => $row['barangay_name'],
            
            // Fire Statistics
            'fire_stats' => [
                'total_readings' => (int)$row['total_fire_readings'],
                'fire_incidents' => (int)$row['fire_incidents'],
                'normal_readings' => (int)$row['normal_readings'],
                'warning_readings' => (int)$row['warning_readings'],
                'incident_rate' => $row['total_fire_readings'] > 0 ? round(($row['fire_incidents'] / $row['total_fire_readings']) * 100, 2) : 0
            ],
            
            // Environmental Statistics
            'environmental_stats' => [
                'avg_heat_index' => round($row['avg_heat_index'] ?? 0, 1),
                'max_heat_index' => round($row['max_heat_index'] ?? 0, 1),
                'min_heat_index' => round($row['min_heat_index'] ?? 0, 1),
                'avg_temperature' => round($row['avg_temperature'] ?? 0, 1),
                'avg_smoke_level' => round($row['avg_smoke_level'] ?? 0, 1)
            ],
            
            // Device Statistics
            'device_stats' => [
                'total_devices' => (int)$row['total_devices'],
                'online_devices' => (int)$row['online_devices'],
                'offline_devices' => (int)$row['offline_devices'],
                'online_percentage' => $row['total_devices'] > 0 ? round(($row['online_devices'] / $row['total_devices']) * 100, 1) : 0
            ],
            
            // Building Statistics
            'building_stats' => [
                'total_buildings' => (int)$row['total_buildings'],
                'residential_buildings' => (int)$row['residential_buildings'],
                'commercial_buildings' => (int)$row['commercial_buildings'],
                'industrial_buildings' => (int)$row['industrial_buildings']
            ],
            
            // Response Statistics
            'response_stats' => [
                'total_responses' => (int)$row['total_responses'],
                'avg_response_time' => round($row['avg_response_time'] ?? 0, 1),
                'min_response_time' => round($row['min_response_time'] ?? 0, 1),
                'max_response_time' => round($row['max_response_time'] ?? 0, 1)
            ],
            
            // Recent Activity
            'recent_activity' => [
                'recent_readings' => (int)$row['recent_readings'],
                'recent_incidents' => (int)$row['recent_incidents'],
                'last_activity' => $row['last_activity'],
                'last_response' => $row['last_response']
            ],
            
            // Risk Assessment
            'risk_assessment' => [
                'risk_level' => calculateRiskLevel($row),
                'risk_score' => calculateRiskScore($row),
                'recommendations' => generateRecommendations($row)
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $barangayStats,
        'message' => 'Comprehensive barangay statistics loaded successfully',
        'total_barangays' => count($barangayStats)
    ]);
    
} catch (Exception $e) {
    error_log("Error in get_all_barangay_stats.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load comprehensive barangay statistics',
        'error' => $e->getMessage()
    ]);
}

function calculateRiskLevel($row) {
    $fireIncidents = (int)$row['fire_incidents'];
    $totalReadings = (int)$row['total_fire_readings'];
    $avgHeat = (float)$row['avg_heat_index'];
    $offlineDevices = (int)$row['offline_devices'];
    $totalDevices = (int)$row['total_devices'];
    
    $riskScore = 0;
    
    // Fire incident rate
    if ($totalReadings > 0) {
        $incidentRate = ($fireIncidents / $totalReadings) * 100;
        if ($incidentRate > 10) $riskScore += 3;
        elseif ($incidentRate > 5) $riskScore += 2;
        elseif ($incidentRate > 1) $riskScore += 1;
    }
    
    // Heat index
    if ($avgHeat > 40) $riskScore += 3;
    elseif ($avgHeat > 35) $riskScore += 2;
    elseif ($avgHeat > 30) $riskScore += 1;
    
    // Device offline rate
    if ($totalDevices > 0) {
        $offlineRate = ($offlineDevices / $totalDevices) * 100;
        if ($offlineRate > 50) $riskScore += 2;
        elseif ($offlineRate > 25) $riskScore += 1;
    }
    
    if ($riskScore >= 6) return 'HIGH';
    elseif ($riskScore >= 3) return 'MEDIUM';
    else return 'LOW';
}

function calculateRiskScore($row) {
    $fireIncidents = (int)$row['fire_incidents'];
    $totalReadings = (int)$row['total_fire_readings'];
    $avgHeat = (float)$row['avg_heat_index'];
    $offlineDevices = (int)$row['offline_devices'];
    $totalDevices = (int)$row['total_devices'];
    
    $score = 0;
    
    // Fire incident rate (40% weight)
    if ($totalReadings > 0) {
        $incidentRate = ($fireIncidents / $totalReadings) * 100;
        $score += min($incidentRate * 2, 40);
    }
    
    // Heat index (30% weight)
    $score += min(($avgHeat - 20) * 1.5, 30);
    
    // Device offline rate (20% weight)
    if ($totalDevices > 0) {
        $offlineRate = ($offlineDevices / $totalDevices) * 100;
        $score += min($offlineRate * 0.2, 20);
    }
    
    // Response time (10% weight)
    $avgResponseTime = (float)$row['avg_response_time'];
    $score += min($avgResponseTime * 0.1, 10);
    
    return min(round($score, 1), 100);
}

function generateRecommendations($row) {
    $recommendations = [];
    
    $fireIncidents = (int)$row['fire_incidents'];
    $totalReadings = (int)$row['total_fire_readings'];
    $avgHeat = (float)$row['avg_heat_index'];
    $offlineDevices = (int)$row['offline_devices'];
    $totalDevices = (int)$row['total_devices'];
    
    // Fire incident recommendations
    if ($totalReadings > 0) {
        $incidentRate = ($fireIncidents / $totalReadings) * 100;
        if ($incidentRate > 5) {
            $recommendations[] = "High fire incident rate detected. Consider additional fire safety measures.";
        }
    }
    
    // Heat index recommendations
    if ($avgHeat > 35) {
        $recommendations[] = "High average heat index. Monitor temperature-sensitive areas closely.";
    }
    
    // Device offline recommendations
    if ($totalDevices > 0) {
        $offlineRate = ($offlineDevices / $totalDevices) * 100;
        if ($offlineRate > 25) {
            $recommendations[] = "High number of offline devices. Check device connectivity and maintenance.";
        }
    }
    
    // Response time recommendations
    $avgResponseTime = (float)$row['avg_response_time'];
    if ($avgResponseTime > 15) {
        $recommendations[] = "Average response time is high. Consider optimizing response procedures.";
    }
    
    if (empty($recommendations)) {
        $recommendations[] = "All systems operating within normal parameters.";
    }
    
    return $recommendations;
}
?>

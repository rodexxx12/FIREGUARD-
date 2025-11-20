<?php
require_once __DIR__ . '/../functions/functions.php';

header('Content-Type: application/json');

try {
    // Get device assignment statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_devices,
            COUNT(CASE WHEN building_id IS NOT NULL THEN 1 END) as assigned_devices,
            COUNT(CASE WHEN building_id IS NULL THEN 1 END) as unassigned_devices,
            COUNT(CASE WHEN status = 'online' THEN 1 END) as online_devices,
            COUNT(CASE WHEN status = 'offline' THEN 1 END) as offline_devices,
            COUNT(CASE WHEN status = 'error' THEN 1 END) as error_devices
        FROM devices
    ";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recent assignment history (last 10 assignments)
    $historyQuery = "
        SELECT 
            d.device_name,
            d.device_number,
            d.serial_number,
            b.building_name,
            b.building_type,
            d.status,
            d.created_at as assigned_at,
            'assigned' as action_type
        FROM devices d
        LEFT JOIN buildings b ON d.building_id = b.id
        WHERE d.building_id IS NOT NULL
        ORDER BY d.created_at DESC
        LIMIT 10
    ";
    
    $historyStmt = $pdo->prepare($historyQuery);
    $historyStmt->execute();
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get building-wise device counts
    $buildingStatsQuery = "
        SELECT 
            b.building_name,
            b.building_type,
            COUNT(d.device_id) as device_count,
            COUNT(CASE WHEN d.status = 'online' THEN 1 END) as online_count
        FROM buildings b
        LEFT JOIN devices d ON b.id = d.building_id
        GROUP BY b.id, b.building_name, b.building_type
        ORDER BY device_count DESC
        LIMIT 5
    ";
    
    $buildingStatsStmt = $pdo->prepare($buildingStatsQuery);
    $buildingStatsStmt->execute();
    $buildingStats = $buildingStatsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the response
    $response = [
        'success' => true,
        'stats' => [
            'total_devices' => (int)$stats['total_devices'],
            'assigned_devices' => (int)$stats['assigned_devices'],
            'unassigned_devices' => (int)$stats['unassigned_devices'],
            'online_devices' => (int)$stats['online_devices'],
            'offline_devices' => (int)$stats['offline_devices'],
            'error_devices' => (int)$stats['error_devices'],
            'assignment_percentage' => $stats['total_devices'] > 0 ? round(($stats['assigned_devices'] / $stats['total_devices']) * 100, 1) : 0
        ],
        'recent_history' => $history,
        'building_stats' => $buildingStats
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching assignment statistics: ' . $e->getMessage()
    ]);
}
?>

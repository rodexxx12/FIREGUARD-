<?php
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    // Get total logs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM system_logs");
    $totalLogs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Get logs by level
    $stmt = $pdo->prepare("SELECT COUNT(*) as error_count FROM system_logs WHERE log_level = 'ERROR'");
    $stmt->execute();
    $errorLogs = $stmt->fetch(PDO::FETCH_ASSOC)['error_count'];

    // Get logs from this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as recent FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $recentLogs = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];

    // Get fire detection count
    $stmt = $pdo->query("SELECT COUNT(*) as fire_count FROM system_logs WHERE fire_detected = 1");
    $fireDetections = $stmt->fetch(PDO::FETCH_ASSOC)['fire_count'];

    // Get log level distribution
    $stmt = $pdo->query("
        SELECT log_level, COUNT(*) as count 
        FROM system_logs 
        GROUP BY log_level
        ORDER BY log_level
    ");
    $logLevelDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get event type distribution
    $stmt = $pdo->query("
        SELECT event_type, COUNT(*) as count 
        FROM system_logs 
        GROUP BY event_type
        ORDER BY count DESC
        LIMIT 10
    ");
    $eventTypeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get log trend (last 6 months)
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM system_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $logTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unique locations
    $stmt = $pdo->query("SELECT COUNT(DISTINCT location) as unique_locations FROM system_logs WHERE location IS NOT NULL");
    $uniqueLocations = $stmt->fetch(PDO::FETCH_ASSOC)['unique_locations'];

    echo json_encode([
        'success' => true,
        'data' => [
            'totalLogs' => (int)$totalLogs,
            'errorLogs' => (int)$errorLogs,
            'recentLogs' => (int)$recentLogs,
            'fireDetections' => (int)$fireDetections,
            'uniqueLocations' => (int)$uniqueLocations,
            'logLevelDistribution' => $logLevelDistribution,
            'eventTypeDistribution' => $eventTypeDistribution,
            'logTrend' => $logTrend
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 
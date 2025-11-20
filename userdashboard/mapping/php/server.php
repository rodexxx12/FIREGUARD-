<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Use centralized database connection
    require_once __DIR__ . '/../db/db.php';
    $pdo = getMappingDBConnection();

    // Get fire data with building information for the current user
    $sql = "
    SELECT 
        f.id,
        f.building_id,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.status,
        f.timestamp,
        f.geo_lat,
        f.geo_long,
        b.building_name,
        b.building_type,
        b.address
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    WHERE f.user_id = :user_id 
    AND f.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY f.timestamp DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $fireData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get counts for different statuses for the current user
    $countSql = "
    SELECT 
        UPPER(status) as status,
        COUNT(*) as count
    FROM fire_data 
    WHERE user_id = :user_id 
    AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY UPPER(status)
    ";
    
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute(['user_id' => $user_id]);
    $counts = $countStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent alerts for the current user
    $alertSql = "
    SELECT 
        f.id,
        f.temp,
        f.smoke,
        f.flame_detected,
        f.timestamp,
        b.building_type
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    WHERE f.user_id = :user_id 
    AND f.status IN ('Emergency', 'Pre-Dispatch', 'Monitoring')
    AND f.timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY f.timestamp DESC
    LIMIT 10
    ";
    
    $alertStmt = $pdo->prepare($alertSql);
    $alertStmt->execute(['user_id' => $user_id]);
    $alerts = $alertStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format counts into associative array
    $countsArray = [
        'SAFE' => 0,
        'MONITORING' => 0,
        'ACKNOWLEDGED' => 0,
        'EMERGENCY' => 0
    ];
    
    foreach ($counts as $count) {
        $status = strtoupper($count['status']);
        if (isset($countsArray[$status])) {
            $countsArray[$status] = (int)$count['count'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => $fireData,
        'counts' => $countsArray,
        'alerts' => $alerts
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database query failed', 
        'error' => $e->getMessage()
    ]);
}
?> 
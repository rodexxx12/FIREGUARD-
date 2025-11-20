<?php
function getStatusCounts($pdo) {
    $counts = [
        "SAFE" => 0,
        "MONITORING" => 0,
        "ACKNOWLEDGED" => 0,
        "EMERGENCY" => 0
    ];
    
    // Get user count
    $sql = "SELECT COUNT(*) as total_users FROM users";
    $stmt = $pdo->query($sql);
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
    $counts['total_users'] = $userCount;
    
    // Get building count
    $sql = "SELECT COUNT(*) as total_buildings FROM buildings";
    $stmt = $pdo->query($sql);
    $buildingCount = $stmt->fetch(PDO::FETCH_ASSOC)['total_buildings'];
    $counts['total_buildings'] = $buildingCount;
    
    // Get fire status counts
    $sql = "SELECT UPPER(status) as status, COUNT(*) as count FROM fire_data GROUP BY UPPER(status)";
    $stmt = $pdo->query($sql);
    $totalFireIncidents = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtoupper($row['status']);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $row['count'];
        }
        // Count all non-SAFE statuses as fire incidents
        if ($status !== 'SAFE') {
            $totalFireIncidents += $row['count'];
        }
    }
    
    $counts['total_fire_incidents'] = $totalFireIncidents;
    
    return $counts;
} 
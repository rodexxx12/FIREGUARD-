<?php
require_once __DIR__ . '/db_connect.php';

function getRecentAlerts($pdo) {
    $emergencyStatuses = ['EMERGENCY', 'ACKNOWLEDGED'];
    $alertsLimit = 5;
    
    $statusList = "'" . implode("','", $emergencyStatuses) . "'";
    $sql = "SELECT * FROM fire_data WHERE UPPER(status) IN ($statusList) ORDER BY timestamp DESC LIMIT " . $alertsLimit;
    $stmt = $pdo->query($sql);
    $alerts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alerts[] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'building_type' => $row['building_type'],
            'smoke' => $row['smoke'],
            'temp' => $row['temp'],
            'heat' => $row['heat'],
            'flame_detected' => $row['flame_detected'],
            'timestamp' => $row['timestamp'],
        ];
    }
    return $alerts;
} 
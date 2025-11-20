<?php
/**
 * Emergency Alerts Component
 * Handles fetching recent emergency alerts
 */

function getEmergencyAlerts($pdo) {
    // Query to get recent EMERGENCY alerts
    $sql = "SELECT * FROM fire_data WHERE UPPER(status) = 'EMERGENCY' ORDER BY timestamp DESC LIMIT 5";
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
?> 
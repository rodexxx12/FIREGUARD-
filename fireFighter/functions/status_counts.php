<?php
/**
 * Status Counts Component
 * Handles counting fire data by status
 */

function getStatusCounts($pdo) {
    // Normalize all status values to uppercase
    $sql = "SELECT UPPER(status) as status, COUNT(*) as count FROM fire_data GROUP BY UPPER(status)";
    $stmt = $pdo->query($sql);

    $counts = [
        "SAFE" => 0,
        "MONITORING" => 0,
        "PRE-DISPATCH" => 0,
        "EMERGENCY" => 0
    ];

    // Fetch data and update the counts array
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = strtoupper($row['status']);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $row['count'];
        }
    }

    return $counts;
}
?> 
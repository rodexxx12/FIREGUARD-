<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

$pdo = getDatabaseConnection();

// Check if there's any data in fire_data table
$stmt = $pdo->query("SELECT COUNT(*) as count FROM fire_data");
$count = $stmt->fetch()['count'];

echo "Total records in fire_data: " . $count . "<br>";

if ($count > 0) {
    // Get latest 5 records
    $stmt = $pdo->query("SELECT id, status, smoke, temp, heat, flame_detected, timestamp, device_id FROM fire_data ORDER BY timestamp DESC LIMIT 5");
    $records = $stmt->fetchAll();
    
    echo "<h3>Latest 5 records:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Status</th><th>Smoke</th><th>Temp</th><th>Heat</th><th>Flame</th><th>Timestamp</th><th>Device ID</th></tr>";
    
    foreach ($records as $record) {
        echo "<tr>";
        echo "<td>" . $record['id'] . "</td>";
        echo "<td>" . $record['status'] . "</td>";
        echo "<td>" . $record['smoke'] . "</td>";
        echo "<td>" . $record['temp'] . "</td>";
        echo "<td>" . $record['heat'] . "</td>";
        echo "<td>" . $record['flame_detected'] . "</td>";
        echo "<td>" . $record['timestamp'] . "</td>";
        echo "<td>" . $record['device_id'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No data found in fire_data table.";
}

// Check devices table
$stmt = $pdo->query("SELECT COUNT(*) as count FROM devices");
$deviceCount = $stmt->fetch()['count'];
echo "<br>Total devices: " . $deviceCount;
?>

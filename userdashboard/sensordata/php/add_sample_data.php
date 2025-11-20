<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

$pdo = getDatabaseConnection();

// Check if there's any data
$stmt = $pdo->query("SELECT COUNT(*) as count FROM fire_data");
$count = $stmt->fetch()['count'];

if ($count == 0) {
    echo "No data found. Adding sample data...<br>";
    
    // Get a user_id to use (first user in the system)
    $stmt = $pdo->query("SELECT user_id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        $userId = $user['user_id'];
        
        // Insert sample data
        $sampleData = [
            [
                'status' => 'normal',
                'building_type' => 'residential',
                'smoke' => 150,
                'temp' => 25,
                'heat' => 28,
                'flame_detected' => 0,
                'timestamp' => date('Y-m-d H:i:s'),
                'user_id' => $userId,
                'device_id' => 1
            ],
            [
                'status' => 'warning',
                'building_type' => 'commercial',
                'smoke' => 800,
                'temp' => 45,
                'heat' => 52,
                'flame_detected' => 0,
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'user_id' => $userId,
                'device_id' => 1
            ],
            [
                'status' => 'critical',
                'building_type' => 'industrial',
                'smoke' => 2500,
                'temp' => 85,
                'heat' => 95,
                'flame_detected' => 1,
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'user_id' => $userId,
                'device_id' => 1
            ]
        ];
        
        $sql = "INSERT INTO fire_data (status, building_type, smoke, temp, heat, flame_detected, timestamp, user_id, device_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($sampleData as $data) {
            $stmt->execute([
                $data['status'],
                $data['building_type'],
                $data['smoke'],
                $data['temp'],
                $data['heat'],
                $data['flame_detected'],
                $data['timestamp'],
                $data['user_id'],
                $data['device_id']
            ]);
        }
        
        echo "Sample data added successfully!<br>";
    } else {
        echo "No users found in the system.<br>";
    }
} else {
    echo "Data already exists (" . $count . " records).<br>";
}

// Show current data
$stmt = $pdo->query("SELECT id, status, smoke, temp, heat, flame_detected, timestamp FROM fire_data ORDER BY timestamp DESC LIMIT 3");
$records = $stmt->fetchAll();

echo "<h3>Latest 3 records:</h3>";
echo "<table border='1'>";
echo "<tr><th>ID</th><th>Status</th><th>Smoke</th><th>Temp</th><th>Heat</th><th>Flame</th><th>Timestamp</th></tr>";

foreach ($records as $record) {
    echo "<tr>";
    echo "<td>" . $record['id'] . "</td>";
    echo "<td>" . $record['status'] . "</td>";
    echo "<td>" . $record['smoke'] . "</td>";
    echo "<td>" . $record['temp'] . "</td>";
    echo "<td>" . $record['heat'] . "</td>";
    echo "<td>" . $record['flame_detected'] . "</td>";
    echo "<td>" . $record['timestamp'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

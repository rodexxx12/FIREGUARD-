<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

$pdo = getDatabaseConnection();

// Check if there are any devices
$stmt = $pdo->query("SELECT COUNT(*) as count FROM devices");
$deviceCount = $stmt->fetch()['count'];

if ($deviceCount == 0) {
    echo "No devices found. Adding sample device...<br>";
    
    // Get a user_id to use (first user in the system)
    $stmt = $pdo->query("SELECT user_id FROM users LIMIT 1");
    $user = $stmt->fetch();
    
    if ($user) {
        $userId = $user['user_id'];
        
        // Insert sample device
        $sql = "INSERT INTO devices (device_name, serial_number, user_id, status) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['Fire Sensor 001', 'FS001', $userId, 'active']);
        
        echo "Sample device added successfully!<br>";
    } else {
        echo "No users found in the system.<br>";
    }
} else {
    echo "Devices already exist (" . $deviceCount . " devices).<br>";
}

// Show current devices
$stmt = $pdo->query("SELECT device_id, device_name, serial_number, user_id, status FROM devices LIMIT 5");
$devices = $stmt->fetchAll();

echo "<h3>Current devices:</h3>";
echo "<table border='1'>";
echo "<tr><th>Device ID</th><th>Name</th><th>Serial</th><th>User ID</th><th>Status</th></tr>";

foreach ($devices as $device) {
    echo "<tr>";
    echo "<td>" . $device['device_id'] . "</td>";
    echo "<td>" . $device['device_name'] . "</td>";
    echo "<td>" . $device['serial_number'] . "</td>";
    echo "<td>" . $device['user_id'] . "</td>";
    echo "<td>" . $device['status'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>

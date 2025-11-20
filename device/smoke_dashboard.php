<?php
// Database connection
$conn = new mysqli("localhost", "u520834156_userBagofire", "i[#[GQ!+=C9", "u520834156_DBBagofire");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get latest readings
$result = $conn->query("SELECT * FROM smoke_readings ORDER BY reading_time DESC LIMIT 10");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Smoke Detection Dashboard</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .danger { background-color: #ffcccc; }
    </style>
</head>
<body>
    <h1>Smoke Detection Monitoring</h1>
    <table>
        <tr>
            <th>ID</th>
            <th>Sensor Value</th>
            <th>Status</th>
            <th>Timestamp</th>
        </tr>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr class="<?= $row['detected'] ? 'danger' : '' ?>">
            <td><?= $row['id'] ?></td>
            <td><?= $row['sensor_value'] ?></td>
            <td><?= $row['detected'] ? 'DETECTED' : 'Normal' ?></td>
            <td><?= $row['reading_time'] ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
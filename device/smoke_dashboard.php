<?php
// Load core configuration and database connection
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/database/database.php';

// Database connection using environment variables
$host = config('db.host', 'localhost');
$dbname = config('db.name', '');
$username = config('db.user', '');
$password = config('db.pass', '');

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get latest readings - using prepared statement
$stmt = $conn->prepare("SELECT * FROM smoke_readings ORDER BY reading_time DESC LIMIT ?");
$limit = 10;
$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();
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
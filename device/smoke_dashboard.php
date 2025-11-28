<?php
/**
 * Device Smoke Dashboard - Secure Version
 * SECURITY FIX: Removed hardcoded credentials
 */

// Environment-aware error handling
$isProduction = (getenv('APP_ENV') === 'production' || 
                 (isset($_SERVER['HTTP_HOST']) && 
                  strpos($_SERVER['HTTP_HOST'], 'localhost') === false &&
                  strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false));

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// SECURITY FIX: Use centralized database connection
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/database/database.php';

// Get database connection using centralized system
$conn = getDatabaseConnection();

// Check connection
if (!$conn) {
    error_log("Database connection failed in smoke_dashboard.php");
    die(json_encode(['error' => 'System temporarily unavailable']));
}

// Get latest readings - SECURITY: Use prepared statement (even for static queries)
$stmt = $conn->prepare("SELECT * FROM smoke_readings ORDER BY reading_time DESC LIMIT 10");
$stmt->execute();
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
        <?php 
        // Fetch all results using PDO
        $readings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($readings as $row): ?>
        <tr class="<?= $row['detected'] ? 'danger' : '' ?>">
            <td><?= htmlspecialchars($row['id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($row['sensor_value'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= ($row['detected'] ?? 0) ? 'DETECTED' : 'Normal' ?></td>
            <td><?= htmlspecialchars($row['reading_time'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
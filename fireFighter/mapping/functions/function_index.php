<?php

// Include all component files
require_once 'session_auth.php';
require_once 'database_connection.php';
require_once 'status_counts.php';
require_once 'emergency_alerts.php';
require_once 'building_data.php';
require_once 'latest_fire_data.php';
require_once 'recent_status_alerts.php';

// Validate session
validateFirefighterSession();

try {
    // Create database connection
    $pdo = getDatabaseConnection();

    // Get status counts
    $counts = getStatusCounts($pdo);

    // Get emergency alerts
    $alerts = getEmergencyAlerts($pdo);

    // Get buildings with coordinates
    $buildings = getBuildingsWithCoordinates($pdo);

    // Get latest fire data
    $data = getLatestFireData($pdo);

    // Get the 3 most recent ACKNOWLEDGED status alerts
    $recentAlerts = getRecentStatusAlerts($pdo, 3, 'ACKNOWLEDGED');

} catch (PDOException $e) {
    // Handle any connection or query errors
    die("Connection failed: " . $e->getMessage());
}
?>
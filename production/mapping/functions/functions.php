<?php
require_once __DIR__ . '/session_check.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/db_utils.php';
require_once __DIR__ . '/get_status_counts.php';
require_once __DIR__ . '/get_recent_alerts.php';
require_once __DIR__ . '/get_buildings.php';
require_once __DIR__ . '/get_latest_fire_data.php';
require_once __DIR__ . '/get_emergency_buildings.php';
require_once __DIR__ . '/get_building_by_id.php';
require_once __DIR__ . '/get_building_stats.php';
require_once __DIR__ . '/get_building_areas.php';

$pdo = getDatabaseConnection();

$counts = getStatusCounts($pdo);
$alerts = getRecentAlerts($pdo);
$buildings = getBuildings($pdo);
$data = getLatestFireData($pdo);
?>
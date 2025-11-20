<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../db/db.php';

try {
    $pdo = getDatabaseConnection();
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit;
}

// Optional filters via GET
$buildingType = isset($_GET['building_type']) ? trim($_GET['building_type']) : '';
$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
$device = isset($_GET['device']) ? trim($_GET['device']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = [];
$params = [];

if ($buildingType !== '') {
    $where[] = 'fd.building_type = :building_type';
    $params['building_type'] = $buildingType;
}
if ($barangay !== '') {
    $where[] = 'br.barangay_name = :barangay';
    $params['barangay'] = $barangay;
}
if ($user !== '') {
    $where[] = 'u.username = :username';
    $params['username'] = $user;
}
if ($device !== '') {
    $where[] = 'COALESCE(d.device_name, CONCAT("Device #", fd.device_id)) = :device';
    $params['device'] = $device;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(fd.timestamp) >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(fd.timestamp) <= :date_to';
    $params['date_to'] = $dateTo;
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

$sql = "
    SELECT fd.id, fd.status, fd.building_type, fd.smoke, fd.temp, fd.heat,
           fd.flame_detected, fd.timestamp, fd.user_id, u.username,
           br.barangay_name,
           fd.geo_lat, fd.geo_long, fd.notified,
           fd.device_id, d.device_name,
           fd.ml_confidence, fd.ml_prediction, fd.ml_fire_probability,
           fd.ai_prediction, fd.ml_timestamp
    FROM fire_data fd
    LEFT JOIN users u ON u.user_id = fd.user_id
    LEFT JOIN barangay br ON br.id = fd.barangay_id
    LEFT JOIN devices d ON d.device_id = fd.device_id
    $whereSql
    ORDER BY fd.id DESC
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue(':' . $k, $v);
}
$stmt->execute();
$row = $stmt->fetch();

echo json_encode([
    'data' => $row ?: null
]);
?>



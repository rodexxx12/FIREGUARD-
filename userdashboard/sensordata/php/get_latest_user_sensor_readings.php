<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
	http_response_code(401);
	echo json_encode(['error' => 'Unauthorized']);
	exit;
}

require_once __DIR__ . '/../../db/db.php';
$pdo = getDatabaseConnection();

$userId = (int)$_SESSION['user_id'];
$deviceId = isset($_GET['device_id']) && $_GET['device_id'] !== '' ? (int)$_GET['device_id'] : null;

try {
	$where = ['f.user_id = :user_id'];
	$params = ['user_id' => $userId];
	if (!is_null($deviceId)) {
		$where[] = 'f.device_id = :device_id';
		$params['device_id'] = $deviceId;
	}
	$whereSql = 'WHERE ' . implode(' AND ', $where);

	$sql = "
		SELECT f.id, f.status, f.building_type, f.smoke, f.temp, f.heat, f.flame_detected, f.timestamp,
		       f.device_id, f.ml_confidence, f.ml_prediction,
		       COALESCE(d.device_name, CONCAT('Device #', f.device_id)) as device_name
		FROM fire_data f
		LEFT JOIN devices d ON f.device_id = d.device_id
		$whereSql
		ORDER BY f.timestamp DESC, f.id DESC
		LIMIT 1
	";
	$stmt = $pdo->prepare($sql);
	$stmt->execute($params);
	$row = $stmt->fetch();

	echo json_encode(['data' => $row]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['error' => 'Server error']);
}
?>



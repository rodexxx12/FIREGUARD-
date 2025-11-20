<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$host = 'localhost';
$dbname = 'u520834156_DBBagofire';
$user = 'u520834156_userBagofire';
$pass = 'i[#[GQ!+=C9';

$mysqli = new mysqli($host, $user, $pass, $dbname);

if ($mysqli->connect_errno) {
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT status, building_type, smoke, temp, heat, flame_detected 
        FROM fire_data 
        WHERE user_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'status' => $row['status'],
        'building_type' => $row['building_type'],
        'smoke' => $row['smoke'],
        'temp' => $row['temp'],
        'heat' => $row['heat'],
        'flame_detected' => $row['flame_detected']
    ]);
} else {
    echo json_encode(['status' => 'SAFE']);
}

$mysqli->close();
?>
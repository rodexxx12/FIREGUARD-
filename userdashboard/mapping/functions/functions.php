<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Use centralized database connection
    require_once __DIR__ . '/../db/db.php';
    $pdo = getMappingDBConnection();

    // Get total device count for the current user
    $sql = "SELECT COUNT(*) AS total_devices FROM devices WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $total_devices = $stmt->fetchColumn();

    // Get total device count for the current user (replacing building count)
    $sql = "SELECT COUNT(*) AS total_buildings FROM devices WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $total_buildings = $stmt->fetchColumn();

    // Get counts for different statuses for the current user
    $sql = "SELECT UPPER(status) as status, COUNT(*) as count 
            FROM fire_data 
            WHERE user_id = :user_id 
            GROUP BY UPPER(status)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    
    $counts = [
        "SAFE" => 0,
        "MONITORING" => 0,
        "ACKNOWLEDGED" => 0,
        "EMERGENCY" => 0
    ];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status = $row['status'];
        if (isset($counts[$status])) {
            $counts[$status] = (int)$row['count'];
        }
    }

    // Query to get recent EMERGENCY alerts for the current user
    $sql = "SELECT * FROM fire_data 
            WHERE UPPER(status) = 'EMERGENCY' AND user_id = :user_id 
            ORDER BY timestamp DESC LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);

    $alerts = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $alerts[] = [
            'id' => $row['id'],
            'status' => $row['status'],
            'building_type' => $row['building_type'],
            'smoke' => $row['smoke'],
            'temp' => $row['temp'],
            'heat' => $row['heat'],
            'flame_detected' => $row['flame_detected'],
            'timestamp' => $row['timestamp'],
        ];
    }

    // Fetch buildings with coordinates for this user
    $sql = "SELECT * FROM buildings 
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get latest fire data for the user
    $sql = "SELECT * FROM fire_data 
            WHERE user_id = :user_id 
            ORDER BY timestamp DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute(['user_id' => $user_id])) {
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        die("Query failed.");
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
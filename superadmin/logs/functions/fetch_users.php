<?php
require_once __DIR__ . '/db_connect.php';

$pdo = getDatabaseConnection();
$stmt = $pdo->query("SELECT * FROM system_logs ORDER BY created_at DESC");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?> 
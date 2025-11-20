<?php
require_once __DIR__ . '/db_connect.php';

$stmt = $pdo->query("SELECT * FROM admin");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC); 
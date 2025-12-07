<?php
require_once __DIR__ . '/db_connect.php';

$stmt = $pdo->prepare("SELECT * FROM admin");
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC); 
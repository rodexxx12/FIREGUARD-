<?php
session_start();
header('Content-Type: application/json');

// Simple login status check
$response = [
    'logged_in' => isset($_SESSION['superadmin_id']) || isset($_SESSION['username']),
    'timestamp' => time()
];

echo json_encode($response);
exit;
?> 
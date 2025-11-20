<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$logged_in = isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);

// Return JSON response
header('Content-Type: application/json');
echo json_encode(['logged_in' => $logged_in]);
?> 
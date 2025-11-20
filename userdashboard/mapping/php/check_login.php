<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'User'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'User not logged in'
    ]);
}
?> 
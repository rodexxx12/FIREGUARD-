<?php
// Start the session only if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include centralized database configuration
require_once(__DIR__ . '/../db/db.php');

// Default image path
$default_image = '../../images/profile1.jpg';

$profile_image_url = $default_image; // Default assignment

// Check if user is logged in
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    $profile_image_url = getUserProfileImage($username);
}
?>



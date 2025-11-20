<?php
// Include database connection and helper functions
require_once('db-connect.php');

// Default image path
$default_image = '../../images/profile1.jpg'; // Ensure this file exists in your assets/images directory
$profile_image_url = $default_image; // Default assignment

// Check if user is logged in and get profile image
if ($currentUser && !empty($currentUser['profile_image']) && 
    file_exists("../../profile/uploads/profile_images/" . $currentUser['profile_image'])) {
    $profile_image_url = '../../profile/uploads/profile_images/' . htmlspecialchars($currentUser['profile_image']);
}
?>



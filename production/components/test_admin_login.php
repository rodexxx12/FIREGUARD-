<?php
// Test script to verify admin login functionality
session_start();

// Include the database connection
require_once __DIR__ . '/../db/db.php';

echo "<h2>Admin Login Test</h2>";

// Check if we have session data
echo "<h3>Current Session Data:</h3>";
echo "<pre>";
if (isset($_SESSION['admin_id'])) {
    echo "admin_id: " . $_SESSION['admin_id'] . "\n";
    echo "admin_username: " . ($_SESSION['admin_username'] ?? 'NOT SET') . "\n";
    echo "admin_full_name: " . ($_SESSION['admin_full_name'] ?? 'NOT SET') . "\n";
    echo "admin_email: " . ($_SESSION['admin_email'] ?? 'NOT SET') . "\n";
    echo "admin_role: " . ($_SESSION['admin_role'] ?? 'NOT SET') . "\n";
    echo "admin_logged_in: " . ($_SESSION['admin_logged_in'] ?? 'NOT SET') . "\n";
    echo "user_type: " . ($_SESSION['user_type'] ?? 'NOT SET') . "\n";
} else {
    echo "No admin session data found.\n";
}
echo "</pre>";

// Test the isAdminLoggedIn function
echo "<h3>Function Tests:</h3>";
if (function_exists('isAdminLoggedIn')) {
    echo "isAdminLoggedIn(): " . (isAdminLoggedIn() ? 'TRUE' : 'FALSE') . "\n";
} else {
    echo "isAdminLoggedIn() function not found.\n";
}

if (function_exists('getAdminData')) {
    $adminData = getAdminData();
    echo "getAdminData(): " . ($adminData ? json_encode($adminData) : 'NULL') . "\n";
} else {
    echo "getAdminData() function not found.\n";
}

// Test database connection and admin table
echo "<h3>Database Test:</h3>";
try {
    $conn = getDatabaseConnection();
    $stmt = $conn->prepare("SELECT admin_id, username, full_name, email, role, status FROM admin LIMIT 1");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if ($admin) {
        echo "Sample admin record found:\n";
        echo "ID: " . $admin['admin_id'] . "\n";
        echo "Username: " . $admin['username'] . "\n";
        echo "Full Name: " . $admin['full_name'] . "\n";
        echo "Email: " . $admin['email'] . "\n";
        echo "Role: " . $admin['role'] . "\n";
        echo "Status: " . $admin['status'] . "\n";
    } else {
        echo "No admin records found in database.\n";
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

echo "<h3>Navigation Test:</h3>";
// Simulate what navigation.php does
$profile_image_url = '../../images/profile1.jpg';
$user_role = 'Guest';
$user_name = 'Guest';
$user_email = '';
$is_logged_in = false;

if (isset($_SESSION['admin_id']) && isset($_SESSION['admin_username'])) {
    $is_logged_in = true;
    $user_role = 'Admin';
    $user_name = $_SESSION['admin_full_name'] ?? $_SESSION['admin_username'];
    $user_email = $_SESSION['admin_email'] ?? '';
    
    if (isset($_SESSION['admin_profile_image']) && !empty($_SESSION['admin_profile_image'])) {
        $profile_image_url = $_SESSION['admin_profile_image'];
    }
}

echo "Navigation variables:\n";
echo "is_logged_in: " . ($is_logged_in ? 'TRUE' : 'FALSE') . "\n";
echo "user_role: " . $user_role . "\n";
echo "user_name: " . $user_name . "\n";
echo "user_email: " . $user_email . "\n";

echo "<hr>";
echo "<p><a href='../mapping/php/map.php'>Go to Map</a> | <a href='../../login/login.php'>Login Page</a></p>";
?>

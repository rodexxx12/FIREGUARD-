<?php
// Include database connection
require_once '../functions/db_connection.php';

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Authentication Test</h2>";

// Check session status
echo "<h3>Session Information</h3>";
echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
echo "<p><strong>Session Status:</strong> " . session_status() . "</p>";
echo "<p><strong>Session Data:</strong></p>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if admin is logged in
if (isset($_SESSION['admin_id'])) {
    echo "<p style='color: green;'><strong>✅ Admin Logged In:</strong> Yes (ID: " . $_SESSION['admin_id'] . ")</p>";
    
    // Test database connection
    try {
        $pdo = getDatabaseConnection();
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "<p style='color: green;'><strong>✅ Admin Data Found:</strong> " . $admin['username'] . "</p>";
            echo "<p><strong>Profile Image:</strong> " . ($admin['profile_image'] ?: 'None') . "</p>";
        } else {
            echo "<p style='color: red;'><strong>❌ Admin Data Not Found</strong></p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'><strong>❌ Database Error:</strong> " . $e->getMessage() . "</p>";
    }
    
    echo "<p><a href='main.php'>Go to Profile Page</a></p>";
    
} else {
    echo "<p style='color: red;'><strong>❌ Admin Not Logged In</strong></p>";
    echo "<p>You need to log in as an admin to use the profile picture upload feature.</p>";
    echo "<p><a href='../../../login/php/login.php'>Go to Login Page</a></p>";
}

// Check upload directory
echo "<h3>Upload Directory Test</h3>";
$uploadDir = __DIR__ . '/uploads/profile_images/';
echo "<p><strong>Upload Directory:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Directory Exists:</strong> " . (file_exists($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Directory Writable:</strong> " . (is_writable($uploadDir) ? 'Yes' : 'No') . "</p>";

// Test file creation
if (is_writable($uploadDir)) {
    $testFile = $uploadDir . 'test_' . time() . '.txt';
    if (file_put_contents($testFile, 'test')) {
        echo "<p style='color: green;'><strong>✅ File Write Test:</strong> Success</p>";
        unlink($testFile);
        echo "<p>Test file cleaned up.</p>";
    } else {
        echo "<p style='color: red;'><strong>❌ File Write Test:</strong> Failed</p>";
    }
}

echo "<p><a href='diagnose_upload.php'>Run Full Upload Diagnostic</a></p>";
?> 
<?php
// Include database connection
require_once '../functions/db_connection.php';

// Diagnostic script for profile picture upload issues
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Profile Picture Upload Diagnostic</h2>";

// Check session
echo "<h3>Session Status</h3>";
echo "<p><strong>Session active:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Admin ID in session:</strong> " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'Not set') . "</p>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings</h3>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</p>";
echo "<p><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";

// Check upload directory
$uploadDir = __DIR__ . '/uploads/profile_images/';
echo "<h3>Upload Directory</h3>";
echo "<p><strong>Directory path:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Directory exists:</strong> " . (file_exists($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Directory writable:</strong> " . (is_writable($uploadDir) ? 'Yes' : 'No') . "</p>";

// Try to create directory if it doesn't exist
if (!file_exists($uploadDir)) {
    echo "<p><strong>Attempting to create directory...</strong></p>";
    if (mkdir($uploadDir, 0755, true)) {
        echo "<p style='color: green;'>Directory created successfully!</p>";
    } else {
        echo "<p style='color: red;'>Failed to create directory!</p>";
    }
}

// Check database connection
echo "<h3>Database Connection</h3>";
try {
    $pdo = getDatabaseConnection();
    echo "<p style='color: green;'><strong>Database connection:</strong> Success</p>";
    
    // Check admin table
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin'");
    if ($stmt->rowCount() > 0) {
        echo "<p><strong>Admin table exists:</strong> Yes</p>";
        
        // Check admin data if logged in
        if (isset($_SESSION['admin_id'])) {
            $stmt = $pdo->prepare("SELECT admin_id, username, profile_image FROM admin WHERE admin_id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
                echo "<p><strong>Current admin:</strong> " . $admin['username'] . "</p>";
                echo "<p><strong>Current profile image:</strong> " . ($admin['profile_image'] ?: 'None') . "</p>";
            } else {
                echo "<p style='color: red;'><strong>Current admin:</strong> Not found in database</p>";
            }
        }
    } else {
        echo "<p style='color: red;'><strong>Admin table exists:</strong> No</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'><strong>Database connection:</strong> Failed - " . $e->getMessage() . "</p>";
}

// Check if functions are loaded
echo "<h3>Function Availability</h3>";
$functions = [
    'handleProfilePictureUpload',
    'getProfileImageUrl',
    'getAdminData'
];

foreach ($functions as $function) {
    echo "<p><strong>$function:</strong> " . (function_exists($function) ? 'Available' : 'Not available') . "</p>";
}

// Test file upload if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>Test Upload Results</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $testFile = $uploadDir . 'test_' . time() . '.jpg';
        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $testFile)) {
            echo "<p style='color: green;'><strong>Test upload:</strong> Success - File saved to " . $testFile . "</p>";
            unlink($testFile);
            echo "<p>Test file cleaned up.</p>";
        } else {
            echo "<p style='color: red;'><strong>Test upload:</strong> Failed to move uploaded file</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>Test upload:</strong> File upload error - " . $_FILES['test_file']['error'] . "</p>";
    }
}

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo "<h3>Login Required</h3>";
    echo "<p style='color: orange;'>You need to log in first to test the profile picture upload.</p>";
    echo "<p><a href='../../login/index.php'>Go to Login Page</a></p>";
} else {
    echo "<h3>Test Upload Form</h3>";
    echo "<form method='POST' enctype='multipart/form-data' action=''>";
    echo "<input type='file' name='test_file' accept='image/*' required>";
    echo "<input type='submit' value='Test Upload'>";
    echo "</form>";
}

echo "<p><a href='main.php'>Back to Profile Page</a></p>";
?> 
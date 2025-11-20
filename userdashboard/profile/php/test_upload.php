<?php
// Include database connection
require_once '../functions/db_connection.php';

// Test script to check upload functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Upload Directory Test</h2>";

// Check upload directory
$uploadDir = __DIR__ . '/uploads/profile_images/';
echo "<p><strong>Upload Directory:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Directory exists:</strong> " . (file_exists($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Directory writable:</strong> " . (is_writable($uploadDir) ? 'Yes' : 'No') . "</p>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings</h3>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>max_file_uploads:</strong> " . ini_get('max_file_uploads') . "</p>";
echo "<p><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";

// Check if we can create a test file
$testFile = $uploadDir . 'test_' . time() . '.txt';
if (file_put_contents($testFile, 'test')) {
    echo "<p><strong>Test file creation:</strong> Success</p>";
    unlink($testFile);
    echo "<p><strong>Test file cleanup:</strong> Success</p>";
} else {
    echo "<p><strong>Test file creation:</strong> Failed</p>";
}

// Check session
echo "<h3>Session Information</h3>";
session_start();
echo "<p><strong>Session active:</strong> " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Admin ID in session:</strong> " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'Not set') . "</p>";

// Check database connection
echo "<h3>Database Connection</h3>";
try {
    $pdo = getDatabaseConnection();
    echo "<p><strong>Database connection:</strong> Success</p>";
    
    // Check admin table
    $stmt = $pdo->query("SHOW TABLES LIKE 'admin'");
    if ($stmt->rowCount() > 0) {
        echo "<p><strong>Admin table exists:</strong> Yes</p>";
        
        // Check admin data
        if (isset($_SESSION['admin_id'])) {
            $stmt = $pdo->prepare("SELECT admin_id, username, profile_image FROM admin WHERE admin_id = ?");
            $stmt->execute([$_SESSION['admin_id']]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($admin) {
                echo "<p><strong>Current admin:</strong> " . $admin['username'] . "</p>";
                echo "<p><strong>Current profile image:</strong> " . ($admin['profile_image'] ?: 'None') . "</p>";
            } else {
                echo "<p><strong>Current admin:</strong> Not found</p>";
            }
        }
    } else {
        echo "<p><strong>Admin table exists:</strong> No</p>";
    }
} catch (PDOException $e) {
    echo "<p><strong>Database connection:</strong> Failed - " . $e->getMessage() . "</p>";
}

echo "<h3>Form Test</h3>";
?>
<form method="POST" enctype="multipart/form-data" action="">
    <input type="file" name="test_file" accept="image/*">
    <input type="submit" value="Test Upload">
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Results</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if ($_FILES['test_file']['error'] === UPLOAD_ERR_OK) {
        $testUploadFile = $uploadDir . 'test_upload_' . time() . '.jpg';
        if (move_uploaded_file($_FILES['test_file']['tmp_name'], $testUploadFile)) {
            echo "<p><strong>Test upload:</strong> Success - File saved to " . $testUploadFile . "</p>";
            unlink($testUploadFile);
            echo "<p><strong>Test cleanup:</strong> Success</p>";
        } else {
            echo "<p><strong>Test upload:</strong> Failed to move uploaded file</p>";
        }
    } else {
        echo "<p><strong>Test upload:</strong> File upload error - " . $_FILES['test_file']['error'] . "</p>";
    }
}
?> 
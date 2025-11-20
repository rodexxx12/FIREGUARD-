<?php
// Simple test page for profile picture upload
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['admin_id'])) {
    echo "<h2>Not logged in</h2>";
    echo "<p>Please log in first to test the upload functionality.</p>";
    exit;
}

echo "<h2>Profile Picture Upload Test</h2>";
echo "<p><strong>Admin ID:</strong> " . $_SESSION['admin_id'] . "</p>";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    echo "<h3>Upload Results:</h3>";
    echo "<pre>";
    print_r($_FILES);
    echo "</pre>";
    
    if ($_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/profile_images/';
        $fileName = 'test_' . time() . '_' . $_FILES['profile_image']['name'];
        $destination = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $destination)) {
            echo "<p style='color: green;'><strong>Success!</strong> File uploaded to: " . $destination . "</p>";
            
            // Clean up test file
            unlink($destination);
            echo "<p>Test file cleaned up.</p>";
        } else {
            echo "<p style='color: red;'><strong>Error!</strong> Failed to move uploaded file.</p>";
        }
    } else {
        echo "<p style='color: red;'><strong>Error!</strong> File upload error: " . $_FILES['profile_image']['error'] . "</p>";
    }
}

// Check upload directory
$uploadDir = __DIR__ . '/uploads/profile_images/';
echo "<h3>Upload Directory Status:</h3>";
echo "<p><strong>Directory:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Exists:</strong> " . (file_exists($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Writable:</strong> " . (is_writable($uploadDir) ? 'Yes' : 'No') . "</p>";

// Check PHP settings
echo "<h3>PHP Upload Settings:</h3>";
echo "<p><strong>upload_max_filesize:</strong> " . ini_get('upload_max_filesize') . "</p>";
echo "<p><strong>post_max_size:</strong> " . ini_get('post_max_size') . "</p>";
echo "<p><strong>file_uploads:</strong> " . (ini_get('file_uploads') ? 'Enabled' : 'Disabled') . "</p>";
?>

<h3>Test Upload Form:</h3>
<form method="POST" enctype="multipart/form-data" action="">
    <input type="file" name="profile_image" accept="image/*" required>
    <input type="submit" value="Test Upload">
</form>

<p><a href="main.php">Back to Profile Page</a></p> 
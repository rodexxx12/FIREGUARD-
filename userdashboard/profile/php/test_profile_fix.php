<?php
// Include database connection
require_once '../functions/db_connection.php';

// Test script to diagnose profile picture issues
session_start();

echo "<h2>Profile Picture Diagnostic Test</h2>";

// Test 1: Session variables
echo "<h3>1. Session Variables</h3>";
echo "Session admin_id: " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'NOT SET') . "<br>";
echo "Session username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NOT SET') . "<br>";

// Test 2: Database connection
echo "<h3>2. Database Connection</h3>";
try {
    $pdo = getDatabaseConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection: SUCCESS<br>";
    
    // Test 3: Admin table structure
    echo "<h3>3. Admin Table Structure</h3>";
    $stmt = $pdo->query("DESCRIBE admin");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Admin table columns:<br>";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")<br>";
    }
    
    // Test 4: Admin data
    if (isset($_SESSION['admin_id'])) {
        echo "<h3>4. Admin Data</h3>";
        $stmt = $pdo->prepare("SELECT admin_id, username, profile_image FROM admin WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "Admin found:<br>";
            echo "- ID: " . $admin['admin_id'] . "<br>";
            echo "- Username: " . $admin['username'] . "<br>";
            echo "- Profile Image: " . ($admin['profile_image'] ?: 'None') . "<br>";
        } else {
            echo "Admin not found in database<br>";
        }
    }
    
} catch (PDOException $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "<br>";
}

// Test 5: File paths
echo "<h3>5. File Paths</h3>";
$uploadDir = __DIR__ . '/uploads/profile_images/';
echo "Upload directory: " . $uploadDir . "<br>";
echo "Directory exists: " . (file_exists($uploadDir) ? 'YES' : 'NO') . "<br>";
echo "Directory writable: " . (is_writable($uploadDir) ? 'YES' : 'NO') . "<br>";

if (file_exists($uploadDir)) {
    echo "Files in upload directory:<br>";
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . "<br>";
        }
    }
}

// Test 6: Profile image URL generation
echo "<h3>6. Profile Image URL Test</h3>";
if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM admin WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['profile_image'])) {
            $profile_image = $result['profile_image'];
            $image_path = __DIR__ . '/uploads/profile_images/' . $profile_image;
            $image_url = 'uploads/profile_images/' . $profile_image;
            
            echo "Profile image: " . $profile_image . "<br>";
            echo "Full path: " . $image_path . "<br>";
            echo "File exists: " . (file_exists($image_path) ? 'YES' : 'NO') . "<br>";
            echo "URL: " . $image_url . "<br>";
            
            if (file_exists($image_path)) {
                echo "<img src='" . $image_url . "' style='max-width: 100px; border: 1px solid #ccc;'><br>";
            }
        } else {
            echo "No profile image found in database<br>";
        }
    } catch (PDOException $e) {
        echo "Error fetching profile image: " . $e->getMessage() . "<br>";
    }
}

// Test 7: Components profile.php test
echo "<h3>7. Components Profile.php Test</h3>";
echo "Testing the fixed profile.php file:<br>";

// Simulate what happens in components/profile.php
$default_image = '../../images/profile1.jpg';
$profile_image_url = $default_image;

if (isset($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT profile_image FROM admin WHERE admin_id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result && !empty($result['profile_image'])) {
            $profile_image = $result['profile_image'];
            
            // Check if file exists in the correct path (from components directory)
            $image_path = __DIR__ . '/../components/../profile/php/uploads/profile_images/' . $profile_image;
            if (file_exists($image_path)) {
                $profile_image_url = '../profile/php/uploads/profile_images/' . htmlspecialchars($profile_image);
            }
        }
        
        echo "Profile image URL: " . $profile_image_url . "<br>";
        echo "File exists: " . (file_exists(__DIR__ . '/../components/' . $profile_image_url) ? 'YES' : 'NO') . "<br>";
        
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage() . "<br>";
    }
}

echo "<h3>8. Recommendations</h3>";
echo "Based on the test results above, here are the recommendations:<br>";
echo "1. Make sure you're logged in as an admin<br>";
echo "2. Check that the admin table has the correct structure<br>";
echo "3. Verify that profile images are being uploaded to the correct directory<br>";
echo "4. Ensure the file paths in profile.php are correct<br>";
?> 
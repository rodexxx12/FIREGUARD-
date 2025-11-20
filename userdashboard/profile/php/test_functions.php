<?php
// Test script to check function loading
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Function Loading Test</h2>";

// Test 1: Check if we can include the functions file
echo "<h3>Test 1: Including functions.php</h3>";
try {
    require_once __DIR__ . '/../functions/functions.php';
    echo "<p style='color: green;'>✓ functions.php included successfully</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error including functions.php: " . $e->getMessage() . "</p>";
}

// Test 2: Check if functions are available
echo "<h3>Test 2: Function Availability</h3>";
$functions = [
    'handleProfilePictureUpload',
    'getProfileImageUrl',
    'getAdminData',
    'getDBConnection'
];

foreach ($functions as $function) {
    if (function_exists($function)) {
        echo "<p style='color: green;'>✓ $function is available</p>";
    } else {
        echo "<p style='color: red;'>✗ $function is NOT available</p>";
    }
}

// Test 3: Check session
echo "<h3>Test 3: Session Status</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>✓ Session is active</p>";
    echo "<p><strong>Session ID:</strong> " . session_id() . "</p>";
    echo "<p><strong>Admin ID:</strong> " . (isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 'Not set') . "</p>";
} else {
    echo "<p style='color: red;'>✗ Session is not active</p>";
}

// Test 4: Check database connection
echo "<h3>Test 4: Database Connection</h3>";
if (function_exists('getDBConnection')) {
    try {
        $conn = getDBConnection();
        echo "<p style='color: green;'>✓ Database connection successful</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Database connection failed: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>✗ getDBConnection function not available</p>";
}

// Test 5: Check if admin data can be retrieved
echo "<h3>Test 5: Admin Data Retrieval</h3>";
if (function_exists('getAdminData') && isset($_SESSION['admin_id'])) {
    try {
        $conn = getDBConnection();
        $admin = getAdminData($conn, $_SESSION['admin_id']);
        if (isset($admin['error'])) {
            echo "<p style='color: red;'>✗ Error getting admin data: " . $admin['error'] . "</p>";
        } else {
            echo "<p style='color: green;'>✓ Admin data retrieved successfully</p>";
            echo "<p><strong>Username:</strong> " . $admin['username'] . "</p>";
            echo "<p><strong>Profile Image:</strong> " . ($admin['profile_image'] ?: 'None') . "</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Exception getting admin data: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ Cannot test admin data retrieval (function not available or not logged in)</p>";
}

// Test 6: Check upload directory
echo "<h3>Test 6: Upload Directory</h3>";
$uploadDir = __DIR__ . '/uploads/profile_images/';
echo "<p><strong>Upload Directory:</strong> " . $uploadDir . "</p>";
echo "<p><strong>Exists:</strong> " . (file_exists($uploadDir) ? 'Yes' : 'No') . "</p>";
echo "<p><strong>Writable:</strong> " . (is_writable($uploadDir) ? 'Yes' : 'No') . "</p>";

// Test 7: Check constants
echo "<h3>Test 7: Constants</h3>";
$constants = [
    'MAX_PROFILE_IMG_SIZE',
    'ALLOWED_IMG_TYPES',
    'PROFILE_IMG_DIR'
];

foreach ($constants as $constant) {
    if (defined($constant)) {
        echo "<p style='color: green;'>✓ $constant is defined: " . constant($constant) . "</p>";
    } else {
        echo "<p style='color: red;'>✗ $constant is NOT defined</p>";
    }
}

echo "<p><a href='main.php'>Back to Profile Page</a></p>";
?> 
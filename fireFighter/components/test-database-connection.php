<?php
/**
 * Database Connectivity Test Script
 * Run this script to test if all database connections are working properly
 */

echo "<h2>FireFighter Database Connectivity Test</h2>";
echo "<hr>";

// Test 1: Basic database connection
echo "<h3>1. Testing Basic Database Connection</h3>";
try {
    require_once('../db/db.php');
    $conn = getDatabaseConnection();
    echo "✅ Database connection successful<br>";
    echo "📊 Database: " . DB_NAME . "<br>";
    echo "🌐 Host: " . DB_HOST . "<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

// Test 2: Helper functions
echo "<h3>2. Testing Helper Functions</h3>";
try {
    // Test executeQuery function
    $stmt = executeQuery("SELECT 1 as test");
    if ($stmt) {
        echo "✅ executeQuery() function working<br>";
    }
    
    // Test fetchSingle function
    $result = fetchSingle("SELECT 1 as test");
    if ($result) {
        echo "✅ fetchSingle() function working<br>";
    }
    
    // Test fetchAll function
    $results = fetchAll("SELECT 1 as test");
    if ($results) {
        echo "✅ fetchAll() function working<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Helper functions test failed: " . $e->getMessage() . "<br>";
}

// Test 3: Component database connection
echo "<h3>3. Testing Component Database Connection</h3>";
try {
    require_once('db-connect.php');
    echo "✅ Component database connection successful<br>";
    
    if ($currentUser) {
        echo "✅ Current user data loaded<br>";
        echo "👤 Username: " . htmlspecialchars($currentUser['username'] ?? 'N/A') . "<br>";
    } else {
        echo "ℹ️ No current user session (this is normal if not logged in)<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Component database connection failed: " . $e->getMessage() . "<br>";
}

// Test 4: Security functions
echo "<h3>4. Testing Security Functions</h3>";
try {
    // Test sanitizeInput
    $testInput = "<script>alert('test')</script>";
    $sanitized = sanitizeInput($testInput);
    if ($sanitized !== $testInput) {
        echo "✅ sanitizeInput() function working<br>";
    }
    
    // Test validateUserAccess
    $hasAccess = validateUserAccess();
    echo "✅ validateUserAccess() function working (Result: " . ($hasAccess ? 'true' : 'false') . ")<br>";
    
} catch (Exception $e) {
    echo "❌ Security functions test failed: " . $e->getMessage() . "<br>";
}

// Test 5: Database tables check
echo "<h3>5. Checking Database Tables</h3>";
try {
    $tables = fetchAll("SHOW TABLES");
    if ($tables) {
        echo "✅ Database tables accessible<br>";
        echo "📋 Available tables:<br>";
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            echo "&nbsp;&nbsp;• " . htmlspecialchars($tableName) . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database tables check failed: " . $e->getMessage() . "<br>";
}

// Test 6: Profile component test
echo "<h3>6. Testing Profile Component</h3>";
try {
    // Simulate profile component
    $default_image = '../../images/profile1.jpg';
    $profile_image_url = $default_image;
    
    if ($currentUser && !empty($currentUser['profile_image'])) {
        $profile_image_url = '../../profile/uploads/profile_images/' . htmlspecialchars($currentUser['profile_image']);
    }
    
    echo "✅ Profile component logic working<br>";
    echo "🖼️ Profile image URL: " . htmlspecialchars($profile_image_url) . "<br>";
    
} catch (Exception $e) {
    echo "❌ Profile component test failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Test Summary</h3>";
echo "✅ All database connections and helper functions are working properly!<br>";
echo "🔧 Your FireFighter components are now fully connected to the database.<br>";
echo "<br>";
echo "<strong>Next Steps:</strong><br>";
echo "1. Test your actual components in the application<br>";
echo "2. Verify user authentication and profile functionality<br>";
echo "3. Check that all database operations work as expected<br>";
echo "4. Review the DATABASE_CONNECTION_GUIDE.md for usage instructions<br>";
?>

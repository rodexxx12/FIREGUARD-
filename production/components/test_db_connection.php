<?php
// Test database connections
require_once __DIR__ . '/db_connection.php';

echo "<h2>Database Connection Test</h2>";

// Test main database connection
echo "<h3>Testing Main Database Connection</h3>";
if (testDatabaseConnection()) {
    echo "<p style='color: green;'>✓ Main database connection successful</p>";
    
    $info = getDatabaseInfo();
    if ($info) {
        echo "<ul>";
        echo "<li>Database: " . htmlspecialchars($info['db_name']) . "</li>";
        echo "<li>User: " . htmlspecialchars($info['user']) . "</li>";
        echo "<li>Version: " . htmlspecialchars($info['version']) . "</li>";
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>✗ Main database connection failed</p>";
}

// Test local database connection
echo "<h3>Testing Local Database Connection</h3>";
if (testDatabaseConnection(true)) {
    echo "<p style='color: green;'>✓ Local database connection successful</p>";
    
    $info = getDatabaseInfo(true);
    if ($info) {
        echo "<ul>";
        echo "<li>Database: " . htmlspecialchars($info['db_name']) . "</li>";
        echo "<li>User: " . htmlspecialchars($info['user']) . "</li>";
        echo "<li>Version: " . htmlspecialchars($info['version']) . "</li>";
        echo "</ul>";
    }
} else {
    echo "<p style='color: red;'>✗ Local database connection failed</p>";
}

echo "<h3>Database Configuration</h3>";
echo "<ul>";
echo "<li>Main DB Host: " . DB_HOST . "</li>";
echo "<li>Main DB Name: " . DB_NAME . "</li>";
echo "<li>Main DB User: " . DB_USER . "</li>";
echo "<li>Local DB Host: " . LOCAL_DB_HOST . "</li>";
echo "<li>Local DB Name: " . LOCAL_DB_NAME . "</li>";
echo "<li>Local DB User: " . LOCAL_DB_USER . "</li>";
echo "</ul>";
?>

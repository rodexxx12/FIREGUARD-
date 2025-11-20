<?php
// Test file for announcements functionality
require_once '../../db/db.php';
require_once '../functions/get_announcements.php';

echo "<h1>Announcements Test</h1>";

try {
    $conn = getDatabaseConnection();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test the announcements function
    $result = getPublicAnnouncements();
    
    if ($result['success']) {
        echo "<p style='color: green;'>✓ Announcements function executed successfully</p>";
        echo "<p>Found " . count($result['announcements']) . " announcements</p>";
        
        if (count($result['announcements']) > 0) {
            echo "<h2>Announcements:</h2>";
            foreach ($result['announcements'] as $announcement) {
                echo "<div style='border: 1px solid #ccc; margin: 10px; padding: 10px;'>";
                echo "<h3>" . htmlspecialchars($announcement['title']) . "</h3>";
                echo "<p><strong>Source:</strong> " . htmlspecialchars($announcement['source']) . "</p>";
                echo "<p><strong>Author:</strong> " . htmlspecialchars($announcement['author_name']) . "</p>";
                echo "<p><strong>Priority:</strong> " . htmlspecialchars($announcement['priority']) . "</p>";
                echo "<p><strong>Date:</strong> " . htmlspecialchars($announcement['created_at']) . "</p>";
                echo "<p>" . nl2br(htmlspecialchars($announcement['content'])) . "</p>";
                echo "</div>";
            }
        } else {
            echo "<p style='color: orange;'>⚠ No announcements found (this might be normal if no announcements are published)</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ Announcements function failed: " . htmlspecialchars($result['message']) . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test direct database queries
echo "<h2>Database Structure Test:</h2>";

try {
    // Test if tables exist
    $tables = ['announcements', 'superadmin_announcements', 'announcement_targets', 'superadmin_announcement_targets'];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' does not exist</p>";
        }
    }
    
    // Test sample data
    echo "<h3>Sample Data Check:</h3>";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM announcements");
    $count = $stmt->fetch()['count'];
    echo "<p>Announcements table: $count records</p>";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM superadmin_announcements");
    $count = $stmt->fetch()['count'];
    echo "<p>Superadmin announcements table: $count records</p>";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM announcement_targets WHERE target_type = 'all'");
    $count = $stmt->fetch()['count'];
    echo "<p>Announcement targets with 'all' type: $count records</p>";
    
    $stmt = $conn->query("SELECT COUNT(*) as count FROM superadmin_announcement_targets WHERE target_type = 'all'");
    $count = $stmt->fetch()['count'];
    echo "<p>Superadmin announcement targets with 'all' type: $count records</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database structure test failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?> 
<?php
session_start();
require_once __DIR__ . '/../../db/db.php';

echo "<h2>Backup System Test</h2>";
echo "<hr>";

echo "<h3>File Check:</h3>";
$backup_file = __DIR__ . '/create_backup.php';
echo "Backup file exists: " . (file_exists($backup_file) ? "✅ Yes" : "❌ No") . "<br>";
echo "File path: " . $backup_file . "<br>";

echo "<h3>Directory Check:</h3>";
$backup_dir = __DIR__ . '/backups';
echo "Backup directory exists: " . (is_dir($backup_dir) ? "✅ Yes" : "❌ No") . "<br>";
echo "Directory path: " . $backup_dir . "<br>";

echo "<h3>Permissions:</h3>";
echo "Backup directory writable: " . (is_writable($backup_dir) ? "✅ Yes" : "❌ No") . "<br>";

echo "<h3>Database Connection:</h3>";
try {
    $pdo = getDatabaseConnection();
    echo "✅ Database connection successful<br>";
    
    // Test query
    $stmt = $pdo->query("SELECT DATABASE()");
    $db_name = $stmt->fetchColumn();
    echo "Current database: " . $db_name . "<br>";
    
    // Get tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Number of tables: " . count($tables) . "<br>";
    
    // Show row counts for each table
    echo "<h4>Table Row Counts:</h4>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Table Name</th><th>Row Count</th></tr>";
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<tr><td>{$table}</td><td>" . number_format($count) . " rows</td></tr>";
    }
    echo "</table>";
    
    // Test fetching all data from first table
    if (count($tables) > 0) {
        $first_table = $tables[0];
        echo "<h4>Test Fetching All Data from '{$first_table}':</h4>";
        
        // Count rows
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM `{$first_table}`");
        $expected_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Fetch all rows
        $stmt = $pdo->query("SELECT * FROM `{$first_table}`");
        $fetched_count = $stmt->rowCount();
        
        echo "Expected rows from COUNT(*): {$expected_count}<br>";
        echo "Rows fetched with SELECT *: {$fetched_count}<br>";
        
        if ($expected_count == $fetched_count) {
            echo "✅ Row count matches!<br>";
        } else {
            echo "⚠️ Row count mismatch - this might indicate an issue.<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

echo "<h3>Session:</h3>";
echo "Admin logged in: " . (isset($_SESSION['admin_id']) ? "✅ Yes" : "❌ No") . "<br>";
if (isset($_SESSION['admin_id'])) {
    echo "Admin ID: " . $_SESSION['admin_id'] . "<br>";
    echo "Admin username: " . ($_SESSION['admin_username'] ?? 'N/A') . "<br>";
}

echo "<h3>URL Test:</h3>";
$base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
$backup_url = $base_url . '/DEFENDED/production/backup/create_backup.php';
echo "Backup URL: <a href='{$backup_url}' target='_blank'>{$backup_url}</a><br>";

echo "<hr>";
echo "<p><strong>✅ All checks complete. If all above show ✅, the backup system should work correctly.</strong></p>";
?>


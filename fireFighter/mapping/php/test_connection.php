<?php
// Test database connection and data retrieval
echo "<h2>Testing Database Connection</h2>";

try {
    // Try different database connection paths
    $dbPaths = [
        '../../../db/db.php',
        '../../db/db.php',
        '../db/db.php',
        'db/db.php'
    ];
    
    $connected = false;
    $conn = null;
    
    foreach ($dbPaths as $path) {
        if (file_exists($path)) {
            echo "<p>Found database file at: $path</p>";
            try {
                require_once($path);
                if (function_exists('getDatabaseConnection')) {
                    $conn = getDatabaseConnection();
                    if ($conn) {
                        echo "<p style='color: green;'>✓ Database connection successful using: $path</p>";
                        $connected = true;
                        break;
                    }
                } else {
                    echo "<p style='color: red;'>✗ Function getDatabaseConnection not found in: $path</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ Failed to connect using: $path - " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: orange;'>Database file not found at: $path</p>";
        }
    }
    
    if ($connected) {
        echo "<h3>Testing Data Retrieval</h3>";
        
        // Test simple query first
        $testQuery = "SELECT COUNT(*) as total FROM fire_data";
        $stmt = $conn->prepare($testQuery);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>Total fire_data records: " . $result['total'] . "</p>";
        
        // Test ACKNOWLEDGED query
        $ackQuery = "SELECT COUNT(*) as count FROM fire_data WHERE status = 'ACKNOWLEDGED'";
        $stmt = $conn->prepare($ackQuery);
        $stmt->execute();
        $ackResult = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p>ACKNOWLEDGED records: " . $ackResult['count'] . "</p>";
        
        // Test the full query
        $fullQuery = "
            SELECT 
                fd.id,
                fd.status,
                fd.timestamp,
                fd.smoke,
                fd.temp,
                fd.heat,
                fd.flame_detected,
                fd.building_type
            FROM fire_data fd
            WHERE fd.status = 'ACKNOWLEDGED'
            ORDER BY fd.timestamp DESC
            LIMIT 3
        ";
        
        $stmt = $conn->prepare($fullQuery);
        $stmt->execute();
        $alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Sample ACKNOWLEDGED Alarms:</h4>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Status</th><th>Timestamp</th><th>Smoke</th><th>Temp</th><th>Heat</th><th>Flame</th><th>Building Type</th></tr>";
        
        foreach ($alarms as $alarm) {
            echo "<tr>";
            echo "<td>" . $alarm['id'] . "</td>";
            echo "<td>" . $alarm['status'] . "</td>";
            echo "<td>" . $alarm['timestamp'] . "</td>";
            echo "<td>" . $alarm['smoke'] . "</td>";
            echo "<td>" . $alarm['temp'] . "</td>";
            echo "<td>" . $alarm['heat'] . "</td>";
            echo "<td>" . $alarm['flame_detected'] . "</td>";
            echo "<td>" . $alarm['building_type'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
    } else {
        echo "<p style='color: red;'>Could not establish database connection with any path.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

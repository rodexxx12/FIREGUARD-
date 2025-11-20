<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();
// Check if user is logged in
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $backup_type = isset($_POST['backup_type']) ? $_POST['backup_type'] : 'manual';
    
    // Remote database credentials
    $db_host = 'srv1322.hstgr.io';
    $db_name = 'u520834156_DBBagofire';
    $db_user = 'u520834156_userBagofire';
    $db_pass = 'i[#[GQ!+=C9';
    
    // Create backup directory
    $backup_dir = __DIR__ . '/backups';
    if (!is_dir($backup_dir)) {
        if (!mkdir($backup_dir, 0755, true)) {
            throw new Exception('Could not create backup directory');
        }
    }
    
    $type_dir = $backup_dir . '/' . $backup_type;
    if (!is_dir($type_dir)) {
        if (!mkdir($type_dir, 0755, true)) {
            throw new Exception('Could not create type directory');
        }
    }
    
    // Generate filename
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "{$db_name}_{$backup_type}_{$timestamp}.sql";
    $backup_path = $type_dir . '/' . $filename;
    
    // Connect to database using mysqli
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set timezone to Philippines
    date_default_timezone_set('Asia/Manila');
    
    // Open file for writing
    $handle = fopen($backup_path, 'w+');
    
    if (!$handle) {
        throw new Exception('Unable to create backup file');
    }
    
    // Write header
    fwrite($handle, "-- Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Database: $db_name\n\n");
    fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($handle, "START TRANSACTION;\n");
    fwrite($handle, "SET time_zone = \"+00:00\";\n\n");

    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    
    if (!$result) {
        throw new Exception('Error getting tables: ' . $conn->error);
    }
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    // Backup each table
    foreach ($tables as $table) {
        // Get table structure
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        if ($result && $row = $result->fetch_row()) {
            fwrite($handle, "\n-- --------------------------------------------------------\n");
            fwrite($handle, "-- Table structure for table `$table`\n");
            fwrite($handle, "-- --------------------------------------------------------\n\n");
            fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($handle, $row[1] . ";\n\n");
        }

        // Get table data
        $result = $conn->query("SELECT * FROM `$table`");
        if ($result && $result->num_rows > 0) {
            fwrite($handle, "-- Dumping data for table `$table`\n\n");
            
            while ($row = $result->fetch_assoc()) {
                // Build columns
                $columns = array_keys($row);
                $columnNames = '`' . implode('`, `', $columns) . '`';
                
                // Build values
                $values = [];
                foreach ($row as $value) {
                    if (is_null($value)) {
                        $values[] = 'NULL';
                    } else {
                        $escaped = $conn->real_escape_string($value);
                        $values[] = "'$escaped'";
                    }
                }
                $valueString = implode(', ', $values);
                
                fwrite($handle, "INSERT INTO `$table` ($columnNames) VALUES ($valueString);\n");
            }
            fwrite($handle, "\n");
        }
    }

    // Write footer
    fwrite($handle, "COMMIT;\n");
    
    // Close file
    fclose($handle);
    
    // Close connection
    $conn->close();
    
    // Check if backup was created successfully
    if (!file_exists($backup_path) || filesize($backup_path) == 0) {
        throw new Exception('Failed to create backup file');
    }
    
    $file_size = filesize($backup_path);
    
    // Build absolute download URL dynamically based on current path
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $current_script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : (isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '/');
    $current_script = str_replace('\\', '/', $current_script);
    $production_pos = strpos($current_script, '/production/');
    if ($production_pos === false) {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? str_replace('\\', '/', $_SERVER['REQUEST_URI']) : '/';
        $production_pos = strpos($request_uri, '/production/');
        $base_path = $production_pos !== false ? substr($request_uri, 0, $production_pos + strlen('/production/')) : '/production/';
    } else {
        $base_path = substr($current_script, 0, $production_pos + strlen('/production/'));
    }
    $base_path = '/' . ltrim($base_path, '/');
    if (substr($base_path, -1) !== '/') { $base_path .= '/'; }
    $download_url = rtrim($base_url, '/') . $base_path . 'backup/download_backup.php?file=' . urlencode($filename) . '&type=' . $backup_type;
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($backup_type) . ' backup created successfully!',
        'backup_filename' => $filename,
        'file_size' => $file_size,
        'file_size_mb' => round($file_size / 1024 / 1024, 2),
        'download_url' => $download_url
    ]);
    
} catch (Exception $e) {
    error_log("Backup Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Backup failed: ' . $e->getMessage()
    ]);
}
?>


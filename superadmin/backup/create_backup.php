<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['superadmin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Please login as superadmin']);
    exit;
}

header('Content-Type: application/json');

try {
    // Set timezone to Philippines FIRST
    date_default_timezone_set('Asia/Manila');
    
    error_log("=== BACKUP REQUEST STARTED ===");
    error_log("POST data: " . print_r($_POST, true));
    
    $backup_type = isset($_POST['backup_type']) ? $_POST['backup_type'] : 'manual';
    error_log("Backup type: " . $backup_type);
    
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
    
    error_log("Generated filename: " . $filename);
    error_log("Full backup path: " . $backup_path);
    error_log("Type directory exists: " . (is_dir($type_dir) ? 'yes' : 'no'));
    error_log("Type directory writable: " . (is_writable($type_dir) ? 'yes' : 'no'));
    
    // Connect to database using mysqli
    error_log("Attempting to connect to database...");
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    error_log("Database connection successful");
    
    // Open file for writing
    error_log("Attempting to open backup file: " . $backup_path);
    $handle = fopen($backup_path, 'w+');
    
    if (!$handle) {
        $error = error_get_last();
        error_log("Failed to open file. Last error: " . $error['message']);
        throw new Exception('Unable to create backup file: ' . ($error['message'] ?? 'Unknown error'));
    }
    error_log("File opened successfully");
    
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
        error_log("Error getting tables: " . $conn->error);
        throw new Exception('Error getting tables: ' . $conn->error);
    }
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    error_log("Found " . count($tables) . " tables to backup");

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
    
    // Flush and close file to ensure data is written
    fflush($handle);
    fclose($handle);
    
    // Close connection
    $conn->close();
    
    // Check if backup was created successfully
    if (!file_exists($backup_path)) {
        error_log("Backup file was not created at: " . $backup_path);
        error_log("Attempting to check if parent directory exists: " . dirname($backup_path));
        error_log("Parent directory exists: " . (is_dir(dirname($backup_path)) ? 'yes' : 'no'));
        throw new Exception('Failed to create backup file at: ' . $backup_path);
    }
    
    if (filesize($backup_path) == 0) {
        error_log("Backup file is empty: " . $backup_path);
        throw new Exception('Backup file is empty');
    }
    
    $file_size = filesize($backup_path);
    error_log("Backup file created successfully. Size: " . $file_size . " bytes");
    
    // Build download URL based on current script location to avoid hardcoded paths
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $currentDir = rtrim(str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? '/')), '/');
    $relativeDownloadPath = $currentDir . '/download_backup.php?file=' . urlencode($filename) . '&type=' . $backup_type;
    $download_url = $scheme . '://' . $host . $relativeDownloadPath;
    
    error_log("Download URL: " . $download_url);
    error_log("=== BACKUP REQUEST COMPLETED SUCCESSFULLY ===");
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($backup_type) . ' backup created successfully!',
        'backup_filename' => $filename,
        'file_size' => $file_size,
        'file_size_mb' => round($file_size / 1024 / 1024, 2),
        'download_url' => $download_url
    ]);
    
} catch (Exception $e) {
    error_log("=== BACKUP REQUEST FAILED ===");
    error_log("Backup Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false, 
        'message' => 'Backup failed: ' . $e->getMessage()
    ]);
}
?>


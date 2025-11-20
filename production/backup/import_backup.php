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
    // Check if file was uploaded
    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred');
    }
    
    $file = $_FILES['backup_file'];
    $file_tmp = $file['tmp_name'];
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_type = $file['type'];
    
    // Validate file type
    $allowed_extensions = ['sql', 'txt'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Invalid file type. Only .sql and .txt files are allowed.');
    }
    
    // Check file size (max 500MB)
    $max_size = 500 * 1024 * 1024; // 500MB
    if ($file_size > $max_size) {
        throw new Exception('File size exceeds maximum limit of 500MB');
    }
    
    // Read file content
    $sql_content = file_get_contents($file_tmp);
    
    if ($sql_content === false) {
        throw new Exception('Failed to read uploaded file');
    }
    
  // Database credentials for import
  $db_host = 'srv1322.hstgr.io';
  $db_name = 'u520834156_DBBagofire';
  $db_user = 'u520834156_userBagofire';
  $db_pass = 'i[#[GQ!+=C9';
    
    // Connect to database
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset
    $conn->set_charset("utf8mb4");
    
    // Disable foreign key checks temporarily
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    $conn->query("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'");
    $conn->query("SET AUTOCOMMIT = 0");
    $conn->query("START TRANSACTION");
    
    // Split SQL file into individual queries
    // Remove comments and split by semicolons
    $sql_content = preg_replace('/--.*$/m', '', $sql_content); // Remove single line comments
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content); // Remove multi-line comments
    
    // Split by semicolons but keep DELIMITER handling
    $queries = [];
    $delimiter = ';';
    $current_query = '';
    $in_delimiter_block = false;
    
    $lines = explode("\n", $sql_content);
    foreach ($lines as $line) {
        $line = trim($line);
        
        if (empty($line)) {
            continue;
        }
        
        // Check for DELIMITER command
        if (preg_match('/^DELIMITER\s+(.+)$/i', $line, $matches)) {
            if (!empty($current_query)) {
                $queries[] = trim($current_query);
                $current_query = '';
            }
            $delimiter = trim($matches[1]);
            $in_delimiter_block = ($delimiter !== ';');
            continue;
        }
        
        $current_query .= $line . "\n";
        
        // Check if line ends with current delimiter
        if ($in_delimiter_block) {
            if (substr($line, -strlen($delimiter)) === $delimiter) {
                $current_query = rtrim($current_query, $delimiter);
                $queries[] = trim($current_query);
                $current_query = '';
                $delimiter = ';';
                $in_delimiter_block = false;
            }
        } else {
            if (substr($line, -1) === ';') {
                $current_query = rtrim($current_query, ';');
                $queries[] = trim($current_query);
                $current_query = '';
            }
        }
    }
    
    // Add last query if exists
    if (!empty(trim($current_query))) {
        $queries[] = trim($current_query);
    }
    
    // Execute queries
    $executed_queries = 0;
    $failed_queries = 0;
    $errors = [];
    
    foreach ($queries as $index => $query) {
        $query = trim($query);
        
        if (empty($query)) {
            continue;
        }
        
        // Skip DELIMITER commands
        if (preg_match('/^DELIMITER\s+/i', $query)) {
            continue;
        }
        
        // Skip comments
        if (preg_match('/^(--|\/\*|\*\/)/', $query)) {
            continue;
        }
        
        try {
            if (!$conn->query($query)) {
                $failed_queries++;
                $errors[] = "Query " . ($index + 1) . " failed: " . $conn->error;
                
                // Stop on critical errors if needed
                if (stripos($conn->error, 'syntax error') !== false) {
                    throw new Exception("SQL Syntax Error at query " . ($index + 1) . ": " . $conn->error);
                }
            } else {
                $executed_queries++;
            }
        } catch (Exception $e) {
            $failed_queries++;
            $errors[] = "Query " . ($index + 1) . " error: " . $e->getMessage();
        }
    }
    
    // Commit or rollback
    if ($failed_queries > 0 && $executed_queries === 0) {
        $conn->query("ROLLBACK");
        throw new Exception("Import failed. No queries were executed successfully. Errors: " . implode("; ", array_slice($errors, 0, 5)));
    } elseif ($failed_queries > 0) {
        // Some queries failed, but commit what succeeded
        $conn->query("COMMIT");
    } else {
        // All queries succeeded
        $conn->query("COMMIT");
    }
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    // Close connection
    $conn->close();
    
    $message = "Import completed successfully! ";
    $message .= "Executed: {$executed_queries} queries";
    if ($failed_queries > 0) {
        $message .= ", Failed: {$failed_queries} queries";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'executed_queries' => $executed_queries,
        'failed_queries' => $failed_queries,
        'errors' => array_slice($errors, 0, 10) // Return first 10 errors
    ]);
    
} catch (Exception $e) {
    // Rollback if transaction is still active
    if (isset($conn) && !$conn->connect_error) {
        $conn->query("ROLLBACK");
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
        $conn->close();
    }
    
    error_log("Import Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Import failed: ' . $e->getMessage()
    ]);
}
?>


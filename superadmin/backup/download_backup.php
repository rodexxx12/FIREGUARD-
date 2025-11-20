<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in as superadmin
if (!isset($_SESSION['superadmin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access - Please login as superadmin']);
    exit;
}

$filename = basename($_GET['file'] ?? '');
$type = basename($_GET['type'] ?? 'manual');

// Validate that filename doesn't contain path traversal attempts
if (empty($filename) || strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid filename']);
    exit;
}

$allowed_types = ['weekly', 'monthly', 'yearly', 'manual'];
if (!in_array($type, $allowed_types)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid backup type']);
    exit;
}

$file_path = __DIR__ . '/backups/' . $type . '/' . $filename;

if (!file_exists($file_path)) {
    // Log the error for debugging
    error_log("Backup download failed - File not found: " . $file_path);
    error_log("Looking for type: " . $type . ", filename: " . $filename);
    
    // Check if the directory exists
    $dir_path = __DIR__ . '/backups/' . $type;
    if (!is_dir($dir_path)) {
        error_log("Directory does not exist: " . $dir_path);
    } else {
        // List available files for debugging
        $files = scandir($dir_path);
        error_log("Available files in " . $dir_path . ": " . implode(', ', $files));
    }
    
    // Check if this is an AJAX request
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($is_ajax) {
        // Return JSON for AJAX requests
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode([
            'error' => 'File not found',
            'message' => 'Backup file not found: ' . $filename,
            'details' => 'The backup file may have been deleted or never created successfully. Please check the backup page for available files.'
        ]);
    } else {
        // For browser requests, redirect back to index with error message
        $error_param = urlencode('File not found: ' . $filename);
        header('Location: index.php?error=' . $error_param);
    }
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));

if (ob_get_level()) ob_end_clean();
readfile($file_path);
exit;
?>


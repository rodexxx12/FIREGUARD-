<?php
session_start();
// Check if user is logged in (either officer or admin)
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    die('Unauthorized access');
}

$filename = basename($_GET['file'] ?? '');
$type = basename($_GET['type'] ?? 'manual');

$allowed_types = ['weekly', 'monthly', 'yearly', 'manual', 'all'];
if (!in_array($type, $allowed_types)) {
    header('HTTP/1.1 400 Bad Request');
    die('Invalid backup type');
}

$file_path = __DIR__ . '/backups/' . $type . '/' . $filename;

if (!file_exists($file_path)) {
    header('HTTP/1.1 404 Not Found');
    die('Backup file not found: ' . $file_path);
}

// Set headers for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Clean output buffer
if (ob_get_level()) ob_end_clean();

// Output file
readfile($file_path);
exit;
?>


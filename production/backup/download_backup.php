<?php
require_once __DIR__ . '/../db/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['officer_id'])) {
    http_response_code(401);
    exit('Unauthorized access');
}

$filename = $_GET['file'] ?? '';
$type = $_GET['type'] ?? 'manual';

$allowed_types = ['weekly', 'monthly', 'yearly', 'manual', 'all'];
if (!in_array($type, $allowed_types, true)) {
    http_response_code(400);
    exit('Invalid backup type');
}

$filename = basename($filename);
if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
    http_response_code(400);
    exit('Invalid file name');
}

if (!str_ends_with(strtolower($filename), '.sql')) {
    http_response_code(400);
    exit('Invalid file extension');
}

$project_root = dirname(__DIR__, 2);
$type_directory = realpath($project_root . '/secure_storage/backups/' . $type);
if ($type_directory === false) {
    http_response_code(404);
    exit('Backup not available');
}

$file_path = $type_directory . DIRECTORY_SEPARATOR . $filename;
$real_file_path = realpath($file_path);

if ($real_file_path === false || strpos($real_file_path, $type_directory) !== 0 || !is_file($real_file_path)) {
    http_response_code(404);
    exit('Backup not found');
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($real_file_path));
header('Cache-Control: no-store');
header('Pragma: private');

if (ob_get_level()) {
    ob_end_clean();
}

readfile($real_file_path);
exit;
?>


<?php
// Secure profile image delivery
require_once __DIR__ . '/db_connection.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo "Not authenticated";
    exit;
}

$requestedFile = $_GET['file'] ?? '';
$requestedFile = trim($requestedFile);

if ($requestedFile === '') {
    http_response_code(404);
    echo "Image not found";
    exit;
}

$sanitizedFile = basename($requestedFile);
if (!preg_match('/^[A-Za-z0-9._-]+$/', $sanitizedFile)) {
    http_response_code(400);
    echo "Invalid file name";
    exit;
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
$extension = strtolower(pathinfo($sanitizedFile, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo "Invalid file type";
    exit;
}

$baseDirectory = realpath(__DIR__ . '/../profile/php/uploads/profile_images');
if ($baseDirectory === false) {
    error_log('Profile image base directory missing.');
    http_response_code(500);
    echo "Image service unavailable";
    exit;
}

$imagePath = $baseDirectory . DIRECTORY_SEPARATOR . $sanitizedFile;
$realImagePath = realpath($imagePath);

if ($realImagePath === false || strpos($realImagePath, $baseDirectory) !== 0 || !is_file($realImagePath)) {
    http_response_code(404);
    echo "Image not found";
    exit;
}

$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';
$lastModified = gmdate('D, d M Y H:i:s \G\M\T', filemtime($realImagePath));
$etag = '"' . hash('sha256', $realImagePath . $lastModified . filesize($realImagePath)) . '"';

// Conditional requests to avoid re-sending unchanged assets
if (
    (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && trim($_SERVER['HTTP_IF_MODIFIED_SINCE']) === $lastModified)
) {
    header('HTTP/1.1 304 Not Modified');
    exit;
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($realImagePath));
header('Cache-Control: private, max-age=86400, immutable');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 86400));
header('Last-Modified: ' . $lastModified);
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\';');

if (ob_get_level()) {
    ob_end_clean();
}

readfile($realImagePath);
exit;
<?php
// Script to serve profile images directly
session_start();

// Get the image filename from the URL
$image_file = $_GET['file'] ?? '';

if (empty($image_file)) {
    http_response_code(404);
    echo "No image file specified";
    exit;
}

// Security: only allow image files
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
$file_extension = strtolower(pathinfo($image_file, PATHINFO_EXTENSION));

if (!in_array($file_extension, $allowed_extensions)) {
    http_response_code(403);
    echo "Invalid file type";
    exit;
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo "Not authenticated";
    exit;
}

// Construct the file path - use absolute path from components directory
$image_path = __DIR__ . '/../profile/php/uploads/profile_images/' . $image_file;

// Check if file exists
if (!file_exists($image_path)) {
    error_log("Profile image not found: " . $image_path);
    http_response_code(404);
    echo "Image file not found: " . $image_path;
    exit;
}

// Get file info
$file_info = pathinfo($image_path);
$mime_type = '';

// Set correct MIME type
switch (strtolower($file_info['extension'])) {
    case 'jpg':
    case 'jpeg':
        $mime_type = 'image/jpeg';
        break;
    case 'png':
        $mime_type = 'image/png';
        break;
    case 'gif':
        $mime_type = 'image/gif';
        break;
    default:
        http_response_code(400);
        echo "Unsupported image type";
        exit;
}

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($image_path));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

// Output the image
readfile($image_path);
?> 
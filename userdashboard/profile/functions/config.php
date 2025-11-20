<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'u520834156_DBBagofire');
define('DB_USER', 'u520834156_userBagofire');
define('DB_PASS', 'i[#[GQ!+=C9');
define('BASE_URL', 'https://fireguard.bccbsis.com/'); // Adjust to your base URL
define('PROFILE_IMG_DIR', 'uploads/profile_images/');
define('MAX_PROFILE_IMG_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMG_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// This path is relative to the `main.php` script in the `php` directory.
$uploadPath = __DIR__ . '/../php/' . PROFILE_IMG_DIR;

// Create upload directory if it doesn't exist
if (!file_exists($uploadPath)) {
    if (!mkdir($uploadPath, 0755, true)) {
        error_log("Failed to create upload directory: " . $uploadPath);
    } else {
        error_log("Created upload directory: " . $uploadPath);
    }
}

// Ensure directory is writable
if (!is_writable($uploadPath)) {
    error_log("Upload directory is not writable: " . $uploadPath);
    // Try to make it writable
    if (!chmod($uploadPath, 0755)) {
        error_log("Failed to make upload directory writable: " . $uploadPath);
    }
}

// Log upload directory status
error_log("Upload directory status - Path: " . $uploadPath . ", Exists: " . (file_exists($uploadPath) ? 'Yes' : 'No') . ", Writable: " . (is_writable($uploadPath) ? 'Yes' : 'No')); 
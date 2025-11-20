<?php
// Start secure session
session_start();

// Regenerate session ID and invalidate old one
session_regenerate_id(true);

// Unset all session variables
$_SESSION = array();

// Destroy the session completely
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Clear any other authentication cookies
setcookie('remember_token', '', time() - 3600, '/', '', true, true);

// Security headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

// JSON response
header('Content-Type: application/json');
echo json_encode([
    'status' => 'success',
    'message' => 'Session terminated successfully',
    'timestamp' => time()
]);
exit();
?>
<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set content type for JSON response
header('Content-Type: application/json');

try {
    // Log the logout attempt
    $admin_id = $_SESSION['admin_id'] ?? 'unknown';
    $admin_username = $_SESSION['admin_username'] ?? 'unknown';
    
    error_log("Admin logout attempt - ID: $admin_id, Username: $admin_username");
    
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any additional cookies that might be set
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Session destroyed successfully',
        'redirect' => '../../../index.php'
    ]);
    
} catch (Exception $e) {
    error_log("Logout error: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error during logout: ' . $e->getMessage()
    ]);
}
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include the centralized database connection
require_once __DIR__ . '/../../db/db.php';

// Admin Authentication Functions
if (!function_exists('adminLogin')) {
function adminLogin($username, $password) {
    try {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare("SELECT admin_id, username, password, full_name, email, contact_number, role, status FROM admin WHERE username = ? AND status = 'Active'");
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
        if ($admin && password_verify($password, $admin['password'])) {
            // Set session variables
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_full_name'] = $admin['full_name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'admin_data' => [
                    'admin_id' => $admin['admin_id'],
                    'username' => $admin['username'],
                    'full_name' => $admin['full_name'],
                    'email' => $admin['email'],
                    'role' => $admin['role']
                ]
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid username or password'
            ];
        }
    } catch(PDOException $e) {
        error_log("Admin login error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Login failed. Please try again.'
        ];
    }
}
}

if (!function_exists('isAdminLoggedIn')) {
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true && isset($_SESSION['admin_id']);
}
}

if (!function_exists('getAdminId')) {
function getAdminId() {
    return isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : null;
}
}

if (!function_exists('getAdminData')) {
function getAdminData() {
    if (!isAdminLoggedIn()) {
        return null;
    }
    
    return [
        'admin_id' => $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'],
        'full_name' => $_SESSION['admin_full_name'],
        'email' => $_SESSION['admin_email'],
        'role' => $_SESSION['admin_role']
    ];
}
}

if (!function_exists('adminLogout')) {
function adminLogout() {
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
    
    return [
        'success' => true,
        'message' => 'Logged out successfully'
    ];
}
}

if (!function_exists('requireAdminLogin')) {
function requireAdminLogin($redirect_url = 'login.php') {
    if (!isAdminLoggedIn()) {
        header("Location: $redirect_url");
        exit();
    }
}
}

if (!function_exists('checkAdminRole')) {
function checkAdminRole($required_role) {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    $admin_data = getAdminData();
    return $admin_data && $admin_data['role'] === $required_role;
}
}

if (!function_exists('updateAdminLastActivity')) {
function updateAdminLastActivity() {
    if (isAdminLoggedIn()) {
        $_SESSION['admin_last_activity'] = time();
    }
}
}

if (!function_exists('checkAdminSessionTimeout')) {
function checkAdminSessionTimeout($timeout_minutes = 30) {
    if (!isAdminLoggedIn()) {
        return false;
    }
    
    $last_activity = isset($_SESSION['admin_last_activity']) ? $_SESSION['admin_last_activity'] : $_SESSION['admin_login_time'];
    $timeout_seconds = $timeout_minutes * 60;
    
    if (time() - $last_activity > $timeout_seconds) {
        adminLogout();
        return false;
    }
    
    updateAdminLastActivity();
    return true;
}
} 
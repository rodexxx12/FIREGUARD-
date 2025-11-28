<?php
/**
 * Authentication Module
 * 
 * Provides centralized authentication functions for all user types
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/database/database.php';
 * require_once __DIR__ . '/../../core/session/session.php';
 * require_once __DIR__ . '/../../core/auth/authentication.php';
 */

// Load required modules
if (!function_exists('getDatabaseConnection')) {
    require_once __DIR__ . '/../database/database.php';
}
if (!function_exists('initSecureSession')) {
    require_once __DIR__ . '/../session/session.php';
}

/**
 * Authenticate user (generic - checks users table)
 * 
 * @param string $username Username
 * @param string $password Password (plain text)
 * @param bool $remember Remember me option
 * @return array ['success' => bool, 'message' => string, 'user' => array|null, 'user_type' => string|null]
 */
if (!function_exists('authenticateUser')) {
    function authenticateUser($username, $password, $remember = false) {
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required',
                'user' => null,
                'user_type' => null
            ];
        }
        
        try {
            $conn = getDatabaseConnection();
            
            // Query users table
            $stmt = $conn->prepare("
                SELECT user_id, username, password, status, email_address, fullname, 
                       device_number, profile_image, contact_number
                FROM users 
                WHERE LOWER(TRIM(username)) = LOWER(TRIM(?))
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'user' => null,
                    'user_type' => null
                ];
            }
            
            // Check status
            $status = strtolower(trim($user['status'] ?? ''));
            if ($status !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Account is not active',
                    'user' => null,
                    'user_type' => null
                ];
            }
            
            // Verify password
            if (empty($user['password']) || !password_verify($password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'user' => null,
                    'user_type' => null
                ];
            }
            
            // Password is correct - set up session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email_address'] = $user['email_address'] ?? '';
            $_SESSION['fullname'] = $user['fullname'] ?? '';
            $_SESSION['device_number'] = $user['device_number'] ?? '';
            $_SESSION['profile_image'] = $user['profile_image'] ?? 'default.png';
            $_SESSION['contact_number'] = $user['contact_number'] ?? '';
            $_SESSION['status'] = $user['status'] ?? 'Active';
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_login_time'] = time();
            $_SESSION['user_type'] = 'user';
            $_SESSION['last_activity'] = time();
            
            // Regenerate session ID for security
            regenerateSessionId();
            
            // Remove password from user array before returning
            unset($user['password']);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => $user,
                'user_type' => 'user'
            ];
            
        } catch (Exception $e) {
            error_log("Authentication error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Authentication failed. Please try again.',
                'user' => null,
                'user_type' => null
            ];
        }
    }
}

/**
 * Authenticate admin user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return array ['success' => bool, 'message' => string, 'admin' => array|null]
 */
if (!function_exists('authenticateAdmin')) {
    function authenticateAdmin($username, $password) {
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required',
                'admin' => null
            ];
        }
        
        try {
            $conn = getDatabaseConnection();
            
            $stmt = $conn->prepare("
                SELECT admin_id, username, password, full_name, email, contact_number, role, status 
                FROM admin 
                WHERE username = ? AND status = 'Active'
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($password, $admin['password'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid username or password',
                    'admin' => null
                ];
            }
            
            // Set up session
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_full_name'] = $admin['full_name'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_login_time'] = time();
            $_SESSION['user_type'] = 'admin';
            $_SESSION['last_activity'] = time();
            
            regenerateSessionId();
            
            unset($admin['password']);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'admin' => $admin
            ];
            
        } catch (Exception $e) {
            error_log("Admin authentication error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Authentication failed. Please try again.',
                'admin' => null
            ];
        }
    }
}

/**
 * Check if user is authenticated
 * 
 * @return bool True if authenticated
 */
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }
        
        // Check for any authenticated user type
        return isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true ||
               isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true ||
               isset($_SESSION['firefighter_id']) ||
               isset($_SESSION['superadmin_id']);
    }
}

/**
 * Require authentication or redirect/die
 * 
 * @param string $redirectUrl URL to redirect to if not authenticated
 * @return int User ID if authenticated
 */
if (!function_exists('requireAuthentication')) {
    function requireAuthentication($redirectUrl = '/index.php') {
        if (!isAuthenticated()) {
            // Check if AJAX request
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                die(json_encode(['error' => 'Unauthorized. Please login.', 'code' => ERROR_UNAUTHORIZED]));
            }
            
            header("Location: {$redirectUrl}");
            exit();
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        // Return user ID
        return $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? $_SESSION['firefighter_id'] ?? $_SESSION['superadmin_id'] ?? 0;
    }
}

/**
 * Get current user data
 * 
 * @return array|null User data or null if not authenticated
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser() {
        if (!isAuthenticated()) {
            return null;
        }
        
        $userType = $_SESSION['user_type'] ?? null;
        
        if ($userType === 'user' && isset($_SESSION['user_id'])) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'] ?? '',
                'email' => $_SESSION['email_address'] ?? '',
                'fullname' => $_SESSION['fullname'] ?? '',
                'type' => 'user'
            ];
        }
        
        if ($userType === 'admin' && isset($_SESSION['admin_id'])) {
            return [
                'id' => $_SESSION['admin_id'],
                'username' => $_SESSION['admin_username'] ?? '',
                'email' => $_SESSION['admin_email'] ?? '',
                'fullname' => $_SESSION['admin_full_name'] ?? '',
                'role' => $_SESSION['admin_role'] ?? '',
                'type' => 'admin'
            ];
        }
        
        return null;
    }
}

/**
 * Logout current user
 * 
 * @return void
 */
if (!function_exists('logout')) {
    function logout() {
        // Clear all session variables
        $_SESSION = [];
        
        // Destroy session cookie
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
        
        // Destroy session
        session_destroy();
        
        // Start new session to avoid session fixation
        session_start();
        session_regenerate_id(true);
    }
}
?>




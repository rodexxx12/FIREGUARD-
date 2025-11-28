<?php
/**
 * Database Connection File for Production Module
 * 
 * MIGRATED: Now uses centralized architecture from core/
 * Maintains backward compatibility with existing production code
 * 
 * This file serves as a bridge between production module and centralized core
 * All existing admin functions remain available, but now use centralized modules
 */

// Load centralized core modules (preferred method)
$corePath = __DIR__ . '/../../core';
if (file_exists($corePath . '/config/config.php')) {
    // Use centralized architecture
    require_once $corePath . '/config/config.php';
    require_once $corePath . '/database/database.php';
    require_once $corePath . '/session/session.php';
    
    // Note: getDatabaseConnection() is now available from core/database/database.php
    // Admin functions below still work but will use centralized connection
} else {
    // Fallback to local environment loading if core not available
    require_once __DIR__ . '/load_env.php';
    
    // Set error reporting based on environment
    $appEnv = getenv('APP_ENV') ?: 'production';
    $appDebug = filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN);
    
    if ($appEnv === 'production' || !$appDebug) {
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
    } else {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
    
    // Start session with secure settings
    if (session_status() == PHP_SESSION_NONE) {
        $sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 1800);
        $sessionSecure = filter_var(getenv('SESSION_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $sessionHttpOnly = filter_var(getenv('SESSION_HTTPONLY') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        $sessionSameSite = getenv('SESSION_SAMESITE') ?: 'Strict';
        
        ini_set('session.cookie_lifetime', $sessionLifetime);
        ini_set('session.cookie_secure', $sessionSecure ? 1 : 0);
        ini_set('session.cookie_httponly', $sessionHttpOnly ? 1 : 0);
        ini_set('session.cookie_samesite', $sessionSameSite);
        ini_set('session.use_strict_mode', 1);
        
        session_start();
    }
    
    // Fallback database connection if core not available
    if (!function_exists('getDatabaseConnection')) {
        function getDatabaseConnection() {
            static $conn = null;
            
            if ($conn === null) {
                $host = getenv('DB_HOST') ?: 'localhost';
                $dbname = getenv('DB_NAME');
                $username = getenv('DB_USER');
                $password = getenv('DB_PASS');
                
                if (empty($dbname) || empty($username)) {
                    $missing = [];
                    if (empty($dbname)) $missing[] = 'DB_NAME';
                    if (empty($username)) $missing[] = 'DB_USER';
                    
                    error_log("CRITICAL: Database configuration incomplete. Missing: " . implode(', ', $missing));
                    
                    if (getenv('APP_ENV') === 'production') {
                        die(json_encode(['success' => false, 'message' => 'System temporarily unavailable']));
                    } else {
                        die("Database configuration error: Missing " . implode(' and ', $missing) . " in .env file");
                    }
                }
                
                if ($password === false) {
                    $password = '';
                }
                
                try {
                    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
                    $options = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_PERSISTENT => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ];
                    
                    $conn = new PDO($dsn, $username, $password, $options);
                    
                } catch(PDOException $e) {
                    error_log("Database connection failed: " . $e->getMessage());
                    error_log("Connection attempt: host={$host}, dbname={$dbname}, user={$username}");
                    
                    if (getenv('APP_ENV') === 'production') {
                        die(json_encode(['success' => false, 'message' => 'System temporarily unavailable']));
                    } else {
                        die(json_encode([
                            'success' => false, 
                            'message' => 'Database connection failed',
                            'error' => $e->getMessage()
                        ]));
                    }
                }
            }
            
            return $conn;
        }
    }
}

// Admin Authentication Functions
// These functions are specific to production module and maintained for backward compatibility
// They now use the centralized getDatabaseConnection() function

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
                
                // Regenerate session ID for security (if function available)
                if (function_exists('regenerateSessionId')) {
                    regenerateSessionId();
                }
                
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
        // Use centralized logout if available, otherwise manual cleanup
        if (function_exists('destroySession')) {
            destroySession();
        } else {
            $_SESSION = array();
            
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params["path"], $params["domain"],
                    $params["secure"], $params["httponly"]
                );
            }
            
            session_destroy();
        }
        
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
            // Also update general last_activity for centralized session handler
            $_SESSION['last_activity'] = time();
        }
    }
}

if (!function_exists('checkAdminSessionTimeout')) {
    function checkAdminSessionTimeout($timeout_minutes = 30) {
        // Use centralized session timeout check if available
        if (function_exists('checkSessionTimeout')) {
            return checkSessionTimeout($timeout_minutes);
        }
        
        // Fallback to manual check
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

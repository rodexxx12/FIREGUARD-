<?php
// Security: Only show errors in development
if (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Include the centralized database connection
require_once __DIR__ . '/../../db/db.php';

// Database Helper Functions
if (!function_exists('executeQuery')) {
    /**
     * Execute a prepared statement with parameters
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters for the query
     * @return PDOStatement|false
     */
    function executeQuery($sql, $params = []) {
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('fetchSingle')) {
    /**
     * Fetch a single row from database
     * @param string $sql SQL query
     * @param array $params Parameters for the query
     * @return array|false
     */
    function fetchSingle($sql, $params = []) {
        $stmt = executeQuery($sql, $params);
        return $stmt ? $stmt->fetch() : false;
    }
}

if (!function_exists('fetchAll')) {
    /**
     * Fetch all rows from database
     * @param string $sql SQL query
     * @param array $params Parameters for the query
     * @return array|false
     */
    function fetchAll($sql, $params = []) {
        $stmt = executeQuery($sql, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }
}

if (!function_exists('getUserProfile')) {
    /**
     * Get user profile information
     * @param string $username Username to fetch profile for
     * @return array|false
     */
    function getUserProfile($username) {
        $sql = "SELECT * FROM firefighters WHERE username = ?";
        return fetchSingle($sql, [$username]);
    }
}

if (!function_exists('updateUserProfile')) {
    /**
     * Update user profile information
     * @param string $username Username to update
     * @param array $data Array of fields to update
     * @return bool
     */
    function updateUserProfile($username, $data) {
        $allowedFields = ['profile_image', 'email', 'phone', 'full_name', 'department'];
        $updates = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = ?";
                $params[] = $value;
            }
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $username;
        $sql = "UPDATE firefighters SET " . implode(', ', $updates) . " WHERE username = ?";
        $stmt = executeQuery($sql, $params);
        return $stmt !== false;
    }
}

if (!function_exists('checkUserExists')) {
    /**
     * Check if user exists in database
     * @param string $username Username to check
     * @return bool
     */
    function checkUserExists($username) {
        $sql = "SELECT COUNT(*) as count FROM firefighters WHERE username = ?";
        $result = fetchSingle($sql, [$username]);
        return $result && $result['count'] > 0;
    }
}

if (!function_exists('getUserSession')) {
    /**
     * Get user session data
     * @return array|false
     */
    function getUserSession() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['username'])) {
            return getUserProfile($_SESSION['username']);
        }
        
        return false;
    }
}

if (!function_exists('sanitizeInput')) {
    /**
     * Sanitize user input
     * @param mixed $input Input to sanitize
     * @return mixed Sanitized input
     */
    function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map('sanitizeInput', $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validateUserAccess')) {
    /**
     * Validate user access to a resource
     * @param string $requiredRole Required role (optional)
     * @return bool
     */
    function validateUserAccess($requiredRole = null) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['username'])) {
            return false;
        }
        
        if ($requiredRole) {
            $user = getUserProfile($_SESSION['username']);
            return $user && isset($user['role']) && $user['role'] === $requiredRole;
        }
        
        return true;
    }
}

if (!function_exists('logActivity')) {
    /**
     * Log user activity
     * @param string $action Action performed
     * @param string $details Additional details
     * @return bool
     */
    function logActivity($action, $details = '') {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $username = $_SESSION['username'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $sql = "INSERT INTO activity_log (username, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = executeQuery($sql, [$username, $action, $details, $ip, $userAgent]);
        
        return $stmt !== false;
    }
}
?>

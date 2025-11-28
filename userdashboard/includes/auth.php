<?php
/**
 * Authentication Helper Functions
 * 
 * Provides authentication and authorization checks
 */

if (!function_exists('requireAuthentication')) {
    /**
     * Require user to be authenticated
     * Redirects to login or returns 401 if not authenticated
     * @return int User ID if authenticated
     */
    function requireAuthentication() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if session was expired by session_config
        if (isset($_SESSION['_session_expired'])) {
            unset($_SESSION['_session_expired']);
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                die(json_encode(['error' => 'Session expired. Please refresh and login.']));
            }
            header('Location: /index.php');
            exit;
        }
        
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
            // Check if this is an AJAX request
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(401);
                header('Content-Type: application/json');
                die(json_encode(['error' => 'Unauthorized. Please login.']));
            }
            
            // Redirect to login for regular requests
            $loginUrl = '/index.php'; // Adjust path as needed
            header('Location: ' . $loginUrl);
            exit;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        return (int)$_SESSION['user_id'];
    }
}

if (!function_exists('requireUserOwnership')) {
    /**
     * Verify that a resource belongs to the current user
     * @param PDO $pdo Database connection
     * @param string $table Table name
     * @param string $idColumn ID column name (default: 'id')
     * @param int $resourceId Resource ID to check
     * @param int $userId User ID (default: from session)
     * @return bool True if user owns the resource
     */
    function requireUserOwnership($pdo, $table, $resourceId, $userId = null, $idColumn = 'id') {
        if ($userId === null) {
            $userId = requireAuthentication();
        }
        
        // Validate input
        $resourceId = filter_var($resourceId, FILTER_VALIDATE_INT);
        if (!$resourceId || $resourceId <= 0) {
            return false;
        }
        
        // Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            error_log("Invalid table name in requireUserOwnership: $table");
            return false;
        }
        
        // Validate column name
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $idColumn)) {
            error_log("Invalid column name in requireUserOwnership: $idColumn");
            return false;
        }
        
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM $table WHERE $idColumn = ?");
            $stmt->execute([$resourceId]);
            $result = $stmt->fetch();
            
            if ($result && (int)$result['user_id'] === $userId) {
                return true;
            }
            
            error_log("User ownership check failed: User $userId attempted to access $table.$idColumn=$resourceId");
            return false;
        } catch (PDOException $e) {
            error_log("Error checking user ownership: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('checkUserPermission')) {
    /**
     * Check if user has specific permission
     * @param string $permission Permission name
     * @param int|null $userId User ID (default: from session)
     * @return bool True if user has permission
     */
    function checkUserPermission($permission, $userId = null) {
        if ($userId === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $userId = $_SESSION['user_id'] ?? null;
        }
        
        if (!$userId) {
            return false;
        }
        
        // Add permission checking logic here
        // For now, return true for authenticated users
        // This can be expanded to check specific permissions from database
        
        return true;
    }
}

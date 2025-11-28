<?php
/**
 * Role-Based Access Control (RBAC) Module
 * 
 * Provides role and permission checking functions
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/auth/rbac.php';
 * 
 * if (!checkRole('admin')) {
 *     die('Access denied');
 * }
 */

// Load required modules
if (!function_exists('getDatabaseConnection')) {
    require_once __DIR__ . '/../database/database.php';
}
if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/authentication.php';
}

/**
 * Check if current user has specific role
 * 
 * @param string $role Role to check (admin, user, firefighter, superadmin)
 * @return bool True if user has role
 */
if (!function_exists('checkRole')) {
    function checkRole($role) {
        if (!isAuthenticated()) {
            return false;
        }
        
        $userType = $_SESSION['user_type'] ?? null;
        
        // Direct role match
        if ($userType === $role) {
            return true;
        }
        
        // Admin role check
        if ($role === 'admin' && isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
            return true;
        }
        
        // Superadmin role check
        if ($role === 'superadmin' && isset($_SESSION['superadmin_id'])) {
            return true;
        }
        
        // Firefighter role check
        if ($role === 'firefighter' && isset($_SESSION['firefighter_id'])) {
            return true;
        }
        
        // Check admin role field
        if (isset($_SESSION['admin_role']) && strtolower($_SESSION['admin_role']) === strtolower($role)) {
            return true;
        }
        
        return false;
    }
}

/**
 * Require specific role or die
 * 
 * @param string $role Required role
 * @param string $message Error message
 * @return void
 */
if (!function_exists('requireRole')) {
    function requireRole($role, $message = 'Access denied') {
        if (!checkRole($role)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(403);
                header('Content-Type: application/json');
                die(json_encode([
                    'error' => $message,
                    'code' => ERROR_PERMISSION_DENIED
                ]));
            }
            
            http_response_code(403);
            die($message);
        }
    }
}

/**
 * Check if user has specific permission
 * Note: This is a basic implementation. Extend based on your permission system.
 * 
 * @param string $permission Permission to check
 * @return bool True if user has permission
 */
if (!function_exists('checkPermission')) {
    function checkPermission($permission) {
        if (!isAuthenticated()) {
            return false;
        }
        
        // Superadmin has all permissions
        if (checkRole('superadmin')) {
            return true;
        }
        
        // Get user permissions from session or database
        $permissions = getUserPermissions();
        
        return in_array($permission, $permissions);
    }
}

/**
 * Get user permissions
 * 
 * @param int|null $userId User ID (null = current user)
 * @return array List of permissions
 */
if (!function_exists('getUserPermissions')) {
    function getUserPermissions($userId = null) {
        if ($userId === null) {
            $user = getCurrentUser();
            if (!$user) {
                return [];
            }
            $userId = $user['id'];
            $userType = $user['type'] ?? 'user';
        } else {
            $userType = $_SESSION['user_type'] ?? 'user';
        }
        
        $permissions = [];
        
        // Superadmin has all permissions
        if (checkRole('superadmin')) {
            return ['*']; // All permissions
        }
        
        // Admin permissions
        if ($userType === 'admin') {
            $role = $_SESSION['admin_role'] ?? 'admin';
            
            // Define role-based permissions
            $rolePermissions = [
                'admin' => [
                    'view_dashboard',
                    'manage_users',
                    'view_reports',
                    'manage_devices'
                ],
                'superadmin' => ['*'] // All permissions
            ];
            
            $permissions = $rolePermissions[$role] ?? $rolePermissions['admin'];
        }
        
        // User permissions
        if ($userType === 'user') {
            $permissions = [
                'view_dashboard',
                'view_own_data',
                'manage_own_profile'
            ];
        }
        
        // Firefighter permissions
        if ($userType === 'firefighter' || checkRole('firefighter')) {
            $permissions = [
                'view_dashboard',
                'view_incidents',
                'respond_to_alerts',
                'update_status'
            ];
        }
        
        return $permissions;
    }
}

/**
 * Require specific permission or die
 * 
 * @param string $permission Required permission
 * @param string $message Error message
 * @return void
 */
if (!function_exists('requirePermission')) {
    function requirePermission($permission, $message = 'Permission denied') {
        if (!checkPermission($permission)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(403);
                header('Content-Type: application/json');
                die(json_encode([
                    'error' => $message,
                    'code' => ERROR_PERMISSION_DENIED
                ]));
            }
            
            http_response_code(403);
            die($message);
        }
    }
}

/**
 * Check if user owns a resource
 * 
 * @param int $resourceUserId User ID of resource owner
 * @return bool True if current user owns resource
 */
if (!function_exists('checkOwnership')) {
    function checkOwnership($resourceUserId) {
        if (!isAuthenticated()) {
            return false;
        }
        
        $currentUser = getCurrentUser();
        if (!$currentUser) {
            return false;
        }
        
        // Superadmin and admin can access any resource
        if (checkRole('superadmin') || checkRole('admin')) {
            return true;
        }
        
        // User can only access their own resources
        return $currentUser['id'] == $resourceUserId;
    }
}

/**
 * Require ownership or die
 * 
 * @param int $resourceUserId User ID of resource owner
 * @param string $message Error message
 * @return void
 */
if (!function_exists('requireOwnership')) {
    function requireOwnership($resourceUserId, $message = 'Access denied') {
        if (!checkOwnership($resourceUserId)) {
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                http_response_code(403);
                header('Content-Type: application/json');
                die(json_encode([
                    'error' => $message,
                    'code' => ERROR_PERMISSION_DENIED
                ]));
            }
            
            http_response_code(403);
            die($message);
        }
    }
}
?>


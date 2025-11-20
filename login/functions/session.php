<?php
// Enhanced Session Security Configuration
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes
define('SESSION_REGENERATE_INTERVAL', 5 * 60); // Regenerate session ID every 5 minutes
define('SESSION_COOKIE_LIFETIME', 0); // Session cookie expires when browser closes
define('SESSION_COOKIE_SECURE', true); // Only send over HTTPS
define('SESSION_COOKIE_HTTPONLY', true); // Prevent JavaScript access
define('SESSION_COOKIE_SAMESITE', 'Strict'); // CSRF protection

// Initialize secure session
function initSecureSession() {
    // Configure session parameters BEFORE starting session
    // Only set ini settings if session is not already active
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_lifetime', SESSION_COOKIE_LIFETIME);
        ini_set('session.cookie_secure', SESSION_COOKIE_SECURE);
        ini_set('session.cookie_httponly', SESSION_COOKIE_HTTPONLY);
        ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.use_trans_sid', 0);
        
        // Start session
        session_start();
    }
    
    // Regenerate session ID if needed
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > SESSION_REGENERATE_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        if ($inactive > SESSION_TIMEOUT) {
            // Log session timeout
            if (isset($_SESSION['username'])) {
                logSecurityEvent('session_timeout', getClientIp(), $_SESSION['username']);
            }
            session_unset();
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

function destroySecureSession() {
    // Log logout event
    if (isset($_SESSION['username'])) {
        logSecurityEvent('user_logout', getClientIp(), $_SESSION['username']);
    }
    
    // Clear remember me cookie if exists
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
    
    // Destroy session
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

function validateSessionIntegrity() {
    // Check if session data is consistent
    if (isset($_SESSION['user_type'])) {
        $requiredFields = [];
        switch ($_SESSION['user_type']) {
            case 'superadmin':
                $requiredFields = ['superadmin_id', 'username', 'user_type'];
                break;
            case 'admin':
                $requiredFields = ['admin_id', 'admin_username', 'user_type'];
                break;
            case 'user':
                $requiredFields = ['user_id', 'username', 'user_type'];
                break;
            case 'firefighter':
                $requiredFields = ['firefighter_id', 'username', 'user_type'];
                break;
        }
        
        foreach ($requiredFields as $field) {
            if (!isset($_SESSION[$field])) {
                logSecurityEvent('session_integrity_failure', getClientIp(), $_SESSION['username'] ?? 'unknown');
                destroySecureSession();
                return false;
            }
        }
    }
    
    return true;
}

function checkConcurrentSessions($userId, $userType) {
    $conn = getDatabaseConnection();
    try {
        $table = ($userType === 'admin') ? 'admin' : 
                (($userType === 'user') ? 'users' : 
                (($userType === 'firefighter') ? 'firefighters' : 'superadmin'));
        
        $idField = ($userType === 'admin') ? 'admin_id' : 
                  (($userType === 'user') ? 'user_id' : 
                  (($userType === 'firefighter') ? 'id' : 'superadmin_id'));
        
        $stmt = $conn->prepare("
            SELECT session_id, last_activity 
            FROM user_sessions 
            WHERE user_id = ? AND user_type = ? AND last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ORDER BY last_activity DESC
        ");
        $stmt->execute([$userId, $userType, SESSION_TIMEOUT]);
        $activeSessions = $stmt->fetchAll();
        
        // Allow only 3 concurrent sessions per user
        if (count($activeSessions) >= 3) {
            // Remove oldest session
            $oldestSession = end($activeSessions);
            $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
            $stmt->execute([$oldestSession['session_id']]);
        }
        
        // Record current session
        $stmt = $conn->prepare("
            INSERT INTO user_sessions (session_id, user_id, user_type, ip_address, last_activity) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE last_activity = NOW()
        ");
        $stmt->execute([session_id(), $userId, $userType, getClientIp()]);
        
    } catch(PDOException $e) {
        error_log("Failed to check concurrent sessions: " . $e->getMessage());
    }
} 
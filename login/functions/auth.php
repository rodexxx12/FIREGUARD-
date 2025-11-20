<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// Enhanced Security Constants
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 15 * 60); // 15 minutes in seconds
define('EXTENDED_LOCKOUT_TIME', 60 * 60); // 1 hour for repeated violations
define('REMEMBER_ME_EXPIRE_DAYS', 30);
define('RATE_LIMIT_WINDOW', 60); // 1 minute window
define('RATE_LIMIT_MAX_REQUESTS', 10); // Max requests per window
define('PROGRESSIVE_DELAY_BASE', 2); // Base delay in seconds
define('MAX_PROGRESSIVE_DELAY', 300); // Max delay: 5 minutes

// Enhanced Rate Limiting System
function checkRateLimit($ip, $action = 'login') {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as request_count 
            FROM rate_limits 
            WHERE ip_address = ? 
            AND action = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $action, RATE_LIMIT_WINDOW]);
        $result = $stmt->fetch();
        
        if ($result['request_count'] >= RATE_LIMIT_MAX_REQUESTS) {
            return ['allowed' => false, 'message' => 'Too many requests. Please wait before trying again.'];
        }
        
        // Record this request
        $stmt = $conn->prepare("
            INSERT INTO rate_limits (ip_address, action, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$ip, $action]);
        
        return ['allowed' => true];
    } catch(PDOException $e) {
        error_log("Rate limit check failed: " . $e->getMessage());
        return ['allowed' => true]; // Fail open for availability
    }
}

// Enhanced Brute Force Protection
function recordLoginAttempt($ip, $success, $username = null) {
    $conn = getDatabaseConnection();
    try {
        if ($success) {
            // Clear all failed attempts for this IP
            $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
            $stmt->execute([$ip]);
            
            // Clear user-specific attempts if username provided
            if ($username) {
                $stmt = $conn->prepare("DELETE FROM login_attempts WHERE username = ?");
                $stmt->execute([$username]);
            }
        } else {
            // Record failed attempt
            $stmt = $conn->prepare("
                INSERT INTO login_attempts (ip_address, username, attempts, last_attempt, violation_count) 
                VALUES (?, ?, 1, NOW(), 1)
                ON DUPLICATE KEY UPDATE 
                    attempts = IF(last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND), 1, attempts + 1),
                    last_attempt = NOW(),
                    violation_count = violation_count + 1
            ");
            $stmt->execute([$ip, $username, LOCKOUT_TIME]);
            
            // Log security event
            logSecurityEvent('failed_login', $ip, $username);
        }
    } catch(PDOException $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

function isIpBlocked($ip) {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare("SELECT attempts, last_attempt, violation_count FROM login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $result = $stmt->fetch();
        
        if ($result) {
            $timeDiff = time() - strtotime($result['last_attempt']);
            $violationCount = $result['violation_count'] ?? 1;
            
            // Progressive lockout: longer lockout for repeat offenders
            $lockoutTime = ($violationCount > 3) ? EXTENDED_LOCKOUT_TIME : LOCKOUT_TIME;
            
            if ($result['attempts'] >= MAX_LOGIN_ATTEMPTS && $timeDiff < $lockoutTime) {
                return [
                    'blocked' => true, 
                    'timeRemaining' => $lockoutTime - $timeDiff,
                    'violationCount' => $violationCount
                ];
            }
        }
    } catch(PDOException $e) {
        error_log("Failed to check IP block status: " . $e->getMessage());
    }
    return ['blocked' => false];
}

function calculateProgressiveDelay($attempts) {
    // Progressive delay: 2^attempts seconds, capped at MAX_PROGRESSIVE_DELAY
    $delay = pow(PROGRESSIVE_DELAY_BASE, min($attempts, 8));
    return min($delay, MAX_PROGRESSIVE_DELAY);
}

function applyProgressiveDelay($attempts) {
    if ($attempts > 0) {
        $delay = calculateProgressiveDelay($attempts);
        sleep($delay);
    }
}

// Security Event Logging
function logSecurityEvent($event, $ip, $username = null, $details = null) {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare("
            INSERT INTO security_logs (event_type, ip_address, username, details, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$event, $ip, $username, $details]);
    } catch(PDOException $e) {
        error_log("Failed to log security event: " . $e->getMessage());
    }
}

function authenticateUser($username, $password, $remember) {
    $conn = getDatabaseConnection();
    $ip = getClientIp();
    
    // Check rate limiting first
    $rateLimitCheck = checkRateLimit($ip, 'login');
    if (!$rateLimitCheck['allowed']) {
        return ['success' => false, 'message' => $rateLimitCheck['message']];
    }
    
    // Check IP blocking
    $ipBlockCheck = isIpBlocked($ip);
    if ($ipBlockCheck['blocked']) {
        $timeRemaining = $ipBlockCheck['timeRemaining'];
        $minutes = ceil($timeRemaining / 60);
        return ['success' => false, 'message' => "Too many login attempts. Please try again in {$minutes} minutes."];
    }
    
    // Trim and sanitize username
    $username = trim($username);
    
    // Enhanced input validation
    $usernameValidation = validateUsername($username);
    if (!$usernameValidation['valid']) {
        recordLoginAttempt($ip, false, $username);
        return ['success' => false, 'message' => $usernameValidation['message']];
    }
    
    $passwordValidation = validatePassword($password);
    if (!$passwordValidation['valid']) {
        recordLoginAttempt($ip, false, $username);
        return ['success' => false, 'message' => $passwordValidation['message']];
    }
    
    // Check for SQL injection patterns
    if (!validateSqlInput($username) || !validateSqlInput($password)) {
        logSecurityEvent('sql_injection_attempt', $ip, $username);
        recordLoginAttempt($ip, false, $username);
        return ['success' => false, 'message' => 'Invalid input detected'];
    }
    
    // Apply progressive delay for failed attempts
    $stmt = $conn->prepare("SELECT attempts FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    $attempts = $stmt->fetchColumn() ?: 0;
    applyProgressiveDelay($attempts);

    // Check superadmin table (allow username or email identifier)
    $identifier = trim($username);
    $identifierField = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $stmt = $conn->prepare("
        SELECT *
        FROM superadmin 
        WHERE {$identifierField} = ? 
        LIMIT 1
    ");
    $stmt->execute([$identifier]);
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        $status = strtolower($user['status'] ?? '');
        if ($status !== 'active') {
            recordLoginAttempt($ip, false, $identifier);
            logSecurityEvent('inactive_superadmin_login', $ip, $identifier, $status);
            return ['success' => false, 'message' => 'Your superadmin account is inactive'];
        }
        if (password_verify($password, $user['password'])) {
            recordLoginAttempt($ip, true, $identifier);
            logSecurityEvent('successful_login', $ip, $identifier, 'superadmin');
            return handleSuccessfulLogin($user, 'superadmin', $remember, $ip);
        } else {
            recordLoginAttempt($ip, false, $identifier);
            logSecurityEvent('failed_login', $ip, $identifier, 'invalid_superadmin_password');
        }
    }



    // Check admin table first
    $stmt = $conn->prepare("
        SELECT admin_id, username, password, full_name, email, contact_number, role, status 
        FROM admin 
        WHERE username = ? 
        LIMIT 1
    ");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        if ($user['status'] !== 'Active') {
            recordLoginAttempt($ip, false, $username);
            logSecurityEvent('inactive_account_login', $ip, $username, 'admin');
            return ['success' => false, 'message' => 'Your account is inactive'];
        }
        if (password_verify($password, $user['password'])) {
            recordLoginAttempt($ip, true, $username);
            logSecurityEvent('successful_login', $ip, $username, 'admin');
            return handleSuccessfulLogin($user, 'admin', $remember, $ip);
        }
    }
    // Check users table - using case-insensitive comparison and handling whitespace
    $stmt = $conn->prepare("
        SELECT user_id, username, password, status, email_address, fullname, device_number, profile_image, contact_number, remember_token, token_expiry
        FROM users 
        WHERE LOWER(TRIM(username)) = LOWER(TRIM(?))
        LIMIT 1
    ");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        // Case-insensitive status check
        $status = strtolower(trim($user['status'] ?? ''));
        if ($status !== 'active') {
            recordLoginAttempt($ip, false, $username);
            logSecurityEvent('inactive_account_login', $ip, $username, 'user');
            return ['success' => false, 'message' => 'Your account is inactive. Please contact administrator to activate your account.'];
        }
        // Verify password
        if (empty($user['password'])) {
            recordLoginAttempt($ip, false, $username);
            logSecurityEvent('empty_password_attempt', $ip, $username, 'user');
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        if (password_verify($password, $user['password'])) {
            recordLoginAttempt($ip, true, $username);
            logSecurityEvent('successful_login', $ip, $username, 'user');
            return handleSuccessfulLogin($user, 'user', $remember, $ip);
        } else {
            // Password doesn't match
            recordLoginAttempt($ip, false, $username);
            logSecurityEvent('failed_login', $ip, $username, 'invalid_password');
            return ['success' => false, 'message' => 'Wrong password'];
        }
    }
    // Check firefighters table
    $stmt = $conn->prepare("
        SELECT id, username, password, availability 
        FROM firefighters 
        WHERE username = ? 
        LIMIT 1
    ");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch();
        if ($user['availability'] !== 1) {
            recordLoginAttempt($ip, false, $username);
            logSecurityEvent('unavailable_account_login', $ip, $username, 'firefighter');
            return ['success' => false, 'message' => 'Your account is currently unavailable'];
        }
        if (password_verify($password, $user['password'])) {
            recordLoginAttempt($ip, true, $username);
            logSecurityEvent('successful_login', $ip, $username, 'firefighter');
            return handleSuccessfulLogin($user, 'firefighter', $remember, $ip);
        }
    }
    recordLoginAttempt($ip, false, $username);
    logSecurityEvent('failed_login', $ip, $username, 'invalid_credentials');
    return ['success' => false, 'message' => 'Invalid username or password'];
}

function handleSuccessfulLogin($user, $userType, $remember, $ip) {
    session_regenerate_id(true);
    if ($userType === 'superadmin') {
        // Store all relevant superadmin fields in session
        $_SESSION['superadmin_id'] = $user['superadmin_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['contact_number'] = $user['contact_number'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['status'] = $user['status'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['profile_image'] = $user['profile_image'];
        $_SESSION['last_login'] = $user['last_login'];
        $_SESSION['remember_token'] = $user['remember_token'];
        $_SESSION['token_expiry'] = $user['token_expiry'];
        $_SESSION['created_at'] = $user['created_at'];
        $_SESSION['updated_at'] = $user['updated_at'];
        $_SESSION['user_type'] = 'superadmin';
        $_SESSION['last_activity'] = time();
        updateSuperadminLastLogin($user['superadmin_id']);
        $redirect = 'superadmin/statistics/php/index.php'; // Change this path to your actual superadmin page
    }    
    if ($userType === 'admin') {
        $_SESSION['admin_id'] = $user['admin_id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['admin_full_name'] = $user['full_name'];
        $_SESSION['admin_email'] = $user['email'];
        $_SESSION['admin_role'] = $user['role'];
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        $redirect = 'production/mapping/php/map.php';
    } elseif ($userType === 'user') {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'] ?? '';
        $_SESSION['email_address'] = $user['email_address'] ?? '';
        $_SESSION['fullname'] = $user['fullname'] ?? '';
        $_SESSION['device_number'] = $user['device_number'] ?? '';
        $_SESSION['profile_image'] = $user['profile_image'] ?? 'default.png';
        $_SESSION['contact_number'] = $user['contact_number'] ?? '';
        $_SESSION['status'] = $user['status'] ?? 'Active';
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_login_time'] = time();
        $redirect = 'userdashboard/mapping/php/main.php';
    } elseif ($userType === 'firefighter') {
        $_SESSION['firefighter_id'] = $user['id'];
        $redirect = 'fireFighter/mapping/php/main.php';
    }
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_type'] = $userType;
    $_SESSION['last_activity'] = time();
    if ($remember) {
        $userIdForCookie = null;
        if ($userType === 'admin') {
            $userIdForCookie = $user['admin_id'];
        } elseif ($userType === 'user') {
            $userIdForCookie = $user['user_id'];
        } elseif ($userType === 'firefighter') {
            $userIdForCookie = $user['id'];
        } elseif ($userType === 'superadmin') {
            $userIdForCookie = $user['superadmin_id'];
        }
        if ($userIdForCookie !== null) {
            setRememberMeCookie($userIdForCookie, $userType);
        }
    }
    recordLoginAttempt($ip, true);
    return ['success' => true, 'redirect' => $redirect];
}

function setRememberMeCookie($userId, $userType) {
    $token = generateToken();
    $expiry = time() + REMEMBER_ME_EXPIRE_DAYS * 24 * 60 * 60;
    setcookie('remember_token', $token, [
        'expires' => $expiry,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    $table = ($userType === 'admin') ? 'admin' : 
    (($userType === 'user') ? 'users' : 
    (($userType === 'firefighter') ? 'firefighters' : 'superadmin'));

$idField = ($userType === 'admin') ? 'admin_id' : 
      (($userType === 'user') ? 'user_id' : 
      (($userType === 'firefighter') ? 'id' : 'superadmin_id'));

    $conn = getDatabaseConnection();
    
    // Only update remember_token for tables that have this column
    // Check if table has remember_token column before updating
    try {
        // For 'users' table, we know it has remember_token
        if ($table === 'users') {
            $stmt = $conn->prepare("
                UPDATE $table SET remember_token = ?, token_expiry = ? 
                WHERE $idField = ?
            ");
            $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $userId]);
        } else {
            // For other tables, check if column exists first
            $checkColumn = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'remember_token'");
            if ($checkColumn && $checkColumn->rowCount() > 0) {
                $stmt = $conn->prepare("
                    UPDATE $table SET remember_token = ?, token_expiry = ? 
                    WHERE $idField = ?
                ");
                $stmt->execute([$token, date('Y-m-d H:i:s', $expiry), $userId]);
            }
            // If column doesn't exist, just skip the update (cookie is still set)
        }
    } catch (PDOException $e) {
        // Log error but don't fail - remember me cookie is still set
        error_log("Failed to update remember_token for table $table: " . $e->getMessage());
    }
}

function updateSuperadminLastLogin($superadminId) {
    $conn = getDatabaseConnection();
    try {
        $stmt = $conn->prepare("UPDATE superadmin SET last_login = NOW() WHERE superadmin_id = ?");
        $stmt->execute([$superadminId]);
    } catch (PDOException $e) {
        error_log("Failed to update superadmin last login: " . $e->getMessage());
    }
}

function checkRememberMeCookie() {
    if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id']) && empty($_SESSION['firefighter_id']) && isset($_COOKIE['remember_token'])) {
        $conn = getDatabaseConnection();
        $token = $_COOKIE['remember_token'];
        
        // Helper function to check if table has remember_token column
        $hasRememberTokenColumn = function($table) use ($conn) {
            try {
                $checkColumn = $conn->query("SHOW COLUMNS FROM `$table` LIKE 'remember_token'");
                return $checkColumn && $checkColumn->rowCount() > 0;
            } catch (PDOException $e) {
                return false;
            }
        };
        
        // Check superadmin table
        try {
            if ($hasRememberTokenColumn('superadmin')) {
                $stmt = $conn->prepare("
                    SELECT superadmin_id, username 
                    FROM superadmin 
                    WHERE remember_token = ? AND token_expiry > NOW() 
                    LIMIT 1
                ");
                $stmt->execute([$token]);
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    return handleSuccessfulLogin($user, 'superadmin', false, $_SERVER['REMOTE_ADDR']);
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking superadmin remember_token: " . $e->getMessage());
        }

        // Check admin table
        try {
            if ($hasRememberTokenColumn('admin')) {
                $stmt = $conn->prepare("
                    SELECT admin_id, username, role 
                    FROM admin 
                    WHERE remember_token = ? AND token_expiry > NOW() 
                    LIMIT 1
                ");
                $stmt->execute([$token]);
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    return handleSuccessfulLogin($user, 'admin', false, $_SERVER['REMOTE_ADDR']);
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking admin remember_token: " . $e->getMessage());
        }
        
        // Check users table
        try {
            if ($hasRememberTokenColumn('users')) {
                $stmt = $conn->prepare("
                    SELECT user_id, username 
                    FROM users 
                    WHERE remember_token = ? AND token_expiry > NOW() 
                    LIMIT 1
                ");
                $stmt->execute([$token]);
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    return handleSuccessfulLogin($user, 'user', false, $_SERVER['REMOTE_ADDR']);
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking users remember_token: " . $e->getMessage());
        }
        
        // Check firefighters table
        try {
            if ($hasRememberTokenColumn('firefighters')) {
                $stmt = $conn->prepare("
                    SELECT id, username 
                    FROM firefighters 
                    WHERE remember_token = ? AND token_expiry > NOW() 
                    LIMIT 1
                ");
                $stmt->execute([$token]);
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch();
                    return handleSuccessfulLogin($user, 'firefighter', false, $_SERVER['REMOTE_ADDR']);
                }
            }
        } catch (PDOException $e) {
            error_log("Error checking firefighters remember_token: " . $e->getMessage());
        }
        
        // Invalid token - clear cookie
        setcookie('remember_token', '', time() - 3600, '/');
    }
    return false;
} 
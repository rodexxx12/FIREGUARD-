<?php
// Enhanced Input Validation & Sanitization
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateUsername($username) {
    // Username validation: 3-30 characters, alphanumeric and underscore only
    if (empty($username)) {
        return ['valid' => false, 'message' => 'Username is required'];
    }
    
    if (strlen($username) < 3 || strlen($username) > 30) {
        return ['valid' => false, 'message' => 'Username must be between 3 and 30 characters'];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['valid' => false, 'message' => 'Username can only contain letters, numbers, and underscores'];
    }
    
    // Check for common attack patterns
    $dangerousPatterns = [
        '/<script/i', '/javascript:/i', '/on\w+\s*=/i', '/data:/i',
        '/vbscript:/i', '/expression\s*\(/i', '/url\s*\(/i'
    ];
    
    foreach ($dangerousPatterns as $pattern) {
        if (preg_match($pattern, $username)) {
            return ['valid' => false, 'message' => 'Invalid username format'];
        }
    }
    
    return ['valid' => true];
}

function validatePassword($password) {
    if (empty($password)) {
        return ['valid' => false, 'message' => 'Password is required'];
    }
    
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'Password must be at least 8 characters long'];
    }
    
    if (strlen($password) > 128) {
        return ['valid' => false, 'message' => 'Password must be less than 128 characters'];
    }
    
    // Check for common weak passwords
    $weakPasswords = ['password', '123456', 'admin', 'qwerty', 'letmein', 'welcome'];
    if (in_array(strtolower($password), $weakPasswords)) {
        return ['valid' => false, 'message' => 'Password is too common. Please choose a stronger password'];
    }
    
    return ['valid' => true];
}

function validateEmail($email) {
    if (empty($email)) {
        return ['valid' => false, 'message' => 'Email is required'];
    }
    
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['valid' => false, 'message' => 'Please enter a valid email address'];
    }
    
    if (strlen($email) > 254) {
        return ['valid' => false, 'message' => 'Email address is too long'];
    }
    
    return ['valid' => true];
}

function validateInput($data, $type, $options = []) {
    switch ($type) {
        case 'username':
            return validateUsername($data);
        case 'password':
            return validatePassword($data);
        case 'email':
            return validateEmail($data);
        case 'string':
            $minLength = $options['min_length'] ?? 1;
            $maxLength = $options['max_length'] ?? 255;
            $pattern = $options['pattern'] ?? null;
            
            if (strlen($data) < $minLength) {
                return ['valid' => false, 'message' => "Input must be at least {$minLength} characters"];
            }
            if (strlen($data) > $maxLength) {
                return ['valid' => false, 'message' => "Input must be less than {$maxLength} characters"];
            }
            if ($pattern && !preg_match($pattern, $data)) {
                return ['valid' => false, 'message' => 'Invalid input format'];
            }
            return ['valid' => true];
        default:
            return ['valid' => false, 'message' => 'Unknown validation type'];
    }
}

// Enhanced SQL Injection Prevention
function escapeSqlString($string) {
    $conn = getDatabaseConnection();
    return $conn->quote($string);
}

function validateSqlInput($input) {
    // Check for common SQL injection patterns
    $sqlPatterns = [
        '/(\bunion\b.*\bselect\b)/i',
        '/(\bselect\b.*\bfrom\b)/i',
        '/(\binsert\b.*\binto\b)/i',
        '/(\bupdate\b.*\bset\b)/i',
        '/(\bdelete\b.*\bfrom\b)/i',
        '/(\bdrop\b.*\btable\b)/i',
        '/(\balter\b.*\btable\b)/i',
        '/(\bexec\b|\bexecute\b)/i',
        '/(\bscript\b)/i',
        '/(\bjavascript\b)/i',
        '/(\bvbscript\b)/i',
        '/(\bonload\b|\bonerror\b|\bonclick\b)/i',
        '/(\b--\b|\b#\b|\b\/\*.*\*\/)/i',
        '/(\bxp_\w+\b)/i',
        '/(\bsp_\w+\b)/i'
    ];
    
    foreach ($sqlPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return false;
        }
    }
    
    return true;
}

// CSRF Protection
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Token Generation
function generateToken($length = 32) {
    try {
        return bin2hex(random_bytes($length));
    } catch (Exception $e) {
        error_log("Token generation failed: " . $e->getMessage());
        throw new Exception("Token generation failed");
    }
}

// Security Headers
function setSecurityHeaders() {
    // Prevent going back to previous page and caching
    header("Cache-Control: no-cache, no-store, must-revalidate, private");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    header("Cache-Control: post-check=0, pre-check=0", false);
    
    // Prevent clickjacking
    header('X-Frame-Options: DENY');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Enable XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Strict Transport Security (HTTPS only)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://code.jquery.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://www.gstatic.com; img-src 'self' data: https:; font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com; connect-src 'self' https://www.google.com https://cdn.jsdelivr.net; frame-src 'self' https://www.google.com; frame-ancestors 'none';");
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Permissions Policy
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// IP Address Validation
function validateIpAddress($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return true; // Public IP
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
        return true; // Private IP (localhost, private networks)
    }
    return false;
}

function getClientIp() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (validateIpAddress($ip)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
} 
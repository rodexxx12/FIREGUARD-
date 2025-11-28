<?php
/**
 * Rate Limiting Module
 * 
 * Provides rate limiting for APIs, login attempts, form submissions, etc.
 * Prevents brute force attacks and abuse
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/database/database.php';
 * require_once __DIR__ . '/../../core/rate_limit/rate_limiter.php';
 * 
 * if (!rateLimitCheck('login', $ip)) {
 *     die('Too many attempts. Please try again later.');
 * }
 */

// Load required modules
if (!function_exists('getDatabaseConnection')) {
    require_once __DIR__ . '/../database/database.php';
}

/**
 * Check if action is allowed (rate limit not exceeded)
 * 
 * @param string $action Action identifier (e.g., 'login', 'registration', 'api')
 * @param string|null $identifier Identifier (IP address, user ID, etc.) - defaults to IP
 * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int|null, 'message' => string|null]
 */
if (!function_exists('rateLimitCheck')) {
    function rateLimitCheck($action, $identifier = null) {
        // Check if rate limiting is enabled
        if (!config('security.rate_limit_enabled', true)) {
            return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'reset_time' => null];
        }
        
        // Get identifier (default to IP address)
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        // Get rate limit configuration for this action
        $limits = getRateLimitConfig($action);
        $maxAttempts = $limits['max_attempts'];
        $timeWindow = $limits['time_window'];
        
        try {
            $conn = getDatabaseConnection();
            
            // Ensure rate_limits table exists
            ensureRateLimitTable($conn);
            
            // Clean up old records
            $cleanupStmt = $conn->prepare("
                DELETE FROM rate_limits 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $cleanupStmt->execute([$timeWindow]);
            
            // Check current request count
            $stmt = $conn->prepare("
                SELECT COUNT(*) as attempt_count,
                       MIN(created_at) as first_attempt
                FROM rate_limits 
                WHERE identifier = ? 
                AND action = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$identifier, $action, $timeWindow]);
            $result = $stmt->fetch();
            
            $attemptCount = (int)($result['attempt_count'] ?? 0);
            $firstAttempt = $result['first_attempt'] ?? null;
            
            // Calculate remaining attempts
            $remaining = max(0, $maxAttempts - $attemptCount);
            
            // Calculate reset time
            $resetTime = null;
            if ($firstAttempt) {
                $resetTime = strtotime($firstAttempt) + $timeWindow;
            }
            
            if ($attemptCount >= $maxAttempts) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => $resetTime,
                    'message' => "Too many {$action} attempts. Please try again later."
                ];
            }
            
            return [
                'allowed' => true,
                'remaining' => $remaining,
                'reset_time' => $resetTime,
                'message' => null
            ];
            
        } catch (Exception $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            
            // Fail closed in production, fail open in development
            if (isProductionEnvironment()) {
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_time' => null,
                    'message' => 'Rate limiting temporarily unavailable. Please try again later.'
                ];
            } else {
                // Development: fail open
                return ['allowed' => true, 'remaining' => PHP_INT_MAX, 'reset_time' => null];
            }
        }
    }
}

/**
 * Record a rate-limited action
 * 
 * @param string $action Action identifier
 * @param string|null $identifier Identifier (defaults to IP)
 * @return bool Success status
 */
if (!function_exists('rateLimitRecord')) {
    function rateLimitRecord($action, $identifier = null) {
        if (!config('security.rate_limit_enabled', true)) {
            return true;
        }
        
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }
        
        try {
            $conn = getDatabaseConnection();
            ensureRateLimitTable($conn);
            
            $stmt = $conn->prepare("
                INSERT INTO rate_limits (identifier, action, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            return $stmt->execute([$identifier, $action]);
            
        } catch (Exception $e) {
            error_log("Rate limit record failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get rate limit configuration for an action
 * 
 * @param string $action Action identifier
 * @return array ['max_attempts' => int, 'time_window' => int]
 */
if (!function_exists('getRateLimitConfig')) {
    function getRateLimitConfig($action) {
        $configs = [
            'login' => [
                'max_attempts' => RATE_LIMIT_LOGIN_ATTEMPTS,
                'time_window' => RATE_LIMIT_LOGIN_WINDOW
            ],
            'registration' => [
                'max_attempts' => RATE_LIMIT_REGISTRATION_ATTEMPTS,
                'time_window' => RATE_LIMIT_REGISTRATION_WINDOW
            ],
            'api' => [
                'max_attempts' => RATE_LIMIT_API_REQUESTS,
                'time_window' => RATE_LIMIT_API_WINDOW
            ],
            'device_api' => [
                'max_attempts' => RATE_LIMIT_API_REQUESTS ?? 100,
                'time_window' => RATE_LIMIT_API_WINDOW ?? 3600 // 1 hour
            ],
            'password_reset' => [
                'max_attempts' => 5,
                'time_window' => 3600 // 1 hour
            ],
            'default' => [
                'max_attempts' => 10,
                'time_window' => 300 // 5 minutes
            ]
        ];
        
        return $configs[$action] ?? $configs['default'];
    }
}

/**
 * Ensure rate_limits table exists
 * 
 * @param PDO $conn Database connection
 * @return void
 */
if (!function_exists('ensureRateLimitTable')) {
    function ensureRateLimitTable($conn) {
        static $tableExists = false;
        
        if ($tableExists) {
            return;
        }
        
        try {
            $conn->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(255) NOT NULL,
                action VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_identifier_action_time (identifier, action, created_at),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            
            $tableExists = true;
            
        } catch (PDOException $e) {
            error_log("Failed to create rate_limits table: " . $e->getMessage());
            throw $e;
        }
    }
}

/**
 * Reset rate limit for an identifier and action
 * 
 * @param string $action Action identifier
 * @param string $identifier Identifier
 * @return bool Success status
 */
if (!function_exists('rateLimitReset')) {
    function rateLimitReset($action, $identifier) {
        try {
            $conn = getDatabaseConnection();
            ensureRateLimitTable($conn);
            
            $stmt = $conn->prepare("
                DELETE FROM rate_limits 
                WHERE identifier = ? AND action = ?
            ");
            
            return $stmt->execute([$identifier, $action]);
            
        } catch (Exception $e) {
            error_log("Rate limit reset failed: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get client IP address
 * 
 * @return string IP address
 */
if (!function_exists('getClientIp')) {
    function getClientIp() {
        $ipKeys = [
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',        // Nginx proxy
            'HTTP_X_FORWARDED_FOR',  // Proxy
            'REMOTE_ADDR'            // Standard
        ];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
?>


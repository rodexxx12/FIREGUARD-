<?php
/**
 * Security Functions for Registration System
 * 
 * Provides:
 * - CSRF token generation and validation
 * - Rate limiting (IP-based)
 * - Enhanced password hashing (argon2id/bcrypt)
 * - reCAPTCHA verification
 */

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate a CSRF token and store it in session
     * @return string CSRF token
     */
    function generate_csrf_token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        // Regenerate token every 30 minutes for security
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 1800) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf_token')) {
    /**
     * Validate CSRF token from POST request
     * @param string $token Token to validate
     * @return bool True if valid, false otherwise
     */
    function validate_csrf_token($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        
        // Token expires after 2 hours
        if (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > 7200) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('get_client_ip')) {
    /**
     * Get the client's IP address
     * @return string IP address
     */
    function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (from proxies)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

if (!function_exists('check_rate_limit')) {
    /**
     * Check if IP address has exceeded rate limit
     * @param string $action Action identifier (e.g., 'registration', 'email_verification')
     * @param int $max_attempts Maximum attempts allowed
     * @param int $time_window Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_time' => int]
     */
    function check_rate_limit($action, $max_attempts = 5, $time_window = 3600) {
        $ip = get_client_ip();
        $conn = getDatabaseConnection();
        
        try {
            // Create rate_limits table if it doesn't exist
            $conn->exec("CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                attempts INT DEFAULT 1,
                first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_ip_action (ip_address, action),
                INDEX idx_ip_action (ip_address, action),
                INDEX idx_last_attempt (last_attempt)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Clean old records (older than time_window)
            $conn->exec("DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL $time_window SECOND)");
            
            // Get or create rate limit record
            $stmt = $conn->prepare("
                INSERT INTO rate_limits (ip_address, action, attempts, first_attempt, last_attempt)
                VALUES (?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    attempts = CASE 
                        WHEN TIMESTAMPDIFF(SECOND, first_attempt, NOW()) > ? THEN 1
                        ELSE attempts + 1
                    END,
                    first_attempt = CASE 
                        WHEN TIMESTAMPDIFF(SECOND, first_attempt, NOW()) > ? THEN NOW()
                        ELSE first_attempt
                    END,
                    last_attempt = NOW()
            ");
            $stmt->execute([$ip, $action, $time_window, $time_window]);
            
            // Get current attempt count
            $stmt = $conn->prepare("
                SELECT attempts, first_attempt, last_attempt
                FROM rate_limits
                WHERE ip_address = ? AND action = ?
            ");
            $stmt->execute([$ip, $action]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                return ['allowed' => true, 'remaining' => $max_attempts, 'reset_time' => time() + $time_window];
            }
            
            $attempts = (int)$record['attempts'];
            $first_attempt = strtotime($record['first_attempt']);
            $time_elapsed = time() - $first_attempt;
            
            // Reset if time window has passed
            if ($time_elapsed > $time_window) {
                $stmt = $conn->prepare("
                    UPDATE rate_limits 
                    SET attempts = 1, first_attempt = NOW(), last_attempt = NOW()
                    WHERE ip_address = ? AND action = ?
                ");
                $stmt->execute([$ip, $action]);
                return ['allowed' => true, 'remaining' => $max_attempts - 1, 'reset_time' => time() + $time_window];
            }
            
            $remaining = max(0, $max_attempts - $attempts);
            $reset_time = $first_attempt + $time_window;
            
            return [
                'allowed' => $attempts < $max_attempts,
                'remaining' => $remaining,
                'reset_time' => $reset_time,
                'attempts' => $attempts
            ];
            
        } catch (PDOException $e) {
            error_log("Rate limit check failed: " . $e->getMessage());
            // On error, allow the request but log it
            return ['allowed' => true, 'remaining' => $max_attempts, 'reset_time' => time() + $time_window];
        }
    }
}

if (!function_exists('hash_password_secure')) {
    /**
     * Hash password using argon2id (preferred) or bcrypt (fallback)
     * @param string $password Plain text password
     * @return string|false Hashed password or false on failure
     */
    function hash_password_secure($password) {
        // Try argon2id first (best security)
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($password, PASSWORD_ARGON2ID, [
                'memory_cost' => 65536, // 64 MB
                'time_cost' => 4,       // 4 iterations
                'threads' => 3          // 3 threads
            ]);
        }
        
        // Fallback to bcrypt (still secure)
        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12 // Higher cost for better security
        ]);
    }
}

if (!function_exists('verify_password_secure')) {
    /**
     * Verify password against hash
     * @param string $password Plain text password
     * @param string $hash Hashed password
     * @return bool True if password matches
     */
    function verify_password_secure($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('verify_recaptcha')) {
    /**
     * Verify reCAPTCHA token
     * @param string $token reCAPTCHA response token
     * @return array ['success' => bool, 'message' => string]
     */
    function verify_recaptcha($token) {
        if (empty($token)) {
            return ['success' => false, 'message' => 'reCAPTCHA token is missing'];
        }
        
        // Load reCAPTCHA config
        $recaptcha_config_file = dirname(__DIR__) . '/login/functions/recaptcha_config.php';
        if (!file_exists($recaptcha_config_file)) {
            return ['success' => false, 'message' => 'reCAPTCHA configuration not found'];
        }
        
        $config = require $recaptcha_config_file;
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Get secret key for current domain
        $secret_key = $config['domains'][$host]['secret_key'] ?? $config['default']['secret_key'] ?? '';
        
        if (empty($secret_key)) {
            error_log("reCAPTCHA secret key not configured for host: $host");
            return ['success' => false, 'message' => 'reCAPTCHA not configured'];
        }
        
        // Verify with Google
        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secret_key,
            'response' => $token,
            'remoteip' => get_client_ip()
        ];
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);
        
        if ($result === false) {
            error_log("reCAPTCHA verification request failed");
            return ['success' => false, 'message' => 'Failed to verify reCAPTCHA'];
        }
        
        $response = json_decode($result, true);
        
        if (!isset($response['success']) || !$response['success']) {
            $error_codes = $response['error-codes'] ?? ['unknown-error'];
            error_log("reCAPTCHA verification failed: " . implode(', ', $error_codes));
            return [
                'success' => false,
                'message' => 'reCAPTCHA verification failed',
                'error_codes' => $error_codes
            ];
        }
        
        return ['success' => true, 'message' => 'reCAPTCHA verified'];
    }
}

if (!function_exists('add_honeypot_field')) {
    /**
     * Generate a honeypot field name (hidden field to catch bots)
     * @return string Honeypot field name
     */
    function add_honeypot_field() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $field_name = 'website_' . bin2hex(random_bytes(4));
        $_SESSION['honeypot_field'] = $field_name;
        return $field_name;
    }
}

if (!function_exists('check_honeypot')) {
    /**
     * Check if honeypot field was filled (indicates bot)
     * @param array $post_data POST data
     * @return bool True if honeypot was filled (bot detected)
     */
    function check_honeypot($post_data) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['honeypot_field'])) {
            return false; // No honeypot set, allow
        }
        
        $honeypot_field = $_SESSION['honeypot_field'];
        $honeypot_value = $post_data[$honeypot_field] ?? '';
        
        // If honeypot field has any value, it's likely a bot
        return !empty($honeypot_value);
    }
}


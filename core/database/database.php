<?php
/**
 * Centralized Database Connection Module
 * 
 * Provides secure PDO database connection with prepared statements only
 * NO SQL injection vulnerabilities - all queries must use prepared statements
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/database/database.php';
 * 
 * $conn = getDatabaseConnection();
 * $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
 * $stmt->execute([$userId]);
 * $user = $stmt->fetch();
 */

// Load configuration
if (!defined('DB_CHARSET')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Get database connection (singleton pattern)
 * 
 * @return PDO Database connection
 * @throws Exception If connection fails
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        static $conn = null;
        
        if ($conn !== null) {
            return $conn; // Return existing connection
        }
        
        // Get database configuration
        $host = config('db.host', 'localhost');
        $dbname = config('db.name', '');
        $username = config('db.user', '');
        $password = config('db.pass', '');
        $charset = config('db.charset', DB_CHARSET);
        
        // Validate required configuration
        if (empty($dbname) || empty($username)) {
            $missing = [];
            if (empty($dbname)) $missing[] = 'DB_NAME';
            if (empty($username)) $missing[] = 'DB_USER';
            
            $errorMsg = "CRITICAL: Database configuration incomplete. Missing: " . implode(', ', $missing);
            $errorMsg .= ". Please check your .env file in the project root.";
            
            error_log($errorMsg);
            
            // Provide helpful error message
            if (isProductionEnvironment()) {
                throw new Exception('Database configuration error. Please contact administrator.');
            } else {
                throw new Exception("Database configuration error: Missing " . implode(' and ', $missing) . ". Check .env file.");
            }
        }
        
        // Ensure password is set (can be empty string for local development)
        if ($password === false) {
            $password = '';
        }
        
        // In production, warn if password is empty (but allow for development)
        if (isProductionEnvironment() && empty($password)) {
            error_log("WARNING: Database password is empty in production environment");
        }
        
        try {
            // Create DSN
            $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
            
            // PDO options for security and performance
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // Throw exceptions on errors
                PDO::ATTR_EMULATE_PREPARES => false,                // Use real prepared statements (security)
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // Return associative arrays
                PDO::ATTR_PERSISTENT => false,                       // Don't use persistent connections (security)
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE utf8mb4_unicode_ci",
                PDO::ATTR_STRINGIFY_FETCHES => false,                // Don't convert numbers to strings
            ];
            
            // Create PDO connection
            $conn = new PDO($dsn, $username, $password, $options);
            
            // Set timezone to application timezone
            $timezone = APP_TIMEZONE;
            $offset = (new DateTimeZone($timezone))->getOffset(new DateTime()) / 3600;
            $timezoneOffset = sprintf('%+03d:00', $offset);
            $conn->exec("SET time_zone = '{$timezoneOffset}'");
            
            return $conn;
            
        } catch (PDOException $e) {
            // Log full error details for debugging
            error_log("Database connection failed: " . $e->getMessage());
            error_log("Connection attempt: host={$host}, dbname={$dbname}, user={$username}");
            
            // Don't expose database details to users
            if (isProductionEnvironment()) {
                throw new Exception('Database connection failed. Please contact administrator.');
            } else {
                // In development, show more details
                throw new Exception('Database connection failed: ' . $e->getMessage());
            }
        }
    }
}

/**
 * Alternative function name for backward compatibility
 * 
 * @return PDO Database connection
 */
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return getDatabaseConnection();
    }
}

/**
 * Legacy function name for backward compatibility
 * 
 * @return PDO Database connection
 */
if (!function_exists('getConnection')) {
    function getConnection() {
        return getDatabaseConnection();
    }
}

/**
 * Execute a prepared query and return results
 * Helper function to simplify common queries
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return PDOStatement|false
 */
if (!function_exists('executeQuery')) {
    function executeQuery($sql, $params = []) {
        $conn = getDatabaseConnection();
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            error_log("Failed to prepare query: " . $sql);
            return false;
        }
        
        if (!$stmt->execute($params)) {
            error_log("Failed to execute query: " . $sql);
            return false;
        }
        
        return $stmt;
    }
}

/**
 * Execute a query and return a single row
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array|false
 */
if (!function_exists('fetchOne')) {
    function fetchOne($sql, $params = []) {
        $stmt = executeQuery($sql, $params);
        if ($stmt === false) {
            return false;
        }
        return $stmt->fetch();
    }
}

/**
 * Execute a query and return all rows
 * 
 * @param string $sql SQL query with placeholders
 * @param array $params Parameters to bind
 * @return array
 */
if (!function_exists('fetchAll')) {
    function fetchAll($sql, $params = []) {
        $stmt = executeQuery($sql, $params);
        if ($stmt === false) {
            return [];
        }
        return $stmt->fetchAll();
    }
}

/**
 * Begin a database transaction
 * 
 * @return bool Success status
 */
if (!function_exists('beginTransaction')) {
    function beginTransaction() {
        $conn = getDatabaseConnection();
        return $conn->beginTransaction();
    }
}

/**
 * Commit a database transaction
 * 
 * @return bool Success status
 */
if (!function_exists('commitTransaction')) {
    function commitTransaction() {
        $conn = getDatabaseConnection();
        return $conn->commit();
    }
}

/**
 * Rollback a database transaction
 * 
 * @return bool Success status
 */
if (!function_exists('rollbackTransaction')) {
    function rollbackTransaction() {
        $conn = getDatabaseConnection();
        return $conn->rollBack();
    }
}
?>


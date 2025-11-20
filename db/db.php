<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Centralized Database Configuration
// Production database configuration (for production environment)
define('DB_HOST_PROD', 'localhost');
define('DB_NAME_PROD', 'firedb');
define('DB_USER_PROD', 'root');
define('DB_PASS_PROD', '');

// Development database configuration (for local development)
define('DB_HOST_DEV', 'localhost');
define('DB_NAME_DEV', 'firedb');
define('DB_USER_DEV', 'root');
define('DB_PASS_DEV', '');

define('DB_CHARSET', 'utf8mb4');

// Determine environment (you can modify this logic based on your needs)
function isProductionEnvironment() {
    // You can modify this logic to detect production vs development
    // For now, we'll use a simple check - modify as needed
    return isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') === false;
}

// Centralized Database connection with error handling
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        static $conn = null;
        if ($conn === null) {
            try {
                // Choose configuration based on environment
                if (isProductionEnvironment()) {
                    $host = DB_HOST_PROD;
                    $dbname = DB_NAME_PROD;
                    $username = DB_USER_PROD;
                    $password = DB_PASS_PROD;
                } else {
                    $host = DB_HOST_DEV;
                    $dbname = DB_NAME_DEV;
                    $username = DB_USER_DEV;
                    $password = DB_PASS_DEV;
                }
                
                $dsn = "mysql:host=$host;dbname=$dbname;charset=" . DB_CHARSET;
                $conn = new PDO($dsn, $username, $password);
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
                $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                die(json_encode(['success' => false, 'message' => 'System temporarily unavailable']));
            }
        }
        return $conn;
    }
}

// Alternative function name for backward compatibility
if (!function_exists('getDBConnection')) {
    function getDBConnection() {
        return getDatabaseConnection();
    }
}

// Legacy function names for backward compatibility
if (!function_exists('getConnection')) {
    function getConnection() {
        return getDatabaseConnection();
    }
}
?>

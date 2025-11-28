<?php
// Error reporting configuration - environment-aware
// Only show errors in development, log them in production
// Wrap in function_exists check to prevent redeclaration if file is included multiple times
if (!function_exists('isDevelopmentEnvironment')) {
    /**
     * Check if running in development environment
     * @return bool
     */
    function isDevelopmentEnvironment() {
        // Check if we're in development mode
        // You can also check for an environment variable or config file
        $isLocalhost = isset($_SERVER['HTTP_HOST']) && (
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false ||
            strpos($_SERVER['HTTP_HOST'], '::1') !== false
        );
        
        // Check for environment variable (if set)
        $env = getenv('APP_ENV');
        if ($env !== false) {
            return strtolower($env) === 'development' || strtolower($env) === 'local';
        }
        
        // Default: assume production if not localhost
        return $isLocalhost;
    }
}

if (isDevelopmentEnvironment()) {
    // Development: show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    // Production: log errors but don't display them
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');
}

// Database charset constant - check if already defined (to prevent duplicate definition)
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', 'utf8mb4');
}

// Helper function to load environment variables
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($filePath) {
        if (!file_exists($filePath)) {
            return;
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                if (!isset($_ENV[$name])) {
                    $_ENV[$name] = $value;
                    putenv("$name=$value");
                }
            }
        }
    }
}

// Load environment variables from centralized .env file ONLY
// All other config.env files should be removed - use root-level .env only
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    loadEnvFile($envPath);
} else {
    error_log("WARNING: Centralized .env file not found at: {$envPath}");
}

// Determine environment - wrap in function_exists check to prevent redeclaration
if (!function_exists('isProductionEnvironment')) {
    /**
     * Check if running in production environment
     * @return bool
     */
    function isProductionEnvironment() {
        $env = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
        if ($env !== false && $env !== '') {
            return strtolower($env) === 'production';
        }
        
        // Fallback: check hostname
        $host = $_SERVER['HTTP_HOST'] ?? '';
        return strpos($host, 'localhost') === false && 
               strpos($host, '127.0.0.1') === false &&
               strpos($host, '::1') === false;
    }
}

// Centralized Database connection with error handling
// SECURITY: Now uses centralized configuration system
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        static $conn = null;
        if ($conn === null) {
            try {
                // Load centralized configuration (preferred method)
                $configPath = __DIR__ . '/../core/config/config.php';
                if (file_exists($configPath)) {
                    require_once $configPath;
                }
                
                // If config() function exists, use it
                if (function_exists('config')) {
                    $host = config('db.host', 'localhost');
                    $dbname = config('db.name', '');
                    $username = config('db.user', '');
                    $password = config('db.pass', '');
                } else {
                    // Fallback: Load environment directly
                    $envPath = __DIR__ . '/../core/config/env.php';
                    if (file_exists($envPath)) {
                        require_once $envPath;
                    }
                    
                    // Try to get from environment
                    $host = env('DB_HOST', 'localhost');
                    $dbname = env('DB_NAME', '');
                    $username = env('DB_USER', '');
                    $password = env('DB_PASS', '');
                    
                    // Last resort: Direct access
                    if (empty($dbname)) {
                        $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
                        $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
                        $username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
                        $password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
                    }
                }
                
                // Get charset constant
                $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
                
                // Validate required configuration
                if (empty($dbname) || empty($username)) {
                    $missing = [];
                    if (empty($dbname)) $missing[] = 'DB_NAME';
                    if (empty($username)) $missing[] = 'DB_USER';
                    
                    $errorMsg = "Database configuration incomplete. Missing: " . implode(', ', $missing);
                    $errorMsg .= ". Please check your .env file in the project root.";
                    
                    error_log("CRITICAL: " . $errorMsg);
                    
                    // Provide helpful error message
                    if (function_exists('isDevelopmentEnvironment') && isDevelopmentEnvironment()) {
                        throw new Exception($errorMsg);
                    } else {
                        throw new Exception('Database configuration error. Please contact administrator.');
                    }
                }
                
                $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                $conn = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_PERSISTENT => false
                ]);
                
            } catch(PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                
                $isDev = (function_exists('isDevelopmentEnvironment') && isDevelopmentEnvironment()) ||
                         (isset($_SERVER['HTTP_HOST']) && 
                          (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                           strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
                
                if ($isDev) {
                    die(json_encode([
                        'success' => false, 
                        'message' => 'Database connection failed: ' . $e->getMessage(),
                        'hint' => 'Check your .env file database credentials'
                    ]));
                } else {
                    die(json_encode(['success' => false, 'message' => 'System temporarily unavailable']));
                }
            } catch(Exception $e) {
                error_log("Database configuration error: " . $e->getMessage());
                
                $isDev = (function_exists('isDevelopmentEnvironment') && isDevelopmentEnvironment()) ||
                         (isset($_SERVER['HTTP_HOST']) && 
                          (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
                           strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false));
                
                if ($isDev) {
                    $message = 'Database configuration error: ' . $e->getMessage();
                    $message .= '. Please check your .env file in the project root.';
                } else {
                    $message = 'System configuration error';
                }
                
                die(json_encode([
                    'success' => false, 
                    'message' => $message,
                    'debug' => $isDev ? $e->getMessage() : null
                ]));
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

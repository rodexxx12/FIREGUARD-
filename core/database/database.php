<?php
/**
 * Database Connection Module
 * 
 * Provides centralized database connection function
 * 
 * This file ensures getDatabaseConnection() is available throughout the application.
 * It includes the database connection implementation from db/db.php
 */

// Prevent direct access
if (!defined('BOOTSTRAP_LOADED') && !function_exists('getDatabaseConnection')) {
    // Include the database connection implementation
    // Path: core/database/database.php -> ../../db/db.php
    $dbPath = __DIR__ . '/../../db/db.php';
    if (file_exists($dbPath)) {
        require_once $dbPath;
    } else {
        // Fallback: Define the function directly if db.php doesn't exist
        if (!function_exists('getDatabaseConnection')) {
            /**
             * Get database connection
             * @return PDO
             * @throws Exception
             */
            function getDatabaseConnection() {
                static $conn = null;
                if ($conn === null) {
                    // Load configuration
                    $configPath = __DIR__ . '/../config/config.php';
                    if (file_exists($configPath)) {
                        require_once $configPath;
                    }
                    
                    // Get database credentials from environment
                    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
                    $dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: '';
                    $username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: '';
                    $password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';
                    $charset = defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4';
                    
                    if (empty($dbname) || empty($username)) {
                        throw new Exception('Database configuration incomplete. Please check your .env file.');
                    }
                    
                    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
                    $conn = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_PERSISTENT => false
                    ]);
                }
                return $conn;
            }
        }
    }
}

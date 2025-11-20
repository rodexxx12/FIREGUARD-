<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection with error handling
if (!function_exists('getDatabaseConnection')) {
function getDatabaseConnection() {
    static $conn = null;
    if ($conn === null) {
        $host = "localhost";
        $dbname = "u520834156_DBBagofire"; 
        $username = "u520834156_userBagofire";
        $password = "i[#[GQ!+=C9";
        
        try {
            $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
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
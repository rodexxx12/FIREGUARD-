<?php

// Only start session and check authentication if this file is accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    session_start();
    if (!isset($_SESSION['superadmin_id'])) {
        header("Location: ../../../index.php");
        exit();
    }
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once __DIR__ . '/../../../db/db.php';

class Database {
    private static $pdo = null;
    
    public static function getConnection() {
        if (self::$pdo === null) {
            self::$pdo = getDatabaseConnection();
        }
        
        return self::$pdo;
    }
} 
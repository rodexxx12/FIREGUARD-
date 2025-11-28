<?php

// Only start session and check authentication if this file is accessed directly
if (basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    session_start();
    if (!isset($_SESSION['superadmin_id'])) {
        header("Location: ../../../index.php");
        exit();
    }
}

// Security: Include the centralized database connection
// The main db.php file already handles environment-aware error reporting
// No need to duplicate the function - it will be declared in db/db.php
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
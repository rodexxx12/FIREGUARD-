<?php
require_once '../../db/db.php'; // Use centralized database configuration

/**
 * Database connection component - now uses centralized connection
 */
class Database {
    /**
     * Get database connection using centralized connection
     * @return PDO
     */
    public static function getConnection() {
        return getDatabaseConnection();
    }
} 
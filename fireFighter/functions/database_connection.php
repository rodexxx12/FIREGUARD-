<?php
/**
 * Database Connection for FireFighter Module
 * SECURITY FIX: Removed hardcoded credentials - now uses centralized database connection
 * 
 * This file is now a wrapper that uses the centralized database connection.
 * All credentials should be in .env file, never hardcoded.
 */

// Load centralized database connection
require_once __DIR__ . '/../../../core/config/config.php';
require_once __DIR__ . '/../../../core/database/database.php';

// Use centralized database connection function
// This ensures all security settings and environment variables are used
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection() {
        // Delegate to centralized database connection
        return \getDatabaseConnection();
    }
} 
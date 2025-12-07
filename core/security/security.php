<?php
/**
 * Security Module Loader
 * 
 * Loads all security-related modules
 * Include this file to get access to all security functions
 * 
 * Usage:
 * require_once __DIR__ . '/../../core/config/config.php';
 * require_once __DIR__ . '/../../core/security/security.php';
 */

// Load all security modules
require_once __DIR__ . '/xss.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/input_sanitizer.php';
require_once __DIR__ . '/headers.php';
?>























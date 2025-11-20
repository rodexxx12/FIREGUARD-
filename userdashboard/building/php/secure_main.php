<?php
/**
 * Secure Building Registration Wrapper
 * This file wraps the existing main.php with enhanced security features
 * without modifying the original functions
 */

// Include the security module
require_once 'security.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Initialize security instance
$security = BuildingSecurity::getInstance();

// Handle CSRF token generation for forms
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'csrf_token') {
    header('Content-Type: application/json');
    echo json_encode(['csrf_token' => generateCSRFToken()]);
    exit;
}

// Enhanced POST request handling with security
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Rate limiting check
        $userIdentifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        checkRateLimit($userIdentifier, 'building_registration');
        
        // CSRF token validation (except for test requests)
        if (!isset($_POST['test_db']) && !isset($_POST['csrf_token'])) {
            logSecurityEvent('MISSING_CSRF_TOKEN', $_SERVER['REMOTE_ADDR']);
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Security token missing']);
            exit;
        }
        
        if (isset($_POST['csrf_token'])) {
            validateCSRFToken($_POST['csrf_token']);
        }
        
        // Enhanced input validation and sanitization
        $validationResult = validateBuildingData($_POST);
        
        if (!empty($validationResult['errors'])) {
            logSecurityEvent('VALIDATION_ERRORS', implode(', ', $validationResult['errors']));
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => implode('<br>', $validationResult['errors'])]);
            exit;
        }
        
        // Replace $_POST with sanitized data
        $_POST = array_merge($_POST, $validationResult['data']);
        
        // Log successful validation
        logSecurityEvent('BUILDING_REGISTRATION_ATTEMPT', 'User: ' . ($_SESSION['user_id'] ?? 'anonymous'));
        
    } catch (SecurityException $e) {
        logSecurityEvent('SECURITY_VIOLATION', $e->getMessage());
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security violation: ' . $e->getMessage()]);
        exit;
    } catch (Exception $e) {
        logSecurityEvent('VALIDATION_ERROR', $e->getMessage());
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Validation error: ' . $e->getMessage()]);
        exit;
    }
}

// Enhanced DELETE request handling
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        // Rate limiting for delete operations
        $userIdentifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        checkRateLimit($userIdentifier, 'building_deletion');
        
        // Get raw input and decode JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new SecurityException('Invalid JSON input');
        }
        
        // Validate CSRF token in JSON input
        if (!isset($input['csrf_token'])) {
            logSecurityEvent('MISSING_CSRF_TOKEN_DELETE', $_SERVER['REMOTE_ADDR']);
            throw new SecurityException('Security token missing');
        }
        
        validateCSRFToken($input['csrf_token']);
        
        // Sanitize building ID
        if (isset($input['building_id'])) {
            $input['building_id'] = sanitizeBuildingInput($input['building_id'], 'int');
        }
        
        // Replace the input with sanitized version
        file_put_contents('php://input', json_encode($input));
        
        logSecurityEvent('BUILDING_DELETE_ATTEMPT', 'Building ID: ' . ($input['building_id'] ?? 'unknown'));
        
    } catch (SecurityException $e) {
        logSecurityEvent('DELETE_SECURITY_VIOLATION', $e->getMessage());
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Security violation: ' . $e->getMessage()]);
        exit;
    }
}

// Enhanced GET request handling
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Sanitize GET parameters
        if (isset($_GET['lat'])) {
            $_GET['lat'] = sanitizeBuildingInput($_GET['lat'], 'coordinates');
        }
        if (isset($_GET['lng'])) {
            $_GET['lng'] = sanitizeBuildingInput($_GET['lng'], 'coordinates');
        }
        if (isset($_GET['building_id'])) {
            $_GET['building_id'] = sanitizeBuildingInput($_GET['building_id'], 'int');
        }
        
    } catch (Exception $e) {
        logSecurityEvent('GET_VALIDATION_ERROR', $e->getMessage());
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
        exit;
    }
}

// Include the original main.php file
// This preserves all your existing functionality while adding security layers
include 'main.php';
?>

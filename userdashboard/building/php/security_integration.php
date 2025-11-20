<?php
/**
 * Building Registration Security Integration Guide
 * 
 * This file demonstrates how to integrate the security features into your existing building registration system
 * without modifying your existing functions.
 */

// Include security modules
require_once 'security.php';
require_once 'secure_db.php';
require_once 'security_config.php';

// Load security configuration
$securityConfig = include 'security_config.php';

// Example: Enhanced building registration with security
function registerBuildingWithSecurity($buildingData) {
    try {
        // Initialize security
        $security = BuildingSecurity::getInstance();
        $secureDB = new SecureDatabaseConnection();
        
        // Rate limiting check
        $userIdentifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        checkRateLimit($userIdentifier, 'building_registration');
        
        // CSRF token validation
        if (isset($buildingData['csrf_token'])) {
            validateCSRFToken($buildingData['csrf_token']);
        }
        
        // Validate and sanitize input data
        $validationResult = validateBuildingData($buildingData);
        
        if (!empty($validationResult['errors'])) {
            logSecurityEvent('BUILDING_VALIDATION_ERROR', implode(', ', $validationResult['errors']));
            throw new Exception('Validation errors: ' . implode(', ', $validationResult['errors']));
        }
        
        // Add user_id to data
        $validationResult['data']['user_id'] = $_SESSION['user_id'];
        
        // Register building using secure database connection
        $buildingId = $secureDB->registerBuildingSecure($validationResult['data']);
        
        // Log successful registration
        logSecurityEvent('BUILDING_REGISTERED_SUCCESS', 'Building ID: ' . $buildingId);
        
        return [
            'status' => 'success',
            'message' => 'Building registered successfully!',
            'building_id' => $buildingId
        ];
        
    } catch (SecurityException $e) {
        logSecurityEvent('SECURITY_VIOLATION', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Security violation: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        logSecurityEvent('BUILDING_REGISTRATION_ERROR', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Registration failed: ' . $e->getMessage()
        ];
    }
}

// Example: Enhanced building update with security
function updateBuildingWithSecurity($buildingId, $buildingData) {
    try {
        $security = BuildingSecurity::getInstance();
        $secureDB = new SecureDatabaseConnection();
        
        // Rate limiting check
        $userIdentifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        checkRateLimit($userIdentifier, 'building_update');
        
        // CSRF token validation
        if (isset($buildingData['csrf_token'])) {
            validateCSRFToken($buildingData['csrf_token']);
        }
        
        // Validate and sanitize input data
        $validationResult = validateBuildingData($buildingData);
        
        if (!empty($validationResult['errors'])) {
            logSecurityEvent('BUILDING_UPDATE_VALIDATION_ERROR', implode(', ', $validationResult['errors']));
            throw new Exception('Validation errors: ' . implode(', ', $validationResult['errors']));
        }
        
        // Add building_id and user_id to data
        $validationResult['data']['building_id'] = $buildingId;
        $validationResult['data']['user_id'] = $_SESSION['user_id'];
        
        // Update building using secure database connection
        $secureDB->registerBuildingSecure($validationResult['data']);
        
        // Log successful update
        logSecurityEvent('BUILDING_UPDATED_SUCCESS', 'Building ID: ' . $buildingId);
        
        return [
            'status' => 'success',
            'message' => 'Building updated successfully!',
            'building_id' => $buildingId
        ];
        
    } catch (SecurityException $e) {
        logSecurityEvent('UPDATE_SECURITY_VIOLATION', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Security violation: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        logSecurityEvent('BUILDING_UPDATE_ERROR', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Update failed: ' . $e->getMessage()
        ];
    }
}

// Example: Enhanced building deletion with security
function deleteBuildingWithSecurity($buildingId) {
    try {
        $secureDB = new SecureDatabaseConnection();
        
        // Rate limiting check
        $userIdentifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
        checkRateLimit($userIdentifier, 'building_deletion');
        
        // CSRF token validation (should be passed in request)
        $input = json_decode(file_get_contents('php://input'), true);
        if (isset($input['csrf_token'])) {
            validateCSRFToken($input['csrf_token']);
        }
        
        // Delete building using secure database connection
        $secureDB->deleteBuildingSecure($buildingId, $_SESSION['user_id']);
        
        // Log successful deletion
        logSecurityEvent('BUILDING_DELETED_SUCCESS', 'Building ID: ' . $buildingId);
        
        return [
            'status' => 'success',
            'message' => 'Building deleted successfully!'
        ];
        
    } catch (SecurityException $e) {
        logSecurityEvent('DELETE_SECURITY_VIOLATION', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Security violation: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        logSecurityEvent('BUILDING_DELETE_ERROR', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Deletion failed: ' . $e->getMessage()
        ];
    }
}

// Example: Secure file upload for building documents
function uploadBuildingDocument($file, $buildingId) {
    try {
        $security = BuildingSecurity::getInstance();
        
        // Validate file upload
        validateFileUpload($file, ['jpg', 'jpeg', 'png', 'pdf'], 5242880); // 5MB max
        
        // Sanitize filename
        $originalName = $file['name'];
        $sanitizedName = sanitizeFileName($originalName);
        
        // Generate unique filename
        $extension = pathinfo($sanitizedName, PATHINFO_EXTENSION);
        $uniqueName = 'building_' . $buildingId . '_' . time() . '.' . $extension;
        
        // Define upload path
        $uploadDir = 'uploads/building_documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $uploadPath = $uploadDir . $uniqueName;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            logSecurityEvent('BUILDING_DOCUMENT_UPLOADED', 'File: ' . $uniqueName);
            
            return [
                'status' => 'success',
                'message' => 'Document uploaded successfully!',
                'filename' => $uniqueName,
                'path' => $uploadPath
            ];
        } else {
            throw new Exception('Failed to move uploaded file');
        }
        
    } catch (SecurityException $e) {
        logSecurityEvent('FILE_UPLOAD_SECURITY_VIOLATION', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Security violation: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        logSecurityEvent('FILE_UPLOAD_ERROR', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Upload failed: ' . $e->getMessage()
        ];
    }
}

// Example: Get buildings with security
function getBuildingsWithSecurity($filters = []) {
    try {
        $secureDB = new SecureDatabaseConnection();
        
        // Sanitize filters
        $sanitizedFilters = [];
        if (isset($filters['building_type'])) {
            $sanitizedFilters['building_type'] = sanitizeBuildingInput($filters['building_type'], 'building_type');
        }
        if (isset($filters['barangay_id'])) {
            $sanitizedFilters['barangay_id'] = sanitizeBuildingInput($filters['barangay_id'], 'int');
        }
        
        // Get buildings using secure database connection
        $buildings = $secureDB->getBuildingsSecure($_SESSION['user_id'], $sanitizedFilters);
        
        return [
            'status' => 'success',
            'buildings' => $buildings
        ];
        
    } catch (Exception $e) {
        logSecurityEvent('GET_BUILDINGS_ERROR', $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to retrieve buildings: ' . $e->getMessage()
        ];
    }
}

// Example: Generate CSRF token for forms
function getCSRFTokenForForm() {
    return generateCSRFToken();
}

// Example: Security audit log viewer (admin only)
function getSecurityLogs($limit = 100) {
    try {
        $logFile = 'security.log';
        if (!file_exists($logFile)) {
            return ['status' => 'success', 'logs' => []];
        }
        
        $logs = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        // Get last N lines
        $lines = array_slice($lines, -$limit);
        
        foreach ($lines as $line) {
            $logEntry = json_decode($line, true);
            if ($logEntry) {
                $logs[] = $logEntry;
            }
        }
        
        return [
            'status' => 'success',
            'logs' => array_reverse($logs) // Most recent first
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Failed to retrieve security logs: ' . $e->getMessage()
        ];
    }
}

// Example usage in your existing code:
/*
// In your main.php, replace your existing building registration code with:

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['test_db'])) {
    $result = registerBuildingWithSecurity($_POST);
    echo json_encode($result);
    exit;
}

// For updates:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['building_id'])) {
    $result = updateBuildingWithSecurity($_POST['building_id'], $_POST);
    echo json_encode($result);
    exit;
}

// For deletions:
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $result = deleteBuildingWithSecurity($input['building_id']);
    echo json_encode($result);
    exit;
}
*/
?>

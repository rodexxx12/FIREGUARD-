<?php
require_once 'UserPhoneModel.php';
require_once '../db_connection.php';
require_once __DIR__ . '/security_functions.php';

// Ensure session is started so we can access $_SESSION['user_id']
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

header("Content-Type: application/json");

try {
    // Use centralized database connection
    $phoneModel = new UserPhoneModel();
    $userId = $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate CSRF token for all state-changing methods
    if (in_array($requestMethod, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        $token = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!validateCSRFToken($token)) {
            logSecurityEvent('CSRF_TOKEN_INVALID', 'API request without valid CSRF token', 'high');
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            exit();
        }
    }
    
    switch ($requestMethod) {
        case 'GET':
            $phones = $phoneModel->getPhoneNumbers($userId);
            // Escape output for XSS protection
            $safePhones = array_map(function($phone) {
                return [
                    'phone_id' => (int)$phone['phone_id'],
                    'phone_number' => escapeOutput($phone['phone_number']),
                    'label' => escapeOutput($phone['label'] ?? ''),
                    'is_primary' => (bool)($phone['is_primary'] ?? false),
                    'verified' => (bool)($phone['verified'] ?? false)
                ];
            }, $phones);
            echo json_encode([
                'data' => $safePhones,
                'csrf_token' => generateCSRFToken()
            ]);
            break;
            
        case 'POST':
            // Rate limiting
            if (!checkRateLimit('api_add_phone', 5, 300)) {
                http_response_code(429);
                echo json_encode(['error' => 'Too many requests. Please wait a moment.']);
                break;
            }
            
            $phoneNumber = sanitizeInput($input['phone_number'] ?? '', 'phone');
            if (!$phoneNumber || !validatePhoneNumber($phoneNumber)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone number format. Must be exactly 11 digits starting with 09.']);
                break;
            }
            
            if ($phoneModel->phoneNumberExists($phoneNumber)) {
                http_response_code(409);
                echo json_encode(['error' => 'This phone number is already registered.']);
                break;
            }
            
            $isPrimary = isset($input['is_primary']) && $input['is_primary'] === true;
            $label = validateLabel($input['label'] ?? '');
            
            if ($label === false) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid label format.']);
                break;
            }
            
            // Check if user already has maximum allowed phone numbers (10)
            $phoneCount = count($phoneModel->getPhoneNumbers($userId));
            if ($phoneCount >= 10) {
                http_response_code(400);
                echo json_encode(['error' => 'Maximum limit of 10 phone numbers reached. Please delete a phone number before adding a new one.']);
                break;
            }
            
            $result = $phoneModel->addPhoneNumber($userId, $phoneNumber, $isPrimary, $label);
            
            if ($result && isset($result['success']) && $result['success']) {
                echo json_encode([
                    'success' => true,
                    'phone_id' => (int)$result['phone_id'],
                    'csrf_token' => generateCSRFToken()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to add phone number']);
            }
            break;
            
        case 'PUT':
            $phoneId = validateInteger($input['phone_id'] ?? null, 1);
            if (!$phoneId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone ID']);
                break;
            }
            
            if (!$phoneModel->verifyPhoneOwnership($userId, $phoneId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Phone number not found or access denied']);
                break;
            }
            
            $result = $phoneModel->setPrimaryPhone($userId, $phoneId);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'csrf_token' => generateCSRFToken()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update primary phone']);
            }
            break;
            
        case 'DELETE':
            $phoneId = validateInteger($input['phone_id'] ?? null, 1);
            if (!$phoneId) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid phone ID']);
                break;
            }
            
            if (!$phoneModel->verifyPhoneOwnership($userId, $phoneId)) {
                http_response_code(403);
                echo json_encode(['error' => 'Phone number not found or access denied']);
                break;
            }
            
            $result = $phoneModel->deletePhoneNumber($userId, $phoneId);
            
            if ($result && (is_array($result) ? $result['success'] : $result)) {
                echo json_encode([
                    'success' => true,
                    'csrf_token' => generateCSRFToken()
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => is_array($result) ? ($result['error'] ?? 'Failed to delete phone number') : 'Failed to delete phone number']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
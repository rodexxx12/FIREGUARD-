<?php
require_once 'UserPhoneModel.php';
require_once '../db_connection.php';

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
    
    switch ($requestMethod) {
        case 'GET':
            $phones = $phoneModel->getPhoneNumbers($userId);
            echo json_encode(['data' => $phones]);
            break;
            
        case 'POST':
            if (empty($input['phone_number'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone number is required']);
                break;
            }
            
            $isPrimary = $input['is_primary'] ?? false;
            $result = $phoneModel->addPhoneNumber($userId, $input['phone_number'], $isPrimary);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to add phone number']);
            }
            break;
            
        case 'PUT':
            if (empty($input['phone_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone ID is required']);
                break;
            }
            
            $result = $phoneModel->setPrimaryPhone($userId, $input['phone_id']);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update primary phone']);
            }
            break;
            
        case 'DELETE':
            if (empty($input['phone_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Phone ID is required']);
                break;
            }
            
            $result = $phoneModel->deletePhoneNumber($userId, $input['phone_id']);
            
            if ($result) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete phone number']);
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
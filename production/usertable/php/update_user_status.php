<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    // Validate required fields
    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
        throw new Exception('User ID is required');
    }
    
    if (!isset($_POST['status']) || empty($_POST['status'])) {
        throw new Exception('Status is required');
    }
    
    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid User ID');
    }
    
    $status = trim($_POST['status']);
    
    // Validate status
    if (!in_array($status, ['Active', 'Inactive'])) {
        throw new Exception('Invalid status value');
    }
    
    // Check if user exists
    $checkQuery = "SELECT user_id FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        throw new Exception('User not found');
    }
    
    // Update user status
    $updateQuery = "
        UPDATE users SET 
            status = :status
        WHERE user_id = :user_id
    ";
    
    $stmt = $conn->prepare($updateQuery);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'User status updated successfully'
            ]);
        } else {
            throw new Exception('No changes made or user not found');
        }
    } else {
        throw new Exception('Failed to update user status');
    }
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

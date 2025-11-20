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
    
    // Validate required fields - only status is editable now
    if (!isset($_POST['status']) || empty(trim($_POST['status']))) {
        throw new Exception("Status is required");
    }
    
    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid User ID');
    }
    
    // Only get status since all other fields are read-only
    $status = trim($_POST['status']);
    
    // Validate status
    if (!in_array($status, ['Active', 'Inactive'])) {
        throw new Exception('Invalid status value');
    }
    
    // Update user data - only status is editable now
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
                'message' => 'User updated successfully'
            ]);
        } else {
            throw new Exception('No changes made or user not found');
        }
    } else {
        throw new Exception('Failed to update user');
    }
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

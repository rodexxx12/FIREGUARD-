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
    
    // Check if user_id is provided
    if (!isset($_POST['user_id']) || empty($_POST['user_id'])) {
        throw new Exception('User ID is required');
    }
    
    $userId = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid User ID');
    }
    
    // Check if user exists
    $checkQuery = "SELECT user_id FROM users WHERE user_id = :user_id";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        throw new Exception('User not found');
    }
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Delete related records first (foreign key constraints)
        // Delete user's devices
        $deleteDevicesQuery = "DELETE FROM devices WHERE user_id = :user_id";
        $stmt = $conn->prepare($deleteDevicesQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete user's buildings
        $deleteBuildingsQuery = "DELETE FROM buildings WHERE user_id = :user_id";
        $stmt = $conn->prepare($deleteBuildingsQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete user's fire incidents
        $deleteIncidentsQuery = "DELETE FROM fire_incidents WHERE user_id = :user_id";
        $stmt = $conn->prepare($deleteIncidentsQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete user's responses
        $deleteResponsesQuery = "DELETE FROM firefighter_responses WHERE user_id = :user_id";
        $stmt = $conn->prepare($deleteResponsesQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Finally delete the user
        $deleteUserQuery = "DELETE FROM users WHERE user_id = :user_id";
        $stmt = $conn->prepare($deleteUserQuery);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            // Commit transaction
            $conn->commit();
            echo json_encode([
                'success' => true,
                'message' => 'User and all related data deleted successfully'
            ]);
        } else {
            throw new Exception('Failed to delete user');
        }
        
    } catch (Exception $e) {
        // Rollback transaction
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

try {
    // Check if user_id is provided
    if (!isset($_GET['user_id']) || empty($_GET['user_id'])) {
        throw new Exception('User ID is required');
    }
    
    $userId = filter_var($_GET['user_id'], FILTER_VALIDATE_INT);
    if (!$userId) {
        throw new Exception('Invalid User ID');
    }
    
    // Fetch user details
    $query = "
        SELECT 
            u.user_id,
            u.fullname,
            u.birthdate,
            u.age,
            u.address,
            u.email_address,
            u.device_number,
            u.username,
            u.status,
            u.registration_date,
            u.email_verified,
            u.profile_image,
            u.contact_number,
            COUNT(d.device_id) as device_count,
            COUNT(b.id) as building_count,
            GROUP_CONCAT(DISTINCT d.status) as device_statuses
        FROM users u
        LEFT JOIN devices d ON u.user_id = d.user_id
        LEFT JOIN buildings b ON u.user_id = b.user_id
        WHERE u.user_id = :user_id
        GROUP BY u.user_id
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('User not found');
    }
    
    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $user
    ]);
    
} catch (Exception $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

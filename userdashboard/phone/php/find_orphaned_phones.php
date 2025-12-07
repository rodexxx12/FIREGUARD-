<?php
/**
 * Find Orphaned Phone Numbers
 * This script finds phone numbers in user_phone_numbers that don't have a matching user
 */

session_start();
require_once '../db_connection.php';
require_once __DIR__ . '/security_functions.php';

header('Content-Type: application/json');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    $db = getDatabaseConnection();
    
    // FIXED QUERY: The users table uses 'id' as primary key, not 'user_id'
    // Original (broken): ON up.user_id = u.user_id WHERE u.user_id IS NULL
    // Fixed: ON up.user_id = u.id WHERE u.id IS NULL
    $sql = "SELECT up.* 
            FROM user_phone_numbers up 
            LEFT JOIN users u ON up.user_id = u.id 
            WHERE u.id IS NULL";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $orphanedPhones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get count
    $count = count($orphanedPhones);
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'orphaned_phones' => $orphanedPhones,
        'message' => $count > 0 
            ? "Found {$count} orphaned phone number(s)" 
            : "No orphaned phone numbers found"
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'query' => $sql ?? 'N/A'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>


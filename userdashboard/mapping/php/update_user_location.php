<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['lat']) || !isset($input['lng'])) {
    echo json_encode(['success' => false, 'message' => 'Missing latitude or longitude']);
    exit;
}

$user_id = $_SESSION['user_id'];
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);

try {
    // Use centralized database connection
    require_once __DIR__ . '/../db/db.php';
    $pdo = getMappingDBConnection();

    // Check if user location record exists
    $checkSql = "SELECT id FROM user_locations WHERE user_id = :user_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute(['user_id' => $user_id]);
    
    if ($checkStmt->fetch()) {
        // Update existing record
        $sql = "UPDATE user_locations SET latitude = :lat, longitude = :lng, updated_at = NOW() WHERE user_id = :user_id";
    } else {
        // Insert new record
        $sql = "INSERT INTO user_locations (user_id, latitude, longitude, created_at, updated_at) VALUES (:user_id, :lat, :lng, NOW(), NOW())";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user_id' => $user_id,
        'lat' => $lat,
        'lng' => $lng
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Location updated successfully'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database update failed', 
        'error' => $e->getMessage()
    ]);
}
?> 
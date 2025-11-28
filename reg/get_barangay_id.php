<?php
// Load environment variables from config.env or .env file if it exists
if (file_exists(__DIR__ . '/env_setup.php')) {
    require_once __DIR__ . '/env_setup.php';
}

// Respect debug mode from environment variable
$debugMode = filter_var(getenv('APP_DEBUG') ?? '0', FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debugMode ? '1' : '0');
ini_set('display_startup_errors', $debugMode ? '1' : '0');
error_reporting($debugMode ? E_ALL : E_ERROR);

// Set content type to JSON
header('Content-Type: application/json');

// Database connection
require_once 'db_config.php';

// Initialize response
$response = [
    'success' => false,
    'barangay_id' => null,
    'message' => ''
];

try {
    // Check if barangay_name is provided
    if (!isset($_POST['barangay_name']) || empty(trim($_POST['barangay_name']))) {
        $response['message'] = 'Barangay name is required';
        echo json_encode($response);
        exit;
    }
    
    $barangay_name = trim($_POST['barangay_name']);
    
    // Get database connection
    $conn = getDatabaseConnection();
    
    // Query to get barangay_id by barangay_name
    $stmt = $conn->prepare("SELECT id FROM barangay WHERE barangay_name = ?");
    $stmt->execute([$barangay_name]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $response['success'] = true;
        $response['barangay_id'] = $result['id'];
        $response['message'] = 'Barangay ID found';
    } else {
        $response['message'] = 'Barangay not found in database';
    }
    
} catch (Exception $e) {
    error_log('Error getting barangay_id: ' . $e->getMessage());
    $response['message'] = 'Database error occurred';
}

// Return JSON response
echo json_encode($response);
?>

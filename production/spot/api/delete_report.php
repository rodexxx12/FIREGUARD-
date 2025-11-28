<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';
require_once '../php/classes/CsrfProtection.php';
require_once '../php/classes/RateLimiter.php';
require_once '../php/classes/InputValidator.php';
require_once '../php/classes/ErrorHandler.php';
require_once '../php/classes/SecurityHeaders.php';

// Initialize error handler
$isProduction = (getenv('APP_ENV') === 'production');
ErrorHandler::init($isProduction);

// Set security headers
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
SecurityHeaders::setAll($isHttps);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Rate limiting
$limiter = new RateLimiter();
$rateLimitKey = 'api_delete_' . ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR']);
if (!$limiter->checkLimit($rateLimitKey, 10, 60)) {
    http_response_code(429);
    header('Retry-After: 60');
    echo json_encode([
        'success' => false,
        'message' => 'Too many requests. Please try again later.',
        'retry_after' => 60
    ]);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

// CSRF protection
if (!isset($input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'CSRF token required']);
    exit();
}

CsrfProtection::requireToken($input['csrf_token']);

// Validate and sanitize input
$report_id = InputValidator::validateInt($input['id'], 1);
if (!$report_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
    exit();
}

try {
    $conn = getDatabaseConnection();
    
    // Check if report exists
    $stmt = $conn->prepare("SELECT id FROM spot_investigation_reports WHERE id = ?");
    $stmt->bindParam(1, $report_id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit();
    }
    
    // Delete the report
    $stmt = $conn->prepare("DELETE FROM spot_investigation_reports WHERE id = ?");
    $stmt->bindParam(1, $report_id, PDO::PARAM_INT);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Report deleted successfully']);
    
} catch (Exception $e) {
    error_log("Error deleting spot report: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to delete report']);
}

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
if (!isset($_SESSION['admin_id'])) {
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
$rateLimitKey = 'api_update_status_' . ($_SESSION['admin_id'] ?? $_SERVER['REMOTE_ADDR']);
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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['report_id']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
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
    $reportId = InputValidator::validateInt($input['report_id'], 1);
    if (!$reportId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid report ID']);
        exit();
    }
    
    $newStatus = InputValidator::validateWhitelist($input['status'], ['draft', 'pending_review', 'final']);
    if (!$newStatus) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit();
    }
    
    $conn = getDatabaseConnection();
    
    // Check if report exists and get current status
    $stmt = $conn->prepare("SELECT id, ir_number, reports_status FROM spot_investigation_reports WHERE id = ?");
    $stmt->bindParam(1, $reportId, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit();
    }
    
    // Prevent status updates for final reports
    if ($report['reports_status'] === 'final') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot update status: Report has been finalized and cannot be modified']);
        exit();
    }
    
    // Update the report status
    $stmt = $conn->prepare("UPDATE spot_investigation_reports SET reports_status = ? WHERE id = ?");
    $stmt->bindParam(1, $newStatus, PDO::PARAM_STR);
    $stmt->bindParam(2, $reportId, PDO::PARAM_INT);
    $result = $stmt->execute();
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Report status updated successfully',
            'data' => [
                'report_id' => $reportId,
                'ir_number' => $report['ir_number'],
                'new_status' => $newStatus
            ]
        ]);
    } else {
        throw new Exception('Failed to update report status');
    }
    
} catch (Exception $e) {
    error_log("Error updating report status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update report status']);
}
?>

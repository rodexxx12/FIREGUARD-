<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';

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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

$report_id = $input['id'];

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

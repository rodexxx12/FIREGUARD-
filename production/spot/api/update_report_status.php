<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';

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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['report_id']) || !isset($input['status'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $reportId = (int)$input['report_id'];
    $newStatus = $input['status'];
    
    // Validate status
    $validStatuses = ['draft', 'pending_review', 'final'];
    if (!in_array($newStatus, $validStatuses)) {
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

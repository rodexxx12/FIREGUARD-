<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';
require_once '../php/datetime_helper.php';

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
    
    if (!$input || !isset($input['report_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit();
    }
    
    $reportId = (int)$input['report_id'];
    
    $conn = getDatabaseConnection();
    
    // Get the report details
    $stmt = $conn->prepare("
        SELECT sir.*, 
               fd.smoke, fd.temp, fd.heat, fd.flame_detected, fd.ml_confidence, fd.ml_prediction, fd.ai_prediction,
               b.building_name, b.address as building_address, b.building_type,
               u.username as user_name,
               br.barangay_name
        FROM spot_investigation_reports sir
        LEFT JOIN fire_data fd ON sir.fire_data_id = fd.id
        LEFT JOIN buildings b ON fd.building_id = b.id
        LEFT JOIN users u ON fd.user_id = u.user_id
        LEFT JOIN barangay br ON fd.barangay_id = br.id
        WHERE sir.id = ?
    ");
    $stmt->bindParam(1, $reportId, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit();
    }
    
    // Check if report status is final
    if ($report['reports_status'] !== 'final') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Report must be in final status to generate official report']);
        exit();
    }
    
    // Generate report data
    $reportData = [
        'report_id' => $report['id'],
        'ir_number' => $report['ir_number'],
        'report_for' => $report['report_for'],
        'subject' => $report['subject'],
        'date_completed' => $report['date_completed'],
        'date_occurrence' => $report['date_occurrence'],
        'time_occurrence' => $report['time_occurrence'],
        'place_occurrence' => $report['place_occurrence'],
        'establishment_name' => $report['establishment_name'],
        'owner' => $report['owner'],
        'occupant' => $report['occupant'],
        'fatalities' => (int)$report['fatalities'],
        'injured' => (int)$report['injured'],
        'estimated_damage' => (float)$report['estimated_damage'],
        'time_fire_started' => $report['time_fire_started'],
        'time_fire_out' => $report['time_fire_out'],
        'highest_alarm_level' => $report['highest_alarm_level'],
        'establishments_affected' => (int)$report['establishments_affected'],
        'estimated_area_sqm' => (float)$report['estimated_area_sqm'],
        'damage_computation' => (float)$report['damage_computation'],
        'location_of_fatalities' => $report['location_of_fatalities'],
        'weather_condition' => $report['weather_condition'],
        'other_info' => $report['other_info'],
        'investigation_details' => $report['investigation_details'] ?? '',
        'disposition' => $report['disposition'],
        'turned_over' => (bool)$report['turned_over'],
        'investigator_name' => $report['investigator_name'],
        'investigator_signature' => $report['investigator_signature'],
        'fire_data_id' => $report['fire_data_id'],
        'created_at' => $report['created_at'],
        'generated_at' => formatDateTimeForDatabase(),
        'generated_by' => $_SESSION['admin_name'] ?? 'System Administrator'
    ];
    
    // Add sensor data if available
    if ($report['fire_data_id']) {
        $reportData['sensor_data'] = [
            'smoke' => $report['smoke'],
            'temp' => $report['temp'],
            'heat' => $report['heat'],
            'flame_detected' => $report['flame_detected'],
            'ml_confidence' => $report['ml_confidence'],
            'ml_prediction' => $report['ml_prediction'],
            'ai_prediction' => $report['ai_prediction'],
            'building_name' => $report['building_name'],
            'building_address' => $report['building_address'],
            'building_type' => $report['building_type'],
            'user_name' => $report['user_name'],
            'barangay_name' => $report['barangay_name']
        ];
    }
    
    // Log the report generation (if table exists)
    try {
        $logStmt = $conn->prepare("
            INSERT INTO report_generation_logs (report_id, generated_by, generated_at, report_type) 
            VALUES (?, ?, ?, 'spot_investigation_report')
        ");
        $adminId = $_SESSION['admin_id'];
        $generatedAt = formatDateTimeForDatabase();
        $reportType = 'spot_investigation_report';
        $logStmt->bindParam(1, $reportId, PDO::PARAM_INT);
        $logStmt->bindParam(2, $adminId, PDO::PARAM_INT);
        $logStmt->bindParam(3, $generatedAt, PDO::PARAM_STR);
        $logStmt->bindParam(4, $reportType, PDO::PARAM_STR);
        $logStmt->execute();
    } catch (Exception $logError) {
        // Log table might not exist yet, continue without logging
        error_log("Report generation log failed: " . $logError->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Official report generated successfully',
        'data' => $reportData,
        'download_url' => 'generate_pdf.php?id=' . $reportId
    ]);
    
} catch (Exception $e) {
    error_log("Report generation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to generate report: ' . $e->getMessage()
    ]);
}
?>

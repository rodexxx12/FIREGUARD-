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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Get report ID from URL
$report_id = $_GET['id'] ?? null;

if (!$report_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit();
}

try {
    $conn = getDatabaseConnection();
    
    // Fetch report details
    $stmt = $conn->prepare("SELECT * FROM spot_investigation_reports WHERE id = ?");
    $stmt->bindParam(1, $report_id, PDO::PARAM_INT);
    $stmt->execute();
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Report not found']);
        exit();
    }
    
    // Format the data for API response
    $formatted_report = [
        'id' => $report['id'],
        'report_for' => $report['report_for'],
        'subject' => $report['subject'],
        'date_completed' => $report['date_completed'],
        'date_occurrence' => $report['date_occurrence'],
        'time_occurrence' => $report['time_occurrence'],
        'place_occurrence' => $report['place_occurrence'],
        'involved' => $report['involved'],
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
        'disposition' => $report['disposition'],
        'turned_over' => (bool)$report['turned_over'],
        'investigator_name' => $report['investigator_name'],
        'investigator_signature' => $report['investigator_signature'],
        'created_at' => $report['created_at']
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_report
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching spot report: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch report']);
}

<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';
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

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Rate limiting
$limiter = new RateLimiter();
$rateLimitKey = 'api_get_all_' . ($_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR']);
if (!$limiter->checkLimit($rateLimitKey, 20, 60)) {
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
    $conn = getDatabaseConnection();
    
    // Get all spot investigation reports
    $stmt = $conn->prepare("SELECT * FROM spot_investigation_reports ORDER BY created_at DESC");
    $stmt->execute();
    $reports = $stmt->fetchAll();
    
    // Format the data for API response
    $formatted_reports = array_map(function($report) {
        return [
            'id' => $report['id'],
            'ir_number' => $report['ir_number'],
            'reports_status' => $report['reports_status'],
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
            'disposition' => $report['disposition'],
            'turned_over' => (bool)$report['turned_over'],
            'investigator_name' => $report['investigator_name'],
            'investigator_signature' => $report['investigator_signature'],
            'fire_data_id' => $report['fire_data_id'],
            'created_at' => $report['created_at']
        ];
    }, $reports);
    
    echo json_encode([
        'success' => true,
        'data' => $formatted_reports,
        'count' => count($formatted_reports)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching spot reports: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch reports']);
}

<?php
// Identify as API so included files don't redirect
if (!defined('INCIDENT_REPORTS_ALLOW_API')) {
    define('INCIDENT_REPORTS_ALLOW_API', true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../functions/functions.php';

// Keep error reporting but avoid echoing HTML into JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Set CORS headers for AJAX requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'trends':
            $period = $_GET['period'] ?? 'monthly';
            $start = $_GET['start'] ?? '';
            $end = $_GET['end'] ?? '';
            switch ($period) {
                case 'daily':
                    $data = !empty($start) && !empty($end) ? getIncidentsByDayRange($start, $end) : getIncidentsByDay();
                    break;
                case 'yearly':
                    $data = getIncidentsByYear();
                    break;
                case 'monthly':
                default:
                    $data = !empty($start) && !empty($end) ? getIncidentsByMonthRange($start, $end) : getIncidentsByMonth();
                    break;
            }
            error_log("Trends data response (period=$period, start=$start, end=$end): " . json_encode($data));
            echo json_encode($data);
            break;
        case 'monthly_data':
            // Backward compatibility for older JS
            $data = getIncidentsByMonth();
            error_log("Monthly (compat) data response: " . json_encode($data));
            echo json_encode($data);
            break;
            
        case 'building_type_data':
            $start = $_GET['start'] ?? '';
            $end = $_GET['end'] ?? '';
            $incidentType = $_GET['incident_type'] ?? '';
            $data = getIncidentsByBuildingType('', $start, $end, $incidentType);
            error_log("Building type data response (start=" . $start . ", end=" . $end . ", incident_type=" . $incidentType . "): " . json_encode($data));
            echo json_encode($data);
            break;
            
        case 'severity_data':
            $data = getIncidentsBySeverity();
            error_log("Severity data response: " . json_encode($data));
            echo json_encode($data);
            break;
            
        case 'real_time_stats':
            $data = getRealTimeIncidentStats();
            error_log("Real-time stats response: " . json_encode($data));
            echo json_encode($data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action: ' . $action]);
            break;
    }
} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 
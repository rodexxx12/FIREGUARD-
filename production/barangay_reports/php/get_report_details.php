<?php
session_start();
require_once '../../../db/db.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid report ID'
    ]);
    exit;
}

$reportId = (int)$_GET['id'];

try {
    $conn = getDatabaseConnection();
    
    // Fetch report details with related data
    $query = "
        SELECT 
            sir.*,
            fd.geo_lat as latitude,
            fd.geo_long as longitude,
            fd.temp as temperature,
            fd.smoke as smoke_level,
            fd.heat as heat_level,
            fd.flame_detected,
            fd.ml_confidence,
            fd.ml_prediction,
            fd.ml_fire_probability,
            fd.ai_prediction,
            fd.status as fire_status,
            fd.timestamp as fire_timestamp
        FROM spot_investigation_reports sir
        LEFT JOIN fire_data fd ON sir.fire_data_id = fd.id
        WHERE sir.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        echo json_encode([
            'success' => false,
            'message' => 'Report not found'
        ]);
        exit;
    }
    
    // Convert numeric values to proper types
    $report['fatalities'] = (int)$report['fatalities'];
    $report['injured'] = (int)$report['injured'];
    $report['establishments_affected'] = (int)$report['establishments_affected'];
    $report['estimated_damage'] = (float)$report['estimated_damage'];
    $report['estimated_area_sqm'] = (float)$report['estimated_area_sqm'];
    $report['damage_computation'] = (float)$report['damage_computation'];
    $report['turned_over'] = (bool)$report['turned_over'];
    
    // Format datetime fields
    if ($report['time_fire_started']) {
        $report['time_fire_started'] = date('Y-m-d H:i:s', strtotime($report['time_fire_started']));
    }
    if ($report['time_fire_out']) {
        $report['time_fire_out'] = date('Y-m-d H:i:s', strtotime($report['time_fire_out']));
    }
    
    echo json_encode([
        'success' => true,
        'report' => $report
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

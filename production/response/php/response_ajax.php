<?php
require_once '../../db/db.php';

header('Content-Type: application/json');

// Get database connection
$conn = getDatabaseConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_response_details':
            $responseId = $_GET['response_id'] ?? 0;
            
            $query = "
                SELECT 
                    r.*,
                    f.name as firefighter_name,
                    f.badge_number,
                    f.rank,
                    f.specialization,
                    f.phone as firefighter_phone,
                    bd.building_name,
                    bd.building_type,
                    bd.address,
                    bd.contact_person,
                    bd.contact_number,
                    bd.total_floors,
                    bd.has_sprinkler_system,
                    bd.has_fire_alarm,
                    bd.has_fire_extinguishers,
                    bd.has_emergency_exits,
                    bd.has_emergency_lighting,
                    bd.has_fire_escape,
                    bd.last_inspected,
                    bd.latitude as building_lat,
                    bd.longitude as building_long,
                    bd.construction_year,
                    bd.building_area,
                    br.barangay_name,
                    fd.status as fire_status,
                    fd.smoke,
                    fd.temp,
                    fd.heat,
                    fd.flame_detected,
                    fd.timestamp as fire_timestamp,
                    fd.geo_lat,
                    fd.geo_long,
                    fd.ml_confidence,
                    fd.ml_prediction,
                    fd.ml_fire_probability,
                    fd.ai_prediction,
                    fd.ml_timestamp,
                    fd.acknowledged_at_time
                FROM responses r
                LEFT JOIN firefighters f ON r.firefighter_id = f.id
                LEFT JOIN buildings bd ON r.building_id = bd.id
                LEFT JOIN barangay br ON bd.barangay_id = br.id
                LEFT JOIN fire_data fd ON r.fire_data_id = fd.id
                WHERE r.id = ?
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$responseId]);
            $response = $stmt->fetch();
            
            if ($response) {
                echo json_encode(['success' => true, 'data' => $response]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Response not found']);
            }
            break;
            
        case 'update_response':
            $responseId = $_POST['response_id'] ?? 0;
            $responseType = $_POST['response_type'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $firefighterId = $_POST['firefighter_id'] ?? null;
            
            $query = "UPDATE responses SET response_type = ?, notes = ?, firefighter_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([$responseType, $notes, $firefighterId, $responseId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Response updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update response']);
            }
            break;
            
        case 'delete_response':
            $responseId = $_POST['response_id'] ?? 0;
            
            $query = "DELETE FROM responses WHERE id = ?";
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([$responseId]);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Response deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete response']);
            }
            break;
            
        case 'get_firefighters':
            $query = "SELECT id, name, badge_number, rank FROM firefighters ORDER BY name";
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $firefighters = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $firefighters]);
            break;
            
        case 'get_response_stats':
            $query = "
                SELECT 
                    COUNT(*) as total_responses,
                    COUNT(CASE WHEN response_type = 'Emergency' THEN 1 END) as emergency_responses,
                    COUNT(CASE WHEN response_type = 'Routine' THEN 1 END) as routine_responses,
                    COUNT(CASE WHEN response_type = 'False Alarm' THEN 1 END) as false_alarms,
                    COUNT(CASE WHEN response_type = 'Training' THEN 1 END) as training_responses,
                    COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as today_responses,
                    COUNT(CASE WHEN DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_responses,
                    COUNT(CASE WHEN DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_responses
                FROM responses
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>

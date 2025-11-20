<?php
require_once '../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'get_building_details':
            $building_id = $_POST['building_id'] ?? 0;
            echo json_encode(getBuildingDetails($conn, $building_id));
            break;
            
        case 'update_alarm_status':
            $building_id = $_POST['building_id'] ?? 0;
            $status = $_POST['status'] ?? '';
            echo json_encode(updateAlarmStatus($conn, $building_id, $status));
            break;
            
        case 'get_alarm_history':
            $building_id = $_POST['building_id'] ?? 0;
            echo json_encode(getAlarmHistory($conn, $building_id));
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

function getBuildingDetails($conn, $building_id) {
    try {
        $query = "
            SELECT 
                b.*,
                u.fullname as owner_name,
                u.email_address as owner_email,
                u.contact_number as owner_contact,
                br.barangay_name,
                d.device_name,
                d.device_number,
                d.status as device_status,
                d.last_activity
            FROM buildings b
            LEFT JOIN users u ON b.user_id = u.user_id
            LEFT JOIN barangay br ON b.barangay_id = br.id
            LEFT JOIN devices d ON b.id = d.building_id
            WHERE b.id = ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$building_id]);
        $building = $stmt->fetch();
        
        if ($building) {
            return ['success' => true, 'data' => $building];
        } else {
            return ['success' => false, 'message' => 'Building not found'];
        }
    } catch (PDOException $e) {
        error_log("Error fetching building details: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

function updateAlarmStatus($conn, $building_id, $status) {
    try {
        // OPTIMIZED Update query to prevent potential loops - FIXED LOOP ISSUE
        $query = "
            UPDATE fire_data fd
            JOIN devices d ON fd.device_id = d.device_id
            SET fd.status = ?, fd.acknowledged_at_time = NOW()
            WHERE d.building_id = ? 
            AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED')
            AND fd.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND fd.id = (
                SELECT MAX(fd2.id) 
                FROM fire_data fd2 
                WHERE fd2.device_id = d.device_id
                AND fd2.timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                LIMIT 1
            )
        ";
        
        $stmt = $conn->prepare($query);
        $result = $stmt->execute([$status, $building_id]);
        
        if ($result && $stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'Alarm status updated successfully'];
        } else {
            return ['success' => false, 'message' => 'No matching alarm found to update'];
        }
    } catch (PDOException $e) {
        error_log("Error updating alarm status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

function getAlarmHistory($conn, $building_id) {
    try {
        // OPTIMIZED Query to prevent potential loops - FIXED LOOP ISSUE
        $query = "
            SELECT 
                fd.*,
                d.device_name,
                d.device_number
            FROM fire_data fd
            JOIN devices d ON fd.device_id = d.device_id
            WHERE d.building_id = ?
            AND fd.status IN ('EMERGENCY', 'ACKNOWLEDGED')
            AND fd.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY fd.timestamp DESC
            LIMIT 50
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([$building_id]);
        $history = $stmt->fetchAll();
        
        return ['success' => true, 'data' => $history];
    } catch (PDOException $e) {
        error_log("Error fetching alarm history: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}
?>

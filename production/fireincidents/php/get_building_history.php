<?php
// Include centralized database connection
require_once '../../db/db.php';

// Start session and enforce admin authentication
if (session_status() == PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        error_log('fireincidents/get_building_history.php: headers already sent, skipping session_start');
    }
}

if (!isset($_SESSION['admin_id'])) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: admin session required',
        'code' => 'NO_ADMIN_SESSION'
    ]);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['building_id']) || empty($input['building_id'])) {
    echo json_encode(['success' => false, 'message' => 'Building ID is required']);
    exit;
}

$buildingId = (int)$input['building_id'];

try {
    $conn = getDatabaseConnection();
    
    // Fetch comprehensive building information
    $buildingQuery = "
        SELECT 
            b.*,
            br.barangay_name,
            u.fullname as owner_name,
            u.email_address as owner_email,
            u.contact_number as owner_contact,
            u.status as owner_status,
            u.registration_date as owner_registration_date
        FROM buildings b
        LEFT JOIN barangay br ON b.barangay_id = br.id
        LEFT JOIN users u ON b.user_id = u.user_id
        WHERE b.id = :building_id
    ";
    
    $stmt = $conn->prepare($buildingQuery);
    $stmt->execute([':building_id' => $buildingId]);
    $building = $stmt->fetch();
    
    if (!$building) {
        echo json_encode(['success' => false, 'message' => 'Building not found']);
        exit;
    }
    
    // Fetch fire incident history with optional date filtering
    $startDate = isset($input['start_date']) ? $input['start_date'] : null;
    $endDate = isset($input['end_date']) ? $input['end_date'] : null;
    
    $incidentsQuery = "
        SELECT 
            fd.*,
            br.barangay_name,
            d.device_name,
            d.status as device_status
        FROM fire_data fd
        LEFT JOIN barangay br ON fd.barangay_id = br.id
        LEFT JOIN devices d ON fd.device_id = d.device_id
        WHERE fd.building_id = :building_id
    ";
    
    $params = [':building_id' => $buildingId];
    
    // Add date filtering if provided
    if ($startDate && $endDate) {
        $incidentsQuery .= " AND DATE(fd.timestamp) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    } elseif ($startDate) {
        $incidentsQuery .= " AND DATE(fd.timestamp) >= :start_date";
        $params[':start_date'] = $startDate;
    } elseif ($endDate) {
        $incidentsQuery .= " AND DATE(fd.timestamp) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    $incidentsQuery .= " ORDER BY fd.timestamp DESC LIMIT 50";
    
    $stmt = $conn->prepare($incidentsQuery);
    $stmt->execute($params);
    $incidents = $stmt->fetchAll();
    
    // Fetch devices associated with this building
    $devicesQuery = "
        SELECT 
            d.*,
            u.fullname as owner_name
        FROM devices d
        LEFT JOIN users u ON d.user_id = u.user_id
        WHERE d.building_id = :building_id
        ORDER BY d.created_at DESC
    ";
    
    $stmt = $conn->prepare($devicesQuery);
    $stmt->execute([':building_id' => $buildingId]);
    $devices = $stmt->fetchAll();
    
    // Fetch recent fire data statistics
    $statsQuery = "
        SELECT 
            COUNT(*) as total_incidents,
            COUNT(CASE WHEN status = 'EMERGENCY' THEN 1 END) as emergency_count,
            COUNT(CASE WHEN status = 'ACKNOWLEDGED' THEN 1 END) as acknowledged_count,
            COUNT(CASE WHEN status = 'NORMAL' THEN 1 END) as normal_count,
            AVG(temp) as avg_temperature,
            AVG(smoke) as avg_smoke,
            AVG(heat) as avg_heat,
            MAX(timestamp) as last_incident_time,
            MIN(timestamp) as first_incident_time
        FROM fire_data 
        WHERE building_id = :building_id
    ";
    
    $stmt = $conn->prepare($statsQuery);
    $stmt->execute([':building_id' => $buildingId]);
    $stats = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'building' => $building,
        'incidents' => $incidents,
        'devices' => $devices,
        'stats' => $stats,
        'incident_count' => count($incidents),
        'device_count' => count($devices)
    ]);
    
} catch(PDOException $e) {
    error_log("Error fetching building data: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch building data'
    ]);
}
?>

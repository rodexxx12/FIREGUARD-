<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../functions/database_connection.php';

try {
    $conn = getDatabaseConnection();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Accept JSON or form-encoded
$input = file_get_contents('php://input');
$data = !empty($input) ? json_decode($input, true) : $_POST;

$buildingId = isset($data['building_id']) ? (int)$data['building_id'] : 0;
$responseType = isset($data['response_type']) && $data['response_type'] !== '' ? trim($data['response_type']) : 'Respond';
$notes = isset($data['notes']) ? trim($data['notes']) : null;

// Get firefighter_id from session
session_start();
$firefighterId = isset($_SESSION['firefighter_id']) ? (int)$_SESSION['firefighter_id'] : null;

if ($buildingId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing building_id']);
    exit;
}

try {
    // Find latest fire_data for this building with EMERGENCY or ACKNOWLEDGED
    // For ACKNOWLEDGED status, order by acknowledged_at_time (combined with date from timestamp)
    $stmt = $conn->prepare(
        "SELECT 
            id AS fire_data_id, 
            status, 
            timestamp,
            acknowledged_at_time
         FROM fire_data
         WHERE building_id = :building_id
           AND UPPER(status) IN ('EMERGENCY','ACKNOWLEDGED')
         ORDER BY 
            -- Prioritize EMERGENCY over ACKNOWLEDGED
            CASE WHEN UPPER(status) = 'EMERGENCY' THEN 0 ELSE 1 END,
            -- For ACKNOWLEDGED status, order by date + acknowledged_at_time
            CASE 
                WHEN UPPER(status) = 'ACKNOWLEDGED' AND acknowledged_at_time IS NOT NULL
                THEN CONCAT(DATE(timestamp), ' ', acknowledged_at_time)
                ELSE timestamp
            END DESC
         LIMIT 1"
    );
    $stmt->execute([':building_id' => $buildingId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'No EMERGENCY or ACKNOWLEDGED record found for this building']);
        exit;
    }

    $fireDataId = (int)$row['fire_data_id'];

    // Insert response
    $insert = $conn->prepare(
        "INSERT INTO responses (fire_data_id, response_type, notes, responded_by, firefighter_id, building_id)
         VALUES (:fire_data_id, :response_type, :notes, :responded_by, :firefighter_id, :building_id)"
    );

    // Determine responded_by if there is a session username; fallback to System
    $respondedBy = isset($_SESSION['username']) && $_SESSION['username'] !== '' ? $_SESSION['username'] : 'System';

    $insert->execute([
        ':fire_data_id' => $fireDataId,
        ':response_type' => $responseType,
        ':notes' => $notes,
        ':responded_by' => $respondedBy,
        ':firefighter_id' => $firefighterId,
        ':building_id' => $buildingId,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Response recorded',
        'response_id' => $conn->lastInsertId(),
        'fire_data_id' => $fireDataId,
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error', 'details' => $e->getMessage()]);
    exit;
}
?>



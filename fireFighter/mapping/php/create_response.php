<?php
header('Content-Type: application/json');

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
$fireDataIdInput = isset($data['fire_data_id']) ? (int)$data['fire_data_id'] : 0;
$responseType = isset($data['response_type']) && $data['response_type'] !== '' ? trim($data['response_type']) : 'Respond';
$notes = isset($data['notes']) ? trim($data['notes']) : null;

// Get firefighter_id from session
session_start();
$firefighterId = isset($_SESSION['firefighter_id']) ? (int)$_SESSION['firefighter_id'] : null;

if (!$firefighterId) {
    echo json_encode(['success' => false, 'message' => 'Firefighter session not found. Please sign in again.']);
    exit;
}

if ($buildingId <= 0 && $fireDataIdInput <= 0) {
    echo json_encode(['success' => false, 'message' => 'Missing building_id or fire_data_id']);
    exit;
}

try {
    if ($fireDataIdInput > 0) {
        // Validate provided fire_data_id and fetch associated building if any
        $stmt = $conn->prepare(
            "SELECT id AS fire_data_id, building_id
             FROM fire_data
             WHERE id = :fire_data_id
               AND UPPER(status) IN ('EMERGENCY','ACKNOWLEDGED')
             LIMIT 1"
        );
        $stmt->execute([':fire_data_id' => $fireDataIdInput]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'fire_data record not found or not actionable']);
            exit;
        }

        $fireDataId = (int)$row['fire_data_id'];
        if ($buildingId <= 0 && !empty($row['building_id'])) {
            $buildingId = (int)$row['building_id'];
        }
    } else {
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
    }

    // Enforce one response per firefighter per fire_data to avoid duplicate entries
    $duplicateCheck = $conn->prepare(
        "SELECT id FROM responses WHERE fire_data_id = :fire_data_id AND firefighter_id = :firefighter_id LIMIT 1"
    );
    $duplicateCheck->execute([
        ':fire_data_id' => $fireDataId,
        ':firefighter_id' => $firefighterId,
    ]);

    if ($duplicateCheck->fetch(PDO::FETCH_ASSOC)) {
        echo json_encode([
            'success' => false,
            'message' => 'You have already submitted a response for this incident.',
            'fire_data_id' => $fireDataId,
        ]);
        exit;
    }

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
        ':building_id' => $buildingId > 0 ? $buildingId : null,
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



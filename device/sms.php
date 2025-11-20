<?php
// Bulk SMS sender to all users' phone numbers with logging to phone_logs

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

header('Content-Type: application/json');

require_once __DIR__ . '/../db/db.php';

// Load SMS provider config
$smsConfig = require __DIR__ . '/config.php';
$apiKey    = $smsConfig['api_key'] ?? '';
$deviceId  = $smsConfig['device'] ?? '';
$apiUrl    = $smsConfig['url'] ?? '';

$message = isset($_POST['message']) ? trim((string)$_POST['message']) : '';
$isEmergencyMode = isset($_POST['emergency']) && (string)$_POST['emergency'] === '1';
$isAutoEmergencyMode = isset($_POST['auto_emergency']) && (string)$_POST['auto_emergency'] === '1';
// Optional filters for emergency mode
$filterFireDataId = isset($_POST['fire_data_id']) ? (int)$_POST['fire_data_id'] : null;
$filterDeviceId   = isset($_POST['device_id']) ? (int)$_POST['device_id'] : null;
$filterBuildingId = isset($_POST['building_id']) ? (int)$_POST['building_id'] : null;
$filterUserId     = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
$markNotified     = isset($_POST['mark_notified']) && (string)$_POST['mark_notified'] === '1';

if ($message === '' && !$isAutoEmergencyMode) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

try {
    $pdo = getDatabaseConnection();

    // Build recipients based on mode
    if ($isAutoEmergencyMode) {
        // Auto-detect latest EMERGENCY status and send to that user
        $conditions = ["fd.status = 'EMERGENCY'", "(fd.notified = 0 OR fd.notified IS NULL)"];
        $params = [];
        
        if (!empty($filterUserId)) { 
            $conditions[] = 'fd.user_id = :user_id'; 
            $params[':user_id'] = $filterUserId; 
        }
        if (!empty($filterDeviceId)) { 
            $conditions[] = 'fd.device_id = :device_id'; 
            $params[':device_id'] = $filterDeviceId; 
        }
        
        $where = 'WHERE ' . implode(' AND ', $conditions);
        
        // Get the latest EMERGENCY record
        $sql = "
            SELECT fd.id, fd.user_id, fd.device_id, fd.building_id, fd.timestamp, 
                   b.building_name, b.address, u.fullname
            FROM fire_data fd
            LEFT JOIN buildings b ON b.id = fd.building_id
            LEFT JOIN users u ON u.user_id = fd.user_id
            $where
            ORDER BY fd.id DESC
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $emergencyRecord = $stmt->fetch();
        
        if (!$emergencyRecord) {
            echo json_encode(['success' => false, 'message' => 'No unprocessed EMERGENCY records found']);
            exit;
        }
        
        // Generate emergency message
        $buildingInfo = $emergencyRecord['building_name'] ? 
            " at {$emergencyRecord['building_name']}" : 
            ($emergencyRecord['address'] ? " at {$emergencyRecord['address']}" : "");
        
        $message = "🚨 FIRE EMERGENCY DETECTED{$buildingInfo}! " .
                  "Please evacuate immediately and call 911. " .
                  "Time: {$emergencyRecord['timestamp']}";
        
        // Get phone numbers for this user
        $phoneSql = "
            SELECT DISTINCT upn.user_id, upn.phone_number
            FROM user_phone_numbers upn
            INNER JOIN users u ON u.user_id = upn.user_id
            WHERE upn.user_id = :user_id
              AND (u.status = 'Active' OR u.status IS NULL)
              AND (upn.verified = 1 OR upn.verified IS NULL)
        ";
        $phoneStmt = $pdo->prepare($phoneSql);
        $phoneStmt->execute([':user_id' => $emergencyRecord['user_id']]);
        $recipients = $phoneStmt->fetchAll();
        
        // Set context for logging
        $filterUserId = (int)$emergencyRecord['user_id'];
        $filterDeviceId = (int)$emergencyRecord['device_id'];
        $filterBuildingId = $emergencyRecord['building_id'] ? (int)$emergencyRecord['building_id'] : null;
        $filterFireDataId = (int)$emergencyRecord['id'];
        
    } elseif ($isEmergencyMode) {
        $conditions = ["fd.status = 'EMERGENCY'"];
        $params = [];
        if (!empty($filterFireDataId)) { $conditions[] = 'fd.id = :fire_data_id'; $params[':fire_data_id'] = $filterFireDataId; }
        if (!empty($filterDeviceId))   { $conditions[] = 'fd.device_id = :device_id'; $params[':device_id'] = $filterDeviceId; }
        if (!empty($filterBuildingId)) { $conditions[] = 'fd.building_id = :building_id'; $params[':building_id'] = $filterBuildingId; }
        if (!empty($filterUserId))     { $conditions[] = 'fd.user_id = :user_id'; $params[':user_id'] = $filterUserId; }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';

        $sql = "
            SELECT DISTINCT upn.user_id, upn.phone_number
            FROM fire_data fd
            INNER JOIN users u ON u.user_id = fd.user_id
            INNER JOIN user_phone_numbers upn ON upn.user_id = u.user_id
            $where
              AND (u.status = 'Active' OR u.status IS NULL)
              AND (upn.verified = 1 OR upn.verified IS NULL)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recipients = $stmt->fetchAll();
    } elseif (!empty($filterUserId)) {
        // Target only the specified user's verified phone numbers
        $sql = "
            SELECT DISTINCT upn.user_id, upn.phone_number
            FROM user_phone_numbers upn
            INNER JOIN users u ON u.user_id = upn.user_id
            WHERE upn.user_id = :user_id
              AND (u.status = 'Active' OR u.status IS NULL)
              AND (upn.verified = 1 OR upn.verified IS NULL)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $filterUserId]);
        $recipients = $stmt->fetchAll();
    } else {
        // Fetch unique, active, verified phone numbers per user (broadcast)
        $sql = "
            SELECT DISTINCT upn.user_id, upn.phone_number
            FROM user_phone_numbers upn
            INNER JOIN users u ON u.user_id = upn.user_id
            WHERE (u.status = 'Active' OR u.status IS NULL)
              AND (upn.verified = 1 OR upn.verified IS NULL)
        ";
        $stmt = $pdo->query($sql);
        $recipients = $stmt->fetchAll();
    }

    if (!$recipients) {
        echo json_encode(['success' => false, 'message' => 'No recipients found']);
        exit;
    }

    // Prepare HTTP headers for SMS API
    $headers = [
        'apikey:' . $apiKey
    ];

    $results = [];
    $successCount = 0;
    $failureCount = 0;

    foreach ($recipients as $row) {
        $userId = (int)$row['user_id'];
        $phone  = (string)$row['phone_number'];

        if ($phone === '') {
            continue;
        }

        $payload = [
            'message'       => $message,
            'mobile_number' => $phone,
            'device'        => $deviceId,
        ];

        // Send SMS via cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Caution in production
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseBody = curl_exec($ch);
        $curlError    = curl_error($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $status = ($curlError === '' && $httpCode >= 200 && $httpCode < 300) ? 'sent' : 'failed';
        if ($status === 'sent') {
            $successCount++;
        } else {
            $failureCount++;
        }

        // Log to phone_logs (adapts to existing columns)
        try {
            logPhoneMessage($pdo, [
                'user_id'          => $userId,
                'phone_number'     => $phone,
                'message'          => $message,
                'status'           => $status,
                'provider'         => 'pagenet',
                'http_code'        => $httpCode,
                'error'            => $curlError,
                'api_response_raw' => $responseBody,
                // Context (if provided)
                'fire_data_id'     => $filterFireDataId,
                'device_id'        => $filterDeviceId,
                'building_id'      => $filterBuildingId,
            ]);
        } catch (Throwable $e) {
            // Do not fail the whole request due to logging
        }

        $results[] = [
            'user_id'      => $userId,
            'phone_number' => $phone,
            'status'       => $status,
            'http_code'    => $httpCode,
            'error'        => $curlError,
        ];
    }

    // Optionally mark fire_data rows as notified
    if (($isEmergencyMode || $isAutoEmergencyMode) && $markNotified) {
        $conditions = ["status = 'EMERGENCY'"];
        $params = [];
        if (!empty($filterFireDataId)) { $conditions[] = 'id = :fire_data_id'; $params[':fire_data_id'] = $filterFireDataId; }
        if (!empty($filterDeviceId))   { $conditions[] = 'device_id = :device_id'; $params[':device_id'] = $filterDeviceId; }
        if (!empty($filterBuildingId)) { $conditions[] = 'building_id = :building_id'; $params[':building_id'] = $filterBuildingId; }
        if (!empty($filterUserId))     { $conditions[] = 'user_id = :user_id'; $params[':user_id'] = $filterUserId; }
        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $pdo->prepare("UPDATE fire_data SET notified = 1 $where")->execute($params);
    }

    echo json_encode([
        'success'       => true,
        'message'       => 'SMS processing completed',
        'sent'          => $successCount,
        'failed'        => $failureCount,
        'total'         => $successCount + $failureCount,
        'results'       => $results,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error', 'error' => $e->getMessage()]);
}

/**
 * Insert a phone log row while adapting to the existing phone_logs schema.
 * It inserts only the columns that exist in the table from a known set.
 */
function logPhoneMessage(PDO $pdo, array $data): void
{
    // Discover available columns in phone_logs
    $columnsStmt = $pdo->query("SHOW COLUMNS FROM phone_logs");
    $available = array_map(static function ($row) {
        return $row['Field'];
    }, $columnsStmt->fetchAll());

    $candidateData = [
        'user_id'          => $data['user_id'] ?? null,
        'phone_number'     => $data['phone_number'] ?? null,
        'message'          => $data['message'] ?? null,
        'status'           => $data['status'] ?? null,
        'provider'         => $data['provider'] ?? null,
        'http_code'        => $data['http_code'] ?? null,
        'error'            => $data['error'] ?? null,
        'api_response_raw' => $data['api_response_raw'] ?? null,
        'fire_data_id'     => $data['fire_data_id'] ?? null,
        'device_id'        => $data['device_id'] ?? null,
        'building_id'      => $data['building_id'] ?? null,
        'created_at'       => date('Y-m-d H:i:s'),
    ];

    $insertCols = [];
    $placeHolders = [];
    $params = [];

    foreach ($candidateData as $col => $val) {
        if (in_array($col, $available, true)) {
            $insertCols[]   = $col;
            $placeHolders[] = ':' . $col;
            $params[':' . $col] = $val;
        }
    }

    if (!$insertCols) {
        return; // Nothing to insert
    }

    $sql = 'INSERT INTO phone_logs (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $placeHolders) . ')';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
}
?>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering to prevent any accidental output
ob_start();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if this is a POST request - handle it early to prevent HTML output
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if ($isPostRequest) {
        // For POST requests, return JSON error
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'User not logged in. Please login first.'
        ]);
        exit;
    } else {
        // For GET requests, show HTML error
        ob_end_clean();
        die('<div class="alert alert-danger">User not logged in. Please <a href="../../login/">login</a> first.</div>');
    }
}

$userId = $_SESSION['user_id'];

// Set PHP timezone to Philippines (Asia/Manila)
date_default_timezone_set('Asia/Manila');

// Try multiple possible paths for database connection
$dbPaths = [
    __DIR__ . '/../../../db/db.php',  // From production/alarm/
    __DIR__ . '/../../db/db.php',     // From production/
    __DIR__ . '/../db/db.php',        // From alarm/
    dirname(__DIR__, 3) . '/db/db.php', // Using dirname with levels
    dirname(__DIR__, 2) . '/db/db.php',
    dirname(__DIR__, 1) . '/db/db.php'
];

$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once($dbPath);
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    if ($isPostRequest) {
        // For POST requests, return JSON error
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Could not find database connection file. Tried paths: ' . implode(', ', $dbPaths)
        ]);
        exit;
    } else {
        // For GET requests, show text error
        ob_end_clean();
        die("Could not find database connection file. Tried paths: " . implode(', ', $dbPaths));
    }
}

// SMS API configuration
$smsConfig = [
    'apiKey' => '6PLX3NFL2A2FLQ81RI7X6C4PJP68ANLJNYQ7XAR6',
    'device' => 'd8d8e6131b00f1a4',
    'url' => 'https://sms.pagenet.info/api/v1/sms/send'
];

// Get database connection
    $pdo = getDatabaseConnection();
    
// Ensure MySQL session uses PH timezone as well
try {
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Exception $e) {
    error_log("Failed to set MySQL session time zone: " . $e->getMessage());
}

// Function to send SMS alerts
function send_sms_alerts($recipients, $message) {
    global $smsConfig;

    if (empty($recipients)) {
        error_log("SMS Alert: No recipients provided");
        return false;
    }

    $successCount = 0;
    $totalRecipients = count($recipients);

    foreach ($recipients as $recipient) {
        // Clean and validate phone number
        $cleanPhone = cleanPhoneNumber($recipient);
        if (!$cleanPhone) {
            error_log("SMS Alert: Invalid phone number format: $recipient");
            continue;
        }

        $params = [
            'message'       => $message,
            'mobile_number' => $cleanPhone,
            'device'        => $smsConfig['device']
        ];

        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "apikey: {$smsConfig['apiKey']}"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $smsConfig['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_error($ch)) {
            error_log("SMS Alert cURL Error for $cleanPhone: " . curl_error($ch));
        } else {
            // Log the SMS attempt
            $smsLogId = log_sms_to_firefighter($cleanPhone, $message, $response, $httpCode);
            
            if ($httpCode == 200) {
                error_log("SMS Alert: Successfully sent to $cleanPhone. Response: $response");
                $successCount++;
            } else {
                error_log("SMS Alert: Failed to send to $cleanPhone. HTTP Code: $httpCode, Response: $response");
            }
        }

        curl_close($ch);
        
        // Small delay between SMS sends to avoid rate limiting
        usleep(100000); // 0.1 second delay
    }

    error_log("SMS Alert: Sent $successCount out of $totalRecipients messages successfully");
    return $successCount > 0;
}

// Function to clean and validate phone number
function cleanPhoneNumber($phone) {
    // Remove all non-digit characters
    $clean = preg_replace('/[^0-9]/', '', $phone);
    
    // Handle Philippine phone numbers
    if (strlen($clean) == 11 && substr($clean, 0, 2) == '09') {
        // Convert 09XXXXXXXXX to 9XXXXXXXXX
        return substr($clean, 1);
    } elseif (strlen($clean) == 10 && substr($clean, 0, 1) == '9') {
        // Already in correct format
        return $clean;
    } elseif (strlen($clean) == 12 && substr($clean, 0, 3) == '639') {
        // Convert 639XXXXXXXXX to 9XXXXXXXXX
        return substr($clean, 2);
    }
    
    // If it's already a valid 10-digit number starting with 9
    if (strlen($clean) == 10 && substr($clean, 0, 1) == '9') {
        return $clean;
    }
    
    return false; // Invalid format
}

// Function to log SMS to firefighter_phone_log with enhanced logging
function log_sms_to_firefighter($phone, $message, $apiResponse = null, $httpCode = null) {
    global $pdo;
    
    try {
        // Get firefighter details by phone number
    $stmt = $pdo->prepare("
            SELECT f.id, f.name, f.availability
            FROM firefighters f 
            WHERE f.phone = ? OR f.phone = ? OR f.phone = ?
        ");
        
        // Try different phone number formats
        $phoneVariations = [
            $phone,
            '0' . $phone, // Add leading zero
            '63' . $phone // Add country code
        ];
        
        $stmt->execute($phoneVariations);
        $firefighter = $stmt->fetch();
        
        if ($firefighter) {
            // Insert into phone log with additional details
            $stmt = $pdo->prepare("
                INSERT INTO firefighter_phone_log 
                (firefighter_id, name, phone, logged_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $firefighter['id'],
                $firefighter['name'],
                $phone
            ]);
            
            $logId = $pdo->lastInsertId();
            
            // Log additional SMS details to error log for debugging
            $availability = $firefighter['availability'] ? 'Available' : 'Unavailable';
            error_log("SMS Log: ID=$logId, Firefighter={$firefighter['name']} (ID: {$firefighter['id']}, Availability: $availability), Phone=$phone, HTTP=$httpCode");
            
            return $logId;
        } else {
            error_log("SMS Log: No firefighter found for phone number: $phone");
            return null;
        }
    } catch (PDOException $e) {
        error_log("SMS Log Error: " . $e->getMessage());
        return null;
    }
}

// Function to get active firefighters' phone numbers with enhanced query
function get_active_firefighters_phones() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT phone, name, id
            FROM firefighters 
            WHERE availability = 1 
            AND phone IS NOT NULL 
            AND phone != ''
            AND LENGTH(TRIM(phone)) >= 10
        ");
        $stmt->execute();
        $firefighters = $stmt->fetchAll();
        
        $validPhones = [];
        foreach ($firefighters as $firefighter) {
            $cleanPhone = cleanPhoneNumber($firefighter['phone']);
            if ($cleanPhone) {
                $validPhones[] = $cleanPhone;
                error_log("SMS Recipient: {$firefighter['name']} (ID: {$firefighter['id']}) - $cleanPhone");
            } else {
                error_log("SMS Recipient: Invalid phone format for {$firefighter['name']} - {$firefighter['phone']}");
            }
        }
        
        error_log("SMS Recipients: Found " . count($validPhones) . " valid phone numbers out of " . count($firefighters) . " firefighters");
        return $validPhones;
        
    } catch (PDOException $e) {
        error_log("Error fetching firefighters: " . $e->getMessage());
        return [];
    }
}

// Handle API requests
if ($isPostRequest) {
    // Clear any output buffer before sending JSON
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        // Handle acknowledgment request
        if (isset($_POST['acknowledge'])) {
            $fireDataId = filter_input(INPUT_POST, 'fire_data_id', FILTER_VALIDATE_INT);
            if (!$fireDataId || $fireDataId <= 0) {
                throw new Exception("Invalid fire_data_id");
            }

            $pdo->beginTransaction();

            try {
                // Verify record exists and get current status
            $stmt = $pdo->prepare("SELECT id, building_type, status FROM fire_data WHERE id = ? FOR UPDATE");
            $stmt->execute([$fireDataId]);
            $fireData = $stmt->fetch();
            
            if (!$fireData) {
                throw new Exception("No fire_data record found with ID: $fireDataId");
            }

                error_log("Acknowledgment: Updating fire_data ID $fireDataId from status '{$fireData['status']}' to 'ACKNOWLEDGED'");

                // Update status to ACKNOWLEDGED and set acknowledged time
                $stmt = $pdo->prepare("UPDATE fire_data SET status = 'ACKNOWLEDGED', acknowledged_at_time = CURTIME() WHERE id = ?");
                $result = $stmt->execute([$fireDataId]);
                
                if (!$result) {
                    throw new Exception("Failed to update fire_data status");
                }
                
                $rowsAffected = $stmt->rowCount();
                error_log("Acknowledgment: Updated $rowsAffected row(s) for fire_data ID $fireDataId");
                
                // Verify the update was successful
                $stmt = $pdo->prepare("SELECT id, status FROM fire_data WHERE id = ?");
            $stmt->execute([$fireDataId]);
                $updatedRecord = $stmt->fetch();
                
                if ($updatedRecord && $updatedRecord['status'] === 'ACKNOWLEDGED') {
                    error_log("Acknowledgment: Successfully verified status update to 'ACKNOWLEDGED' for fire_data ID $fireDataId");
                } else {
                    throw new Exception("Status update verification failed");
                }
            
            $pdo->commit();
                error_log("Acknowledgment: Transaction committed successfully for fire_data ID $fireDataId");

            // Prepare response data (return immediately for faster UX)
            $responseData = [
                'success' => true,
                'fire_data_id' => $fireDataId,
                    'old_status' => $fireData['status'],
                    'new_status' => 'ACKNOWLEDGED',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'rows_affected' => $rowsAffected,
                    // Indicate SMS will be processed asynchronously
                    'sms_queued' => true
            ];

            // Send immediate response to the client and continue processing in background
            $json = json_encode($responseData);
            // Ensure no previous output buffers block flushing
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
            }
            header('Content-Type: application/json');
            header('Connection: close');
            header('Content-Length: ' . strlen($json));
            echo $json;
            flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }

            // Continue work without client waiting
            ignore_user_abort(true);
            if (function_exists('session_write_close')) {
                session_write_close();
            }

            // Send SMS alerts to firefighters (non-blocking for client)
            try {
                $firefightersPhones = get_active_firefighters_phones();
                if (!empty($firefightersPhones)) {
                    $location = $fireData['building_type'] ?? 'Unknown location';
                        $message = "EMERGENCY: Fire alert acknowledged at $location. Status: ACKNOWLEDGED. Please respond immediately.";

                    $smsResult = send_sms_alerts($firefightersPhones, $message);

                    if ($smsResult) {
                        error_log("SMS Alert: Successfully sent emergency alerts to " . count($firefightersPhones) . " firefighters");
                    } else {
                        error_log("SMS Alert: Failed to send emergency alerts to firefighters");
                    }
                } else {
                    error_log("SMS Alert: No valid phone numbers found for active firefighters");
                }
            } catch (Exception $smsEx) {
                error_log('SMS Async Error: ' . $smsEx->getMessage());
            }
            // Important: do not echo anything else here to avoid corrupting the JSON response
            exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Acknowledgment Error: " . $e->getMessage());
                throw $e;
            }
        }
        
        // Get latest fire data with EMERGENCY status for the logged-in user
        if (isset($_POST['get_latest_data'])) {
            // Get user's devices first
            $stmt = $pdo->prepare("
                SELECT device_id FROM devices 
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$userId]);
            $userDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($userDevices)) {
                echo json_encode(['success' => true, 'data' => null, 'message' => 'No active devices found for user']);
                exit;
            }
        
        // Get latest fire data for user's devices
            $placeholders = implode(',', array_fill(0, count($userDevices), '?'));
        $stmt = $pdo->prepare("
                SELECT f.*, d.device_name, d.device_number, d.serial_number, b.building_name, b.building_type
                FROM fire_data f
                JOIN devices d ON f.device_id = d.device_id
            LEFT JOIN buildings b ON d.building_id = b.id
                WHERE f.device_id IN ($placeholders)
                ORDER BY f.timestamp DESC 
            LIMIT 1
        ");
            $stmt->execute($userDevices);
            $data = $stmt->fetch();
            
            echo json_encode(['success' => true, 'data' => $data, 'user_id' => $userId]);
            exit;
        }
        
        // Get all active alerts (EMERGENCY status) for the logged-in user
        if (isset($_POST['get_active_alerts'])) {
            // Get user's devices first
            $stmt = $pdo->prepare("
                SELECT device_id FROM devices 
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);
            $userDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($userDevices)) {
                echo json_encode(['success' => true, 'alerts' => [], 'message' => 'No active devices found for user']);
                exit;
            }
            
            // Get active alerts for user's devices
            $placeholders = implode(',', array_fill(0, count($userDevices), '?'));
            $stmt = $pdo->prepare("
                SELECT f.*, d.device_name, d.device_number, d.serial_number, b.building_name, b.building_type
                FROM fire_data f
                JOIN devices d ON f.device_id = d.device_id
                LEFT JOIN buildings b ON d.building_id = b.id
                WHERE f.device_id IN ($placeholders)
                  AND f.status IN ('EMERGENCY', 'ACKNOWLEDGED')
                ORDER BY f.timestamp DESC
            ");
            $stmt->execute($userDevices);
            $alerts = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'alerts' => $alerts, 'user_id' => $userId]);
            exit;
        }
        
        // Test SMS functionality
        if (isset($_POST['test_sms'])) {
            $firefightersPhones = get_active_firefighters_phones();
            if (!empty($firefightersPhones)) {
                $testMessage = "TEST SMS: Fire Alert System is working properly. This is a test message sent at " . date('Y-m-d H:i:s');
                
                $smsResult = send_sms_alerts($firefightersPhones, $testMessage);
                
                echo json_encode([
                    'success' => true,
                    'test_sms_sent' => $smsResult,
                    'recipients_count' => count($firefightersPhones),
                    'recipients' => $firefightersPhones,
                    'message' => $testMessage
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'No active firefighters with valid phone numbers found',
                    'recipients_count' => 0
                ]);
            }
            exit;
        }
        
        // Get SMS recipients info
        if (isset($_POST['get_sms_recipients'])) {
            $firefightersPhones = get_active_firefighters_phones();
            
            // Get detailed firefighter info
            try {
                $stmt = $pdo->prepare("
                    SELECT id, name, phone, availability
                    FROM firefighters 
                    WHERE availability = 1 
                    AND phone IS NOT NULL 
                    AND phone != ''
                    AND LENGTH(TRIM(phone)) >= 10
                    ORDER BY name
                ");
                $stmt->execute();
                $firefighters = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'recipients_count' => count($firefightersPhones),
                    'firefighters' => $firefighters,
                    'valid_phones' => $firefightersPhones
                ]);
} catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        
        // Check for new EMERGENCY records (for real-time monitoring)
        if (isset($_POST['check_emergency'])) {
            try {
                // Get user's devices first
                $stmt = $pdo->prepare("
                    SELECT device_id FROM devices 
                    WHERE user_id = ? AND is_active = 1
                ");
                $stmt->execute([$userId]);
                $userDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($userDevices)) {
                    echo json_encode([
                        'success' => true,
                        'has_emergency' => false,
                        'message' => 'No active devices found for user',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    exit;
                }
                
                // Get the latest record from fire_data table for user's devices
                $placeholders = implode(',', array_fill(0, count($userDevices), '?'));
                $stmt = $pdo->prepare("
                    SELECT f.*
                    FROM fire_data f
                    WHERE f.device_id IN ($placeholders)
                    ORDER BY f.timestamp DESC 
                    LIMIT 1
                ");
                $stmt->execute($userDevices);
                $latestData = $stmt->fetch();
                
                if ($latestData) {
                    // Check if the LATEST status is EMERGENCY
                    $hasEmergency = ($latestData['status'] === 'EMERGENCY');
                    
                    echo json_encode([
                        'success' => true,
                        'has_emergency' => $hasEmergency,
                        'data' => $latestData,
                        'latest_status' => $latestData['status'],
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'has_emergency' => false,
                        'message' => 'No records found in fire_data table for user devices',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        
        // Get all EMERGENCY records (for monitoring multiple alerts)
        if (isset($_POST['get_all_emergencies'])) {
            try {
                // Get user's devices first
                $stmt = $pdo->prepare("
                    SELECT device_id FROM devices 
                    WHERE user_id = ? AND is_active = 1
                ");
                $stmt->execute([$userId]);
                $userDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($userDevices)) {
                    echo json_encode([
                        'success' => true,
                        'emergencies' => [],
                        'count' => 0,
                        'message' => 'No active devices found for user',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                    exit;
                }
                
                // Get the latest record from fire_data table for user's devices
                $placeholders = implode(',', array_fill(0, count($userDevices), '?'));
                $stmt = $pdo->prepare("
                    SELECT f.*
                    FROM fire_data f
                    WHERE f.device_id IN ($placeholders)
                    ORDER BY f.timestamp DESC
                    LIMIT 1
                ");
                $stmt->execute($userDevices);
                $latestRecord = $stmt->fetch();
                
                if ($latestRecord) {
                    // Check if the LATEST status is EMERGENCY
                    $hasEmergency = ($latestRecord['status'] === 'EMERGENCY');
                    
                    echo json_encode([
                        'success' => true,
                        'emergencies' => $hasEmergency ? [$latestRecord] : [],
                        'count' => $hasEmergency ? 1 : 0,
                        'latest_status' => $latestRecord['status'],
                        'latest_record' => $latestRecord,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    echo json_encode([
                        'success' => true,
                        'emergencies' => [],
                        'count' => 0,
                        'message' => 'No records found in fire_data table for user devices',
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            }
            exit;
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTrace()
        ]);
        exit;
    }
}

// For non-POST requests, end output buffering to allow HTML output
if (!$isPostRequest) {
    ob_end_flush();
}

// Get initial data for page load (check LATEST status only)
try {
    // Get user's devices first
    $stmt = $pdo->prepare("
        SELECT device_id FROM devices 
        WHERE user_id = ? AND is_active = 1
    ");
    $stmt->execute([$userId]);
    $userDevices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($userDevices)) {
        $initialData = null;
    } else {
        // Get the latest record from fire_data table for user's devices
        $placeholders = implode(',', array_fill(0, count($userDevices), '?'));
        $stmt = $pdo->prepare("
            SELECT f.*
            FROM fire_data f
            WHERE f.device_id IN ($placeholders)
            ORDER BY f.timestamp DESC 
            LIMIT 1
        ");
        $stmt->execute($userDevices);
        $initialData = $stmt->fetch();
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<?php 
// Try multiple possible paths for header.php
$headerPaths = [
    __DIR__ . '/../components/header.php',  // From production/alarm/
    __DIR__ . '/../../components/header.php', // From production/
    __DIR__ . '/components/header.php',     // From alarm/
    dirname(__DIR__, 2) . '/components/header.php', // Using dirname with levels
    dirname(__DIR__, 1) . '/components/header.php'
];

$headerLoaded = false;
foreach ($headerPaths as $headerPath) {
    if (file_exists($headerPath)) {
        include($headerPath);
        $headerLoaded = true;
        break;
    }
}

if (!$headerLoaded) {
    // Fallback: include basic HTML structure if header not found
    echo '<!DOCTYPE html><html><head><title>Fire Alert System</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
    echo '<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">';
    echo '</head><body>';
}
?>
    <style>
        :root {
    --color-emergency: #dc3545;
    --color-pre-dispatch: #fd7e14;
    --color-acknowledged: #ffc107;
    --color-safe: #28a745;
    --color-disconnected: #6c757d;
    --color-connected: #28a745;
    --color-critical: #dc3545;
    --color-warning: #ffc107;
}

.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 10px;
            font-weight: bold;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-emergency { background-color: var(--color-emergency); color: white; }
.status-pre-dispatch { background-color: var(--color-pre-dispatch); color: white; }
.status-acknowledged { background-color: var(--color-acknowledged); color: #212529; }
.status-safe { background-color: var(--color-safe); color: white; }

.connection-status {
    position: fixed;
    bottom: 8px;
    right: 8px;
    padding: 6px 12px;
    border-radius: 16px;
    font-size: 0.75rem;
    background: var(--color-disconnected);
    color: white;
    z-index: 10000;
            display: flex;
            align-items: center;
    gap: 6px;
}

.connection-status.connected {
    background: var(--color-connected);
}

.connection-status.disconnected {
    background: var(--color-disconnected);
}

.sensor-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin: 12px 0;
}

.sensor-box {
    padding: 8px;
    border-radius: 6px;
    background: #f8f9fa;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.sensor-title {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 3px;
}

.sensor-reading {
    font-size: 1rem;
    font-weight: bold;
}

.sensor-critical {
    color: var(--color-critical);
    font-weight: bold;
}

.sensor-warning {
    color: var(--color-warning);
    font-weight: bold;
}

.alert-details {
    text-align: left;
    max-width: 100%;
    font-size: 0.9rem;
    line-height: 1.4;
}

.alert-details p {
    margin: 6px 0;
}

.alert-divider {
    margin: 12px 0;
    border: 0;
    height: 1px;
    background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(0,0,0,0.1), rgba(0,0,0,0));
}
    </style>
</head>
<body>
    <div class="connection-status" id="connectionStatus">
        <i class="fas fa-sync-alt"></i>
        <span>Connecting...</span>
                        </div>
    
    <!-- Audio Status Indicator -->
    <div id="audioStatus" style="position: fixed; bottom: 8px; left: 8px; padding: 6px 12px; border-radius: 16px; font-size: 0.75rem; background: #6c757d; color: white; z-index: 10000; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-volume-mute"></i>
        <span>Audio: Disabled</span>
                    </div>
    
    <!-- Debug Panel (only visible in development) -->
    <div id="debugPanel" style="position: fixed; top: 8px; left: 8px; background: rgba(0,0,0,0.8); color: white; padding: 8px; border-radius: 4px; font-size: 11px; z-index: 9999; display: none;">
        <h4 style="margin: 0 0 6px 0; font-size: 12px;">Debug Info</h4>
        <div style="margin: 2px 0;">Connection: <span id="wsStatus">Polling</span></div>
        <div style="margin: 2px 0;">Messages: <span id="msgCount">0</span></div>
        <div style="margin: 2px 0;">Current Status: <span id="currentStatus">None</span></div>
        <div style="margin: 2px 0;">Last Alert: <span id="lastAlert">None</span></div>
        <button onclick="testEmergencyAlert()" style="margin: 3px; padding: 3px; background: red; color: white; font-size: 10px;">Test Emergency</button>
        <button onclick="simulateDeviceEmergency()" style="margin: 3px; padding: 3px; font-size: 10px;">Simulate Device</button>
        <button onclick="forceEmergencyAlert()" style="margin: 3px; padding: 3px; background: darkred; color: white; font-size: 10px;">FORCE EMERGENCY</button>
        <button onclick="manualEmergencyCheck()" style="margin: 3px; padding: 3px; background: yellow; color: black; font-size: 10px;">MANUAL CHECK</button>
        <button onclick="checkForNewEmergenciesFromServer()" style="margin: 3px; padding: 3px; background: orange; color: white; font-size: 10px;">CHECK EMERGENCY</button>
        <button onclick="getAllActiveEmergencies()" style="margin: 3px; padding: 3px; background: purple; color: white; font-size: 10px;">ALL EMERGENCIES</button>
        <button onclick="checkDatabaseForEmergency()" style="margin: 3px; padding: 3px; background: blue; color: white; font-size: 10px;">DB CHECK</button>
        <button onclick="insertTestEmergency()" style="margin: 3px; padding: 3px; background: darkblue; color: white; font-size: 10px;">INSERT TEST</button>
        <button onclick="testAudioSystem()" style="margin: 3px; padding: 3px; background: blue; color: white; font-size: 10px;">TEST AUDIO</button>
        <button onclick="testSMS()" style="margin: 3px; padding: 3px; background: green; color: white; font-size: 10px;">TEST SMS</button>
        <button onclick="getSMSRecipients()" style="margin: 3px; padding: 3px; background: purple; color: white; font-size: 10px;">SMS RECIPIENTS</button>
        <button onclick="clearSuppressedAlerts()" style="margin: 3px; padding: 3px; background: orange; color: white; font-size: 10px;">CLEAR SUPPRESSED</button>
        <button onclick="document.getElementById('debugPanel').style.display='none'" style="margin: 3px; padding: 3px; font-size: 10px;">Hide</button>
    </div>

    <!-- Show debug panel with Ctrl+Shift+D -->
    <script>
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && e.key === 'D') {
                document.getElementById('debugPanel').style.display = 'block';
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Constants
        const POLLING_INTERVAL = 2000; // Reduced to 2 seconds for more real-time updates
        const ALARM_SOUND_URL = 'alarm.mp3';
        
        // Thresholds
        const CRITICAL_THRESHOLDS = {
            temp: 50,    // °C
            heat: 60,    // °C
            smoke: 500   // ppm
        };
        
        const WARNING_THRESHOLDS = {
            temp: 40,
            heat: 50,
            smoke: 300
        };

        // State management
        const appState = {
            userId: <?php echo json_encode($userId); ?>,
            currentFireData: <?php echo json_encode($initialData); ?>,
            isAlarmPlaying: false,
            currentAlert: null,
            lastAlertTime: null,
            pollingInterval: null,
            alarmAudio: null,
            messageCount: 0,
            audioContext: null,
            audioEnabled: false,
            audioPermissionShown: false,
            lastCheckedId: null,
            lastCheckedTimestamp: null,
            emergencyCheckInterval: null,
            suppressAlertsUntil: 0
        };

        // DOM Elements
        const elements = {
            connectionStatus: document.getElementById('connectionStatus'),
            audioStatus: document.getElementById('audioStatus')
        };

        // Initialize the application
        function init() {
            console.log("Initializing Fire Alert System");
            initAudio();
            setupEventListeners();
            startPolling();
            startEmergencyMonitoring();
            
            // Check for immediate EMERGENCY alerts on page load (check LATEST status only)
            if (appState.currentFireData && 
                appState.currentFireData.status === 'EMERGENCY') {
                console.log("🚨 IMMEDIATE EMERGENCY DETECTED ON PAGE LOAD - LATEST STATUS IS EMERGENCY");
                setTimeout(() => {
                    checkAndShowAlert(appState.currentFireData);
                }, 1000);
            } else if (appState.currentFireData && appState.currentFireData.status === 'ACKNOWLEDGED') {
                console.log("✅ Initial data: LATEST status is ACKNOWLEDGED - not showing alert");
            } else if (appState.currentFireData && appState.currentFireData.status === 'SAFE') {
                console.log("🟢 Initial data: LATEST status is SAFE - not showing alert");
            } else if (appState.currentFireData && appState.currentFireData.status === 'MONITORING') {
                console.log("⚠️ Initial data: LATEST status is MONITORING - not showing alert");
            } else {
                console.log("❓ No initial fire data available");
            }
            
            // Pre-load and test audio after a short delay
            setTimeout(preloadAndTestAudio, 2000);
        }

        // Initialize audio
        function initAudio() {
            appState.alarmAudio = new Audio(ALARM_SOUND_URL);
            appState.alarmAudio.loop = true;
            appState.alarmAudio.preload = 'auto';
            
            // Set audio properties for better compatibility
            appState.alarmAudio.volume = 0.8;
            appState.alarmAudio.muted = false;
            
            // Create audio context for better control
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    appState.audioContext = new AudioContext();
                    console.log("🔊 Audio context created successfully");
                }
            } catch (e) {
                console.log("🔊 Audio context creation failed:", e);
            }
            
            // Multiple strategies for enabling audio
            setupAudioEnablingStrategies();
        }

        // Setup multiple strategies for enabling audio
        function setupAudioEnablingStrategies() {
            // Strategy 1: User interaction listeners
            const enableAudioOnInteraction = () => {
                console.log("🔊 User interaction detected - enabling audio");
                enableAudioContext();
                removeInteractionListeners();
            };
            
            const removeInteractionListeners = () => {
                document.removeEventListener('click', enableAudioOnInteraction);
                document.removeEventListener('keydown', enableAudioOnInteraction);
                document.removeEventListener('touchstart', enableAudioOnInteraction);
                document.removeEventListener('mousedown', enableAudioOnInteraction);
            };
            
            document.addEventListener('click', enableAudioOnInteraction, { once: true });
            document.addEventListener('keydown', enableAudioOnInteraction, { once: true });
            document.addEventListener('touchstart', enableAudioOnInteraction, { once: true });
            document.addEventListener('mousedown', enableAudioOnInteraction, { once: true });
            
            // Strategy 2: Try to enable on page load with silent audio
            setTimeout(() => {
                trySilentAudioPlayback();
            }, 1000);
            
            // Strategy 3: Show audio permission request
            setTimeout(() => {
                if (!appState.audioEnabled) {
                    showAudioPermissionRequest();
                }
            }, 3000);
        }

        // Update audio status indicator
        function updateAudioStatus(enabled) {
            const icon = elements.audioStatus.querySelector('i');
            const text = elements.audioStatus.querySelector('span');
            
            if (enabled) {
                elements.audioStatus.style.background = '#28a745';
                icon.className = 'fas fa-volume-up';
                text.textContent = 'Audio: Enabled';
            } else {
                elements.audioStatus.style.background = '#6c757d';
                icon.className = 'fas fa-volume-mute';
                text.textContent = 'Audio: Disabled';
            }
        }

        // Enable audio context
        function enableAudioContext() {
            if (appState.audioContext && appState.audioContext.state === 'suspended') {
                appState.audioContext.resume().then(() => {
                    console.log("🔊 Audio context resumed successfully");
                    appState.audioEnabled = true;
                    updateAudioStatus(true);
                }).catch(e => {
                    console.log("🔊 Audio context resume failed:", e);
                });
            } else {
                appState.audioEnabled = true;
                updateAudioStatus(true);
            }
        }

        // Try silent audio playback to enable audio
        function trySilentAudioPlayback() {
            try {
                // Create a silent audio element
                const silentAudio = new Audio();
                silentAudio.src = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
                silentAudio.volume = 0;
                silentAudio.muted = true;
                
                silentAudio.play().then(() => {
                    console.log("🔊 Silent audio playback successful - audio enabled");
                    appState.audioEnabled = true;
                    updateAudioStatus(true);
                    silentAudio.pause();
                }).catch(e => {
                    console.log("🔊 Silent audio playback failed:", e);
                });
            } catch (e) {
                console.log("🔊 Silent audio creation failed:", e);
            }
        }

        // Show audio permission request
        function showAudioPermissionRequest() {
            if (appState.audioPermissionShown) return;
            appState.audioPermissionShown = true;
            
            // Audio permission modal removed - audio will work without user interaction
        }

        // Improved alarm control functions
        function startAlarm() {
            if (!appState.isAlarmPlaying) {
                console.log("🔊 Starting alarm with improved playback");
                appState.isAlarmPlaying = true;
                
                // Strategy 1: Direct play if audio is enabled
                if (appState.audioEnabled) {
                    playAlarmDirect();
                } else {
                    // Strategy 2: Try to enable audio and play
                    enableAudioAndPlay();
                }
            }
        }

        // Direct alarm playback
        function playAlarmDirect() {
            appState.alarmAudio.currentTime = 0;
            appState.alarmAudio.volume = 0.8;
            appState.alarmAudio.muted = false;
            
            appState.alarmAudio.play().then(() => {
                console.log("🔊 Alarm started successfully");
            }).catch(e => {
                console.log("🔊 Direct play failed, trying fallback:", e);
                playAlarmFallback();
            });
        }

        // Enable audio and play alarm
        function enableAudioAndPlay() {
            // Try to enable audio context first
            if (appState.audioContext && appState.audioContext.state === 'suspended') {
                appState.audioContext.resume().then(() => {
                    console.log("🔊 Audio context enabled, now playing alarm");
                    playAlarmDirect();
                }).catch(e => {
                    console.log("🔊 Audio context enable failed:", e);
                    playAlarmFallback();
                });
            } else {
                // Try silent audio to enable playback
                trySilentAudioPlayback();
                setTimeout(() => {
                    playAlarmDirect();
                }, 100);
            }
        }

        // Fallback alarm playback methods
        function playAlarmFallback() {
            console.log("🔊 Using fallback alarm methods");
            
            // Method 1: Try with muted first
            appState.alarmAudio.muted = true;
            appState.alarmAudio.play().then(() => {
                console.log("🔊 Muted playback successful, unmuting...");
                setTimeout(() => {
                    appState.alarmAudio.muted = false;
                }, 200);
            }).catch(e => {
                console.log("🔊 Muted playback failed:", e);
                
                // Method 2: Try with lower volume
                appState.alarmAudio.volume = 0.3;
                appState.alarmAudio.muted = false;
                appState.alarmAudio.play().catch(e2 => {
                    console.log("🔊 Low volume playback failed:", e2);
                    
                    // Method 3: Show user notification
                    showAudioPlaybackNotification();
                });
            });
        }

        // Show notification for audio playback issues
        function showAudioPlaybackNotification() {
            // Audio playback notification modal removed - audio will work without user interaction
            console.log("🔊 Audio playback issue - continuing without modal");
        }

        // Pre-load and test audio playback
        function preloadAndTestAudio() {
            console.log("🔊 Pre-loading and testing audio...");
            
            // Try to play a very short sound to enable audio context
            const testAudio = new Audio(ALARM_SOUND_URL);
            testAudio.volume = 0.1;
            testAudio.currentTime = 0;
            
            testAudio.play().then(() => {
                console.log("🔊 Audio test successful - automatic playback enabled");
                appState.audioEnabled = true;
                updateAudioStatus(true);
                setTimeout(() => {
                    testAudio.pause();
                    testAudio.currentTime = 0;
                }, 100);
            }).catch(e => {
                console.log("🔊 Audio test failed, will use fallback methods:", e);
                updateAudioStatus(false);
            });
        }

        // Set up event listeners
        function setupEventListeners() {
            // Handle page visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    console.log("📱 PAGE BECAME VISIBLE - CHECKING FOR EMERGENCY ALERTS");
                    
                    // Immediately fetch latest data and check for Emergency alerts
                    fetchLatestDataFromServer();
                    
                    // Check for Emergency alerts when page becomes visible (check LATEST status only)
                    if (appState.currentFireData && 
                        appState.currentFireData.status === 'EMERGENCY') {
                        setTimeout(() => {
                            console.log("🚨 PAGE VISIBLE - CHECKING EMERGENCY ALERTS (LATEST STATUS IS EMERGENCY)");
                            checkForEmergencyAlerts();
                        }, 500);
                    } else if (appState.currentFireData && appState.currentFireData.status === 'ACKNOWLEDGED') {
                        console.log("✅ Page visible but LATEST status is ACKNOWLEDGED - not checking");
                    } else if (appState.currentFireData && appState.currentFireData.status === 'SAFE') {
                        console.log("🟢 Page visible but LATEST status is SAFE - not checking");
                    } else if (appState.currentFireData && appState.currentFireData.status === 'MONITORING') {
                        console.log("⚠️ Page visible but LATEST status is MONITORING - not checking");
                    } else {
                        console.log("❓ Page visible but no fire data available");
                    }
                } else {
                    pauseAlarm();
                }
            });
            
            // Global click handler to enable audio
            document.addEventListener('click', enableAudioOnClick, { once: true });
            document.addEventListener('keydown', enableAudioOnClick, { once: true });
            document.addEventListener('touchstart', enableAudioOnClick, { once: true });
            
            // Audio status indicator click handler
            if (elements.audioStatus) {
                elements.audioStatus.addEventListener('click', () => {
                    if (!appState.audioEnabled) {
                        console.log("🔊 Manual audio enable requested");
                        enableAudioContext();
                        setTimeout(() => {
                            playAlarmDirect();
                        }, 500);
                    }
                });
                
                // Add hover effect
                elements.audioStatus.style.cursor = 'pointer';
                elements.audioStatus.title = 'Click to enable audio';
            }
        }

        // Enable audio on first user interaction
        function enableAudioOnClick() {
            console.log("🔊 User interaction detected - enabling audio");
            
            // Try to play a silent sound to enable audio context
            const silentAudio = new Audio();
            silentAudio.src = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
            silentAudio.volume = 0;
            
            silentAudio.play().then(() => {
                console.log("🔊 Audio context enabled successfully");
                // Now try to play the actual alarm if it should be playing
                if (appState.isAlarmPlaying && appState.alarmAudio.paused) {
                    appState.alarmAudio.play().catch(e => {
                        console.log("🔊 Still can't play alarm:", e);
                    });
                }
            }).catch(e => {
                console.log("🔊 Silent audio failed:", e);
            });
        }

        // Start polling for fire data updates
        function startPolling() {
            updateConnectionStatus('Connected (Real-time Polling)');
            console.log("Starting real-time polling for fire data updates");
            
            // Clear any existing polling
            if (appState.pollingInterval) {
                clearInterval(appState.pollingInterval);
            }
            
            // Start polling every 2 seconds for more real-time updates
            appState.pollingInterval = setInterval(() => {
                fetchLatestDataFromServer();
            }, POLLING_INTERVAL);
            
            // Initial fetch
            fetchLatestDataFromServer();
        }

        // Start emergency monitoring for immediate EMERGENCY detection
        function startEmergencyMonitoring() {
            console.log("🚨 Starting emergency monitoring system");
            
            // Clear any existing emergency check interval
            if (appState.emergencyCheckInterval) {
                clearInterval(appState.emergencyCheckInterval);
            }
            
            // Check for emergencies every 1 second
            appState.emergencyCheckInterval = setInterval(() => {
                checkForImmediateEmergencies();
                checkForNewEmergenciesFromServer();
            }, 1000);
            
            // Also check every 500ms for ultra-real-time detection
            setInterval(() => {
                if (!appState.currentAlert && !appState.isAlarmPlaying) {
                    checkForNewEmergenciesFromServer();
                }
            }, 500);
            
            // Add automatic database check every 3 seconds
            setInterval(() => {
                if (!appState.currentAlert && !appState.isAlarmPlaying) {
                    checkDatabaseForEmergency();
                }
            }, 3000);
            
            // Initial emergency check
            setTimeout(() => {
                checkForNewEmergenciesFromServer();
                getAllActiveEmergencies();
                checkDatabaseForEmergency(); // Check database immediately
            }, 2000);
        }

        // Check for immediate emergencies without waiting for polling
        function checkForImmediateEmergencies() {
            if (!appState.currentFireData) return;
            
            // Check if this alert is suppressed for this session
            if (isAlertSuppressed(appState.currentFireData.id)) {
                console.log(`🚫 Alert ${appState.currentFireData.id} is suppressed for this session - not showing`);
                return;
            }
            
            // ONLY check for EMERGENCY status in the LATEST record
            const isEmergency = appState.currentFireData.status === 'EMERGENCY';
            const noCurrentAlert = !appState.currentAlert;
            
            if (isEmergency && noCurrentAlert) {
                console.log("🚨 IMMEDIATE EMERGENCY DETECTED - LATEST STATUS IS EMERGENCY - SHOWING ALERT NOW");
                showFireAlert(appState.currentFireData);
            } else if (appState.currentFireData.status === 'ACKNOWLEDGED') {
                console.log("✅ LATEST status is ACKNOWLEDGED - not showing alert");
            } else if (appState.currentFireData.status === 'SAFE') {
                console.log("🟢 LATEST status is SAFE - not showing alert");
            } else if (appState.currentFireData.status === 'MONITORING') {
                console.log("⚠️ LATEST status is MONITORING - not showing alert");
            } else if (appState.currentAlert) {
                console.log("📱 Alert already showing - not showing again");
            } else {
                console.log(`❓ LATEST status is: ${appState.currentFireData.status} - not showing alert`);
            }
        }

        // Actively check for new EMERGENCY records from server
        function checkForNewEmergenciesFromServer() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'check_emergency=1'
            })
            .then(response => response.json())
                .then(data => {
                if (data.success && data.data) {
                    console.log("📡 CHECKING LATEST STATUS FROM SERVER:", data.data);
                    console.log("📡 LATEST STATUS IS:", data.latest_status);
                    
                    // Check if the LATEST status is EMERGENCY
                    if (data.latest_status === 'EMERGENCY') {
                        console.log("🚨 LATEST STATUS IS EMERGENCY - PROCESSING ALERT");
                        
                        // Update emergency monitoring status
                        updateEmergencyMonitoringStatus(true);
                        
                        // Check if this is a new emergency we haven't seen before
                        const isNewEmergency = !appState.currentFireData || 
                                             appState.currentFireData.status !== 'EMERGENCY' ||
                                             appState.currentFireData.id !== data.data.id;
                        
                        if (isNewEmergency) {
                            console.log("🚨 PROCESSING NEW EMERGENCY FROM LATEST RECORD");
                            processNewData(data.data);
                        }
                    } else if (data.latest_status === 'ACKNOWLEDGED') {
                        console.log("✅ LATEST status is ACKNOWLEDGED - not processing");
                        updateEmergencyMonitoringStatus(false);
                    } else if (data.latest_status === 'SAFE') {
                        console.log("🟢 LATEST status is SAFE - not processing");
                        updateEmergencyMonitoringStatus(false);
                    } else if (data.latest_status === 'MONITORING') {
                        console.log("⚠️ LATEST status is MONITORING - not processing");
                        updateEmergencyMonitoringStatus(false);
                    } else {
                        console.log(`❓ LATEST status is: ${data.latest_status} - not processing`);
                        updateEmergencyMonitoringStatus(false);
                    }
                } else {
                    // No data found, reset status if no current emergency
                    if (!appState.currentFireData || 
                        appState.currentFireData.status !== 'EMERGENCY') {
                        updateEmergencyMonitoringStatus(false);
                    }
                    }
                })
                .catch(error => {
                console.error("❌ Error checking for emergencies:", error);
            });
        }

        // Enhanced function to get all active emergencies
        function getAllActiveEmergencies() {
            console.log("🚨 Getting latest status from database...");
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_all_emergencies=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`📡 LATEST STATUS: ${data.latest_status}`);
                    console.log(`📡 LATEST RECORD:`, data.latest_record);
                    
                    if (data.count > 0 && data.latest_status === 'EMERGENCY') {
                        // Show the latest EMERGENCY record
                        const latestEmergency = data.latest_record;
                        if (!appState.currentFireData || 
                            appState.currentFireData.id !== latestEmergency.id) {
                            console.log("🚨 SHOWING LATEST EMERGENCY RECORD");
                            processNewData(latestEmergency);
                        }
                    } else {
                        console.log(`⚠️ LATEST status is ${data.latest_status} - not showing alert`);
                    }
                }
            })
            .catch(error => {
                console.error("❌ Error getting latest status:", error);
            });
        }

        // Update connection status UI
        function updateConnectionStatus(status) {
            const icon = elements.connectionStatus.querySelector('i');
            const text = elements.connectionStatus.querySelector('span');
            
            text.textContent = status;
            
            if (status.includes('Connected')) {
                elements.connectionStatus.classList.add('connected');
                elements.connectionStatus.classList.remove('disconnected');
                icon.className = 'fas fa-plug connected-icon';
            } else {
                elements.connectionStatus.classList.add('disconnected');
                elements.connectionStatus.classList.remove('connected');
                icon.className = 'fas fa-plug disconnected-icon';
            }
            
            // Update debug panel
            updateDebugPanel();
        }

        // Update emergency monitoring status
        function updateEmergencyMonitoringStatus(isActive) {
            const connectionStatus = elements.connectionStatus;
            const icon = connectionStatus.querySelector('i');
            const text = connectionStatus.querySelector('span');
            
            if (isActive) {
                connectionStatus.style.background = '#dc3545'; // Emergency red
                icon.className = 'fas fa-exclamation-triangle';
                text.textContent = 'EMERGENCY MONITORING ACTIVE';
            } else {
                // Reset to normal connection status
                if (appState.pollingInterval) {
                    updateConnectionStatus('Connected (Real-time Polling)');
                } else {
                    updateConnectionStatus('Disconnected');
                }
            }
        }

        // Update debug panel
        function updateDebugPanel() {
            const wsStatus = document.getElementById('wsStatus');
            const msgCount = document.getElementById('msgCount');
            const currentStatus = document.getElementById('currentStatus');
            const lastAlert = document.getElementById('lastAlert');
            
            if (wsStatus) wsStatus.textContent = 'Polling';
            if (msgCount) msgCount.textContent = appState.messageCount;
            if (currentStatus) currentStatus.textContent = appState.currentFireData?.status || 'None';
            if (lastAlert) lastAlert.textContent = appState.lastAlertTime ? 
                new Date(appState.lastAlertTime).toLocaleTimeString() : 'None';
        }

        // Process new fire data with enhanced EMERGENCY detection
        function processNewData(newData) {
            console.log("Processing new data:", newData);
            
            if (!newData) {
                console.log("No data received");
                return false;
            }
            
            // Check if this is a new alert (different ID, timestamp, or status change)
            const isNewAlert = !appState.lastCheckedId || 
                              appState.lastCheckedId !== newData.id ||
                              (appState.currentFireData && appState.currentFireData.timestamp !== newData.timestamp) ||
                              (appState.currentFireData && appState.currentFireData.status !== newData.status);
            
            if (isNewAlert) {
                console.log("🆕 NEW ALERT DETECTED:", newData);
                appState.messageCount++;
                appState.lastCheckedId = newData.id;
                appState.lastCheckedTimestamp = newData.timestamp;
                
                // Update current fire data
                appState.currentFireData = newData;
                
                // ONLY show alerts for EMERGENCY status
                if (newData.status === 'EMERGENCY') {
                    console.log(`🚨 EMERGENCY DETECTED - SHOWING ALERT IMMEDIATELY`);
                    updateEmergencyMonitoringStatus(true);
                    showFireAlert(newData);
                    return true;
                } else if (newData.status === 'ACKNOWLEDGED') {
                    console.log(`✅ Status is ACKNOWLEDGED - not showing alert`);
                    updateEmergencyMonitoringStatus(false);
                    return false;
                } else if (newData.status === 'SAFE') {
                    console.log(`🟢 Status is SAFE - not showing alert`);
                    updateEmergencyMonitoringStatus(false);
                    return false;
                } else if (newData.status === 'MONITORING') {
                    console.log(`⚠️ Status is MONITORING - not showing alert`);
                    updateEmergencyMonitoringStatus(false);
                    return false;
                } else {
                    console.log(`❓ Unknown status: ${newData.status} - not showing alert`);
                    updateEmergencyMonitoringStatus(false);
                    return false;
                }
            }
            
            return false;
        }

        // Enhanced check if alert should be shown
        function checkAndShowAlert(data) {
            if (!data) return false;
            
            // Suppress alerts briefly after user action
            if (Date.now() < appState.suppressAlertsUntil) {
                console.log('⏳ Alert suppressed temporarily');
                return false;
            }
            
            console.log("Checking alert conditions for:", data);
            
            const isEmergency = data.status === 'EMERGENCY';
            
            // ONLY show Emergency alerts - ignore all other statuses
            if (isEmergency) {
                console.log(`🚨 EMERGENCY ALERT - SHOWING NOW`);
                showFireAlert(data);
                return true;
            } else if (data.status === 'ACKNOWLEDGED') {
                console.log(`✅ Status is ACKNOWLEDGED - not showing alert`);
                return false;
            } else if (data.status === 'SAFE') {
                console.log(`🟢 Status is SAFE - not showing alert`);
                return false;
            } else if (data.status === 'MONITORING') {
                console.log(`⚠️ Status is MONITORING - not showing alert`);
                return false;
            } else {
                console.log(`❓ Unknown status: ${data.status} - not showing alert`);
                return false;
            }
        }

        // Show fire alert modal
        function showFireAlert(data) {
            console.log("🎯 SHOWING FIRE ALERT MODAL FOR:", data);
            
            // Check if this alert is suppressed for this session
            if (isAlertSuppressed(data.id)) {
                console.log(`🚫 Alert ${data.id} is suppressed for this session - not showing`);
                return;
            }
            
            // Only show alerts for EMERGENCY status
            if (data.status !== 'EMERGENCY') {
                console.log(`⚠️ Status is ${data.status}, not showing alert (EMERGENCY only)`);
                return;
            }
            
            const statusClass = data.status.toLowerCase().replace('_', '-');
            
            // Close any existing alert first
            if (appState.currentAlert) {
                console.log("Closing existing alert");
                Swal.close();
            }
            
            const sensorGridHTML = createSensorGridHTML(data);
            
            const html = `
                <div class="alert-details">
                    <p><strong>Status:</strong> <span class="status-badge status-${statusClass}">${data.status}</span></p>
                    <p><strong>Location:</strong> <span class="sensor-value">${data.building_type || 'Unknown location'}</span></p>
                    <p><strong>Detected at:</strong> <span class="sensor-value">${formatTimestamp(data.timestamp)}</span></p>
                    
                    ${sensorGridHTML}
                    </div>
            `;
            
            const alertConfig = {
                title: '🚨 EMERGENCY: FIRE DETECTED',
                html: html + '<div style="text-align: center; margin-top: 20px;"><button id="closeAlertBtn" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-size: 16px; cursor: pointer;">Close Alert</button></div>',
                icon: 'error',
                showConfirmButton: false,
                showCancelButton: false,
                showCloseButton: true,
                allowOutsideClick: false,
                backdrop: 'rgba(0,0,0,0.9)',
                width: '500px',
                padding: '1.5rem',
                focusConfirm: false,
                didOpen: () => {
                    console.log("🎯 ALERT MODAL OPENED");
                    console.log("🔊 STARTING ALARM SOUND");
                    startAlarm();
                    
                    // Add event listener for custom close button
                    const closeBtn = document.getElementById('closeAlertBtn');
                    if (closeBtn) {
                        closeBtn.addEventListener('click', () => {
                            console.log("🚨 Custom close button clicked - suppressing alert for session");
                            suppressAlertForSession(data.id);
                            stopAlarm();
                            Swal.close();
                        });
                    }
                },
                willClose: () => {
                    console.log("🎯 ALERT MODAL CLOSING");
                    stopAlarm();
                    appState.currentAlert = null;
                }
            };
            
            console.log("🎯 FIRING SWAL.FIRE WITH CONFIG:", alertConfig);
            appState.currentAlert = Swal.fire(alertConfig).then((result) => {
                console.log("🎯 ALERT RESULT:", result);
                // Since we only have a close button, handle any dismissal as closing
                if (result.dismiss === Swal.DismissReason.close || result.dismiss === Swal.DismissReason.backdrop) {
                    console.log("🚨 Alert closed by user - suppressing alert for session");
                    suppressAlertForSession(data.id);
                    stopAlarm();
                    appState.currentAlert = null;
                }
            });
        }

        // Create sensor grid HTML
        function createSensorGridHTML(data) {
            const tempClass = getSensorClass(data.temp, 'temp');
            const heatClass = getSensorClass(data.heat, 'heat');
            const smokeClass = getSensorClass(data.smoke, 'smoke');
            
            return `
                <div class="sensor-grid">
                    <div class="sensor-box">
                        <div class="sensor-title">Temperature</div>
                        <div class="sensor-reading ${tempClass}">${data.temp || 0}°C</div>
                </div>
                    <div class="sensor-box">
                        <div class="sensor-title">Heat Index</div>
                        <div class="sensor-reading ${heatClass}">${data.heat || 0}°C</div>
                    </div>
                    <div class="sensor-box">
                        <div class="sensor-title">Smoke Level</div>
                        <div class="sensor-reading ${smokeClass}">${data.smoke || 0} ppm</div>
                    </div>
                    <div class="sensor-box">
                        <div class="sensor-title">Flame Detected</div>
                        <div class="sensor-reading">${data.flame_detected ? '✅ Yes' : '❌ No'}</div>
                    </div>
                </div>
            `;
        }

        // Get sensor CSS class based on thresholds
        function getSensorClass(value, type) {
            if (value >= CRITICAL_THRESHOLDS[type]) return 'sensor-critical';
            if (value >= WARNING_THRESHOLDS[type]) return 'sensor-warning';
            return '';
        }

        // Format timestamp
        function formatTimestamp(timestamp) {
            if (!timestamp) return 'N/A';
            const date = new Date(timestamp);
            return date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }

        // Alarm control functions
        function stopAlarm() {
            if (appState.isAlarmPlaying) {
                console.log("Stopping alarm");
                appState.alarmAudio.pause();
                appState.alarmAudio.currentTime = 0;
                appState.isAlarmPlaying = false;
            }
        }

        function pauseAlarm() {
            if (appState.isAlarmPlaying) {
                console.log("Pausing alarm");
                appState.alarmAudio.pause();
            }
        }

        // Show notification when alarm is dismissed
        function showDismissedNotification() {
            Swal.fire({
                title: 'Alarm Silenced',
                text: 'The alarm has been stopped.',
                icon: 'info',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }

        // Session-based alert suppression
        function suppressAlertForSession(alertId) {
            if (!alertId) return;
            
            // Get current suppressed alerts from sessionStorage
            const suppressedAlerts = getSuppressedAlerts();
            suppressedAlerts.add(alertId);
            
            // Save back to sessionStorage
            sessionStorage.setItem('suppressedAlerts', JSON.stringify(Array.from(suppressedAlerts)));
            console.log(`🚫 Alert ${alertId} suppressed for this session`);
        }
        
        function getSuppressedAlerts() {
            try {
                const stored = sessionStorage.getItem('suppressedAlerts');
                const alerts = stored ? JSON.parse(stored) : [];
                return new Set(alerts);
            } catch (e) {
                console.error('Error getting suppressed alerts:', e);
                return new Set();
            }
        }
        
        function isAlertSuppressed(alertId) {
            if (!alertId) return false;
            const suppressedAlerts = getSuppressedAlerts();
            return suppressedAlerts.has(alertId);
        }
        
        function clearSuppressedAlerts() {
            sessionStorage.removeItem('suppressedAlerts');
            console.log('🧹 All suppressed alerts cleared for session');
        }

        // Clear acknowledged alerts from the system
        function clearAcknowledgedAlert() {
            if (appState.currentFireData && appState.currentFireData.status === 'ACKNOWLEDGED') {
                console.log("🧹 Clearing acknowledged alert from system");
                appState.currentFireData = null;
                appState.lastCheckedId = null;
                appState.currentAlert = null;
            }
        }

        // Function to verify database update after acknowledgment
        function verifyDatabaseUpdate(fireDataId) {
            console.log("🔍 Verifying database update for fire_data ID:", fireDataId);
            
            // Wait a moment for the database to update, then verify
            setTimeout(() => {
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `check_emergency=1`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.has_emergency && data.data && data.data.id == fireDataId) {
                            if (data.data.status === 'ACKNOWLEDGED') {
                                console.log("✅ Database verification successful: Status is ACKNOWLEDGED");
                            } else {
                                console.warn("⚠️ Database verification: Status is still", data.data.status);
                            }
                        } else {
                            console.log("✅ Database verification: No active emergencies found (expected after acknowledgment)");
                        }
                    }
                })
                .catch(error => {
                    console.error("❌ Database verification failed:", error);
                });
            }, 1000); // Wait 1 second before verifying
        }


        // Handle fetch response
        function handleResponse(response) {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || 'Network response was not ok');
                });
            }
            return response.json();
        }

        // Show notification
        function showNotification(title, text, icon) {
            Swal.fire({
                title,
                text,
                icon,
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        }

        // Fetch latest data from server with enhanced EMERGENCY detection
        function fetchLatestDataFromServer() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_latest_data=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    console.log("📡 FETCHED LATEST DATA:", data.data);
                    console.log("📡 LATEST STATUS:", data.data.status);
                    
                    // Check if the LATEST status is EMERGENCY
                    if (data.data.status === 'EMERGENCY') {
                        // Check if this is a new emergency or status change
                        const isNewEmergency = !appState.currentFireData || 
                                             appState.currentFireData.status !== 'EMERGENCY' ||
                                             appState.currentFireData.id !== data.data.id;
                        
                        if (isNewEmergency) {
                            console.log("🚨 NEW EMERGENCY DETECTED FROM LATEST RECORD - PROCESSING IMMEDIATELY");
                            processNewData(data.data);
                        } else {
                            console.log("📡 Same emergency still active, not re-triggering");
                        }
                    } else if (data.data.status === 'ACKNOWLEDGED') {
                        console.log("📡 LATEST status is ACKNOWLEDGED - clearing from system");
                        // Clear acknowledged data from system
                        if (appState.currentFireData && appState.currentFireData.id === data.data.id) {
                            clearAcknowledgedAlert();
                        }
                    } else if (data.data.status === 'SAFE') {
                        console.log("📡 LATEST status is SAFE - no emergency");
                        // Update status but don't show alert
                        if (appState.currentFireData && appState.currentFireData.id === data.data.id) {
                            appState.currentFireData.status = 'SAFE';
                        }
                    } else if (data.data.status === 'MONITORING') {
                        console.log("📡 LATEST status is MONITORING - no emergency");
                        // Update status but don't show alert
                        if (appState.currentFireData && appState.currentFireData.id === data.data.id) {
                            appState.currentFireData.status = 'MONITORING';
                        }
                    } else {
                        console.log(`📡 LATEST status is: ${data.data.status} - not processing`);
                    }
                } else {
                    console.log("📡 No new data available");
                }
            })
            .catch(error => {
                console.error("❌ Error fetching latest data:", error);
                updateConnectionStatus('Connection Error');
            });
        }

        // Enhanced periodic check for Emergency alerts
        function checkForEmergencyAlerts() {
            if (!appState.currentFireData) {
                console.log("Periodic check: No current fire data available");
                return;
            }
            
            // ONLY show alerts for EMERGENCY status
            if (appState.currentFireData.status === 'EMERGENCY' && !appState.currentAlert) {
                console.log(`🚨 Periodic check: Showing EMERGENCY alert`);
                showFireAlert(appState.currentFireData);
            } else if (appState.currentFireData.status === 'ACKNOWLEDGED') {
                console.log(`✅ Periodic check: Status is ACKNOWLEDGED - not showing alert`);
            } else if (appState.currentFireData.status === 'SAFE') {
                console.log(`🟢 Periodic check: Status is SAFE - not showing alert`);
            } else if (appState.currentFireData.status === 'MONITORING') {
                console.log(`⚠️ Periodic check: Status is MONITORING - not showing alert`);
            } else if (appState.currentAlert) {
                console.log(`📱 Periodic check: Alert already showing, not showing again`);
            } else {
                console.log(`❓ Periodic check: Unknown status: ${appState.currentFireData.status} - not showing alert`);
            }
        }

        // Enhanced test function for Emergency alerts
        function testEmergencyAlert() {
            const testData = {
                id: 'test_' + Date.now(),
                status: 'EMERGENCY',
                building_type: 'Test Building',
                temp: 75,
                heat: 85,
                smoke: 600,
                flame_detected: true,
                timestamp: new Date().toISOString(),
                user_id: 1,
                building_id: 1,
                geo_lat: 10.5376,
                geo_long: 122.8334,
                notified: 0,
                smoke_reading_id: null,
                flame_reading_id: null,
                device_id: 1,
                ml_confidence: 95.50,
                ml_prediction: 1,
                ml_fire_probability: 0.9550,
                ai_prediction: 'FIRE_DETECTED',
                ml_timestamp: new Date().toISOString()
            };
            
            console.log("🚨 Testing Emergency alert with data:", testData);
            processNewData(testData);
        }

        // Enhanced simulate device Emergency alert
        function simulateDeviceEmergency() {
            const deviceData = {
                id: 'device_' + Date.now(),
                status: 'EMERGENCY',
                building_type: 'Test Building',
                smoke: 10,
                temp: 1000,
                heat: 1000,
                flame_detected: 1,
                user_id: 1,
                building_id: 1,
                geo_lat: 10.5376,
                geo_long: 122.8334,
                notified: 0,
                smoke_reading_id: null,
                flame_reading_id: null,
                device_id: 4,
                ml_confidence: 98.75,
                ml_prediction: 1,
                ml_fire_probability: 0.9875,
                ai_prediction: 'FIRE_DETECTED',
                ml_timestamp: new Date().toISOString(),
                timestamp: new Date().toISOString()
            };
            
            console.log("🚨 Simulating device Emergency alert:", deviceData);
            processNewData(deviceData);
        }

        // Force trigger Emergency alert (for testing)
        function forceEmergencyAlert() {
            console.log("🚨 FORCE TRIGGERING EMERGENCY ALERT");
            const emergencyData = {
                id: 'force_' + Date.now(),
                status: 'EMERGENCY',
                building_type: 'Test Building',
                temp: 100,
                heat: 120,
                smoke: 800,
                flame_detected: 1,
                timestamp: new Date().toISOString()
            };
            
            showFireAlert(emergencyData);
        }

        // Manual Emergency check function (can be called from console)
        function manualEmergencyCheck() {
            console.log("🔍 MANUAL EMERGENCY CHECK TRIGGERED");
            fetchLatestDataFromServer();
            checkForEmergencyAlerts();
        }

        // Function to check database for EMERGENCY status and show alert
        function checkDatabaseForEmergency() {
            console.log("🔍 Checking LATEST status from database...");
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'check_emergency=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    console.log("📡 LATEST RECORD FROM DATABASE:", data.data);
                    console.log("📡 LATEST STATUS:", data.latest_status);
                    
                    // ONLY show alerts if the LATEST status is EMERGENCY
                    if (data.latest_status === 'EMERGENCY') {
                        console.log("🚨 LATEST STATUS IS EMERGENCY - SHOWING ALERT");
                        showFireAlert(data.data);
                    } else if (data.latest_status === 'ACKNOWLEDGED') {
                        console.log("✅ LATEST status is ACKNOWLEDGED - not showing alert");
                    } else if (data.latest_status === 'SAFE') {
                        console.log("🟢 LATEST status is SAFE - not showing alert");
                    } else if (data.latest_status === 'MONITORING') {
                        console.log("⚠️ LATEST status is MONITORING - not showing alert");
                    } else {
                        console.log(`❓ LATEST status is: ${data.latest_status} - not showing alert`);
                    }
                } else {
                    console.log("✅ No records found in database");
                }
            })
            .catch(error => {
                console.error("❌ Error checking database:", error);
            });
        }

        // Function to insert a test EMERGENCY record into database
        function insertTestEmergency() {
            console.log("🚨 Inserting test EMERGENCY record into database...");
            
            // This would require a new API endpoint, but for now we'll simulate it
            // You can manually insert this SQL into your database:
            // INSERT INTO fire_data (status, building_type, smoke, temp, heat, flame_detected, user_id, building_id, geo_lat, geo_long, ml_confidence, ml_prediction, ml_fire_probability, ai_prediction) 
            // VALUES ('EMERGENCY', 'Test Building', 800, 95, 110, 1, 1, 1, 10.5376, 122.8334, 98.50, 1, 0.9850, 'FIRE_DETECTED');
            
            Swal.fire({
                title: 'Test EMERGENCY Record',
                html: `
                    <p>To test the alarm system, manually insert this SQL into your database:</p>
                    <textarea style="width: 100%; height: 100px; font-family: monospace; font-size: 10px;" readonly>
INSERT INTO fire_data (status, building_type, smoke, temp, heat, flame_detected, user_id, building_id, geo_lat, geo_long, ml_confidence, ml_prediction, ml_fire_probability, ai_prediction) 
VALUES ('EMERGENCY', 'Test Building', 800, 95, 110, 1, 1, 1, 10.5376, 122.8334, 98.50, 1, 0.9850, 'FIRE_DETECTED');
                    </textarea>
                    <p style="margin-top: 10px; font-size: 12px;">After inserting, click "DB CHECK" button to trigger the alarm.</p>
                `,
                    icon: 'info',
                confirmButtonText: 'OK',
                width: '600px'
            });
        }

        // Test audio system
        function testAudioSystem() {
            console.log("🔊 Testing audio system...");
            
            // Try to play a short test sound
            const testSound = new Audio(ALARM_SOUND_URL);
            testSound.volume = 0.3;
            testSound.currentTime = 0;
            
            testSound.play().then(() => {
                console.log("🔊 Audio test successful!");
                // Audio test success modal removed
                
                // Stop the test sound after 2 seconds
                setTimeout(() => {
                    testSound.pause();
                    testSound.currentTime = 0;
                }, 2000);
            }).catch(e => {
                console.log("🔊 Audio test failed:", e);
                // Audio test failure modal removed
            });
        }

        // Test SMS functionality
        function testSMS() {
            console.log("📱 Testing SMS functionality...");
            
            Swal.fire({
                title: 'SMS Test',
                text: 'Sending test SMS to all active firefighters...',
                icon: 'info',
                showConfirmButton: false,
                allowOutsideClick: false
            });
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'test_sms=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'SMS Test Result',
                html: `
                            <p><strong>Status:</strong> ${data.test_sms_sent ? '✅ Success' : '❌ Failed'}</p>
                            <p><strong>Recipients:</strong> ${data.recipients_count}</p>
                            <p><strong>Message:</strong> ${data.message}</p>
                            ${data.recipients ? `<p><strong>Phone Numbers:</strong> ${data.recipients.join(', ')}</p>` : ''}
                        `,
                        icon: data.test_sms_sent ? 'success' : 'warning',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'SMS Test Failed',
                        text: data.error || 'Unknown error occurred',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error("SMS test error:", error);
                Swal.fire({
                    title: 'SMS Test Error',
                    text: 'Network error occurred while testing SMS',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Get SMS recipients info
        function getSMSRecipients() {
            console.log("📱 Getting SMS recipients info...");
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'get_sms_recipients=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let recipientsHtml = `
                        <p><strong>Total Recipients:</strong> ${data.recipients_count}</p>
                        <p><strong>Valid Phone Numbers:</strong> ${data.valid_phones.join(', ')}</p>
                        <hr>
                        <h6>Firefighter Details:</h6>
                    `;
                    
                    data.firefighters.forEach(firefighter => {
                        const cleanPhone = cleanPhoneNumber(firefighter.phone);
                        const phoneStatus = cleanPhone ? '✅ Valid' : '❌ Invalid';
                        const availabilityStatus = firefighter.availability == 1 ? '✅ Available' : '❌ Unavailable';
                        recipientsHtml += `
                            <p><strong>${firefighter.name}</strong> (ID: ${firefighter.id})<br>
                            Phone: ${firefighter.phone} - ${phoneStatus}<br>
                            Availability: ${availabilityStatus}</p>
                        `;
                    });
                    
                    Swal.fire({
                        title: 'SMS Recipients Info',
                        html: recipientsHtml,
                        icon: 'info',
                        width: '600px',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error',
                        text: data.error || 'Failed to get recipients info',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error("Get recipients error:", error);
                Swal.fire({
                    title: 'Error',
                    text: 'Network error occurred',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Clean phone number (JavaScript version)
        function cleanPhoneNumber(phone) {
            // Remove all non-digit characters
            const clean = phone.replace(/[^0-9]/g, '');
            
            // Handle Philippine phone numbers
            if (clean.length === 11 && clean.startsWith('09')) {
                return clean.substring(1);
            } else if (clean.length === 10 && clean.startsWith('9')) {
                return clean;
            } else if (clean.length === 12 && clean.startsWith('639')) {
                return clean.substring(2);
            }
            
            // If it's already a valid 10-digit number starting with 9
            if (clean.length === 10 && clean.startsWith('9')) {
                return clean;
            }
            
            return null; // Invalid format
        }

        // Make test functions globally accessible
        window.testEmergencyAlert = testEmergencyAlert;
        window.simulateDeviceEmergency = simulateDeviceEmergency;
        window.forceEmergencyAlert = forceEmergencyAlert;
        window.manualEmergencyCheck = manualEmergencyCheck;
        window.checkForNewEmergenciesFromServer = checkForNewEmergenciesFromServer;
        window.getAllActiveEmergencies = getAllActiveEmergencies;
        window.testAudioSystem = testAudioSystem;
        window.testSMS = testSMS;
        window.getSMSRecipients = getSMSRecipients;
        window.checkDatabaseForEmergency = checkDatabaseForEmergency; // Make new function globally accessible
        window.insertTestEmergency = insertTestEmergency; // Make new function globally accessible
        window.clearSuppressedAlerts = clearSuppressedAlerts; // Make suppression function globally accessible

        // Initialize on page load
        window.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
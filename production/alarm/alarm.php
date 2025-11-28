<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
date_default_timezone_set('Asia/Manila');

function sendJsonError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function sendJsonSuccess($data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

// Load database connection
$dbPaths = [__DIR__ . '/../../../db/db.php', __DIR__ . '/../../db/db.php', __DIR__ . '/../db/db.php'];
$dbLoaded = false;
foreach ($dbPaths as $dbPath) {
    if (file_exists($dbPath)) {
        require_once($dbPath);
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded) {
    sendJsonError("Database connection file not found");
}

$smsConfig = [
    'apiKey' => '6PLX3NFL2A2FLQ81RI7X6C4PJP68ANLJNYQ7XAR6',
    'device' => 'd8d8e6131b00f1a4',
    'url' => 'https://sms.pagenet.info/api/v1/sms/send'
];

try {
    $pdo = getDatabaseConnection();
    $pdo->exec("SET time_zone = '+08:00'");
} catch (Exception $e) {
    sendJsonError("Database connection error: " . $e->getMessage());
}

function validateSmsMessage($message, $maxLength = 1600) {
    $message = preg_replace('/\s+/', ' ', trim($message));
    $message = str_replace(["\r\n", "\r", "\n"], "\n", $message);
    
    if (strlen($message) > $maxLength) {
        $truncated = substr($message, 0, $maxLength - 50);
        $lastNewline = strrpos($truncated, "\n");
        if ($lastNewline !== false) {
            $truncated = substr($truncated, 0, $lastNewline);
        }
        $message = $truncated . "\n\n[Message truncated]";
    }
    return $message;
}

function sendSms($recipients, $message) {
    global $smsConfig;
    if (empty($recipients)) return false;

    $successCount = 0;
    $failedCount = 0;
    $results = [];
    
    // Use multi-curl for parallel requests
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    
    foreach ($recipients as $index => $recipient) {
        $cleanPhone = cleanPhoneNumber($recipient);
        if (!$cleanPhone) {
            $failedCount++;
            continue;
        }

        $params = [
            'message' => validateSmsMessage($message),
            'mobile_number' => $cleanPhone,
            'device' => $smsConfig['device']
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $smsConfig['url'],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => ["Content-Type: application/x-www-form-urlencoded", "apikey: {$smsConfig['apiKey']}"],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 15, // Reduced timeout for faster response
            CURLOPT_CONNECTTIMEOUT => 5
        ]);
        
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$index] = $ch;
    }
    
    // Execute all requests in parallel
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);
    
    // Process results
    foreach ($curlHandles as $index => $ch) {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if (!$error && $httpCode == 200) {
            $successCount++;
            $results[] = ['phone' => $recipients[$index], 'status' => 'success'];
        } else {
            $failedCount++;
            $results[] = ['phone' => $recipients[$index], 'status' => 'failed', 'error' => $error];
        }
        
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    
    curl_multi_close($multiHandle);
    
    return [
        'success' => $successCount > 0,
        'success_count' => $successCount,
        'failed_count' => $failedCount,
        'total_count' => count($recipients),
        'results' => $results
    ];
}

function cleanPhoneNumber($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($clean) == 11 && substr($clean, 0, 2) == '09') {
        return substr($clean, 1);
    } elseif (strlen($clean) == 10 && substr($clean, 0, 1) == '9') {
        return $clean;
    } elseif (strlen($clean) == 12 && substr($clean, 0, 3) == '639') {
        return substr($clean, 2);
    }
    return (strlen($clean) == 10 && substr($clean, 0, 1) == '9') ? $clean : false;
}

function reverseGeocode($latitude, $longitude) {
    if (!$latitude || !$longitude) {
        return null;
    }
    
    // Use OpenStreetMap Nominatim API (free, no API key required)
    // Add delay to respect rate limits (max 1 request per second)
    static $lastRequestTime = 0;
    $timeSinceLastRequest = microtime(true) - $lastRequestTime;
    if ($timeSinceLastRequest < 1.0) {
        usleep((1.0 - $timeSinceLastRequest) * 1000000);
    }
    
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($latitude) . "&lon=" . urlencode($longitude) . "&zoom=18&addressdetails=1";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'FireAlertSystem/1.0', // Required by Nominatim
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    $lastRequestTime = microtime(true);
    
    if ($error || $httpCode !== 200) {
        error_log("Reverse geocoding error: " . ($error ?: "HTTP $httpCode"));
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['display_name'])) {
        return null;
    }
    
    // Format address nicely
    $address = $data['display_name'];
    
    // Try to extract more readable format from address components
    if (isset($data['address'])) {
        $addr = $data['address'];
        $parts = [];
        
        if (isset($addr['house_number']) && isset($addr['road'])) {
            $parts[] = $addr['house_number'] . ' ' . $addr['road'];
        } elseif (isset($addr['road'])) {
            $parts[] = $addr['road'];
        }
        
        if (isset($addr['suburb'])) {
            $parts[] = $addr['suburb'];
        } elseif (isset($addr['neighbourhood'])) {
            $parts[] = $addr['neighbourhood'];
        }
        
        if (isset($addr['city'])) {
            $parts[] = $addr['city'];
        } elseif (isset($addr['town'])) {
            $parts[] = $addr['town'];
        } elseif (isset($addr['municipality'])) {
            $parts[] = $addr['municipality'];
        }
        
        if (isset($addr['state'])) {
            $parts[] = $addr['state'];
        }
        
        if (isset($addr['postcode'])) {
            $parts[] = $addr['postcode'];
        }
        
        if (!empty($parts)) {
            $address = implode(', ', $parts);
        }
    }
    
    return $address;
}

function getFireDataDetails($fireDataId) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT fd.*, u.fullname as user_name, u.contact_number as user_phone, u.device_number,
                   b.building_name, b.address as building_address, br.barangay_name
            FROM fire_data fd
            LEFT JOIN users u ON fd.user_id = u.user_id
            LEFT JOIN buildings b ON fd.building_id = b.id
            LEFT JOIN barangay br ON fd.barangay_id = br.id
            WHERE fd.id = ?
        ");
        $stmt->execute([$fireDataId]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Error getting fire data details: " . $e->getMessage());
        return null;
    }
}

function getActiveFirefighters() {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT phone, name, id FROM firefighters 
            WHERE availability = 1 AND phone IS NOT NULL AND phone != '' AND LENGTH(TRIM(phone)) >= 10
        ");
        $stmt->execute();
        $firefighters = $stmt->fetchAll();
        
        $validPhones = [];
        foreach ($firefighters as $firefighter) {
            $cleanPhone = cleanPhoneNumber($firefighter['phone']);
            if ($cleanPhone) $validPhones[] = $cleanPhone;
        }
        return $validPhones;
    } catch (PDOException $e) {
        error_log("Error fetching firefighters: " . $e->getMessage());
        return [];
    }
}

function sendAcknowledgmentSms($fireDataId) {
    global $smsConfig, $pdo;
    
    $fireData = getFireDataDetails($fireDataId);
    if (!$fireData) return false;
    
    // Concise message format with key words only
    $location = $fireData['building_name'] ?: $fireData['building_type'];
    $userName = $fireData['user_name'] ?: 'Unknown';
    $deviceName = $fireData['device_number'] ?: 'Unknown';
    $barangay = $fireData['barangay_name'] ?: 'Unknown';
    $detectedTime = date('M d, h:i A', strtotime($fireData['timestamp']));
    $acknowledgedTime = date('M d, h:i A');
    
    $message = "🚨 FIRE ALERT ACKNOWLEDGED\n";
    $message .= "Status: " . $fireData['status'] . "\n";
    $message .= "Location: " . $location . "\n";
    $message .= "Barangay: " . $barangay . "\n";
    $message .= "User: " . $userName . "\n";
    $message .= "Device: " . $deviceName . "\n";
    $message .= "Detected: " . $detectedTime . "\n";
    $message .= "Acknowledged: " . $acknowledgedTime;
    
    $results = [];
    
    // Send to verified user phone numbers
    $stmt = $pdo->prepare("SELECT phone_number FROM user_phone_numbers WHERE user_id = ? AND verified = 1");
    $stmt->execute([$fireData['user_id']]);
    $userPhones = array_column($stmt->fetchAll(), 'phone_number');
    if (!empty($userPhones)) {
        $userSmsResult = sendSms($userPhones, $message);
        $results['user_sms'] = [
            'success' => $userSmsResult['success'],
            'count' => $userSmsResult['total_count'],
            'success_count' => $userSmsResult['success_count'],
            'failed_count' => $userSmsResult['failed_count'],
            'results' => $userSmsResult['results']
        ];
    }
    
    // Send to all firefighters
    $firefighterPhones = getActiveFirefighters();
    if (!empty($firefighterPhones)) {
        $firefighterSmsResult = sendSms($firefighterPhones, $message);
        $results['firefighters_sms'] = [
            'success' => $firefighterSmsResult['success'],
            'count' => $firefighterSmsResult['total_count'],
            'success_count' => $firefighterSmsResult['success_count'],
            'failed_count' => $firefighterSmsResult['failed_count'],
            'results' => $firefighterSmsResult['results']
        ];
    }
    
    return $results;
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (isset($_POST['acknowledge'])) {
            $fireDataId = filter_input(INPUT_POST, 'fire_data_id', FILTER_VALIDATE_INT);
            if (!$fireDataId || $fireDataId <= 0) {
                sendJsonError("Invalid fire_data_id");
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT id, status FROM fire_data WHERE id = ? FOR UPDATE");
                $stmt->execute([$fireDataId]);
                $fireData = $stmt->fetch();
                
                if (!$fireData) {
                    throw new Exception("No fire_data record found with ID: $fireDataId");
                }

                $stmt = $pdo->prepare("UPDATE fire_data SET status = 'ACKNOWLEDGED', acknowledged_at_time = CURTIME() WHERE id = ?");
                $stmt->execute([$fireDataId]);
                
                $pdo->commit();
                
                $smsResults = sendAcknowledgmentSms($fireDataId);
                
                sendJsonSuccess([
                    'fire_data_id' => $fireDataId,
                    'old_status' => $fireData['status'],
                    'new_status' => 'ACKNOWLEDGED',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'sms_results' => $smsResults
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        
        if (isset($_POST['get_latest_data'])) {
            $stmt = $pdo->prepare("SELECT * FROM fire_data ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            sendJsonSuccess(['data' => $stmt->fetch()]);
        }
        
        if (isset($_POST['check_emergency'])) {
            $stmt = $pdo->prepare("SELECT * FROM fire_data ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            $latestData = $stmt->fetch();
            
            sendJsonSuccess([
                'has_emergency' => $latestData && $latestData['status'] === 'EMERGENCY',
                'data' => $latestData,
                'latest_status' => $latestData['status'] ?? null,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
        
        if (isset($_POST['test_sms'])) {
            $firefightersPhones = getActiveFirefighters();
            if (!empty($firefightersPhones)) {
                $testMessage = "TEST SMS: Fire Alert System working at " . date('Y-m-d H:i:s');
                $smsResult = sendSms($firefightersPhones, $testMessage);
                
                sendJsonSuccess([
                    'test_sms_sent' => $smsResult['success'],
                    'recipients_count' => $smsResult['total_count'],
                    'success_count' => $smsResult['success_count'],
                    'failed_count' => $smsResult['failed_count'],
                    'recipients' => $firefightersPhones,
                    'message' => $testMessage,
                    'detailed_results' => $smsResult['results']
                ]);
            } else {
                sendJsonError('No active firefighters found', 400);
            }
        }
        
        if (isset($_POST['reverse_geocode'])) {
            $latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
            $longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
            
            if ($latitude === false || $longitude === false) {
                sendJsonError("Invalid latitude or longitude");
            }
            
            $address = reverseGeocode($latitude, $longitude);
            
            if ($address) {
                sendJsonSuccess(['address' => $address]);
            } else {
                sendJsonError("Could not retrieve address for coordinates");
            }
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendJsonError($e->getMessage());
    }
}

// Get initial data
try {
    $stmt = $pdo->prepare("SELECT * FROM fire_data ORDER BY timestamp DESC LIMIT 1");
    $stmt->execute();
    $initialData = $stmt->fetch();
} catch (PDOException $e) {
    $initialData = null;
}
?>

<?php 
$headerPaths = [__DIR__ . '/../components/header.php', __DIR__ . '/../../components/header.php', __DIR__ . '/components/header.php'];
$headerLoaded = false;
foreach ($headerPaths as $headerPath) {
    if (file_exists($headerPath)) {
        include($headerPath);
        $headerLoaded = true;
        break;
    }
}

if (!$headerLoaded) {
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
            --color-acknowledged: #ffc107;
            --color-safe: #28a745;
            --color-disconnected: #6c757d;
            --color-connected: #28a745;
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

        .connection-status.connected { background: var(--color-connected); }

        .sensor-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 4px;
            margin: 4px 0;
        }

        .sensor-box {
            padding: 4px;
            border-radius: 4px;
            background: #f8f9fa;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .sensor-title {
            font-size: 0.65rem;
            color: #6c757d;
            margin-bottom: 1px;
            font-weight: bold !important;
        }

        .sensor-reading {
            font-size: 1.1rem;
            font-weight: bold !important;
        }
        
        .sensor-reading.flame-detected {
            color: #dc3545;
            font-size: 1.3rem;
            font-weight: bold !important;
        }

        .sensor-critical { color: var(--color-emergency); font-weight: bold; }
        .sensor-warning { color: var(--color-acknowledged); font-weight: bold; }

        .alert-details {
            text-align: left;
            max-width: 100%;
            font-size: 0.85rem;
            line-height: 1.2;
            font-weight: bold !important;
        }

        .alert-details p { 
            margin: 2px 0; 
            font-weight: bold !important;
            font-size: 0.85rem;
            line-height: 1.2;
        }
        
        .alert-details p * {
            font-weight: bold !important;
            font-size: 0.85rem;
        }
        
        .alert-details .sensor-value {
            font-weight: bold !important;
            font-size: 0.85rem;
        }
        
        .alert-details .sensor-grid * {
            font-weight: bold !important;
        }
        
        .alert-details .ml-analysis-section * {
            font-weight: bold !important;
            font-size: 0.85rem;
        }

        .alert-divider {
            margin: 4px 0;
            border: 0;
            height: 1px;
            background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(0,0,0,0.1), rgba(0,0,0,0));
        }

        /* Toast notification styles for faster loading */
        .swal2-toast-popup {
            width: 300px !important;
            padding: 8px 16px !important;
            font-size: 0.9rem !important;
            transition: all 0.2s ease-in-out !important;
        }

        .swal2-toast-fast {
            animation: slideInRight 0.2s ease-out !important;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .swal2-toast-title {
            font-size: 0.9rem !important;
            font-weight: bold !important;
            margin-bottom: 4px !important;
        }

        .swal2-toast-content {
            font-size: 0.8rem !important;
            line-height: 1.2 !important;
        }

        /* Faster progress bar animation */
        .swal2-timer-progress-bar {
            transition: width 0.1s linear !important;
        }

        /* Emergency alert styles - ensure it's always on top */
        .emergency-alert-container {
            z-index: 99999 !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }

        .emergency-alert-popup {
            z-index: 99999 !important;
            position: fixed !important;
            top: 50% !important;
            left: 50% !important;
            transform: translate(-50%, -50%) !important;
            margin: 0 !important;
            max-width: 90% !important;
        }

        .emergency-alert-popup .swal2-title {
            margin-bottom: 0.5rem !important;
            padding-bottom: 0.25rem !important;
        }

        .emergency-alert-popup .swal2-content {
            margin-top: 0.5rem !important;
            padding-top: 0 !important;
        }

        .emergency-alert-popup .swal2-actions {
            margin-top: 0.75rem !important;
            padding-top: 0.5rem !important;
        }

        /* Fire particles canvas */
        .fire-particles-canvas {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            height: 100% !important;
            pointer-events: none !important;
            z-index: 99998 !important;
        }

        /* Prevent body scroll when alert is active */
        body.swal2-shown.swal2-height-auto {
            overflow: hidden !important;
        }
        
        /* Fire icon animation */
        .emergency-alert-popup .swal2-icon {
            animation: fireGlow 1.5s ease-in-out infinite !important;
        }

        /* Emergency title animation - light red to dark red with transitions */
        .emergency-alert-popup .swal2-title.emergency-title-animated,
        .emergency-alert-popup .swal2-title .emergency-title-animated,
        .emergency-title-animated {
            color: #dc3545 !important;
            font-weight: bold !important;
            animation: emergencyTitleAnimation 3s cubic-bezier(0.4, 0, 0.6, 1) infinite !important;
            display: inline-block;
            transition: color 0.5s cubic-bezier(0.4, 0, 0.6, 1), transform 0.5s cubic-bezier(0.4, 0, 0.6, 1) !important;
        }

        @keyframes emergencyTitleAnimation {
            0% {
                color: #ff4444; /* Light red */
                transform: scale(1);
            }
            12.5% {
                color: #ff3333;
                transform: scale(1.01);
            }
            25% {
                color: #cc0000;
                transform: scale(1.02);
            }
            37.5% {
                color: #aa0000;
                transform: scale(1.035);
            }
            50% {
                color: #8b0000; /* Dark red */
                transform: scale(1.05);
            }
            62.5% {
                color: #aa0000;
                transform: scale(1.035);
            }
            75% {
                color: #cc0000;
                transform: scale(1.02);
            }
            87.5% {
                color: #ff3333;
                transform: scale(1.01);
            }
            100% {
                color: #ff4444; /* Light red */
                transform: scale(1);
            }
        }
    </style>
</head>
<body>
    <div class="connection-status" id="connectionStatus">
        <i class="fas fa-sync-alt"></i>
        <span>Connecting...</span>
    </div>
    
    <div id="audioStatus" style="position: fixed; bottom: 8px; left: 8px; padding: 6px 12px; border-radius: 16px; font-size: 0.75rem; background: #6c757d; color: white; z-index: 10000; display: flex; align-items: center; gap: 6px;">
        <i class="fas fa-volume-mute"></i>
        <span>Audio: Disabled</span>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const POLLING_INTERVAL = 5000;
        const ALARM_SOUND_URL = 'alarm.mp3';
        
        const CRITICAL_THRESHOLDS = { temp: 50, heat: 60, smoke: 500 };
        const WARNING_THRESHOLDS = { temp: 40, heat: 50, smoke: 300 };

        const appState = {
            currentFireData: <?php echo json_encode($initialData); ?>,
            isAlarmPlaying: false,
            currentAlert: null,
            pollingInterval: null,
            alarmAudio: null,
            audioEnabled: false,
            fireParticles: null,
            fireCanvas: null,
            fireCtx: null,
            fireAnimationId: null
        };
        
        const elements = {
            connectionStatus: document.getElementById('connectionStatus'),
            audioStatus: document.getElementById('audioStatus')
        };

        function init() {
            console.log("Initializing Fire Alert System");
            initAudio();
            setupEventListeners();
            startPolling();
            
            // Show alarm FIRST before any mapping interactions
            if (appState.currentFireData && appState.currentFireData.status === 'EMERGENCY') {
                // Immediate check - don't wait
                checkAndShowAlert(appState.currentFireData);
            }
            
            setTimeout(preloadAndTestAudio, 2000);
        }

        function initAudio() {
            appState.alarmAudio = new Audio(ALARM_SOUND_URL);
            appState.alarmAudio.loop = true;
            appState.alarmAudio.preload = 'auto';
            appState.alarmAudio.volume = 0.8;
            
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (AudioContext) {
                    appState.audioContext = new AudioContext();
                }
            } catch (e) {
                console.log("Audio context creation failed:", e);
            }
            
            setupAudioEnablingStrategies();
        }

        function setupAudioEnablingStrategies() {
            const enableAudioOnInteraction = () => {
                enableAudioContext();
                removeInteractionListeners();
            };
            
            const removeInteractionListeners = () => {
                document.removeEventListener('click', enableAudioOnInteraction);
                document.removeEventListener('keydown', enableAudioOnInteraction);
                document.removeEventListener('touchstart', enableAudioOnInteraction);
            };
            
            document.addEventListener('click', enableAudioOnInteraction, { once: true });
            document.addEventListener('keydown', enableAudioOnInteraction, { once: true });
            document.addEventListener('touchstart', enableAudioOnInteraction, { once: true });
            
            setTimeout(() => trySilentAudioPlayback(), 1000);
        }

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

        function enableAudioContext() {
            if (appState.audioContext && appState.audioContext.state === 'suspended') {
                appState.audioContext.resume().then(() => {
                    appState.audioEnabled = true;
                    updateAudioStatus(true);
                }).catch(e => console.log("Audio context resume failed:", e));
            } else {
                appState.audioEnabled = true;
                updateAudioStatus(true);
            }
        }

        function trySilentAudioPlayback() {
            try {
                const silentAudio = new Audio();
                silentAudio.src = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
                silentAudio.volume = 0;
                silentAudio.muted = true;
                
                silentAudio.play().then(() => {
                    appState.audioEnabled = true;
                    updateAudioStatus(true);
                    silentAudio.pause();
                }).catch(e => console.log("Silent audio playback failed:", e));
            } catch (e) {
                console.log("Silent audio creation failed:", e);
            }
        }

        function startAlarm() {
            if (!appState.isAlarmPlaying) {
                appState.isAlarmPlaying = true;
                
                if (appState.audioEnabled) {
                    playAlarmDirect();
                } else {
                    enableAudioAndPlay();
                }
            }
        }

        function playAlarmDirect() {
            appState.alarmAudio.currentTime = 0;
            appState.alarmAudio.volume = 0.8;
            appState.alarmAudio.muted = false;
            
            appState.alarmAudio.play().then(() => {
                console.log("Alarm started successfully");
            }).catch(e => {
                console.log("Direct play failed, trying fallback:", e);
                playAlarmFallback();
            });
        }

        function enableAudioAndPlay() {
            if (appState.audioContext && appState.audioContext.state === 'suspended') {
                appState.audioContext.resume().then(() => {
                    playAlarmDirect();
                }).catch(e => {
                    playAlarmFallback();
                });
            } else {
                trySilentAudioPlayback();
                setTimeout(() => playAlarmDirect(), 100);
            }
        }

        function playAlarmFallback() {
            appState.alarmAudio.muted = true;
            appState.alarmAudio.play().then(() => {
                setTimeout(() => {
                    appState.alarmAudio.muted = false;
                }, 200);
            }).catch(e => {
                appState.alarmAudio.volume = 0.3;
                appState.alarmAudio.muted = false;
                appState.alarmAudio.play().catch(e2 => {
                    console.log("Low volume playback failed:", e2);
                });
            });
        }

        function preloadAndTestAudio() {
            const testAudio = new Audio(ALARM_SOUND_URL);
            testAudio.volume = 0.1;
            testAudio.currentTime = 0;
            
            testAudio.play().then(() => {
                appState.audioEnabled = true;
                updateAudioStatus(true);
                setTimeout(() => {
                    testAudio.pause();
                    testAudio.currentTime = 0;
                }, 100);
            }).catch(e => {
                updateAudioStatus(false);
            });
        }

        function stopAlarm() {
            if (appState.isAlarmPlaying) {
                appState.alarmAudio.pause();
                appState.alarmAudio.currentTime = 0;
                appState.isAlarmPlaying = false;
            }
        }

        function setupEventListeners() {
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    fetchLatestDataFromServer();
                    if (appState.currentFireData && appState.currentFireData.status === 'EMERGENCY') {
                        setTimeout(() => checkForEmergencyAlerts(), 500);
                        if (appState.currentAlert) {
                            initFireParticles();
                        }
                    }
                } else {
                    stopAlarm();
                    stopFireParticles();
                }
            });
            
            document.addEventListener('click', enableAudioOnClick, { once: true });
            document.addEventListener('keydown', enableAudioOnClick, { once: true });
            document.addEventListener('touchstart', enableAudioOnClick, { once: true });
            
            if (elements.audioStatus) {
                elements.audioStatus.addEventListener('click', () => {
                    if (!appState.audioEnabled) {
                        enableAudioContext();
                        setTimeout(() => playAlarmDirect(), 500);
                    }
                });
                elements.audioStatus.style.cursor = 'pointer';
                elements.audioStatus.title = 'Click to enable audio';
            }
        }

        function enableAudioOnClick() {
            const silentAudio = new Audio();
            silentAudio.src = 'data:audio/wav;base64,UklGRigAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
            silentAudio.volume = 0;
            
            silentAudio.play().then(() => {
                if (appState.isAlarmPlaying && appState.alarmAudio.paused) {
                    appState.alarmAudio.play().catch(e => console.log("Still can't play alarm:", e));
                }
            }).catch(e => console.log("Silent audio failed:", e));
        }

        function startPolling() {
            updateConnectionStatus('Connected (Auto-Detection Every 5s)');
            
            if (appState.pollingInterval) {
                clearInterval(appState.pollingInterval);
            }
            
            appState.pollingInterval = setInterval(() => {
                fetchLatestDataFromServer();
            }, POLLING_INTERVAL);
            
            fetchLatestDataFromServer();
        }

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
        }

        function processNewData(newData) {
            if (!newData) return false;
            
            const isNewAlert = !appState.currentFireData || 
                              appState.currentFireData.id !== newData.id ||
                              appState.currentFireData.status !== newData.status;
            
            if (isNewAlert) {
                appState.currentFireData = newData;
                
                if (newData.status === 'EMERGENCY') {
                    // Prevent map interactions before showing alert
                    preventMapInteractions();
                    showFireAlert(newData);
                    return true;
                }
            }
            
            return false;
        }

        function checkAndShowAlert(data) {
            if (!data) return false;
            
            const isEmergency = data.status === 'EMERGENCY';
            
            if (isEmergency) {
                // Prevent any other page interactions first
                preventMapInteractions();
                // Show alert immediately
                showFireAlert(data);
                return true;
            }
            
            return false;
        }

        function initFireParticles() {
            // Create canvas for fire particles
            if (!appState.fireCanvas) {
                appState.fireCanvas = document.createElement('canvas');
                appState.fireCanvas.className = 'fire-particles-canvas';
                document.body.appendChild(appState.fireCanvas);
                appState.fireCtx = appState.fireCanvas.getContext('2d');
                
                // Set canvas size
                function resizeCanvas() {
                    appState.fireCanvas.width = window.innerWidth;
                    appState.fireCanvas.height = window.innerHeight;
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);
            }
            
            // Initialize particles
            appState.fireParticles = [];
            const particleCount = 150;
            
            for (let i = 0; i < particleCount; i++) {
                appState.fireParticles.push({
                    x: Math.random() * appState.fireCanvas.width,
                    y: Math.random() * appState.fireCanvas.height,
                    vx: (Math.random() - 0.5) * 0.5,
                    vy: -Math.random() * 2 - 1,
                    life: Math.random(),
                    decay: Math.random() * 0.02 + 0.01,
                    size: Math.random() * 3 + 1,
                    color: Math.random() > 0.5 ? '#ff4500' : '#ff8c00'
                });
            }
            
            animateFireParticles();
        }

        function animateFireParticles() {
            if (!appState.fireCtx || !appState.fireParticles) return;
            
            const ctx = appState.fireCtx;
            const canvas = appState.fireCanvas;
            const popup = document.querySelector('.emergency-alert-popup');
            
            // Clear canvas with fade effect
            ctx.fillStyle = 'rgba(0, 0, 0, 0.15)';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            
            // Get popup position for particle generation
            let popupX = canvas.width / 2;
            let popupY = canvas.height / 2;
            let popupWidth = 500;
            let popupHeight = 400;
            
            if (popup) {
                const rect = popup.getBoundingClientRect();
                popupX = rect.left + rect.width / 2;
                popupY = rect.top + rect.height;
                popupWidth = rect.width;
                popupHeight = rect.height;
            }
            
            // Update and draw particles
            for (let i = appState.fireParticles.length - 1; i >= 0; i--) {
                const p = appState.fireParticles[i];
                
                // Reset particles that are off screen or dead
                if (p.life <= 0 || p.y < -10 || p.x < -10 || p.x > canvas.width + 10 || p.y > canvas.height + 10) {
                    // Respawn at bottom of popup (fire source)
                    p.x = popupX + (Math.random() - 0.5) * popupWidth * 0.8;
                    p.y = popupY + Math.random() * 10;
                    p.vx = (Math.random() - 0.5) * 0.8;
                    p.vy = -Math.random() * 3 - 1.5;
                    p.life = 1;
                    p.decay = Math.random() * 0.015 + 0.008;
                    p.size = Math.random() * 4 + 2;
                    // Color gradient: yellow at bottom, red in middle, orange at top
                    const colorRand = Math.random();
                    if (colorRand < 0.3) {
                        p.color = '#ffff00'; // Yellow (hot)
                    } else if (colorRand < 0.7) {
                        p.color = '#ff4500'; // Red-orange
                    } else {
                        p.color = '#ff8c00'; // Orange
                    }
                }
                
                // Update particle physics
                p.x += p.vx;
                p.y += p.vy;
                p.vx += (Math.random() - 0.5) * 0.1; // Random horizontal movement
                p.vy -= 0.05; // Upward force (fire rises)
                p.life -= p.decay;
                p.size *= 0.98; // Shrink as it rises
                
                // Draw particle with glow
                const alpha = Math.max(0, p.life);
                ctx.save();
                ctx.globalAlpha = alpha;
                
                // Create gradient for particle
                const gradient = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.size);
                gradient.addColorStop(0, p.color);
                gradient.addColorStop(0.5, p.color + '80');
                gradient.addColorStop(1, 'transparent');
                
                ctx.fillStyle = gradient;
                ctx.shadowBlur = 15;
                ctx.shadowColor = p.color;
                ctx.beginPath();
                ctx.arc(p.x, p.y, p.size, 0, Math.PI * 2);
                ctx.fill();
                
                ctx.restore();
            }
            
            appState.fireAnimationId = requestAnimationFrame(animateFireParticles);
        }

        function stopFireParticles() {
            if (appState.fireAnimationId) {
                cancelAnimationFrame(appState.fireAnimationId);
                appState.fireAnimationId = null;
            }
            if (appState.fireCtx && appState.fireCanvas) {
                appState.fireCtx.clearRect(0, 0, appState.fireCanvas.width, appState.fireCanvas.height);
            }
            // Optionally remove canvas (but keep it for reuse)
            // if (appState.fireCanvas && appState.fireCanvas.parentNode) {
            //     appState.fireCanvas.parentNode.removeChild(appState.fireCanvas);
            //     appState.fireCanvas = null;
            //     appState.fireCtx = null;
            // }
        }

        function showFireAlert(data) {
            if (data.status !== 'EMERGENCY') return;
            
            const statusClass = data.status.toLowerCase().replace('_', '-');
            
            // Close any existing alert first
            if (appState.currentAlert) {
                Swal.close();
                appState.currentAlert = null;
            }
            
            const sensorGridHTML = createSensorGridHTML(data);
            
            const html = `
                <div class="alert-details" style="font-weight: bold !important; font-size: 0.85rem; line-height: 1.2;">
                    <p style="font-weight: bold !important; margin: 2px 0; font-size: 0.85rem; line-height: 1.2;"><strong>Status:</strong> <span class="status-badge status-${statusClass}" style="font-weight: bold !important;">${data.status}</span></p>
                    ${getGpsCoordinatesDisplay(data)}
                    <p style="font-weight: bold !important; margin: 2px 0; font-size: 0.85rem; line-height: 1.2;"><strong>Detected at:</strong> <span class="sensor-value" style="font-weight: bold !important; font-size: 0.85rem;">${formatTimestamp(data.timestamp)}</span></p>
                    ${sensorGridHTML}
                </div>
            `;
            
            const alertConfig = {
                title: '<span class="emergency-title-animated">🚨 EMERGENCY STATUS DETECTED</span>',
                html: html,
                icon: 'error',
                confirmButtonText: 'ACKNOWLEDGE',
                confirmButtonColor: 'var(--color-emergency)',
                showCancelButton: false,
                showCloseButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: true,
                backdrop: 'rgba(0,0,0,0.95)',
                width: '500px',
                padding: '1rem',
                focusConfirm: true,
                customClass: {
                    container: 'emergency-alert-container',
                    popup: 'emergency-alert-popup'
                },
                didOpen: () => {
                    // Apply red animated styling to the title with transitions
                    const titleElement = document.querySelector('.emergency-alert-popup .swal2-title');
                    if (titleElement) {
                        titleElement.classList.add('emergency-title-animated');
                        titleElement.style.color = '#dc3545';
                        titleElement.style.fontWeight = 'bold';
                        titleElement.style.transition = 'color 0.5s cubic-bezier(0.4, 0, 0.6, 1), transform 0.5s cubic-bezier(0.4, 0, 0.6, 1)';
                        titleElement.style.animation = 'emergencyTitleAnimation 3s cubic-bezier(0.4, 0, 0.6, 1) infinite';
                    }
                    
                    // Fetch and display readable address using reverse geocoding
                    const lat = data.gps_latitude || data.geo_lat;
                    const lng = data.gps_longitude || data.geo_long;
                    if (lat && lng) {
                        setTimeout(() => fetchAndDisplayAddress(lat, lng), 500);
                    }
                    
                    startAlarm();
                    // Prevent map interactions from closing the alert
                    preventMapInteractions();
                    // Start fire particles
                    setTimeout(() => initFireParticles(), 100);
                },
                willClose: () => {
                    stopAlarm();
                    stopFireParticles();
                    appState.currentAlert = null;
                    restoreMapInteractions();
                }
            };
            
            // Use setTimeout to ensure alert shows first, before any mapping interactions
            setTimeout(() => {
                appState.currentAlert = Swal.fire(alertConfig).then((result) => {
                    if (result.isConfirmed) {
                        stopAlarm();
                        stopFireParticles();
                        appState.currentAlert = null;
                        restoreMapInteractions();
                        if (appState.currentFireData && appState.currentFireData.id === data.id) {
                            appState.currentFireData.status = 'ACKNOWLEDGED';
                        }
                        acknowledgeAlert(data.id);
                    }
                });
            }, 100);
        }
        
        function preventMapInteractions() {
            // Add a flag to prevent map interactions
            window.emergencyAlertActive = true;
            
            // Prevent map zoom/pan events from interfering
            if (window.map && typeof window.map === 'object') {
                try {
                    window.map.scrollWheelZoom.disable();
                    window.map.doubleClickZoom.disable();
                    window.map.boxZoom.disable();
                    window.map.keyboard.disable();
                } catch (e) {
                    console.log("Map interaction prevention:", e);
                }
            }
        }
        
        function restoreMapInteractions() {
            window.emergencyAlertActive = false;
            
            // Restore map interactions
            if (window.map && typeof window.map === 'object') {
                try {
                    window.map.scrollWheelZoom.enable();
                    window.map.doubleClickZoom.enable();
                    window.map.boxZoom.enable();
                    window.map.keyboard.enable();
                } catch (e) {
                    console.log("Map interaction restoration:", e);
                }
            }
        }

        function createSensorGridHTML(data) {
            const tempClass = getSensorClass(data.temp, 'temp');
            const heatClass = getSensorClass(data.heat, 'heat');
            const smokeClass = getSensorClass(data.smoke, 'smoke');
            
            return `
                <div class="sensor-grid">
                    <div class="sensor-box">
                        <div class="sensor-title" style="font-weight: bold !important;">Temperature</div>
                        <div class="sensor-reading ${tempClass}" style="font-weight: bold !important;">${data.temp || 0}°C</div>
                    </div>
                    <div class="sensor-box">
                        <div class="sensor-title" style="font-weight: bold !important;">Heat Index</div>
                        <div class="sensor-reading ${heatClass}" style="font-weight: bold !important;">${data.heat || 0}°C</div>
                    </div>
                    <div class="sensor-box">
                        <div class="sensor-title" style="font-weight: bold !important;">Smoke Level</div>
                        <div class="sensor-reading ${smokeClass}" style="font-weight: bold !important;">${data.smoke || 0} ppm</div>
                    </div>
                    <div class="sensor-box">
                        <div class="sensor-title" style="font-weight: bold !important;">Flame Detected</div>
                        <div class="sensor-reading flame-detected" style="font-weight: bold !important; color: #dc3545; font-size: 1.3rem;">${data.flame_detected ? '✅ Yes' : '❌ No'}</div>
                    </div>
                </div>
                <div class="alert-divider"></div>
                <div class="ml-analysis-section" style="text-align: center;">
                    <h6 style="margin: 2px 0; color: #dc3545; font-weight: bold !important; font-size: 1.1rem; line-height: 1.2; text-align: center;">🤖 AI/ML Analysis</h6>
                    <div class="ml-grid" style="display: flex; justify-content: center; align-items: center; gap: 4px; margin: 2px 0;">
                        <div class="ml-box" style="padding: 4px; border-radius: 4px; background: #f8f9fa; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); min-width: 150px;">
                            <div class="ml-title" style="font-size: 0.9rem; color: #dc3545; margin-bottom: 1px; font-weight: bold !important;">AI Prediction</div>
                            <div class="ml-reading" style="font-size: 1.1rem; font-weight: bold !important; color: #dc3545;">${data.ai_prediction || 'N/A'}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function getSensorClass(value, type) {
            if (value >= CRITICAL_THRESHOLDS[type]) return 'sensor-critical';
            if (value >= WARNING_THRESHOLDS[type]) return 'sensor-warning';
            return '';
        }

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

        function formatGpsCoordinates(data) {
            // Prioritize gps_latitude/gps_longitude, fallback to geo_lat/geo_long
            let lat = data.gps_latitude || data.geo_lat;
            let lng = data.gps_longitude || data.geo_long;
            
            if (!lat || !lng) return null;
            
            // Format to 6 decimal places for accuracy
            const formattedLat = parseFloat(lat).toFixed(6);
            const formattedLng = parseFloat(lng).toFixed(6);
            
            return `${formattedLat}, ${formattedLng}`;
        }

        function getGpsCoordinatesDisplay(data) {
            const coords = formatGpsCoordinates(data);
            if (!coords) return '<p style="font-weight: bold !important; margin: 2px 0; font-size: 0.85rem; line-height: 1.2;"><strong>Device Location:</strong> <span class="sensor-value" style="font-weight: bold !important; font-size: 0.85rem;">N/A</span></p>';
            
            // Create a clickable Google Maps link
            const lat = data.gps_latitude || data.geo_lat;
            const lng = data.gps_longitude || data.geo_long;
            const mapsUrl = `https://www.google.com/maps?q=${lat},${lng}`;
            
            // Return with placeholder for address that will be fetched asynchronously
            return `<p id="device-location-display" style="font-weight: bold !important; margin: 2px 0; font-size: 0.85rem; line-height: 1.2;"><strong>Device Location:</strong><br>
                    <span class="sensor-value" style="display: inline-block; margin-top: 2px; font-weight: bold !important; font-size: 0.85rem; line-height: 1.2;">
                        <span id="device-address" style="color: #28a745; font-weight: bold !important; font-size: 0.85rem;">Loading address...</span>
                    </span></p>`;
        }

        function fetchAndDisplayAddress(latitude, longitude) {
            if (!latitude || !longitude) {
                const addressElement = document.getElementById('device-address');
                if (addressElement) {
                    addressElement.textContent = 'Address unavailable';
                    addressElement.style.color = '#6c757d';
                    addressElement.style.fontWeight = 'bold';
                    addressElement.style.fontStyle = 'italic';
                }
                return;
            }
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `reverse_geocode=1&latitude=${latitude}&longitude=${longitude}`
            })
            .then(response => response.json())
            .then(data => {
                const addressElement = document.getElementById('device-address');
                if (addressElement) {
                    if (data.success && data.address) {
                        addressElement.textContent = data.address;
                        addressElement.style.color = '#28a745';
                        addressElement.style.fontWeight = 'bold';
                        addressElement.style.fontStyle = 'normal';
                    } else {
                        addressElement.textContent = 'Address unavailable';
                        addressElement.style.color = '#6c757d';
                        addressElement.style.fontWeight = 'bold';
                        addressElement.style.fontStyle = 'italic';
                    }
                }
            })
            .catch(error => {
                console.error("Error fetching address:", error);
                const addressElement = document.getElementById('device-address');
                if (addressElement) {
                    addressElement.textContent = 'Address unavailable (network error)';
                    addressElement.style.color = '#6c757d';
                    addressElement.style.fontWeight = 'bold';
                    addressElement.style.fontStyle = 'italic';
                }
            });
        }

        function acknowledgeAlert(fireDataId) {
            stopAlarm();
            stopFireParticles();
            
            // Show immediate feedback
            showToast('⏳ Processing...', 'Acknowledging alert and sending SMS...', 'info');
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `acknowledge=1&fire_data_id=${fireDataId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (appState.currentFireData && appState.currentFireData.id == fireDataId) {
                        appState.currentFireData.status = 'ACKNOWLEDGED';
                    }
                    appState.currentAlert = null;
                    
                    if (data.sms_results) {
                        let userSmsSent = 0;
                        let firefighterSmsSent = 0;
                        let userSmsFailed = 0;
                        let firefighterSmsFailed = 0;
                        
                        // Process user SMS results
                        if (data.sms_results.user_sms) {
                            userSmsSent = data.sms_results.user_sms.success_count || 0;
                            userSmsFailed = data.sms_results.user_sms.failed_count || 0;
                        }
                        
                        // Process firefighter SMS results
                        if (data.sms_results.firefighters_sms) {
                            firefighterSmsSent = data.sms_results.firefighters_sms.success_count || 0;
                            firefighterSmsFailed = data.sms_results.firefighters_sms.failed_count || 0;
                        }
                        
                        // Show detailed SMS feedback with faster animations
                        setTimeout(() => {
                            if (userSmsSent > 0) {
                                showFastToast('✅ User SMS', `${userSmsSent} sent successfully`, 'success', 1500);
                            }
                            if (firefighterSmsSent > 0) {
                                showFastToast('✅ Firefighter SMS', `${firefighterSmsSent} sent successfully`, 'success', 1500);
                            }
                            if (userSmsFailed > 0) {
                                showFastToast('❌ User SMS Failed', `${userSmsFailed} failed to send`, 'error', 2000);
                            }
                            if (firefighterSmsFailed > 0) {
                                showFastToast('❌ Firefighter SMS Failed', `${firefighterSmsFailed} failed to send`, 'error', 2000);
                            }
                            
                            // Final success message
                            setTimeout(() => {
                                showFastToast('✅ Alert Acknowledged', 'All notifications sent', 'success', 2000);
                            }, 500);
                        }, 200);
                    } else {
                        showFastToast('✅ Alert Acknowledged', 'Fire alert acknowledged successfully', 'success', 2000);
                    }
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            })
            .catch(error => {
                console.error("Acknowledgment failed:", error);
                showFastToast('❌ Acknowledgment Failed', 'Failed to acknowledge the alert. Please try again.', 'error', 3000);
            });
        }

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

        function showToast(title, text, icon) {
            Swal.fire({
                title,
                text,
                icon,
                timer: 2000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                timerProgressBar: true,
                showCloseButton: true,
                customClass: {
                    popup: 'swal2-toast-popup',
                    title: 'swal2-toast-title',
                    content: 'swal2-toast-content'
                }
            });
        }

        function showFastToast(title, text, icon, duration = 1500) {
            Swal.fire({
                title,
                text,
                icon,
                timer: duration,
                showConfirmButton: false,
                toast: true,
                position: 'top-end',
                timerProgressBar: true,
                showCloseButton: true,
                customClass: {
                    popup: 'swal2-toast-popup swal2-toast-fast',
                    title: 'swal2-toast-title',
                    content: 'swal2-toast-content'
                },
                animation: false,
                didOpen: () => {
                    const popup = Swal.getPopup();
                    if (popup) {
                        popup.style.transition = 'all 0.2s ease-in-out';
                        popup.style.transform = 'translateX(0)';
                    }
                }
            });
        }

        function fetchLatestDataFromServer() {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'get_latest_data=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data) {
                    const isNewEmergency = !appState.currentFireData || 
                                         appState.currentFireData.status !== 'EMERGENCY' ||
                                         appState.currentFireData.id !== data.data.id;
                    
                    if (data.data.status === 'EMERGENCY' && isNewEmergency) {
                        processNewData(data.data);
                    } else if (data.data.status === 'ACKNOWLEDGED') {
                        if (appState.currentFireData && appState.currentFireData.id === data.data.id) {
                            appState.currentFireData = null;
                        }
                    }
                }
            })
            .catch(error => {
                console.error("Error fetching latest data:", error);
                updateConnectionStatus('Connection Error');
            });
        }

        function checkForEmergencyAlerts() {
            if (!appState.currentFireData) return;
            
            if (appState.currentFireData.status === 'EMERGENCY' && !appState.currentAlert) {
                showFireAlert(appState.currentFireData);
            }
        }

        // Test functions (simplified)
        window.testEmergencyAlert = function() {
            const testData = {
                id: 'test_' + Date.now(),
                status: 'EMERGENCY',
                building_type: 'Test Building',
                temp: 75,
                heat: 85,
                smoke: 600,
                flame_detected: true,
                timestamp: new Date().toISOString(),
                ai_prediction: 'FIRE_DETECTED'
            };
            processNewData(testData);
        };

        window.testSMS = function() {
            showFastToast('⏳ Testing SMS...', 'Sending test messages...', 'info', 1000);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'test_sms=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    setTimeout(() => {
                        showFastToast('✅ SMS Test Successful', 
                            `${data.success_count}/${data.recipients_count} SMS sent successfully`, 
                            'success', 2000);
                        
                        if (data.failed_count > 0) {
                            setTimeout(() => {
                                showFastToast('⚠️ Partial SMS Failure', 
                                    `${data.failed_count} SMS failed to send`, 
                                    'warning', 2000);
                            }, 500);
                        }
                    }, 300);
                } else {
                    showFastToast('❌ SMS Test Failed', data.error, 'error', 3000);
                }
            })
            .catch(error => {
                showFastToast('❌ SMS Test Error', 'Network error occurred', 'error', 3000);
            });
        };

        window.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
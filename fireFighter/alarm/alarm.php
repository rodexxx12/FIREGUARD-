<?php
// Enable detailed error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$dbConfig = [
    'host' => 'localhost',
    'dbname' => 'u520834156_DBBagofire',
    'username' => 'u520834156_userBagofire',
    'password' => 'i[#[GQ!+=C9',
    'charset' => 'utf8mb4'
];

// Establish database connection
try {
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        // Handle acknowledgment request
        if (isset($_POST['acknowledge'])) {
            $fireDataId = filter_input(INPUT_POST, 'fire_data_id', FILTER_VALIDATE_INT);
            if (!$fireDataId || $fireDataId <= 0) {
                throw new Exception("Invalid fire_data_id");
            }

            $pdo->beginTransaction();

            // Verify record exists
            $stmt = $pdo->prepare("SELECT id FROM fire_data WHERE id = ? FOR UPDATE");
            $stmt->execute([$fireDataId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("No fire_data record found with ID: $fireDataId");
            }

            // Check for existing acknowledgment
            $stmt = $pdo->prepare("SELECT id FROM acknowledgments WHERE fire_data_id = ?");
            $stmt->execute([$fireDataId]);
            if ($stmt->rowCount() > 0) {
                $pdo->commit();
                echo json_encode(['success' => true, 'already_acknowledged' => true]);
                exit;
            }

            // Record acknowledgment
            $stmt = $pdo->prepare("INSERT INTO acknowledgments (fire_data_id, acknowledged_by) VALUES (?, ?)");
            $stmt->execute([$fireDataId, 'Web Dashboard']);
            
            // Update status
            $stmt = $pdo->prepare("UPDATE fire_data SET status = 'ACKNOWLEDGED' WHERE id = ?");
            $stmt->execute([$fireDataId]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'acknowledgment_id' => $pdo->lastInsertId(),
                'fire_data_id' => $fireDataId,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            exit;
        }
        
        // Get latest fire data (all statuses)
        if (isset($_POST['get_latest_data'])) {
            $stmt = $pdo->prepare("
                SELECT f.*, a.id AS acknowledgment_id, a.acknowledged_at, a.acknowledged_by
                FROM fire_data f
                LEFT JOIN acknowledgments a ON f.id = a.fire_data_id
                ORDER BY f.timestamp DESC 
                LIMIT 1
            ");
            $stmt->execute();
            $data = $stmt->fetch();
            
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTrace()
        ]);
        exit;
    }
}

// Get initial data for page load - latest record (all statuses)
try {
    $stmt = $pdo->prepare("
        SELECT f.*, a.id AS acknowledgment_id, a.acknowledged_at, a.acknowledged_by
        FROM fire_data f
        LEFT JOIN acknowledgments a ON f.id = a.fire_data_id
        ORDER BY f.timestamp DESC 
        LIMIT 1
    ");
    $stmt->execute();
    $initialData = $stmt->fetch();
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-acknowledged: #ffc107;
            --color-safe: #28a745;
            --color-disconnected: #6c757d;
            --color-connected: #28a745;
            --color-critical: #dc3545;
            --color-warning: #ffc107;
            --color-background: #f8f9fa;
            --color-text: #212529;
            --shadow-sm: 0 2px 4px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }
        
        

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-acknowledged { background-color: var(--color-acknowledged); color: #212529; }
        .status-safe { background-color: var(--color-safe); color: white; }
        .status-fire-detected { background-color: var(--color-critical); color: white; }
        .status-warning { background-color: var(--color-warning); color: #212529; }
        .status-critical { background-color: var(--color-critical); color: white; }
        .status-active { background-color: #17a2b8; color: white; }
        .status-pending { background-color: #6c757d; color: white; }
        
        .connection-status {
            position: fixed;
            bottom: 10px;
            right: 10px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            background: var(--color-disconnected);
            color: white;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 8px;
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
            padding: 10px;
            border-radius: 8px;
            background: #f8f9fa;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .sensor-title {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .sensor-reading {
            font-size: 1.2rem;
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
            margin: 10px 0;
            border: 0;
            height: 1px;
            background-image: linear-gradient(to right, rgba(0,0,0,0), rgba(0,0,0,0.1), rgba(0,0,0,0));
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .sensor-grid {
                grid-template-columns: 1fr;
            }
            
            .alert-details {
                font-size: 0.9rem;
            }
            
            .sensor-reading {
                font-size: 1rem;
            }
            
            .connection-status {
                font-size: 0.7rem;
                padding: 6px 10px;
                bottom: 5px;
                right: 5px;
            }
        }
    </style>
</head>
<body>

    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Constants
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
            currentFireData: <?php echo json_encode($initialData); ?>,
            isAlarmPlaying: false,
            currentAlert: null,
            alarmAudio: null,
            initialLoad: true,
            sessionAlarms: new Set() // Track alarms shown in this session
        };
        
        // DOM Elements
        const elements = {
            connectionStatus: document.getElementById('connectionStatus')
        };

        // Initialize the application
        function init() {
            console.log("Initializing Fire Alert System - Latest Status Check");
            
            initAudio();
            setupEventListeners();
            loadSessionAlarms();
            
            // Check if latest status is ACKNOWLEDGED
            if (appState.currentFireData && appState.currentFireData.status) {
                console.log("Latest status detected:", appState.currentFireData.status);
                
                // Only show alarm if latest status is ACKNOWLEDGED
                if (appState.currentFireData.status === 'ACKNOWLEDGED') {
                    console.log("Latest status is ACKNOWLEDGED, checking if already shown in session");
                    
                    // Only show alarm if not already shown in this session
                    if (!hasAlarmBeenShown(appState.currentFireData)) {
                        console.log("Showing alarm for first time in this session");
                        showFireAlert(appState.currentFireData);
                        startAlarm();
                        markAlarmAsShown(appState.currentFireData);
                    } else {
                        console.log("Alarm already shown in this session, skipping");
                    }
                } else {
                    console.log("Latest status is not ACKNOWLEDGED, no alarm will be shown. Status:", appState.currentFireData.status);
                }
            } else {
                console.log("No data found in initial load");
            }
            
            setTimeout(() => {
                appState.initialLoad = false;
            }, 1000);
        }

        // Initialize audio
        function initAudio() {
            appState.alarmAudio = new Audio(ALARM_SOUND_URL);
            appState.alarmAudio.loop = true;
            
            // Mobile browsers require user interaction to play audio
            document.addEventListener('click', () => {
                if (appState.alarmAudio.paused && appState.isAlarmPlaying) {
                    appState.alarmAudio.play().catch(e => {
                        console.error("Audio play failed:", e);
                    });
                }
            }, { once: true });
        }

        // Session management functions
        function loadSessionAlarms() {
            try {
                const stored = sessionStorage.getItem('fireAlarmsShown');
                if (stored) {
                    const alarmIds = JSON.parse(stored);
                    appState.sessionAlarms = new Set(alarmIds);
                    console.log("Loaded session alarms:", Array.from(appState.sessionAlarms));
                }
            } catch (e) {
                console.error("Failed to load session alarms:", e);
                appState.sessionAlarms = new Set();
            }
        }

        function saveSessionAlarms() {
            try {
                const alarmIds = Array.from(appState.sessionAlarms);
                sessionStorage.setItem('fireAlarmsShown', JSON.stringify(alarmIds));
                console.log("Saved session alarms:", alarmIds);
            } catch (e) {
                console.error("Failed to save session alarms:", e);
            }
        }

        function hasAlarmBeenShown(data) {
            if (!data || !data.id) return false;
            const alarmKey = `${data.id}_${data.timestamp}`;
            return appState.sessionAlarms.has(alarmKey);
        }

        function markAlarmAsShown(data) {
            if (!data || !data.id) return;
            const alarmKey = `${data.id}_${data.timestamp}`;
            appState.sessionAlarms.add(alarmKey);
            saveSessionAlarms();
            console.log("Marked alarm as shown:", alarmKey);
        }

        // Set up event listeners
        function setupEventListeners() {
            // Handle visibility changes to pause/resume alarm
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    pauseAlarm();
                } else if (appState.isAlarmPlaying) {
                    startAlarm();
                }
            });
        }

        // Validate if data should trigger alarm (only if latest status is ACKNOWLEDGED)
        function shouldTriggerAlarm(data) {
            return data && data.status === 'ACKNOWLEDGED';
        }

        // Process new fire data
        function processNewData(newData) {
            console.log("Processing new data:", newData);
            
            if (!newData.id) {
                newData.id = 'ws_' + Date.now();
            }
            
            if (!newData.timestamp) {
                newData.timestamp = new Date().toISOString();
            }
            
            // Only process if latest status is ACKNOWLEDGED
            if (shouldTriggerAlarm(newData)) {
                checkAndShowAlert(newData);
                appState.currentFireData = newData;
            } else {
                console.log("Latest status is not ACKNOWLEDGED, skipping alarm processing. Status:", newData.status);
            }
        }

        // Check if alert should be shown
        function checkAndShowAlert(data) {
            if (!data || !data.status) {
                console.log("No data or status found, skipping alert check");
                return false;
            }
            
            console.log("Checking alert conditions for:", data);
            console.log("Current status:", data.status);
            
            // Only show alert if latest status is ACKNOWLEDGED
            if (data.status !== 'ACKNOWLEDGED') {
                console.log("Latest status is not ACKNOWLEDGED, skipping alert. Current status:", data.status);
                return false;
            }
            
            // Check if this alarm has already been shown in this session
            if (hasAlarmBeenShown(data)) {
                console.log("Alarm already shown in this session, skipping");
                return false;
            }
            
            const isNewAlert = (!appState.initialLoad && (
                !appState.lastAlertTime || 
                (data.id !== appState.currentFireData?.id) ||
                (data.timestamp !== appState.currentFireData?.timestamp)
            ));
            
            if (isNewAlert) {
                console.log("Showing alarm for latest ACKNOWLEDGED status:", data);
                showFireAlert(data);
                markAlarmAsShown(data);
                appState.lastAlertTime = new Date();
                return true;
            }
            
            return false;
        }

       // Show fire alert modal (only if latest status is ACKNOWLEDGED)
function showFireAlert(data) {
    const statusClass = data.status.toLowerCase().replace('_', '-');
    const isAcknowledged = data.status === 'ACKNOWLEDGED';
    
    if (appState.currentAlert) {
        Swal.close();
    }
    
    const sensorGridHTML = createSensorGridHTML(data);
    
    let alertTitle = '🚨 ACKNOWLEDGED ALERT';
    let iconType = 'warning';
    let playAlarm = true;
    
    const html = `
        <div class="alert-details" style="font-size: 20px;">
            <p><strong>Status:</strong> <span class="status-badge status-${statusClass}">${data.status}</span></p>
            <p><strong>Location:</strong> <span class="sensor-value">${data.building_type || 'Unknown location'}</span></p>
            <p><strong>Detected at:</strong> <span class="sensor-value">${formatTimestamp(data.timestamp)}</span></p>
            
            ${sensorGridHTML}
            
            <hr class="alert-divider">
            <p><strong>Acknowledged at:</strong> <span class="sensor-value">${formatTimestamp(data.acknowledged_at)}</span></p>
        </div>
    `;
    
    const alertConfig = {
        title: alertTitle,
        html: html,
        icon: iconType,
        confirmButtonText: 'CLOSE',
        confirmButtonColor: `var(--color-${statusClass})`,
        showCancelButton: false,
        allowOutsideClick: false,
        backdrop: 'rgba(0,0,0,0.9)',
        width: '500px',
        padding: '1rem',
        timer: null,
        timerProgressBar: true,
        focusConfirm: false,
        didOpen: () => {
            if (playAlarm) {
                startAlarm();
            }
        },
        willClose: () => {
            appState.currentAlert = null;
            stopAlarm();
        }
    };
    
    appState.currentAlert = Swal.fire(alertConfig);
}

// Create sensor grid HTML
function createSensorGridHTML(data) {
    const tempClass = getSensorClass(data.temp, 'temp');
    const heatClass = getSensorClass(data.heat, 'heat');
    const smokeClass = getSensorClass(data.smoke, 'smoke');
    
    return `
        <div class="sensor-grid" style="grid-template-columns: repeat(2, 1fr); gap: 6px; margin: 8px 0;">
            <div class="sensor-box" style="padding: 6px;">
                <div class="sensor-title" style="font-size: 11px;">Temperature</div>
                <div class="sensor-reading ${tempClass}" style="font-size: 13px;">${data.temp || 0}°C</div>
            </div>
            <div class="sensor-box" style="padding: 6px;">
                <div class="sensor-title" style="font-size: 11px;">Heat Index</div>
                <div class="sensor-reading ${heatClass}" style="font-size: 13px;">${data.heat || 0}°C</div>
            </div>
            <div class="sensor-box" style="padding: 6px;">
                <div class="sensor-title" style="font-size: 11px;">Smoke Level</div>
                <div class="sensor-reading ${smokeClass}" style="font-size: 13px;">${data.smoke || 0} ppm</div>
            </div>
            <div class="sensor-box" style="padding: 6px;">
                <div class="sensor-title" style="font-size: 11px;">Flame Detected</div>
                <div class="sensor-reading" style="font-size: 13px;">${data.flame_detected ? '✅ Yes' : '❌ No'}</div>
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
        function startAlarm() {
            if (!appState.isAlarmPlaying) {
                console.log("Starting alarm");
                appState.alarmAudio.play().catch(e => {
                    console.error("Audio play failed:", e);
                });
                appState.isAlarmPlaying = true;
            }
        }

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

        // Acknowledge alert
        function acknowledgeAlert(fireDataId) {
            console.log("Acknowledging alert:", fireDataId);
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `acknowledge=1&fire_data_id=${fireDataId}`
            })
            .then(handleResponse)
            .then(data => {
                if (data.success) {
                    console.log("Acknowledgment successful:", data);
                    stopAlarm();
                    
                    appState.currentFireData.status = 'ACKNOWLEDGED';
                    appState.currentFireData.acknowledgment_id = data.acknowledgment_id;
                    appState.currentFireData.acknowledged_at = data.timestamp;
                    appState.currentFireData.acknowledged_by = 'Web Dashboard';
                    
                    showFireAlert(appState.currentFireData);
                    showNotification('Alert Acknowledged', 'The emergency has been officially acknowledged and logged.', 'success');
                } else {
                    throw new Error(data.error || 'Unknown error');
                }
            })
            .catch(error => {
                console.error("Acknowledgment failed:", error);
                showNotification('Acknowledgment Failed', error.message, 'error');
            });
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

// Function to get status color based on status
function getStatusColor(status) {
    switch (status.toUpperCase()) {
        case 'FIRE_DETECTED':
        case 'CRITICAL':
            return '#dc3545'; // Red
        case 'WARNING':
            return '#ffc107'; // Yellow
        case 'ACKNOWLEDGED':
            return '#ffc107'; // Yellow
        case 'ACTIVE':
            return '#17a2b8'; // Blue
        case 'PENDING':
            return '#6c757d'; // Gray
        case 'SAFE':
            return '#28a745'; // Green
        default:
            return '#6c757d'; // Gray
    }
}

        // Initialize on page load
        window.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
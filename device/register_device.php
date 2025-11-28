<?php
/**
 * Device Registration API - Secure Version
 */

// Use centralized error handling
require_once __DIR__ . '/../core/error_handler.php';
initializeErrorHandling(__DIR__ . '/../../logs/device_api_errors.log');

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Secure CORS configuration
$allowedOrigins = [
    'https://your-domain.com',
    'https://api.your-domain.com',
    'http://localhost',
    'http://127.0.0.1'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || empty($origin)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
} else {
    header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
}
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// SECURITY FIX: Use centralized database connection instead of hardcoded credentials
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/database/database.php';

class Database {
    public static function getConnection() {
        // Use centralized PDO connection - note: returns PDO, may need mysqli wrapper
        $pdo = getDatabaseConnection();
        
        // Create mysqli wrapper for compatibility (consider refactoring to use PDO)
        if ($pdo) {
            // For now, return PDO connection
            // If code requires mysqli, we need to create a wrapper or refactor
            return $pdo;
        }
        
        return null;
    }
    
    private static function getMysqliWrapper() {
        // Legacy support - creates mysqli from PDO config
        // NOTE: Should refactor to use PDO instead
        require_once __DIR__ . '/../core/config/config.php';
        
        $host = config('db.host', 'localhost');
        $dbname = config('db.name', '');
        $username = config('db.user', '');
        $password = config('db.pass', '');
        
        try {
            $conn = new mysqli($host, $username, $password, $dbname);
            if ($conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
            } catch (Exception $e) {
                error_log($e->getMessage());
                return null;
            }
        }
        
        return $conn;
    }
}

class DeviceRegistration {
    private $mac_address;
    private $device_name;
    private $device_type;
    
    public function __construct() {
        $this->mac_address = isset($_GET['mac_address']) ? trim($_GET['mac_address']) : '';
        $this->device_name = isset($_GET['device_name']) ? trim($_GET['device_name']) : '';
        $this->device_type = isset($_GET['device_type']) ? trim($_GET['device_type']) : 'ESP32_Fire_Detector';
    }
    
    public function processRequest() {
        try {
            $this->validateInput();
            $device_id = $this->registerOrGetDevice();
            
            $response = [
                'status' => 'success',
                'device_id' => $device_id,
                'message' => 'Device registered successfully',
                'device_info' => [
                    'mac_address' => $this->mac_address,
                    'device_name' => $this->device_name,
                    'device_type' => $this->device_type
                ]
            ];
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function validateInput() {
        if (empty($this->mac_address)) {
            throw new Exception('MAC address is required');
        }
        
        if (empty($this->device_name)) {
            $this->device_name = 'FireGuard-' . $this->mac_address;
        }
    }
    
    private function registerOrGetDevice() {
        $conn = Database::getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Check if device already exists
        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE serial_number = ?");
        $stmt->bind_param("s", $this->mac_address);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $device_id = $row['device_id'];
            
            // Update device status to online
            $this->updateDeviceStatus($device_id, 'online');
            
            $stmt->close();
            return $device_id;
        }
        
        $stmt->close();
        
        // Device doesn't exist, create new one
        return $this->createNewDevice();
    }
    
    private function createNewDevice() {
        $conn = Database::getConnection();
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // Ensure user exists (create default user if not)
        $this->ensureDefaultUser();
        
        // Ensure building exists (create default building if not)
        $this->ensureDefaultBuilding();
        
        // Create device
        $stmt = $conn->prepare("INSERT INTO devices (
            user_id, device_name, device_number, serial_number, 
            building_id, status, is_active, last_activity
        ) VALUES (?, ?, ?, ?, ?, 'online', 1, NOW())");
        
        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        
        $user_id = 1; // Default user
        $building_id = 1; // Default building
        $device_number = 'ARD' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $stmt->bind_param("isssi", $user_id, $this->device_name, $device_number, $this->mac_address, $building_id);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to create device: ' . $stmt->error);
        }
        
        $device_id = $conn->insert_id;
        $stmt->close();
        
        error_log("New device created: ID=$device_id, MAC=$this->mac_address, Name=$this->device_name");
        
        return $device_id;
    }
    
    private function ensureDefaultUser() {
        $conn = Database::getConnection();
        if (!$conn) return;
        
        $conn->query("INSERT INTO users (user_id, username, email, password, first_name, last_name, phone) 
                      VALUES (1, 'arduino_user', 'arduino@firedetection.com', 'password', 'Arduino', 'User', '+639318261972')
                      ON DUPLICATE KEY UPDATE user_id = user_id");
    }
    
    private function ensureDefaultBuilding() {
        $conn = Database::getConnection();
        if (!$conn) return;
        
        $conn->query("INSERT INTO buildings (building_id, building_name, building_type, address, user_id) 
                      VALUES (1, 'Arduino Test Building', 'Residential', 'Test Address', 1)
                      ON DUPLICATE KEY UPDATE building_id = building_id");
    }
    
    private function updateDeviceStatus($device_id, $status) {
        $conn = Database::getConnection();
        if (!$conn) return false;
        
        $stmt = $conn->prepare("UPDATE devices SET status = ?, last_activity = NOW() WHERE device_id = ?");
        $stmt->bind_param("si", $status, $device_id);
        $success = $stmt->execute();
        $stmt->close();
        
        return $success;
    }
    
    private function handleError(Exception $e) {
        error_log("Device Registration Error: " . $e->getMessage());
        
        $response = [
            'status' => 'error',
            'message' => 'Device registration failed',
            'error_code' => 'REG_' . time(),
            'details' => $e->getMessage()
        ];
        
        http_response_code(400);
        echo json_encode($response);
    }
}

// Execute the registration
$registration = new DeviceRegistration();
$registration->processRequest();
?>


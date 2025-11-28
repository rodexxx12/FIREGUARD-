<?php
/**
 * Device Smoke API - Secure Version
 * Handles sensor data from IoT fire detection devices
 */

// Environment-aware error handling
$isProduction = (getenv('APP_ENV') === 'production' || 
                 (isset($_SERVER['HTTP_HOST']) && 
                  strpos($_SERVER['HTTP_HOST'], 'localhost') === false &&
                  strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false));

if ($isProduction) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/device_api_errors.log');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

// Rate limiting
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/rate_limit/rate_limiter.php';
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateLimitResult = rateLimitCheck('device_api', $clientIp);

if (!$rateLimitResult['allowed']) {
    http_response_code(429);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'error',
        'message' => $rateLimitResult['message'] ?? 'Too many requests. Please wait before trying again.'
    ]);
    exit;
}

// Record this API request (rate limit check already passed)
rateLimitRecord('device_api', $clientIp);

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

// Load configuration for SMS
$config = require 'config.php';
$apiKey = $config['api_key'];
$device = $config['device'];
$url = $config['url'];

/**
 * Database Connection Class
 * SECURITY FIX: Removed hardcoded credentials - now uses environment variables
 * 
 * WARNING: This class uses mysqli. For better security, consider refactoring to use PDO.
 * All credentials are now loaded from .env file, never hardcoded.
 */
class Database {
    private static $conn = null;
    
    public static function getConnection() {
        if (self::$conn === null) {
            // Load environment configuration
            require_once __DIR__ . '/../core/config/config.php';
            
            // Get database credentials from environment variables
            $host = config('db.host', 'localhost');
            $dbname = config('db.name', '');
            $username = config('db.user', '');
            $password = config('db.pass', '');
            
            // Validate required configuration
            if (empty($dbname) || empty($username)) {
                error_log("CRITICAL: Database configuration incomplete in device/smoke_api.php");
                return null;
            }
            
            try {
                // Create mysqli connection (temporary - consider migrating to PDO)
                $conn = new mysqli($host, $username, $password, $dbname);
                
                if ($conn->connect_error) {
                    throw new Exception("Database connection failed: " . $conn->connect_error);
                }
                
                // Ensure MySQL uses Philippine timezone (UTC+08:00) for NOW() and TIMESTAMP fields
                // SECURITY FIX: Use prepared statement for timezone setting
                $stmt = $conn->prepare("SET time_zone = '+08:00'");
                if ($stmt) {
                    if (!$stmt->execute()) {
                        error_log("Failed to set MySQL time_zone: " . $conn->error);
                    }
                    $stmt->close();
                }
                
                self::$conn = $conn;
                
            } catch (Exception $e) {
                error_log("Database connection failed in smoke_api.php: " . $e->getMessage());
                return null;
            }
        }
        
        return self::$conn;
    }
}

class SmokeAPI {
    private $device_id;
    private $value;
    private $detected;
    private $flame_detected;
    private $temperature;
    private $humidity;
    private $log;
    private $gps_latitude;
    private $gps_longitude;
    private $gps_altitude;
    private $gps_satellites;
    private $gps_valid;
    
    public function __construct() {
        // Get data from POST or GET
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            parse_str($input, $data);
        } else {
            $data = $_GET;
        }
        
        // SECURITY FIX: Validate and sanitize all inputs with bounds checking
        $this->value = isset($data['value']) ? max(0, min(1023, intval($data['value']))) : 0;
        $this->detected = isset($data['detected']) ? (intval($data['detected']) === 1 ? 1 : 0) : 0;
        $this->flame_detected = isset($data['flame_detected']) ? (intval($data['flame_detected']) === 1 ? 1 : 0) : 0;
        
        // Validate temperature range (-50 to 200°C is reasonable for fire detection)
        if (isset($data['temperature']) && $data['temperature'] !== '') {
            $temp = floatval($data['temperature']);
            $this->temperature = ($temp >= -50 && $temp <= 200) ? $temp : null;
        } else {
            $this->temperature = null;
        }
        
        // Validate humidity range (0-100%)
        if (isset($data['humidity']) && $data['humidity'] !== '') {
            $hum = floatval($data['humidity']);
            $this->humidity = ($hum >= 0 && $hum <= 100) ? $hum : null;
        } else {
            $this->humidity = null;
        }
        
        // Validate device ID (must be positive integer)
        if (isset($data['device_id'])) {
            $deviceId = intval($data['device_id']);
            $this->device_id = ($deviceId > 0) ? $deviceId : null;
        } else {
            $this->device_id = null;
        }
        
        $this->log = isset($data['log']) ? (intval($data['log']) === 1 ? 1 : 0) : 0;
        
        // GPS data validation
        // Latitude: -90 to 90
        if (isset($data['gps_latitude'])) {
            $lat = floatval($data['gps_latitude']);
            $this->gps_latitude = ($lat >= -90 && $lat <= 90) ? $lat : 0.0;
        } else {
            $this->gps_latitude = 0.0;
        }
        
        // Longitude: -180 to 180
        if (isset($data['gps_longitude'])) {
            $lon = floatval($data['gps_longitude']);
            $this->gps_longitude = ($lon >= -180 && $lon <= 180) ? $lon : 0.0;
        } else {
            $this->gps_longitude = 0.0;
        }
        
        // Altitude: reasonable range (0 to 8848m - Mount Everest height)
        if (isset($data['gps_altitude'])) {
            $alt = floatval($data['gps_altitude']);
            $this->gps_altitude = ($alt >= 0 && $alt <= 8848) ? $alt : 0.0;
        } else {
            $this->gps_altitude = 0.0;
        }
        
        // Satellites: 0-12 is reasonable
        if (isset($data['gps_satellites'])) {
            $sats = intval($data['gps_satellites']);
            $this->gps_satellites = ($sats >= 0 && $sats <= 12) ? $sats : 0;
        } else {
            $this->gps_satellites = 0;
        }
        
        $this->gps_valid = isset($data['gps_valid']) ? (intval($data['gps_valid']) === 1 ? 1 : 0) : 0;
    }
    
    public function processRequest() {
        try {
            $this->validateDevice();
            $this->updateDeviceStatus();
            
            $heat_index = $this->calculateHeatIndex();
            
            // Insert sensor readings
            $smoke_insertion = $this->insertSmokeReading();
            $flame_insertion = $this->insertFlameReading();
            $environment_insertion = $this->insertEnvironmentReading($heat_index);
            $gps_insertion = $this->insertGPSReading();
            
            // Get the inserted reading IDs for fire_data table
            $smoke_reading_id = $this->getLastInsertedId('smoke_readings');
            $flame_reading_id = $this->getLastInsertedId('flame_readings');
            
            // Prepare sensor data for fire_data table
            $sensor_data = [
                'smoke' => $this->value,
                'temp' => $this->temperature !== null ? $this->temperature : 0,
                'heat' => $heat_index !== null ? $heat_index : ($this->temperature !== null ? $this->temperature : 0),
                'flame_detected' => $this->flame_detected,
                'humidity' => $this->humidity !== null ? $this->humidity : 0
            ];
            
            // Insert into fire_data table with status NORMAL
            $fire_data_insertion = $this->insertFireData($sensor_data, $smoke_reading_id, $flame_reading_id);
            
            $response = [
                'status' => 'success',
                'device_id' => $this->device_id,
                'data_received' => [
                    'smoke_value' => $this->value,
                    'smoke_detected' => $this->detected,
                    'flame_detected' => $this->flame_detected,
                    'temperature' => $this->temperature,
                    'humidity' => $this->humidity,
                    'heat_index' => $heat_index,
                    'gps_data' => [
                        'latitude' => $this->gps_latitude,
                        'longitude' => $this->gps_longitude,
                        'altitude' => $this->gps_altitude,
                        'satellites' => $this->gps_satellites,
                        'valid' => $this->gps_valid
                    ],
                    'data_quality' => [
                        'temperature_available' => $this->temperature !== null,
                        'humidity_available' => $this->humidity !== null,
                        'heat_index_calculated' => $heat_index !== null,
                        'gps_available' => $this->gps_valid
                    ]
                ],
                'insertions' => [
                    'smoke' => $smoke_insertion,
                    'flame' => $flame_insertion,
                    'environment' => $environment_insertion,
                    'gps' => $gps_insertion,
                    'fire_data' => $fire_data_insertion
                ],
                'processing_info' => [
                    'environment_data_processed' => $environment_insertion !== 'skipped (invalid data)',
                    'fire_data_inserted' => $fire_data_insertion['success']
                ]
            ];
            
            if ($this->log) {
                $this->logData();
            }
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }
    
    private function validateDevice() {
        if ($this->device_id === null) {
            $this->device_id = $this->getFirstActiveDeviceId();
            
            if ($this->device_id === null) {
                $this->device_id = $this->createDefaultDevice();
            }
        }
        
        if (!$this->isValidDeviceId($this->device_id)) {
            throw new Exception('Invalid or inactive device ID: ' . $this->device_id);
        }
    }
    
    private function getFirstActiveDeviceId() {
        $conn = Database::getConnection();
        if (!$conn) return null;

        // SECURITY FIX: Use prepared statement instead of direct query
        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE is_active = 1 ORDER BY device_id LIMIT 1");
        if (!$stmt) {
            error_log("Failed to prepare statement: " . $conn->error);
            return null;
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['device_id'];
        }

        $stmt->close();
        return null;
    }
    
    private function createDefaultDevice() {
        // SECURITY FIX: Do not create default users with hardcoded passwords
        // Devices must be properly registered through the registration flow
        error_log("SECURITY: Attempt to create default device - device registration required");
        throw new Exception('Device not registered. Please register device through proper registration flow before sending data.');
    }
    
    private function isValidDeviceId($device_id) {
        $conn = Database::getConnection();
        if (!$conn) return false;

        $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ? AND is_active = 1");
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $valid = $result && $result->num_rows > 0;
        
        $stmt->close();
        
        return $valid;
    }
    
    private function updateDeviceStatus($status = 'online') {
        $conn = Database::getConnection();
        if (!$conn) return false;

        $stmt = $conn->prepare("UPDATE devices SET status = ?, last_activity = CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00') WHERE device_id = ?");
        $stmt->bind_param("si", $status, $this->device_id);
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Error updating device status: " . $stmt->error);
        }
        
        $stmt->close();
        
        return $success;
    }
    
    private function calculateHeatIndex() {
        if ($this->temperature === null || $this->humidity === null) {
            return null;
        }

        // Simple heat index calculation (same as Arduino)
        if ($this->temperature >= 20.0 && $this->humidity >= 40.0) {
            $heat_index = 0.5 * ($this->temperature + 61.0 + (($this->temperature - 68.0) * 1.2) + ($this->humidity * 0.094));
            
            if ($heat_index >= 80.0) {
                $heat_index = -42.379 + 2.04901523 * $this->temperature + 10.14333127 * $this->humidity 
                            - 0.22475541 * $this->temperature * $this->humidity - 0.00683783 * $this->temperature * $this->temperature 
                            - 0.05481717 * $this->humidity * $this->humidity + 0.00122874 * $this->temperature * $this->temperature * $this->humidity 
                            + 0.00085282 * $this->temperature * $this->humidity * $this->humidity - 0.00000199 * $this->temperature * $this->temperature * $this->humidity * $this->humidity;
            }
        } else {
            $heat_index = $this->temperature;
        }
        
        return $heat_index;
    }
    
    private function insertSmokeReading() {
        $conn = Database::getConnection();
        if (!$conn) return 'failed';

        $stmt = $conn->prepare("INSERT INTO smoke_readings (device_id, sensor_value, detected) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $this->device_id, $this->value, $this->detected);
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Error inserting smoke reading: " . $stmt->error);
            return 'failed';
        }
        
        $stmt->close();
        
        return 'success';
    }
    
    private function insertFlameReading() {
        $conn = Database::getConnection();
        if (!$conn) return 'failed';

        $stmt = $conn->prepare("INSERT INTO flame_readings (device_id, detected) VALUES (?, ?)");
        $stmt->bind_param("ii", $this->device_id, $this->flame_detected);
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Error inserting flame reading: " . $stmt->error);
            return 'failed';
        }
        
        $stmt->close();
        
        return 'success';
    }
    
    private function insertEnvironmentReading($heat_index) {
        $conn = Database::getConnection();
        if (!$conn) return 'failed';

        // Handle null values more gracefully
        $temperature = $this->temperature !== null ? $this->temperature : 0;
        $humidity = $this->humidity !== null ? $this->humidity : 0;
        $heat_index_value = $heat_index !== null ? $heat_index : $temperature;

        // REMOVED THE RESTRICTIVE VALIDATION - Accept all sensor readings
        // The Arduino already validates the readings, so we trust the device

        // Insert with the processed values
        $stmt = $conn->prepare("INSERT INTO environment_readings 
                                (device_id, temperature, humidity, heat_index) 
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iddd", $this->device_id, $temperature, 
                          $humidity, $heat_index_value);
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Error inserting environment reading: " . $stmt->error);
            return 'failed';
        }
        
        $stmt->close();
        
        return 'success';
    }

    private function insertGPSReading() {
        $conn = Database::getConnection();
        if (!$conn) return 'failed';

        // Only insert GPS data if it's valid
        if (!$this->gps_valid) {
            return 'skipped (invalid GPS data)';
        }

        $stmt = $conn->prepare("INSERT INTO gps_readings 
                                (device_id, latitude, longitude, altitude, satellites) 
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("idddi", $this->device_id, $this->gps_latitude, 
                          $this->gps_longitude, $this->gps_altitude, $this->gps_satellites);
        
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Error inserting GPS reading: " . $stmt->error);
            return 'failed';
        }
        
        $stmt->close();
        
        return 'success';
    }

    private function insertFireData($sensor_data, $smoke_reading_id, $flame_reading_id) {
        $conn = Database::getConnection();
        if (!$conn) return ['success' => false, 'id' => null];

        // Ensure MySQL session timezone is set to Philippine Time before inserting
        // SECURITY FIX: Use prepared statement
        $stmt = $conn->prepare("SET time_zone = '+08:00'");
        if ($stmt) {
            if (!$stmt->execute()) {
                error_log("Failed to set MySQL time_zone before fire_data insert: " . $conn->error);
            }
            $stmt->close();
        }

        // Get device info to extract user_id and building_id
        $device_info = $this->getDeviceInfo();
        if (!$device_info) {
            error_log("Device info not found for device_id: " . $this->device_id);
            return ['success' => false, 'id' => null];
        }

        $user_id = $device_info['user_id'];
        $building_id = $device_info['building_id'];
        $barangay_id = isset($device_info['barangay_id']) ? $device_info['barangay_id'] : null;

        // Get current Philippine time as string for timestamp field
        $philippine_timestamp = date('Y-m-d H:i:s');

        // Insert with status NORMAL and include timestamp field
        $stmt = $conn->prepare("INSERT INTO fire_data (
            status, building_type, smoke, temp, heat, flame_detected, timestamp,
            user_id, building_id, barangay_id, smoke_reading_id, flame_reading_id, device_id,
            gps_latitude, gps_longitude, gps_altitude
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'id' => null];
        }

        $building_type = "Residential";
        $status = "NORMAL";
        $temp = intval($sensor_data['temp']); // Convert to int as per table schema
        $heat = intval($sensor_data['heat']); // Convert to int as per table schema

        $stmt->bind_param(
            "ssiiisssiiiiiddd",
            $status,
            $building_type,
            $sensor_data['smoke'],
            $temp,
            $heat,
            $sensor_data['flame_detected'],
            $philippine_timestamp,
            $user_id,
            $building_id,
            $barangay_id,
            $smoke_reading_id,
            $flame_reading_id,
            $this->device_id,
            $this->gps_latitude,
            $this->gps_longitude,
            $this->gps_altitude
        );

        $success = $stmt->execute();
        if (!$success) {
            error_log("Execute failed: " . $stmt->error);
        }

        $fire_data_id = $conn->insert_id;
        
        // Update device's latest_fire_data_id
        if ($success && $fire_data_id) {
            $this->updateDeviceLatestFireData($fire_data_id);
        }

        $stmt->close();
        return ['success' => $success, 'id' => $fire_data_id];
    }

    private function getDeviceInfo($device_id = null) {
        $device_id = $device_id ?: $this->device_id;
        $conn = Database::getConnection();
        if (!$conn) return null;

        $stmt = $conn->prepare("SELECT device_id, user_id, device_name, device_number, serial_number, building_id, barangay_id, status FROM devices WHERE device_id = ?");
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $device_info = $result->fetch_assoc();
            $stmt->close();
            return $device_info;
        }

        $stmt->close();
        return null;
    }

    private function updateDeviceLatestFireData($fire_data_id) {
        $conn = Database::getConnection();
        if (!$conn) return false;

        $stmt = $conn->prepare("UPDATE devices SET latest_fire_data_id = ? WHERE device_id = ?");
        $stmt->bind_param("ii", $fire_data_id, $this->device_id);
        $success = $stmt->execute();
        
        if (!$success) {
            error_log("Failed to update device latest_fire_data_id: " . $stmt->error);
        } else {
            error_log("Updated device {$this->device_id} with latest_fire_data_id: $fire_data_id");
        }

        $stmt->close();
        return $success;
    }

    private function getLastInsertedId($table) {
        $conn = Database::getConnection();
        if (!$conn) return null;
        
        return $conn->insert_id;
    }

    private function handleError(Exception $e) {
        // Log the full error with context
        $errorDetails = [
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage(),
            'device_id' => $this->device_id,
            'request_data' => [
                'smoke_value' => $this->value,
                'smoke_detected' => $this->detected,
                'flame_detected' => $this->flame_detected,
                'temperature' => $this->temperature,
                'humidity' => $this->humidity,
                'gps_latitude' => $this->gps_latitude,
                'gps_longitude' => $this->gps_longitude
            ],
            'trace' => $e->getTraceAsString()
        ];
        
        error_log("API Error: " . json_encode($errorDetails));
        
        $response = [
            'status' => 'error',
            'message' => 'An error occurred processing your request',
            'error_code' => 'API_' . time(),
            'suggestion' => 'Check device connections and try again'
        ];
        
        http_response_code(400);
        echo json_encode($response);
    }
    
    private function logData() {
        $temp_str = $this->temperature !== null ? $this->temperature : 'null';
        $humidity_str = $this->humidity !== null ? $this->humidity : 'null';
        $heat_index = $this->calculateHeatIndex();
        $heat_index_str = $heat_index !== null ? $heat_index : 'null';
        
        error_log("Device {$this->device_id} data: Smoke={$this->value} (detected={$this->detected}), Flame={$this->flame_detected}, Temp={$temp_str}°C, Humidity={$humidity_str}%, Heat Index={$heat_index_str}°C, GPS Lat={$this->gps_latitude}, Lng={$this->gps_longitude}, Valid={$this->gps_valid}");
    }
}

// Execute the API
$api = new SmokeAPI();
$api->processRequest();
?>
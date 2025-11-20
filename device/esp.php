<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set Philippine timezone
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load configuration for SMS
$config = require 'config.php';
$apiKey = $config['api_key'];
$device = $config['device'];
$url = $config['url'];

class Database {
    private static $host = "localhost";
    private static $dbname = "u520834156_DBBagofire";
    private static $username = "u520834156_userBagofire";
    private static $password = "i[#[GQ!+=C9";
    
    public static function getConnection() {
        static $conn = null;
        
        if ($conn === null) {
            try {
                $conn = new mysqli(
                    self::$host, 
                    self::$username, 
                    self::$password, 
                    self::$dbname
                );
                
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

class SmokeAPI {
    private $device_id;
    private $value;
    private $detected;
    private $flame_detected;
    private $temperature;
    private $humidity;
    private $log;
    
    public function __construct() {
        // Get data from POST or GET
        $input = file_get_contents('php://input');
        if (!empty($input)) {
            parse_str($input, $data);
        } else {
            $data = $_GET;
        }
        
        $this->value = isset($data['value']) ? intval($data['value']) : 0;
        $this->detected = isset($data['detected']) ? intval($data['detected']) : 0;
        $this->flame_detected = isset($data['flame_detected']) ? intval($data['flame_detected']) : 0;
        $this->temperature = isset($data['temperature']) && $data['temperature'] !== '' ? floatval($data['temperature']) : null;
        $this->humidity = isset($data['humidity']) && $data['humidity'] !== '' ? floatval($data['humidity']) : null;
        $this->device_id = isset($data['device_id']) ? intval($data['device_id']) : null;
        $this->log = isset($data['log']) ? intval($data['log']) : 0;
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
                    'data_quality' => [
                        'temperature_available' => $this->temperature !== null,
                        'humidity_available' => $this->humidity !== null,
                        'heat_index_calculated' => $heat_index !== null
                    ]
                ],
                'insertions' => [
                    'smoke' => $smoke_insertion,
                    'flame' => $flame_insertion,
                    'environment' => $environment_insertion,
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

        $query = "SELECT device_id FROM devices WHERE is_active = 1 ORDER BY device_id LIMIT 1";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['device_id'];
        }

        return null;
    }
    
    private function createDefaultDevice() {
        $conn = Database::getConnection();
        if (!$conn) return null;

        // Ensure user exists
        $conn->query("INSERT INTO users (user_id, username, email, password, first_name, last_name, phone) 
                      VALUES (1, 'arduino_user', 'arduino@firedetection.com', 'password', 'Arduino', 'User', '+639318261972')
                      ON DUPLICATE KEY UPDATE user_id = user_id");

        // Ensure building exists
        $conn->query("INSERT INTO buildings (building_id, building_name, building_type, address, user_id) 
                      VALUES (1, 'Arduino Test Building', 'Residential', 'Test Address', 1)
                      ON DUPLICATE KEY UPDATE building_id = building_id");

        // Create device
        $conn->query("INSERT INTO devices (device_id, user_id, device_name, device_number, serial_number, building_id, status, is_active) 
                      VALUES (1, 1, 'Arduino Fire Sensor', 'ARD001', 'ESP32-FIRE-001', 1, 'online', 1)
                      ON DUPLICATE KEY UPDATE 
                          status = 'online',
                          is_active = 1,
                          last_activity = NOW()");

        return 1;
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

        $stmt = $conn->prepare("UPDATE devices SET status = ?, last_activity = NOW() WHERE device_id = ?");
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

    private function insertFireData($sensor_data, $smoke_reading_id, $flame_reading_id) {
        $conn = Database::getConnection();
        if (!$conn) return ['success' => false, 'id' => null];

        // Get device info to extract user_id and building_id
        $device_info = $this->getDeviceInfo();
        if (!$device_info) {
            error_log("Device info not found for device_id: " . $this->device_id);
            return ['success' => false, 'id' => null];
        }

        $user_id = $device_info['user_id'];
        $building_id = $device_info['building_id'];

        // Insert with status NORMAL
        $stmt = $conn->prepare("INSERT INTO fire_data (
            status, building_type, smoke, temp, heat, flame_detected,
            user_id, building_id, smoke_reading_id, flame_reading_id, device_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'id' => null];
        }

        $building_type = "Residential";
        $status = "NORMAL";
        $temp = $sensor_data['temp'];
        $heat = $sensor_data['heat'];

        $stmt->bind_param(
            "ssiiiisiiii",
            $status,
            $building_type,
            $sensor_data['smoke'],
            $temp,
            $heat,
            $sensor_data['flame_detected'],
            $user_id,
            $building_id,
            $smoke_reading_id,
            $flame_reading_id,
            $this->device_id
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

        $stmt = $conn->prepare("SELECT device_id, user_id, device_name, device_number, serial_number, building_id, status FROM devices WHERE device_id = ?");
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
                'humidity' => $this->humidity
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
        
        error_log("Device {$this->device_id} data: Smoke={$this->value} (detected={$this->detected}), Flame={$this->flame_detected}, Temp={$temp_str}°C, Humidity={$humidity_str}%, Heat Index={$heat_index_str}°C");
    }
}

// Execute the API
$api = new SmokeAPI();
$api->processRequest();
?>
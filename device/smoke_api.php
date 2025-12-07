<?php
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

// Load centralized database connection (uses environment variables from .env)
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/database/database.php';

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
        
        $this->value = isset($data['value']) ? intval($data['value']) : 0;
        $this->detected = isset($data['detected']) ? intval($data['detected']) : 0;
        $this->flame_detected = isset($data['flame_detected']) ? intval($data['flame_detected']) : 0;
        $this->temperature = isset($data['temperature']) && $data['temperature'] !== '' ? floatval($data['temperature']) : null;
        $this->humidity = isset($data['humidity']) && $data['humidity'] !== '' ? floatval($data['humidity']) : null;
        $this->device_id = isset($data['device_id']) ? intval($data['device_id']) : null;
        $this->log = isset($data['log']) ? intval($data['log']) : 0;
        
        // GPS data
        $this->gps_latitude = isset($data['gps_latitude']) ? floatval($data['gps_latitude']) : 0.0;
        $this->gps_longitude = isset($data['gps_longitude']) ? floatval($data['gps_longitude']) : 0.0;
        $this->gps_altitude = isset($data['gps_altitude']) ? floatval($data['gps_altitude']) : 0.0;
        $this->gps_satellites = isset($data['gps_satellites']) ? intval($data['gps_satellites']) : 0;
        $this->gps_valid = isset($data['gps_valid']) ? intval($data['gps_valid']) : 0;
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
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("SELECT device_id FROM devices WHERE is_active = 1 ORDER BY device_id LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            
            if ($row) {
                return $row['device_id'];
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting first active device: " . $e->getMessage());
            return null;
        }
    }
    
    private function createDefaultDevice() {
        try {
            $conn = getDatabaseConnection();
            
            // Ensure user exists
            $stmt = $conn->prepare("INSERT INTO users (user_id, username, email, password, first_name, last_name, phone) 
                      VALUES (1, 'arduino_user', 'arduino@firedetection.com', 'password', 'Arduino', 'User', '+639318261972')
                      ON DUPLICATE KEY UPDATE user_id = user_id");
            $stmt->execute();

            // Ensure building exists
            $stmt = $conn->prepare("INSERT INTO buildings (building_id, building_name, building_type, address, user_id) 
                      VALUES (1, 'Arduino Test Building', 'Residential', 'Test Address', 1)
                      ON DUPLICATE KEY UPDATE building_id = building_id");
            $stmt->execute();

            // Create device
            $stmt = $conn->prepare("INSERT INTO devices (device_id, user_id, device_name, device_number, serial_number, building_id, status, is_active) 
                      VALUES (1, 1, 'Arduino Fire Sensor', 'ARD001', 'ESP32-FIRE-001', 1, 'online', 1)
                      ON DUPLICATE KEY UPDATE 
                          status = 'online',
                          is_active = 1,
                          last_activity = CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00')");
            $stmt->execute();

            return 1;
        } catch (Exception $e) {
            error_log("Error creating default device: " . $e->getMessage());
            return null;
        }
    }
    
    private function isValidDeviceId($device_id) {
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("SELECT device_id FROM devices WHERE device_id = ? AND is_active = 1");
            $stmt->execute([$device_id]);
            $row = $stmt->fetch();
            
            return $row !== false;
        } catch (Exception $e) {
            error_log("Error validating device ID: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateDeviceStatus($status = 'online') {
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("UPDATE devices SET status = ?, last_activity = CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', '+08:00') WHERE device_id = ?");
            $success = $stmt->execute([$status, $this->device_id]);
            
            if (!$success) {
                error_log("Error updating device status");
            }
            
            return $success;
        } catch (Exception $e) {
            error_log("Error updating device status: " . $e->getMessage());
            return false;
        }
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
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("INSERT INTO smoke_readings (device_id, sensor_value, detected) VALUES (?, ?, ?)");
            $success = $stmt->execute([$this->device_id, $this->value, $this->detected]);
            
            if (!$success) {
                error_log("Error inserting smoke reading");
                return 'failed';
            }
            
            return 'success';
        } catch (Exception $e) {
            error_log("Error inserting smoke reading: " . $e->getMessage());
            return 'failed';
        }
    }
    
    private function insertFlameReading() {
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("INSERT INTO flame_readings (device_id, detected) VALUES (?, ?)");
            $success = $stmt->execute([$this->device_id, $this->flame_detected]);
            
            if (!$success) {
                error_log("Error inserting flame reading");
                return 'failed';
            }
            
            return 'success';
        } catch (Exception $e) {
            error_log("Error inserting flame reading: " . $e->getMessage());
            return 'failed';
        }
    }
    
    private function insertEnvironmentReading($heat_index) {
        try {
            $conn = getDatabaseConnection();
            
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
            $success = $stmt->execute([$this->device_id, $temperature, $humidity, $heat_index_value]);
            
            if (!$success) {
                error_log("Error inserting environment reading");
                return 'failed';
            }
            
            return 'success';
        } catch (Exception $e) {
            error_log("Error inserting environment reading: " . $e->getMessage());
            return 'failed';
        }
    }

    private function insertGPSReading() {
        try {
            // Only insert GPS data if it's valid
            if (!$this->gps_valid) {
                return 'skipped (invalid GPS data)';
            }

            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("INSERT INTO gps_readings 
                                    (device_id, latitude, longitude, altitude, satellites) 
                                    VALUES (?, ?, ?, ?, ?)");
            $success = $stmt->execute([$this->device_id, $this->gps_latitude, 
                                      $this->gps_longitude, $this->gps_altitude, $this->gps_satellites]);
            
            if (!$success) {
                error_log("Error inserting GPS reading");
                return 'failed';
            }
            
            return 'success';
        } catch (Exception $e) {
            error_log("Error inserting GPS reading: " . $e->getMessage());
            return 'failed';
        }
    }

    private function insertFireData($sensor_data, $smoke_reading_id, $flame_reading_id) {
        try {
            $conn = getDatabaseConnection();

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

            $building_type = "Residential";
            $status = "NORMAL";
            $temp = intval($sensor_data['temp']); // Convert to int as per table schema
            $heat = intval($sensor_data['heat']); // Convert to int as per table schema

            $success = $stmt->execute([
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
            ]);

            if (!$success) {
                error_log("Execute failed for fire_data insert");
                return ['success' => false, 'id' => null];
            }

            $fire_data_id = $conn->lastInsertId();
            
            // Update device's latest_fire_data_id
            if ($success && $fire_data_id) {
                $this->updateDeviceLatestFireData($fire_data_id);
            }

            return ['success' => $success, 'id' => $fire_data_id];
        } catch (Exception $e) {
            error_log("Error inserting fire data: " . $e->getMessage());
            return ['success' => false, 'id' => null];
        }
    }

    private function getDeviceInfo($device_id = null) {
        try {
            $device_id = $device_id ?: $this->device_id;
            $conn = getDatabaseConnection();

            $stmt = $conn->prepare("SELECT device_id, user_id, device_name, device_number, serial_number, building_id, barangay_id, status FROM devices WHERE device_id = ?");
            $stmt->execute([$device_id]);
            $device_info = $stmt->fetch();

            if ($device_info) {
                return $device_info;
            }

            return null;
        } catch (Exception $e) {
            error_log("Error getting device info: " . $e->getMessage());
            return null;
        }
    }

    private function updateDeviceLatestFireData($fire_data_id) {
        try {
            $conn = getDatabaseConnection();
            $stmt = $conn->prepare("UPDATE devices SET latest_fire_data_id = ? WHERE device_id = ?");
            $success = $stmt->execute([$fire_data_id, $this->device_id]);
            
            if (!$success) {
                error_log("Failed to update device latest_fire_data_id");
            } else {
                error_log("Updated device {$this->device_id} with latest_fire_data_id: $fire_data_id");
            }

            return $success;
        } catch (Exception $e) {
            error_log("Error updating device latest_fire_data_id: " . $e->getMessage());
            return false;
        }
    }

    private function getLastInsertedId($table) {
        try {
            $conn = getDatabaseConnection();
            return $conn->lastInsertId();
        } catch (Exception $e) {
            error_log("Error getting last inserted ID: " . $e->getMessage());
            return null;
        }
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
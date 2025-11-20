<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Load configuration for SMS
$config = require 'config.php';
$apiKey = $config['api_key'];
$device = $config['device'];
$url = $config['url'];

// Fire Detection Logic Constants
const SMOKE_MONITORING_THRESHOLD = 500;
const SMOKE_EMERGENCY_THRESHOLD = 700;
const FLAME_EMERGENCY_THRESHOLD = 1;
const HIGH_TEMPERATURE_THRESHOLD = 50;  // °C
const CRITICAL_TEMPERATURE_THRESHOLD = 60;  // °C
const HIGH_SMOKE_THRESHOLD = 2000;
const HIGH_HEAT_INDEX_THRESHOLD = 35;  // °C

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
    private $last_alert_time = [];
    
    public function __construct() {
        $this->value = isset($_GET['value']) ? intval($_GET['value']) : 0;
        $this->detected = isset($_GET['detected']) ? intval($_GET['detected']) : 0;
        $this->flame_detected = isset($_GET['flame_detected']) ? intval($_GET['flame_detected']) : 0;
        $this->temperature = isset($_GET['temperature']) && $_GET['temperature'] !== '' ? floatval($_GET['temperature']) : null;
        $this->humidity = isset($_GET['humidity']) && $_GET['humidity'] !== '' ? floatval($_GET['humidity']) : null;
        $this->device_id = isset($_GET['device_id']) ? intval($_GET['device_id']) : null;
        $this->log = isset($_GET['log']) ? intval($_GET['log']) : 0;
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
            
            // Prepare sensor data for fire detection with better null handling
            $sensor_data = [
                'smoke' => $this->value,
                'temp' => $this->temperature !== null ? $this->temperature : 0,
                'heat' => $heat_index !== null ? $heat_index : ($this->temperature !== null ? $this->temperature : 0),
                'flame_detected' => $this->flame_detected,
                'humidity' => $this->humidity !== null ? $this->humidity : 0
            ];
            
            // Check fire detection logic
            $detection_result = $this->checkFireDetectionLogic($sensor_data);
            
            // Insert into fire_data table if conditions are met
            $fire_data_insertion = $this->insertFireData($sensor_data, $smoke_reading_id, $flame_reading_id);
            
            // Send SMS alerts if needed
            $sms_sent = $this->sendSMSAlertsIfNeeded($detection_result, $sensor_data);
            
            // Log the event to system_logs if there's a significant status
            if ($detection_result['status'] !== 'NORMAL') {
                $event_type = $detection_result['fire_detected'] ? "Fire Emergency" : $detection_result['status'] . " Alert";
                $this->logEvent($event_type, $sensor_data, $fire_data_insertion['id'], null);
            }
            
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
                'fire_detection' => $detection_result,
                'insertions' => [
                    'smoke' => $smoke_insertion,
                    'flame' => $flame_insertion,
                    'environment' => $environment_insertion,
                    'fire_data' => $fire_data_insertion
                ],
                'sms_sent' => $sms_sent,
                'processing_info' => [
                    'environment_data_processed' => $environment_insertion !== 'skipped (invalid data)',
                    'fire_data_inserted' => $fire_data_insertion['success'],
                    'event_logged' => $detection_result['status'] !== 'NORMAL'
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
    
   // Update the environment reading validation
    private function insertEnvironmentReading($heat_index) {
        $conn = Database::getConnection();
        if (!$conn) return 'failed';

        // Handle null values more gracefully
        $temperature = $this->temperature !== null ? $this->temperature : 0;
        $humidity = $this->humidity !== null ? $this->humidity : 0;
        $heat_index_value = $heat_index !== null ? $heat_index : $temperature;

        // Skip if values are clearly invalid (but allow 0 values)
        if (($this->temperature !== null && ($this->temperature < -20 || $this->temperature > 80)) ||
            ($this->humidity !== null && ($this->humidity < 0 || $this->humidity > 100))) {
            return 'skipped (invalid data)';
        }

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

/**
 * Comprehensive Fire Detection Logic Checker
 * Implements Arduino fire detection conditions:
 * 1. Flame detected = Immediate fire
 * 2. Smoke detected + High temperature (>50°C) = Fire
 * 3. High smoke (>2000) + High temperature (>60°C) = Fire
 * 4. High heat index (>35°C) + Smoke detected = Fire
 */
private function checkFireDetectionLogic($sensor_data) {
    $smoke = $sensor_data['smoke'];
    $temperature = $sensor_data['temp'];
    $heat_index = $sensor_data['heat'];
    $flame_detected = $sensor_data['flame_detected'];
    
    $detection_result = [
        'fire_detected' => false,
        'status' => 'NORMAL',
        'severity' => 'NONE',
        'conditions_met' => [],
        'details' => '',
        'emergency_level' => 0
    ];
    
    // Condition 1: Flame detected = Immediate fire (HIGHEST PRIORITY)
    if ($flame_detected >= FLAME_EMERGENCY_THRESHOLD) {
        $detection_result['fire_detected'] = true;
        $detection_result['status'] = 'EMERGENCY';
        $detection_result['severity'] = 'CRITICAL';
        $detection_result['emergency_level'] = 5;
        $detection_result['conditions_met'][] = 'FLAME_DETECTED';
        $detection_result['details'] = "🔥 FLAME DETECTED! Immediate fire emergency - Evacuate immediately!";
        return $detection_result;
    }
    
    // Condition 2: Smoke detected + High temperature (>50°C) = Fire
    if ($smoke > 0 && $temperature > HIGH_TEMPERATURE_THRESHOLD) {
        $detection_result['fire_detected'] = true;
        $detection_result['status'] = 'EMERGENCY';
        $detection_result['severity'] = 'HIGH';
        $detection_result['emergency_level'] = 4;
        $detection_result['conditions_met'][] = 'SMOKE_AND_HIGH_TEMP';
        $detection_result['details'] = "🚨 FIRE DETECTED! Smoke detected with high temperature ({$temperature}°C) - Immediate action required!";
        return $detection_result;
    }
    
    // Condition 3: High smoke (>2000) + High temperature (>60°C) = Fire
    if ($smoke > HIGH_SMOKE_THRESHOLD && $temperature > CRITICAL_TEMPERATURE_THRESHOLD) {
        $detection_result['fire_detected'] = true;
        $detection_result['status'] = 'EMERGENCY';
        $detection_result['severity'] = 'CRITICAL';
        $detection_result['emergency_level'] = 5;
        $detection_result['conditions_met'][] = 'HIGH_SMOKE_AND_CRITICAL_TEMP';
        $detection_result['details'] = "🔥 CRITICAL FIRE! High smoke level ({$smoke}) with critical temperature ({$temperature}°C) - Evacuate immediately!";
        return $detection_result;
    }
    
    // Condition 4: High heat index (>35°C) + Smoke detected = Fire
    if ($heat_index > HIGH_HEAT_INDEX_THRESHOLD && $smoke > 0) {
        $detection_result['fire_detected'] = true;
        $detection_result['status'] = 'EMERGENCY';
        $detection_result['severity'] = 'HIGH';
        $detection_result['emergency_level'] = 4;
        $detection_result['conditions_met'][] = 'HIGH_HEAT_INDEX_AND_SMOKE';
        $detection_result['details'] = "🚨 FIRE DETECTED! High heat index ({$heat_index}°C) with smoke detected - Immediate action required!";
        return $detection_result;
    }
    
    // Additional monitoring conditions for early warning
    if ($smoke >= SMOKE_EMERGENCY_THRESHOLD) {
        $detection_result['status'] = 'EMERGENCY';
        $detection_result['severity'] = 'HIGH';
        $detection_result['emergency_level'] = 3;
        $detection_result['conditions_met'][] = 'HIGH_SMOKE_LEVEL';
        $detection_result['details'] = "⚠️ CRITICAL SMOKE LEVEL! Smoke detected at dangerous levels ({$smoke}) - Monitor closely!";
    } elseif ($smoke >= SMOKE_MONITORING_THRESHOLD) {
        $detection_result['status'] = 'MONITORING';
        $detection_result['severity'] = 'MEDIUM';
        $detection_result['emergency_level'] = 2;
        $detection_result['conditions_met'][] = 'ELEVATED_SMOKE';
        $detection_result['details'] = "⚠️ Monitoring Mode: Elevated smoke levels detected ({$smoke}) - Stay alert!";
    } elseif ($temperature > HIGH_TEMPERATURE_THRESHOLD && $temperature > 0) {
        $detection_result['status'] = 'MONITORING';
        $detection_result['severity'] = 'MEDIUM';
        $detection_result['emergency_level'] = 1;
        $detection_result['conditions_met'][] = 'HIGH_TEMPERATURE';
        $detection_result['details'] = "🌡️ High temperature detected ({$temperature}°C) - Monitor for smoke or other fire indicators!";
    } elseif ($smoke > 0) {
        // If we have any smoke detection but no other conditions met, still log it
        $detection_result['status'] = 'MONITORING';
        $detection_result['severity'] = 'LOW';
        $detection_result['emergency_level'] = 1;
        $detection_result['conditions_met'][] = 'SMOKE_DETECTED';
        $detection_result['details'] = "⚠️ Smoke detected ({$smoke}) - Monitor for additional fire indicators!";
    }
    
    return $detection_result;
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

    // Check fire detection logic to determine status
    $detection_result = $this->checkFireDetectionLogic($sensor_data);
    
    // Only insert if we have a status to report (not NORMAL)
    if ($detection_result['status'] !== 'NORMAL') {
        $stmt = $conn->prepare("INSERT INTO fire_data (
            status, building_type, smoke, temp, heat, flame_detected,
            user_id, building_id, smoke_reading_id, flame_reading_id, device_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            return ['success' => false, 'id' => null];
        }

        $building_type = "Residential";
        $temp = $sensor_data['temp'];
        $heat = $sensor_data['heat'];

        $stmt->bind_param(
            "ssiiiisiiii",
            $detection_result['status'],
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
    
    return ['success' => false, 'id' => null, 'reason' => 'Normal conditions - no fire data needed'];
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

    private function getBuildingInfo($building_id) {
        if (!$building_id) return null;
        
        $conn = Database::getConnection();
        if (!$conn) return null;

        $stmt = $conn->prepare("SELECT building_id, building_name, building_type, address, contact_person, contact_number, latitude, longitude FROM buildings WHERE building_id = ?");
        $stmt->bind_param("i", $building_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $building_info = $result->fetch_assoc();
            $stmt->close();
            return $building_info;
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

private function sendSMSAlertsIfNeeded($detection_result, $sensor_data) {
    // Only send SMS for emergency conditions
    if ($detection_result['fire_detected'] || $detection_result['emergency_level'] >= 3) {
        $current_time = time();
        $alert_cooldown = 300; // 5 minutes cooldown between alerts
        
        $last_alert = isset($this->last_alert_time[$this->device_id]) ? $this->last_alert_time[$this->device_id] : 0;
        
        if ($current_time - $last_alert >= $alert_cooldown) {
            $this->last_alert_time[$this->device_id] = $current_time;
            return $this->sendSMSAlerts($detection_result, $sensor_data);
        } else {
            error_log("Skipping SMS alert for device {$this->device_id} - cooldown period active");
            return false;
        }
    }
    
    return false;
}

private function sendSMSAlerts($detection_result, $sensor_data) {
    global $apiKey, $device, $url;
    
    // Get device info to extract user_id
    $device_info = $this->getDeviceInfo();
    if (!$device_info) {
        error_log("Device info not found for device_id: " . $this->device_id);
        return false;
    }
    
    $user_id = $device_info['user_id'];
    
    // Get user's verified phone numbers
    $recipients = $this->getUserPhoneNumbers($user_id, true);
    
    if (empty($recipients)) {
        error_log("No verified phone numbers found for user ID: $user_id (device: {$this->device_id})");
        // Fallback to default recipients if no user phone numbers found
        $recipients = ["09318261972", "+63956250805", "09850232318"];
        error_log("Using fallback recipients for device: {$this->device_id}");
    }
    
    $message = $this->generateEmergencyMessage($device_info, $detection_result, $sensor_data);
    
    $success_count = 0;
    foreach ($recipients as $recipient) {
        $params = [
            'message'       => $message,
            'mobile_number' => $recipient,
            'device'        => $device
        ];

        $headers = [
            "Content-Type: application/x-www-form-urlencoded",
            "apikey: $apiKey"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);

        if (curl_error($ch)) {
            error_log('cURL Error: ' . curl_error($ch));
        } else {
            error_log("SMS sent to $recipient for device {$this->device_id}: $message\nResponse: $response");
            $success_count++;
        }

        curl_close($ch);
    }
    
    return $success_count > 0;
}

private function getUserPhoneNumbers($user_id, $only_verified = true) {
    $conn = Database::getConnection();
    if (!$conn) return [];

    $query = "SELECT phone_number FROM user_phone_numbers WHERE user_id = ?";
    
    if ($only_verified) {
        $query .= " AND verified = 1";
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $phone_numbers = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $phone_numbers[] = $row['phone_number'];
        }
    }
    
    $stmt->close();
    
    return $phone_numbers;
}

private function generateEmergencyMessage($device_info, $detection_result, $sensor_data) {
    $device_name = $device_info['device_name'];
    $device_number = $device_info['device_number'];
    
    // Get building information
    $building_info = $this->getBuildingInfo($device_info['building_id']);
    $building_location = $building_info ? $building_info['address'] : 'Location Unknown';
    $building_name = $building_info ? $building_info['building_name'] : 'Unknown Building';
    
    // Create a shorter, more concise message to stay within SMS limits
    $message = "FIRE ALERT: $device_name\n";
    $message .= "Building: $building_name\n";
    $message .= "Location: $building_location\n";
    $message .= "Status: {$detection_result['status']}\n";
    $message .= "Smoke: {$sensor_data['smoke']}\n";
    $message .= "Temp: {$sensor_data['temp']}C\n";
    $message .= "Flame: " . ($sensor_data['flame_detected'] ? 'YES' : 'NO') . "\n";
    
    // Add emergency details based on level
    switch ($detection_result['emergency_level']) {
        case 5:
            $message .= "EMERGENCY: EVACUATE NOW!";
            break;
        case 4:
            $message .= "CRITICAL: IMMEDIATE ACTION!";
            break;
        case 3:
            $message .= "DANGEROUS: MONITOR CLOSELY!";
            break;
        case 2:
            $message .= "ELEVATED RISK!";
            break;
        case 1:
            $message .= "MONITORING ALERT!";
            break;
        default:
            $message .= "NORMAL CONDITIONS";
    }
    
    return $message;
}

// Update the error handling
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
    
    /**
     * Log events to system_logs table
     */
    private function logEvent($event_type, $sensor_data, $fire_data_id = null, $admin_id = null) {
        $conn = Database::getConnection();
        if (!$conn) return;

        // Get device info to extract user_id and building_id
        $device_info = $this->getDeviceInfo();
        $user_id = $device_info ? $device_info['user_id'] : null;
        $building_id = $device_info ? $device_info['building_id'] : null;

        $stmt = $conn->prepare(
            "INSERT INTO system_logs (
                event_type, temperature, heat_level, smoke_level, flame_detected,
                user_action, log_message, log_level, user_id, fire_data_id, admin_id, building_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        $user_action = "System detected fire risk";
        $log_message = "Fire risk detected for device ID {$this->device_id}.";
        $log_level = "INFO";

        $stmt->bind_param(
            "siddiissssii",
            $event_type,
            $sensor_data['temp'],
            $sensor_data['heat'],
            $sensor_data['smoke'],
            $sensor_data['flame_detected'],
            $user_action,
            $log_message,
            $log_level,
            $user_id,
            $fire_data_id,
            $admin_id,
            $building_id
        );

        if ($stmt->execute()) {
            error_log("Event logged for device ID: {$this->device_id}");
        } else {
            error_log("Error logging event: " . $stmt->error);
        }

        $stmt->close();
    }
    
    /**
     * Get latest smoke reading for a device
     */
    private function getLatestSmokeReading($device_id = null) {
        $device_id = $device_id ?: $this->device_id;
        $conn = Database::getConnection();
        if (!$conn) return ['smoke' => 0, 'detected' => 0, 'id' => null];

        $query = "SELECT id, sensor_value, detected FROM smoke_readings WHERE device_id = ? ORDER BY reading_time DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return [
                'smoke' => $row['sensor_value'],
                'detected' => $row['detected'],
                'id' => $row['id']
            ];
        }

        $stmt->close();
        return ['smoke' => 0, 'detected' => 0, 'id' => null];
    }
    
    /**
     * Get latest flame reading for a device
     */
    private function getLatestFlameReading($device_id = null) {
        $device_id = $device_id ?: $this->device_id;
        $conn = Database::getConnection();
        if (!$conn) return ['flame_detected' => 0, 'id' => null];

        $query = "SELECT id, detected FROM flame_readings WHERE device_id = ? ORDER BY reading_time DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return [
                'flame_detected' => (int)$row['detected'],
                'id' => $row['id']
            ];
        }

        $stmt->close();
        return ['flame_detected' => 0, 'id' => null];
    }
    
    /**
     * Get latest environment reading for a device
     */
    private function getLatestEnvironmentReading($device_id = null) {
        $device_id = $device_id ?: $this->device_id;
        $conn = Database::getConnection();
        if (!$conn) return ['temperature' => 0, 'humidity' => 0, 'heat_index' => 0, 'id' => null];

        $query = "SELECT id, temperature, humidity, heat_index FROM environment_readings WHERE device_id = ? ORDER BY reading_time DESC LIMIT 1";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $device_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return [
                'temperature' => $row['temperature'],
                'humidity' => $row['humidity'],
                'heat_index' => $row['heat_index'],
                'id' => $row['id']
            ];
        }

        $stmt->close();
        return ['temperature' => 0, 'humidity' => 0, 'heat_index' => 0, 'id' => null];
    }
    
    /**
     * Get all active devices
     */
    private function getAllActiveDevices() {
        $conn = Database::getConnection();
        if (!$conn) return [];

        $query = "SELECT device_id, user_id, device_name, device_number, serial_number, building_id, status 
                  FROM devices 
                  WHERE is_active = 1 AND status = 'online'
                  ORDER BY device_id";
        
        $result = $conn->query($query);
        $devices = [];

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $devices[] = $row;
            }
        }

        return $devices;
    }
    
    /**
     * Get active sensor device (preferably device 5)
     */
    private function getActiveSensorDevice() {
        $conn = Database::getConnection();
        if (!$conn) return null;

        // Get the device that's actually sending sensor data
        // First try to find device 5 (DEV425FTVWIE) which is the known active sensor device
        $query = "SELECT device_id, user_id, device_name, device_number, serial_number, building_id, status 
                  FROM devices 
                  WHERE device_id = 5 AND is_active = 1 AND status = 'online'
                  LIMIT 1";
        
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $device = $result->fetch_assoc();
            return $device;
        }

        // Fallback: get the first active online device
        $fallback_query = "SELECT device_id, user_id, device_name, device_number, serial_number, building_id, status 
                           FROM devices 
                           WHERE is_active = 1 AND status = 'online'
                           ORDER BY device_id
                           LIMIT 1";
        
        $fallback_result = $conn->query($fallback_query);
        
        if ($fallback_result && $fallback_result->num_rows > 0) {
            $device = $fallback_result->fetch_assoc();
            error_log("Using fallback device: " . $device['device_name'] . " (" . $device['device_number'] . ")");
            return $device;
        }

        return null;
    }
    
    /**
     * Send SMS alerts to multiple users
     */
    private function sendSMSAlertsToMultipleUsers($user_ids, $message) {
        global $apiKey, $device, $url;
        
        $all_recipients = [];
        
        // Get phone numbers for all specified users
        foreach ($user_ids as $user_id) {
            $recipients = $this->getUserPhoneNumbers($user_id, true);
            $all_recipients = array_merge($all_recipients, $recipients);
        }
        
        // Remove duplicates
        $all_recipients = array_unique($all_recipients);
        
        if (empty($all_recipients)) {
            error_log("No verified phone numbers found for any of the specified users");
            return false;
        }
        
        $success_count = 0;
        foreach ($all_recipients as $recipient) {
            $params = [
                'message'       => $message,
                'mobile_number' => $recipient,
                'device'        => $device
            ];

            $headers = [
                "Content-Type: application/x-www-form-urlencoded",
                "apikey: $apiKey"
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($ch);

            if (curl_error($ch)) {
                error_log('cURL Error: ' . curl_error($ch));
            } else {
                error_log("SMS sent to $recipient: $message\nResponse: $response");
                $success_count++;
            }

            curl_close($ch);
        }
        
        return $success_count > 0;
    }
    
    /**
     * Check latest status for a device (for periodic monitoring)
     */
    private function checkLatestStatusForDevice($device_id = null) {
        $device_id = $device_id ?: $this->device_id;
        
        try {
            // Get device info for better messaging
            $device_info = $this->getDeviceInfo($device_id);
            if (!$device_info) {
                error_log("Device info not found for device_id: $device_id");
                return;
            }

            // Get latest readings
            $smoke_data = $this->getLatestSmokeReading($device_id);
            $flame_data = $this->getLatestFlameReading($device_id);
            $env_data = $this->getLatestEnvironmentReading($device_id);

            // Prepare sensor data
            $sensor_data = [
                "smoke" => $smoke_data['smoke'],
                "temp" => $env_data['temperature'],
                "heat" => $env_data['heat_index'],
                "flame_detected" => $flame_data['flame_detected'],
                "humidity" => $env_data['humidity']
            ];

            // Use the comprehensive fire detection logic
            $detection_result = $this->checkFireDetectionLogic($sensor_data);
            
            // Log the periodic status check with enhanced information
            $event_type = "Periodic Status Check - " . $detection_result['status'];
            $this->logEvent($event_type, $sensor_data, null, null);
            
            // Log detailed status information
            $device_name = $device_info['device_name'];
            $device_number = $device_info['device_number'];
            error_log("Periodic Status Check (Device: $device_name - $device_number): " . 
                     "Status={$detection_result['status']}, Severity={$detection_result['severity']}, " .
                     "Emergency Level={$detection_result['emergency_level']}, " .
                     "Smoke=" . $sensor_data["smoke"] . ", Temp=" . $sensor_data["temp"] . "°C, " .
                     "Heat=" . $sensor_data["heat"] . "°C, Flame=" . $sensor_data["flame_detected"] . 
                     ", Conditions Met: " . implode(', ', $detection_result['conditions_met']));

        } catch (Exception $e) {
            error_log("Error in periodic status check for device $device_id: " . $e->getMessage());
        }
    }
    
    /**
     * Monitor all active devices (for continuous monitoring mode)
     */
    private function monitorAllActiveDevices() {
        // Only monitor the device that's actually sending sensor data
        $active_sensor_device = $this->getActiveSensorDevice();
        
        if (!$active_sensor_device) {
            error_log("No active sensor device found to monitor.");
            return;
        }
        
        error_log("Monitoring active sensor device: " . $active_sensor_device['device_id'] . " (" . $active_sensor_device['device_name'] . " - " . $active_sensor_device['device_number'] . ")");
        
        try {
            // Get latest readings and process them
            $smoke_data = $this->getLatestSmokeReading($active_sensor_device['device_id']);
            $flame_data = $this->getLatestFlameReading($active_sensor_device['device_id']);
            $env_data = $this->getLatestEnvironmentReading($active_sensor_device['device_id']);

            // Prepare sensor data
            $sensor_data = [
                "smoke" => $smoke_data['smoke'],
                "temp" => $env_data['temperature'],
                "heat" => $env_data['heat_index'],
                "flame_detected" => $flame_data['flame_detected'],
                "humidity" => $env_data['humidity']
            ];

            // Use the comprehensive fire detection logic
            $detection_result = $this->checkFireDetectionLogic($sensor_data);
            
            // Insert into fire_data (will only insert if status is not null)
            $insert_result = $this->insertFireData($sensor_data, $smoke_data['id'], $flame_data['id']);
            
            // Send SMS alerts based on detection result - but only if it's a new emergency or enough time has passed
            $current_time = time();
            $alert_cooldown = 300; // 5 minutes cooldown between alerts for same condition
            
            $should_send_alert = false;
            if ($detection_result['fire_detected'] || $detection_result['emergency_level'] >= 2) {
                $last_alert = isset($this->last_alert_time[$active_sensor_device['device_id']]) ? $this->last_alert_time[$active_sensor_device['device_id']] : 0;
                
                if ($current_time - $last_alert >= $alert_cooldown) {
                    $should_send_alert = true;
                    $this->last_alert_time[$active_sensor_device['device_id']] = $current_time;
                    error_log("Sending SMS alert for device {$active_sensor_device['device_id']} - cooldown period passed");
                } else {
                    error_log("Skipping SMS alert for device {$active_sensor_device['device_id']} - cooldown period active (" . ($current_time - $last_alert) . "s remaining)");
                }
            }
            
            if ($should_send_alert) {
                $emergency_message = $this->generateEmergencyMessage($device_info, $detection_result, $sensor_data);
                $this->sendSMSAlerts($detection_result, $sensor_data);
            }

            // Log the event with enhanced information
            if ($detection_result['status'] !== 'NORMAL') {
                $event_type = $detection_result['fire_detected'] ? "Fire Emergency" : $detection_result['status'] . " Alert";
                $this->logEvent($event_type, $sensor_data, $insert_result['id'], null);
                
                // Log detailed detection information
                error_log("Fire Detection Result for device {$active_sensor_device['device_id']}: " . json_encode($detection_result));
            } else {
                error_log("Normal conditions detected for device {$active_sensor_device['device_id']}. No alerts sent.");
            }

        } catch (Exception $e) {
            error_log("Error monitoring device {$active_sensor_device['device_id']}: " . $e->getMessage());
        }
    }
    
    /**
     * Check all devices status every 5 minutes (for continuous monitoring)
     */
    private function checkAllDevicesStatusEvery5mins() {
        // Only check the device that's actually sending sensor data
        $active_sensor_device = $this->getActiveSensorDevice();
        
        if (!$active_sensor_device) {
            error_log("No active sensor device found for periodic status check.");
            return;
        }
        
        error_log("Performing 5-minute status check on active sensor device: " . $active_sensor_device['device_name'] . " (" . $active_sensor_device['device_number'] . ")");
        
        try {
            $this->checkLatestStatusForDevice($active_sensor_device['device_id']);
        } catch (Exception $e) {
            error_log("Error in periodic status check for device {$active_sensor_device['device_id']}: " . $e->getMessage());
        }
    }
    


}

// Execute the API
$api = new SmokeAPI();
$api->processRequest();
?>
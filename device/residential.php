<?php

require '../../vendor/autoload.php';
$config = require 'config.php';
use WebSocket\Client;

// Load configuration
$apiKey = $config['api_key'];
$device = $config['device'];
$url    = $config['url'];

// Threshold constants
const SMOKE_MONITORING_THRESHOLD = 500;
const SMOKE_EMERGENCY_THRESHOLD = 700;
const FLAME_EMERGENCY_THRESHOLD = 1;

// Fire Detection Logic Constants
const HIGH_TEMPERATURE_THRESHOLD = 50;  // °C
const CRITICAL_TEMPERATURE_THRESHOLD = 60;  // °C
const HIGH_SMOKE_THRESHOLD = 2000;
const HIGH_HEAT_INDEX_THRESHOLD = 35;  // °C

// Database configuration
$host = "localhost";
$dbname = "u520834156_DBBagofire";
$username = "u520834156_userBagofire";
$password = "i[#[GQ!+=C9";

// WebSocket URL for clients: wss://fireguard.bccbsis.com/ws

// Add global variables to track last processed data
$last_processed_data = [];
$last_alert_time = [];

function get_db_connection() {
    $conn = new mysqli($host, $username, $password, $dbname);
    if ($conn->connect_error) {
        error_log("Database connection failed: " . $conn->connect_error);
        return null;
    }
    return $conn;
}

function get_all_active_devices() {
    $conn = get_db_connection();
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

    $conn->close();
    return $devices;
}

function get_device_info($device_id) {
    $conn = get_db_connection();
    if (!$conn) return null;

    $stmt = $conn->prepare("SELECT device_id, user_id, device_name, device_number, serial_number, building_id, status FROM devices WHERE device_id = ?");
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $device_info = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $device_info;
    }

    $stmt->close();
    $conn->close();
    return null;
}

function get_user_phone_numbers($user_id, $only_verified = true) {
    $conn = get_db_connection();
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
    $conn->close();
    
    return $phone_numbers;
}

function send_sms_alerts_to_device_user($device_id, $message) {
    global $apiKey, $device, $url;
    
    // Get device info to extract user_id
    $device_info = get_device_info($device_id);
    if (!$device_info) {
        error_log("Device info not found for device_id: $device_id");
        return false;
    }
    
    $user_id = $device_info['user_id'];
    
    // Get user's verified phone numbers
    $recipients = get_user_phone_numbers($user_id, true);
    
    if (empty($recipients)) {
        error_log("No verified phone numbers found for user ID: $user_id (device: $device_id)");
        // Fallback to default recipients if no user phone numbers found
        $recipients = ["09318261972", "+63956250805", "09850232318"];
        error_log("Using fallback recipients for device: $device_id");
    }
    
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
            error_log("SMS sent to $recipient for device $device_id: $message\nResponse: $response");
            $success_count++;
        }

        curl_close($ch);
    }
    
    return $success_count > 0;
}

function send_sms_alerts_to_multiple_users($user_ids, $message) {
    global $apiKey, $device, $url;
    
    $all_recipients = [];
    
    // Get phone numbers for all specified users
    foreach ($user_ids as $user_id) {
        $recipients = get_user_phone_numbers($user_id, true);
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

function log_event($event_type, $sensor_data, $device_id = null, $fire_data_id = null, $admin_id = null) {
    $conn = get_db_connection();
    if (!$conn) return;

    // Get device info to extract user_id and building_id
    $device_info = null;
    if ($device_id) {
        $device_info = get_device_info($device_id);
    }

    $user_id = $device_info ? $device_info['user_id'] : null;
    $building_id = $device_info ? $device_info['building_id'] : null;

    $stmt = $conn->prepare(
        "INSERT INTO system_logs (
            event_type, temperature, heat_level, smoke_level, flame_detected,
            user_action, log_message, log_level, user_id, fire_data_id, admin_id, building_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $user_action = "System detected fire risk";
    $log_message = "Fire risk detected for device ID $device_id.";
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
        error_log("Event logged for device ID: $device_id");
    } else {
        error_log("Error logging event: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();
}

function get_latest_smoke_reading($device_id = 5) {
    $conn = get_db_connection();
    if (!$conn) return ['smoke' => 0, 'detected' => 0, 'id' => null];

    $query = "SELECT id, sensor_value, detected FROM smoke_readings WHERE device_id = ? ORDER BY reading_time DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return [
            'smoke' => $row['sensor_value'],
            'detected' => $row['detected'],
            'id' => $row['id']
        ];
    }

    $stmt->close();
    $conn->close();
    return ['smoke' => 0, 'detected' => 0, 'id' => null];
}

function get_latest_flame_reading($device_id = 5) {
    $conn = get_db_connection();
    if (!$conn) return ['flame_detected' => 0, 'id' => null];

    $query = "SELECT id, detected FROM flame_readings WHERE device_id = ? ORDER BY reading_time DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return [
            'flame_detected' => (int)$row['detected'],
            'id' => $row['id']
        ];
    }

    $stmt->close();
    $conn->close();
    return ['flame_detected' => 0, 'id' => null];
}

function get_latest_environment_reading($device_id = 5) {
    $conn = get_db_connection();
    if (!$conn) return ['temperature' => 0, 'humidity' => 0, 'heat_index' => 0, 'id' => null];

    $query = "SELECT id, temperature, humidity, heat_index FROM environment_readings WHERE device_id = ? ORDER BY reading_time DESC LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $device_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        return [
            'temperature' => $row['temperature'],
            'humidity' => $row['humidity'],
            'heat_index' => $row['heat_index'],
            'id' => $row['id']
        ];
    }

    $stmt->close();
    $conn->close();
    return ['temperature' => 0, 'humidity' => 0, 'heat_index' => 0, 'id' => null];
}

function insert_fire_data($smoke_data, $flame_data, $env_data, $device_id) {
    $conn = get_db_connection();
    if (!$conn) return ['success' => false, 'id' => null];

    // Get device info to extract user_id and building_id
    $device_info = get_device_info($device_id);
    if (!$device_info) {
        error_log("Device info not found for device_id: $device_id");
        $conn->close();
        return ['success' => false, 'id' => null];
    }

    $user_id = $device_info['user_id'];
    $building_id = $device_info['building_id'];

    // Initialize status as null (no action for smoke < 500)
    $status = null;
    
    // Set status based on conditions
    if ($flame_data['flame_detected'] || $smoke_data['smoke'] >= SMOKE_EMERGENCY_THRESHOLD) {
        $status = "EMERGENCY";
    } elseif ($smoke_data['smoke'] >= SMOKE_MONITORING_THRESHOLD) {
        $status = "MONITORING";
    }

    // Only insert if we have a status to report
    if ($status !== null) {
        $stmt = $conn->prepare("INSERT INTO fire_data (
            status, building_type, smoke, temp, heat, flame_detected,
            user_id, building_id, smoke_reading_id, flame_reading_id, device_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            $conn->close();
            return ['success' => false, 'id' => null];
        }

        $building_type = "Residential";
        $temp = $env_data['temperature'];
        $heat = $env_data['heat_index'];

        $stmt->bind_param(
            "ssiiiisiiii",
            $status,
            $building_type,
            $smoke_data['smoke'],
            $temp,
            $heat,
            $flame_data['flame_detected'],
            $user_id,
            $building_id,
            $smoke_data['id'],
            $flame_data['id'],
            $device_id
        );

        $success = $stmt->execute();
        if (!$success) {
            error_log("Execute failed: " . $stmt->error);
        }

        $fire_data_id = $conn->insert_id;
        
        // Update device's latest_fire_data_id
        if ($success && $fire_data_id) {
            update_device_latest_fire_data($device_id, $fire_data_id);
        }

        $stmt->close();
        $conn->close();
        return ['success' => $success, 'id' => $fire_data_id];
    }
    
    $conn->close();
    return ['success' => false, 'id' => null];
}

function update_device_latest_fire_data($device_id, $fire_data_id) {
    $conn = get_db_connection();
    if (!$conn) return false;

    $stmt = $conn->prepare("UPDATE devices SET latest_fire_data_id = ? WHERE device_id = ?");
    $stmt->bind_param("ii", $fire_data_id, $device_id);
    $success = $stmt->execute();
    
    if (!$success) {
        error_log("Failed to update device latest_fire_data_id: " . $stmt->error);
    } else {
        error_log("Updated device $device_id with latest_fire_data_id: $fire_data_id");
    }

    $stmt->close();
    $conn->close();
    return $success;
}

function send_latest_readings_to_websocket($device_id) {
    global $last_processed_data, $last_alert_time;
    
    try {
        // Get device info for better messaging
        $device_info = get_device_info($device_id);
        if (!$device_info) {
            error_log("Device info not found for device_id: $device_id");
            return;
        }

        // Get latest readings
        $smoke_data = get_latest_smoke_reading($device_id);
        $flame_data = get_latest_flame_reading($device_id);
        $env_data = get_latest_environment_reading($device_id);

        // Prepare sensor data
        $sensor_data = [
            "smoke" => $smoke_data['smoke'],
            "temp" => $env_data['temperature'],
            "heat" => $env_data['heat_index'],
            "flame_detected" => $flame_data['flame_detected'],
            "humidity" => $env_data['humidity']
        ];

        // Use the new comprehensive fire detection logic
        $detection_result = check_fire_detection_logic($sensor_data);
        
        // Insert into fire_data (will only insert if status is not null)
        $insert_result = insert_fire_data($smoke_data, $flame_data, $env_data, $device_id);
        
        $device_name = $device_info['device_name'];
        $device_number = $device_info['device_number'];
        
        // Send SMS alerts based on detection result - but only if it's a new emergency or enough time has passed
        $current_time = time();
        $alert_cooldown = 300; // 5 minutes cooldown between alerts for same condition
        
        $should_send_alert = false;
        if ($detection_result['fire_detected'] || $detection_result['emergency_level'] >= 2) {
            $last_alert = isset($last_alert_time[$device_id]) ? $last_alert_time[$device_id] : 0;
            
            if ($current_time - $last_alert >= $alert_cooldown) {
                $should_send_alert = true;
                $last_alert_time[$device_id] = $current_time;
                error_log("Sending SMS alert for device $device_id - cooldown period passed");
            } else {
                error_log("Skipping SMS alert for device $device_id - cooldown period active (" . ($current_time - $last_alert) . "s remaining)");
            }
        }
        
        if ($should_send_alert) {
            $emergency_message = generate_emergency_message($device_info, $detection_result, $sensor_data);
            send_sms_alerts_to_device_user($device_id, $emergency_message);
        }

        // Send to WebSocket if we have any significant status
        if ($detection_result['status'] !== 'NORMAL') {
            $client = new Client("ws://localhost:3000");
            
            // Prepare enhanced data for WebSocket
            $data = [
                "status" => $detection_result['status'],
                "building_type" => "Residential",
                "smoke" => $sensor_data["smoke"],
                "temp" => $sensor_data["temp"],
                "heat" => $sensor_data["heat"],
                "humidity" => $sensor_data["humidity"],
                "flame_detected" => $sensor_data["flame_detected"],
                "device_id" => $device_id,
                "device_name" => $device_name,
                "device_number" => $device_number,
                "serial_number" => $device_info['serial_number'],
                "user_id" => $device_info['user_id'],
                "building_id" => $device_info['building_id'],
                "timestamp" => date('Y-m-d H:i:s'),
                "fire_detected" => $detection_result['fire_detected'],
                "severity" => $detection_result['severity'],
                "emergency_level" => $detection_result['emergency_level'],
                "conditions_met" => $detection_result['conditions_met'],
                "details" => $detection_result['details']
            ];

            // Send to WebSocket
            $client->send(json_encode($data));
            error_log("Sent to WebSocket (device ID $device_id): " . json_encode($data));
            
            // Log the event with enhanced information
            $event_type = $detection_result['fire_detected'] ? "Fire Emergency" : $detection_result['status'] . " Alert";
            log_event($event_type, $sensor_data, $device_id, $insert_result['id'], null);
            
            // Log detailed detection information
            error_log("Fire Detection Result for device $device_id: " . json_encode($detection_result));
        } else {
            error_log("Normal conditions detected for device $device_id. No alerts sent.");
        }

    } catch (Exception $e) {
        error_log("Error processing device $device_id: " . $e->getMessage());
    }
}

function check_latest_status_every_5mins($device_id) {
    try {
        // Get device info for better messaging
        $device_info = get_device_info($device_id);
        if (!$device_info) {
            error_log("Device info not found for device_id: $device_id");
            return;
        }

        // Get latest readings
        $smoke_data = get_latest_smoke_reading($device_id);
        $flame_data = get_latest_flame_reading($device_id);
        $env_data = get_latest_environment_reading($device_id);

        // Prepare sensor data
        $sensor_data = [
            "smoke" => $smoke_data['smoke'],
            "temp" => $env_data['temperature'],
            "heat" => $env_data['heat_index'],
            "flame_detected" => $flame_data['flame_detected'],
            "humidity" => $env_data['humidity']
        ];

        // Use the new comprehensive fire detection logic
        $detection_result = check_fire_detection_logic($sensor_data);
        
        // Log the periodic status check with enhanced information
        $event_type = "Periodic Status Check - " . $detection_result['status'];
        log_event($event_type, $sensor_data, $device_id, null, null);
        
        // Log detailed status information
        $device_name = $device_info['device_name'];
        $device_number = $device_info['device_number'];
        error_log("5-Minute Status Check (Device: $device_name - $device_number): " . 
                 "Status={$detection_result['status']}, Severity={$detection_result['severity']}, " .
                 "Emergency Level={$detection_result['emergency_level']}, " .
                 "Smoke=" . $sensor_data["smoke"] . ", Temp=" . $sensor_data["temp"] . "°C, " .
                 "Heat=" . $sensor_data["heat"] . "°C, Flame=" . $sensor_data["flame_detected"] . 
                 ", Conditions Met: " . implode(', ', $detection_result['conditions_met']));

        // Always send to WebSocket with enhanced data
        $client = new Client("ws://localhost:3000");
        
        $data = [
            "status" => $detection_result['status'],
            "building_type" => "Residential",
            "smoke" => $sensor_data["smoke"],
            "temp" => $sensor_data["temp"],
            "heat" => $sensor_data["heat"],
            "humidity" => $sensor_data["humidity"],
            "flame_detected" => $sensor_data["flame_detected"],
            "device_id" => $device_id,
            "device_name" => $device_name,
            "device_number" => $device_number,
            "serial_number" => $device_info['serial_number'],
            "user_id" => $device_info['user_id'],
            "building_id" => $device_info['building_id'],
            "timestamp" => date('Y-m-d H:i:s'),
            "check_type" => "periodic_5min",
            "fire_detected" => $detection_result['fire_detected'],
            "severity" => $detection_result['severity'],
            "emergency_level" => $detection_result['emergency_level'],
            "conditions_met" => $detection_result['conditions_met'],
            "details" => $detection_result['details']
        ];

        $client->send(json_encode($data));
        error_log("Periodic status sent to WebSocket (device: $device_name - $device_number): " . json_encode($data));

    } catch (Exception $e) {
        error_log("Error in periodic status check for device $device_id: " . $e->getMessage());
    }
}

function get_active_sensor_device() {
    $conn = get_db_connection();
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
        $conn->close();
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
        $conn->close();
        error_log("Using fallback device: " . $device['device_name'] . " (" . $device['device_number'] . ")");
        return $device;
    }

    $conn->close();
    return null;
}

function monitor_all_active_devices() {
    // Only monitor the device that's actually sending sensor data
    $active_sensor_device = get_active_sensor_device();
    
    if (!$active_sensor_device) {
        error_log("No active sensor device found to monitor.");
        return;
    }
    
    error_log("Monitoring active sensor device: " . $active_sensor_device['device_id'] . " (" . $active_sensor_device['device_name'] . " - " . $active_sensor_device['device_number'] . ")");
    
    try {
        send_latest_readings_to_websocket($active_sensor_device['device_id']);
    } catch (Exception $e) {
        error_log("Error monitoring device {$active_sensor_device['device_id']}: " . $e->getMessage());
    }
}

function check_all_devices_status_every_5mins() {
    // Only check the device that's actually sending sensor data
    $active_sensor_device = get_active_sensor_device();
    
    if (!$active_sensor_device) {
        error_log("No active sensor device found for periodic status check.");
        return;
    }
    
    error_log("Performing 5-minute status check on active sensor device: " . $active_sensor_device['device_name'] . " (" . $active_sensor_device['device_number'] . ")");
    
    try {
        check_latest_status_every_5mins($active_sensor_device['device_id']);
    } catch (Exception $e) {
        error_log("Error in periodic status check for device {$active_sensor_device['device_id']}: " . $e->getMessage());
    }
}

/**
 * Comprehensive Fire Detection Logic Checker
 * Implements Arduino fire detection conditions:
 * 1. Flame detected = Immediate fire
 * 2. Smoke detected + High temperature (>50°C) = Fire
 * 3. High smoke (>2000) + High temperature (>60°C) = Fire
 * 4. High heat index (>35°C) + Smoke detected = Fire
 * 
 * @param array $sensor_data Array containing smoke, temp, heat, flame_detected, humidity
 * @return array Returns detection result with status, severity, and details
 */
function check_fire_detection_logic($sensor_data) {
    $smoke = $sensor_data['smoke'];
    $temperature = $sensor_data['temp'];
    $heat_index = $sensor_data['heat'];
    $flame_detected = $sensor_data['flame_detected'];
    $humidity = $sensor_data['humidity'];
    
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
    } elseif ($temperature > HIGH_TEMPERATURE_THRESHOLD) {
        $detection_result['status'] = 'MONITORING';
        $detection_result['severity'] = 'MEDIUM';
        $detection_result['emergency_level'] = 1;
        $detection_result['conditions_met'][] = 'HIGH_TEMPERATURE';
        $detection_result['details'] = "🌡️ High temperature detected ({$temperature}°C) - Monitor for smoke or other fire indicators!";
    }
    
    return $detection_result;
}

/**
 * Enhanced SMS message generator based on fire detection logic
 */
function generate_emergency_message($device_info, $detection_result, $sensor_data) {
    $device_name = $device_info['device_name'];
    $device_number = $device_info['device_number'];
    
    // Create a shorter, more concise message to stay within SMS limits
    $message = "FIRE ALERT: $device_name\n";
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

// Main execution
if (defined('SINGLE_EXECUTION') || php_sapi_name() !== 'cli') {
    // Single execution mode (for web requests or manual checks)
    if (defined('SINGLE_EXECUTION')) {
        error_log("Single execution mode - processing current sensor data");
    } else {
        error_log("Web request mode - processing current sensor data");
    }
    monitor_all_active_devices();
    if (!defined('SINGLE_EXECUTION')) {
        echo "All active devices monitored.";
    }
} else {
    // Command-line mode (for continuous monitoring)
    error_log("Starting continuous monitoring mode - press Ctrl+C to stop");
    $last_periodic_check = 0;
    $loop_count = 0;
    
    while (true) {
        try {
            $current_time = time();
            $loop_count++;
            
            // Log every 100th iteration (about every 8 minutes) to show the script is still running
            if ($loop_count % 100 === 0) {
                error_log("Continuous monitoring active - loop #$loop_count at " . date('Y-m-d H:i:s'));
            }
            
            // Monitor all active devices every 5 seconds
            monitor_all_active_devices();
            
            // Check status of all devices every 30 minutes (1800 seconds)
            if ($current_time - $last_periodic_check >= 1800) {
                check_all_devices_status_every_5mins();
                $last_periodic_check = $current_time;
                error_log("Completed 30-minute periodic status check for all devices at " . date('Y-m-d H:i:s'));
            }
            
            sleep(5); // Wait for 5 seconds before next check
        } catch (Exception $e) {
            error_log("Main loop error: " . $e->getMessage());
            sleep(10); // Wait longer if error occurs
        }
    }
}
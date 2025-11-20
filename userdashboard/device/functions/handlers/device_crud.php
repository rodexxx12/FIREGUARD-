<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/handlers/device_validator.php';

class DeviceCRUD {
    private $pdo;
    private $validator;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
        $this->validator = new DeviceValidator();
    }
    
    public function addDevice($user_id, $device_name, $device_number, $serial_number) {
        $required_fields = ['device_name', 'device_number', 'serial_number'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($$field)) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            return ['status' => 'error', 'message' => 'Missing required fields: ' . implode(', ', $missing_fields)];
        }
        
        $device_name = htmlspecialchars($device_name);
        $device_number = htmlspecialchars($device_number);
        $serial_number = htmlspecialchars($serial_number);
        
        try {
            // Check if both device_number and serial_number exist together in admin_devices and is approved
            if (!$this->validator->isDeviceApproved($device_number, $serial_number)) {
                return ['status' => 'error', 'message' => 'Device number and serial number combination not found in approved devices list'];
            }
            
            // Check if device is already assigned to any user (prevent duplicates)
            if (!$this->validator->isDeviceAvailable($serial_number)) {
                return ['status' => 'error', 'message' => 'This device is already assigned to a user'];
            }
            
            // Check if device name already exists for this user (prevent duplicate device names)
            if (!$this->validator->isDeviceNameAvailable($user_id, $device_name)) {
                return ['status' => 'error', 'message' => 'A device with this name already exists. Please choose a different name.'];
            }
            
            // Add the device
            $stmt = $this->pdo->prepare("INSERT INTO devices (user_id, device_name, device_number, serial_number) 
                                      VALUES (:user_id, :device_name, :device_number, :serial_number)");
            
            $stmt->execute([
                ':user_id' => $user_id,
                ':device_name' => $device_name,
                ':device_number' => $device_number,
                ':serial_number' => $serial_number
            ]);
            
            $device_id = $this->pdo->lastInsertId();
            return [
                'status' => 'success', 
                'message' => "Device added successfully!",
                'device_id' => $device_id
            ];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getDeviceDetails($user_id, $device_id) {
        if (empty($device_id)) {
            return ['status' => 'error', 'message' => 'Missing device ID'];
        }
        
        $device_id = (int)$device_id;
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE device_id = :device_id AND user_id = :user_id");
            $stmt->execute([':device_id' => $device_id, ':user_id' => $user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($device) {
                return ['status' => 'success', 'device' => $device];
            } else {
                return ['status' => 'error', 'message' => 'Device not found or not authorized'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function updateDevice($user_id, $device_id, $device_name, $device_number, $serial_number) {
        $required_fields = ['device_id', 'device_name', 'device_number', 'serial_number'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($$field)) {
                $missing_fields[] = $field;
            }
        }
        
        if (!empty($missing_fields)) {
            return ['status' => 'error', 'message' => 'Missing required fields: ' . implode(', ', $missing_fields)];
        }
        
        $device_id = (int)$device_id;
        $device_name = htmlspecialchars($device_name);
        $device_number = htmlspecialchars($device_number);
        $serial_number = htmlspecialchars($serial_number);

        
        try {
            // Check if the new device_number and serial_number combination exists in admin_devices
            if (!$this->validator->isDeviceApproved($device_number, $serial_number)) {
                return ['status' => 'error', 'message' => 'Device number and serial number combination not found in approved devices list'];
            }
            
            // Check if serial number is being changed to one that already exists (other than this device)
            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE serial_number = :serial_number AND device_id != :device_id");
            $stmt->execute([':serial_number' => $serial_number, ':device_id' => $device_id]);
            if ($stmt->fetch()) {
                return ['status' => 'error', 'message' => 'A device with this serial number already exists'];
            }
            
            // Check if device name already exists for this user (prevent duplicate device names)
            if (!$this->validator->isDeviceNameAvailable($user_id, $device_name, $device_id)) {
                return ['status' => 'error', 'message' => 'A device with this name already exists. Please choose a different name.'];
            }
            
            $stmt = $this->pdo->prepare("UPDATE devices 
                                      SET device_name = :device_name, 
                                          device_number = :device_number, 
                                          serial_number = :serial_number, 
                                          updated_at = NOW()
                                      WHERE device_id = :device_id AND user_id = :user_id");
            
            $stmt->execute([
                ':device_name' => $device_name,
                ':device_number' => $device_number,
                ':serial_number' => $serial_number,
                ':device_id' => $device_id,
                ':user_id' => $user_id
            ]);
            
            if ($stmt->rowCount() > 0) {
                return ['status' => 'success', 'message' => 'Device updated successfully'];
            } else {
                return ['status' => 'error', 'message' => 'Device not found or not authorized'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function deleteDevice($user_id, $device_id) {
        if (empty($device_id)) {
            return ['status' => 'error', 'message' => 'Missing device ID'];
        }
        
        $device_id = (int)$device_id;
        
        try {
            $stmt = $this->pdo->prepare("DELETE FROM devices WHERE device_id = :device_id AND user_id = :user_id");
            $stmt->execute([':device_id' => $device_id, ':user_id' => $user_id]);
            
            if ($stmt->rowCount() > 0) {
                return ['status' => 'success', 'message' => 'Device deleted successfully'];
            } else {
                return ['status' => 'error', 'message' => 'Device not found or not authorized'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getAllDevices($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE user_id = :user_id ORDER BY created_at ASC");
            $stmt->execute([':user_id' => $user_id]);
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['status' => 'success', 'devices' => $devices];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function getAvailableDevices() {
        try {
            $stmt = $this->pdo->prepare("SELECT ad.device_number, ad.serial_number, ad.device_type 
                                       FROM admin_devices ad
                                       LEFT JOIN devices d ON ad.serial_number = d.serial_number
                                       WHERE ad.status = 'approved' AND d.device_id IS NULL");
            $stmt->execute();
            $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return ['status' => 'success', 'devices' => $devices];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?> 
<?php
require_once dirname(__DIR__) . '/config/database.php';

class DeviceValidator {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }
    
    public function validateDevice($device_number, $serial_number) {
        if (empty($device_number) || empty($serial_number)) {
            return ['status' => 'error', 'message' => 'Device number and serial number are required'];
        }
        
        $device_number = htmlspecialchars($device_number);
        $serial_number = htmlspecialchars($serial_number);
        
        try {
            // Check if both device_number and serial_number exist together in admin_devices
            $stmt = $this->pdo->prepare("SELECT admin_device_id FROM admin_devices 
                                      WHERE device_number = :device_number 
                                      AND serial_number = :serial_number
                                      AND status = 'approved'");
            $stmt->execute([
                ':device_number' => $device_number,
                ':serial_number' => $serial_number
            ]);
            
            if ($stmt->fetch()) {
                return ['status' => 'success', 'message' => 'Valid device - device number and serial number combination exists in approved devices'];
            } else {
                return ['status' => 'error', 'message' => 'Device number and serial number combination not found in approved devices'];
            }
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    public function isDeviceAvailable($serial_number) {
        try {
            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE serial_number = :serial_number");
            $stmt->execute([':serial_number' => $serial_number]);
            return !$stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
    
    public function isDeviceApproved($device_number, $serial_number) {
        try {
            $stmt = $this->pdo->prepare("SELECT admin_device_id FROM admin_devices 
                                      WHERE device_number = :device_number 
                                      AND serial_number = :serial_number
                                      AND status = 'approved'");
            $stmt->execute([
                ':device_number' => $device_number,
                ':serial_number' => $serial_number
            ]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Check if device name already exists for the same user
     * @param int $user_id
     * @param string $device_name
     * @param int $exclude_device_id (optional) - exclude current device when updating
     * @return bool - true if device name is available (no duplicate), false if duplicate exists
     */
    public function isDeviceNameAvailable($user_id, $device_name, $exclude_device_id = null) {
        try {
            if ($exclude_device_id) {
                $stmt = $this->pdo->prepare("SELECT device_id FROM devices 
                                          WHERE user_id = :user_id 
                                          AND device_name = :device_name 
                                          AND device_id != :exclude_device_id");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':device_name' => $device_name,
                    ':exclude_device_id' => $exclude_device_id
                ]);
            } else {
                $stmt = $this->pdo->prepare("SELECT device_id FROM devices 
                                          WHERE user_id = :user_id 
                                          AND device_name = :device_name");
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':device_name' => $device_name
                ]);
            }
            return !$stmt->fetch();
        } catch (PDOException $e) {
            return false;
        }
    }
}
?> 
<?php

class DeviceValidation {
    
    /**
     * Validate device number format: DV1-PHI-[UNIQUE_ID]
     * @param string $device_number
     * @return bool
     */
    public static function validateDeviceNumber($device_number) {
        $device_number = trim($device_number);
        // Format: DV1-PHI-[UNIQUE_ID]
        // Example: DV1-PHI-000345
        // DV1: Fixed device type code
        // PHI: Fixed location code (Deployment or production site)
        // UNIQUE_ID: 6 digits (incrementing unique identifier)
        return preg_match('/^DV1-PHI-\d{6}$/', $device_number);
    }
    
    /**
     * Validate serial number format: SEN-[YYWW]-[SERIAL]
     * @param string $serial_number
     * @return bool
     */
    public static function validateSerialNumber($serial_number) {
        $serial_number = trim($serial_number);
        // Format: SEN-[YYWW]-[SERIAL]
        // Example: SEN-2519-005871
        // SEN: Fixed product code
        // YYWW: 4 digits (Year-Week format)
        // SERIAL: 6 digits (incrementing number for that batch)
        return preg_match('/^SEN-\d{4}-\d{6}$/', $serial_number);
    }
    
    /**
     * Validate required fields for device operations
     * @param array $data
     * @param array $required_fields
     * @return bool
     */
    public static function validateRequiredFields($data, $required_fields) {
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Get validation error message for device number
     * @return string
     */
    public static function getDeviceNumberErrorMessage() {
        return 'Device number must be in format: DV1-PHI-[UNIQUE_ID] (e.g., DV1-PHI-000345)';
    }
    
    /**
     * Get validation error message for serial number
     * @return string
     */
    public static function getSerialNumberErrorMessage() {
        return 'Serial number must be in format: SEN-[YYWW]-[SERIAL] (e.g., SEN-2519-005871)';
    }
    
    /**
     * Generate a device number with incrementing unique ID
     * @param PDO $pdo Database connection
     * @return string
     */
    public static function generateDeviceNumber($pdo = null) {
        // Fixed device type and location
        $device_type = 'DV1';
        $location = 'PHI';
        
        // Get the next available unique ID
        $next_id = self::getNextDeviceUniqueId($pdo);
        
        // Format as 6-digit number with leading zeros
        $unique_id = str_pad($next_id, 6, '0', STR_PAD_LEFT);
        
        return $device_type . '-' . $location . '-' . $unique_id;
    }
    
    /**
     * Get the next available unique ID for device numbers
     * @param PDO $pdo Database connection
     * @return int
     */
    private static function getNextDeviceUniqueId($pdo) {
        if (!$pdo) {
            // If no database connection, start from 1
            return 1;
        }
        
        try {
            // Get the highest unique ID from existing devices
            $stmt = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(device_number, '-', -1) AS UNSIGNED)) as max_id 
                FROM admin_devices 
                WHERE device_number LIKE 'DV1-PHI-%'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $max_id = $result['max_id'] ? (int)$result['max_id'] : 0;
            
            // Return next ID (start from 1 if no devices exist)
            return $max_id + 1;
            
        } catch (Exception $e) {
            error_log("Error getting next device ID: " . $e->getMessage());
            // Fallback: start from 1
            return 1;
        }
    }
    
    /**
     * Generate a serial number using current date
     * @param PDO $pdo Database connection
     * @return string
     */
    public static function generateSerialNumber($pdo = null) {
        // Fixed product code: SEN
        $product_code = 'SEN';
        
        // Generate YYWW (Year-Week) format using current date
        $current_year = date('y'); // 2-digit year
        $current_week = str_pad(date('W'), 2, '0', STR_PAD_LEFT); // Week number
        $yyww = $current_year . $current_week;
        
        // Get the next available serial number for this week
        $next_serial = self::getNextSerialNumber($pdo, $yyww);
        
        // Format as 6-digit number with leading zeros
        $serial = str_pad($next_serial, 6, '0', STR_PAD_LEFT);
        
        return $product_code . '-' . $yyww . '-' . $serial;
    }
    
    /**
     * Get the next available serial number for a specific week
     * @param PDO $pdo Database connection
     * @param string $yyww Year-Week format
     * @return int
     */
    private static function getNextSerialNumber($pdo, $yyww) {
        if (!$pdo) {
            // If no database connection, start from 1
            return 1;
        }
        
        try {
            // Get the highest serial number for this week
            $stmt = $pdo->prepare("
                SELECT MAX(CAST(SUBSTRING_INDEX(serial_number, '-', -1) AS UNSIGNED)) as max_serial 
                FROM admin_devices 
                WHERE serial_number LIKE 'SEN-{$yyww}-%'
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $max_serial = $result['max_serial'] ? (int)$result['max_serial'] : 0;
            
            // Return next serial number (start from 1 if no devices exist for this week)
            return $max_serial + 1;
            
        } catch (Exception $e) {
            error_log("Error getting next serial number: " . $e->getMessage());
            // Fallback: start from 1
            return 1;
        }
    }
} 
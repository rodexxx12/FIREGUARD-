<?php

class ActivityLogger {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log device activity
     * @param int $admin_device_id
     * @param string $description
     * @param string $activity_type
     * @return bool
     */
    public function logActivity($admin_device_id, $description, $activity_type = 'device') {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO admin_device_activities (admin_device_id, description, activity_type) VALUES (?, ?, ?)");
            return $stmt->execute([$admin_device_id, $description, $activity_type]);
        } catch (Exception $e) {
            // Log error but don't throw to avoid breaking main operations
            error_log("Failed to log activity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get device activities
     * @param int $admin_device_id
     * @param int $limit
     * @return array
     */
    public function getDeviceActivities($admin_device_id, $limit = 10) {
        $stmt = $this->pdo->prepare("SELECT * FROM admin_device_activities 
                                    WHERE admin_device_id = ? 
                                    ORDER BY created_at DESC 
                                    LIMIT ?");
        $stmt->execute([$admin_device_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all activities
     * @param int $limit
     * @return array
     */
    public function getAllActivities($limit = 50) {
        $stmt = $this->pdo->prepare("SELECT ada.*, ad.device_number, ad.serial_number 
                                    FROM admin_device_activities ada
                                    LEFT JOIN admin_devices ad ON ada.admin_device_id = ad.admin_device_id
                                    ORDER BY ada.created_at DESC 
                                    LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 
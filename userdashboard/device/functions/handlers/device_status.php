<?php
require_once dirname(__DIR__) . '/config/database.php';

class DeviceStatus {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }
    
    
    public function toggleDeviceActive($user_id, $device_id) {
        if (empty($device_id)) {
            return ['status' => 'error', 'message' => 'Missing device ID'];
        }
        
        $device_id = (int)$device_id;
        
        try {
            // Get current status
            $stmt = $this->pdo->prepare("SELECT is_active FROM devices WHERE device_id = :device_id AND user_id = :user_id");
            $stmt->execute([':device_id' => $device_id, ':user_id' => $user_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                return ['status' => 'error', 'message' => 'Device not found or not authorized'];
            }
            
            $new_status = $device['is_active'] ? 0 : 1;
            
            $stmt = $this->pdo->prepare("UPDATE devices SET is_active = :is_active, updated_at = NOW() 
                                      WHERE device_id = :device_id AND user_id = :user_id");
            $stmt->execute([
                ':is_active' => $new_status,
                ':device_id' => $device_id,
                ':user_id' => $user_id
            ]);
            
            return [
                'status' => 'success', 
                'message' => 'Device status updated',
                'is_active' => $new_status
            ];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?> 
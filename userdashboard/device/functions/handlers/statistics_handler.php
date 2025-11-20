<?php
require_once dirname(__DIR__) . '/config/database.php';

class StatisticsHandler {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }
    
    public function getStatistics($user_id) {
        try {
            // Total devices
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as total_devices FROM devices WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $user_id]);
            $total_devices = $stmt->fetchColumn();
            
            // Active devices
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as active_devices FROM devices WHERE user_id = :user_id AND is_active = 1");
            $stmt->execute([':user_id' => $user_id]);
            $active_devices = $stmt->fetchColumn();
            
            // Status counts
            $stmt = $this->pdo->prepare("SELECT status, COUNT(*) as count FROM devices WHERE user_id = :user_id GROUP BY status");
            $stmt->execute([':user_id' => $user_id]);
            $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $status_data = [
                'online' => 0,
                'offline' => 0
            ];
            
            foreach ($status_counts as $row) {
                $status_data[$row['status']] = (int)$row['count'];
            }
            
            return [
                'status' => 'success',
                'statistics' => [
                    'total_devices' => $total_devices,
                    'active_devices' => $active_devices,
                    'status_data' => $status_data
                ]
            ];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
?> 
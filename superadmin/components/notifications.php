<?php
// Database configuration
$host = 'localhost';
$dbname = 'u520834156_DBBagofire';
$username = 'u520834156_userBagofire';
$password = 'i[#[GQ!+=C9';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    class NotificationSystem {
        private $db;
        
        public function __construct($db) {
            $this->db = $db;
        }
        
        // Main method to notify admins about inactive users
        public function notifyAdminsAboutInactiveUsers() {
            $inactiveUsers = $this->getInactiveUsers();
            
            if (empty($inactiveUsers)) {
                return ['success' => false, 'message' => 'No inactive users found'];
            }
            
            $adminIds = $this->getActiveAdminIds();
            $results = [];
            
            foreach ($adminIds as $adminId) {
                foreach ($inactiveUsers as $user) {
                    $result = $this->createInactiveUserNotification(
                        $adminId,
                        $user['user_id'],
                        $user['username'],
                        $user['registration_date']
                    );
                    $results[] = $result;
                }
            }
            
            return [
                'success' => true,
                'message' => 'Created notifications for inactive users',
                'details' => $results
            ];
        }
        
        // Get all inactive users
        private function getInactiveUsers() {
            $query = "SELECT user_id, username, registration_date 
                     FROM users 
                     WHERE status = 'Inactive'
                     ORDER BY registration_date DESC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Get all active admin IDs
        private function getActiveAdminIds() {
            $query = "SELECT superadmin_id FROM admin WHERE status = 'Active'";
            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        }
        
        // Create a notification for an inactive user
        private function createInactiveUserNotification($adminId, $userId, $username, $regDate) {
            // Check if notification already exists
            if ($this->notificationExists($adminId, 'inactive_user', $userId)) {
                return [
                    'status' => 'skipped',
                    'superadmin_id' => $adminId,
                    'user_id' => $userId,
                    'reason' => 'Notification already exists'
                ];
            }
            
            $title = "Inactive User Alert";
            $message = "User {$username} has been inactive since " . date('M j, Y', strtotime($regDate));
            
            $query = "INSERT INTO admin_notifications 
                     (superadmin_id, title, message, type, reference_id, created_at)
                     VALUES (:superadmin_id, :title, :message, 'inactive_user', :user_id, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':superadmin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return [
                    'status' => 'created',
                    'superadmin_id' => $adminId,
                    'user_id' => $userId,
                    'notification' => [
                        'title' => $title,
                        'message' => $message
                    ]
                ];
            } else {
                return [
                    'status' => 'failed',
                    'superadmin_id' => $adminId,
                    'user_id' => $userId,
                    'error' => $stmt->errorInfo()
                ];
            }
        }
        
        // Check if notification already exists
        private function notificationExists($adminId, $type, $referenceId) {
            $query = "SELECT id FROM admin_notifications 
                     WHERE superadmin_id = :superadmin_id 
                     AND type = :type 
                     AND reference_id = :reference_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':superadmin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':reference_id', $referenceId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        }
        
        // Get notifications for display
        public function getNotifications($adminId, $limit = 10) {
            $query = "SELECT * FROM admin_notifications 
                     WHERE superadmin_id = :superadmin_id
                     ORDER BY created_at DESC
                     LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':superadmin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Mark notification as read
        public function markAsRead($notificationId, $adminId) {
            $query = "UPDATE admin_notifications SET status = 'read'
                     WHERE id = :id AND superadmin_id = :superadmin_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindParam(':superadmin_id', $adminId, PDO::PARAM_INT);
            return $stmt->execute();
        }
    }
    
    // Create notification system instance
    $notificationSystem = new NotificationSystem($db);
    
    // Handle requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        header('Content-Type: application/json');
        
        switch ($_GET['action']) {
            case 'generate_inactive_notifications':
                $result = $notificationSystem->notifyAdminsAboutInactiveUsers();
                echo json_encode($result);
                break;
                
            case 'get_notifications':
                // Session dependency removed - return empty notifications
                echo json_encode([]);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        // Session dependency removed - return error for POST requests
        echo json_encode(['error' => 'Session required for POST requests']);
        exit;
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Helper function for notification icons
function getNotificationIcon($type) {
    $icons = [
        'inactive_user' => 'fa-user-times',
        'new_user' => 'fa-user-plus',
        'new_device' => 'fa-microchip',
        'new_building' => 'fa-building',
        'water_alert' => 'fa-tint',
        'fire_response' => 'fa-fire-extinguisher',
        'acknowledgment' => 'fa-check-circle'
    ];
    return $icons[$type] ?? 'fa-bell';
}
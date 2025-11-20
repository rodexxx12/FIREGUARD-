<?php
// Include database connection
require_once __DIR__ . '/db_connection.php';

try {
    $db = getDatabaseConnection();
    
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
            $query = "SELECT admin_id FROM admin WHERE status = 'Active'";
            $stmt = $this->db->query($query);
            return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        }
        
        // Create a notification for an inactive user
        private function createInactiveUserNotification($adminId, $userId, $username, $regDate) {
            // Check if notification already exists
            if ($this->notificationExists($adminId, 'inactive_user', $userId)) {
                return [
                    'status' => 'skipped',
                    'admin_id' => $adminId,
                    'user_id' => $userId,
                    'reason' => 'Notification already exists'
                ];
            }
            
            $title = "Inactive User Alert";
            $message = "User {$username} has been inactive since " . date('M j, Y', strtotime($regDate));
            
            $query = "INSERT INTO admin_notifications 
                     (admin_id, title, message, type, reference_id, created_at)
                     VALUES (:admin_id, :title, :message, 'inactive_user', :user_id, NOW())";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':title', $title, PDO::PARAM_STR);
            $stmt->bindParam(':message', $message, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                return [
                    'status' => 'created',
                    'admin_id' => $adminId,
                    'user_id' => $userId,
                    'notification' => [
                        'title' => $title,
                        'message' => $message
                    ]
                ];
            } else {
                return [
                    'status' => 'failed',
                    'admin_id' => $adminId,
                    'user_id' => $userId,
                    'error' => $stmt->errorInfo()
                ];
            }
        }
        
        // Check if notification already exists
        private function notificationExists($adminId, $type, $referenceId) {
            $query = "SELECT id FROM admin_notifications 
                     WHERE admin_id = :admin_id 
                     AND type = :type 
                     AND reference_id = :reference_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':type', $type, PDO::PARAM_STR);
            $stmt->bindParam(':reference_id', $referenceId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        }
        
        // Get notifications for display
        public function getNotifications($adminId, $limit = 10) {
            $query = "SELECT * FROM admin_notifications 
                     WHERE admin_id = :admin_id
                     ORDER BY created_at DESC
                     LIMIT :limit";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Mark notification as read
        public function markAsRead($notificationId, $adminId) {
            $query = "UPDATE admin_notifications SET status = 'read'
                     WHERE id = :id AND admin_id = :admin_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $notificationId, PDO::PARAM_INT);
            $stmt->bindParam(':admin_id', $adminId, PDO::PARAM_INT);
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
                $adminId = $_SESSION['admin_id'] ?? null;
                if (!$adminId) {
                    echo json_encode(['error' => 'Unauthorized']);
                    break;
                }
                $notifications = $notificationSystem->getNotifications($adminId);
                echo json_encode($notifications);
                break;
                
            default:
                echo json_encode(['error' => 'Invalid action']);
        }
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        $adminId = $_SESSION['admin_id'] ?? null;
        
        if (!$adminId) {
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action']) && $data['action'] === 'mark_as_read' && isset($data['notification_id'])) {
            $success = $notificationSystem->markAsRead($data['notification_id'], $adminId);
            echo json_encode(['success' => $success]);
            exit;
        }
        
        echo json_encode(['error' => 'Invalid request']);
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
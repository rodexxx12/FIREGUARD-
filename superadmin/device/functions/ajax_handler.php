<?php
require_once 'database.php';
require_once 'device_operations.php';

class AjaxHandler {
    private $deviceOperations;
    
    public function __construct() {
        $this->deviceOperations = new DeviceOperations();
    }
    
    /**
     * Handle AJAX requests
     * @return array
     */
    public function handleRequest() {
        // Ensure no output has been sent yet
        if (headers_sent()) {
            return ['success' => false, 'message' => 'Headers already sent'];
        }
        
        // Set JSON content type
        header('Content-Type: application/json');
        
        // Check for AJAX request - be more flexible with validation
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
                  (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
                  (isset($_POST['action']) && !empty($_POST['action']));
        
        if (!$isAjax) {
            return ['success' => false, 'message' => 'Invalid request - AJAX required'];
        }
        
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = isset($_POST['action']) ? $_POST['action'] : '';
                $response = ['success' => false, 'message' => 'Invalid action'];
                
                switch ($action) {
                    case 'check_device':
                        if (isset($_POST['device_number'])) {
                            $response = ['exists' => $this->deviceOperations->checkDeviceExists($_POST['device_number'])];
                        } else {
                            $response = ['success' => false, 'message' => 'Device number is required'];
                        }
                        break;
                        
                    case 'check_serial':
                        if (isset($_POST['serial_number'])) {
                            $response = ['exists' => $this->deviceOperations->checkSerialExists($_POST['serial_number'])];
                        } else {
                            $response = ['success' => false, 'message' => 'Serial number is required'];
                        }
                        break;
                        
                    case 'add':
                        $response = $this->deviceOperations->addDevice($_POST);
                        break;
                    
                    case 'update':
                        $response = $this->deviceOperations->updateDevice($_POST);
                        break;
                    
                    case 'delete':
                        if (isset($_POST['id'])) {
                            $response = $this->deviceOperations->deleteDevice($_POST['id']);
                        } else {
                            $response = ['success' => false, 'message' => 'Device ID is required'];
                        }
                        break;
                        
                    case 'generate_device_number':
                        $response = ['success' => true, 'device_number' => $this->deviceOperations->generateUniqueDeviceNumber()];
                        break;
                        
                    case 'generate_serial_number':
                        $response = ['success' => true, 'serial_number' => $this->deviceOperations->generateUniqueSerialNumber()];
                        break;
                        
                    case 'generate_both':
                        $response = ['success' => true, 'data' => $this->deviceOperations->generateDeviceData()];
                        break;
                        
                    case 'get_statistics':
                        // Get device statistics
                        require_once 'statistics.php';
                        $statistics = new DeviceStatistics();
                        $allStats = $statistics->getAllStatistics();
                        $response = ['success' => true, 'statistics' => $allStats];
                        break;
                        
                    case 'deactivate':
                        if (isset($_POST['id'])) {
                            $response = $this->deviceOperations->deactivateDevice($_POST['id']);
                        } else {
                            $response = ['success' => false, 'message' => 'Device ID is required'];
                        }
                        break;
                        
                    case 'set_pending':
                        if (isset($_POST['id'])) {
                            $response = $this->deviceOperations->setDevicePending($_POST['id']);
                        } else {
                            $response = ['success' => false, 'message' => 'Device ID is required'];
                        }
                        break;
                        
                    case 'approve':
                        if (isset($_POST['id'])) {
                            $response = $this->deviceOperations->approveDevice($_POST['id']);
                        } else {
                            $response = ['success' => false, 'message' => 'Device ID is required'];
                        }
                        break;
                        
                    case 'get_devices_by_status':
                        if (isset($_POST['status'])) {
                            $response = ['success' => true, 'devices' => $this->deviceOperations->getDevicesByStatus($_POST['status'])];
                        } else {
                            $response = ['success' => false, 'message' => 'Status is required'];
                        }
                        break;
                        
                    case 'get_status_summary':
                        $response = ['success' => true, 'summary' => $this->deviceOperations->getDeviceStatusSummary()];
                        break;

                    case 'search_devices_realtime':
                        $term = isset($_POST['search_term']) ? trim($_POST['search_term']) : '';
                        $status = isset($_POST['status']) ? trim($_POST['status']) : '';
                        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
                        $perPage = isset($_POST['per_page']) ? (int)$_POST['per_page'] : 10;
                        list($devices, $total) = $this->deviceOperations->getDevicesPaginated($page, $perPage, $term, $status);
                        $totalPages = (int)ceil($total / max(1, $perPage));
                        $response = [
                            'success' => true,
                            'devices' => $devices,
                            'total' => $total,
                            'page' => $page,
                            'per_page' => $perPage,
                            'total_pages' => $totalPages
                        ];
                        break;
                        
                    case 'create_activities_table':
                        $response = $this->createActivitiesTable();
                        break;
                        
                    default:
                        $response = ['success' => false, 'message' => 'Invalid action: ' . $action];
                        break;
                }
                
                return $response;
            } else {
                return ['success' => false, 'message' => 'Invalid request method'];
            }
        } catch (Exception $e) {
            error_log("AJAX Handler Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send JSON response and exit
     * @param array $response
     */
    public function sendResponse($response) {
        // Ensure no output has been sent
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo json_encode($response);
        exit;
    }
    
    /**
     * Create the admin_device_activities table if it doesn't exist
     * @return array
     */
    private function createActivitiesTable() {
        try {
            $pdo = $this->deviceOperations->getPdo();
            
            // Check if table already exists
            $stmt = $pdo->query("SHOW TABLES LIKE 'admin_device_activities'");
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Table already exists'];
            }
            
            // Create the table
            $sql = "CREATE TABLE admin_device_activities (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_device_id INT NOT NULL,
                description TEXT NOT NULL,
                activity_type VARCHAR(50) DEFAULT 'device',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_admin_device_id (admin_device_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
            
            $pdo->exec($sql);
            
            return ['success' => true, 'message' => 'admin_device_activities table created successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to create table: ' . $e->getMessage()];
        }
    }
}

// Handle AJAX requests when this file is called directly
if (basename($_SERVER['SCRIPT_NAME']) === 'ajax_handler.php') {
    try {
        $handler = new AjaxHandler();
        $response = $handler->handleRequest();
        $handler->sendResponse($response);
    } catch (Exception $e) {
        // Ensure we send a proper JSON response even on errors
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
        exit;
    }
} 
<?php
/**
 * Form handler component
 */
class FormHandler {
    private $deviceManager;
    
    public function __construct($deviceManager) {
        $this->deviceManager = $deviceManager;
    }
    
    /**
     * Handle form submissions
     * @return array|null
     */
    public function handleFormSubmission() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return null;
        }
        
        // Check if this is an AJAX request
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        
        if (isset($_POST['assign_device'])) {
            $result = $this->handleAssignDevice();
            if ($isAjax) {
                $this->sendJsonResponse($result);
            } else {
                $this->handleTraditionalResponse($result);
            }
            return $result;
        }
        
        if (isset($_POST['remove_device'])) {
            $result = $this->handleRemoveDevice();
            if ($isAjax) {
                $this->sendJsonResponse($result);
            } else {
                $this->handleTraditionalResponse($result);
            }
            return $result;
        }
        
        if (isset($_POST['update_status'])) {
            $result = $this->handleUpdateStatus();
            if ($isAjax) { $this->sendJsonResponse($result); } else { $this->handleTraditionalResponse($result); }
            return $result;
        }
        
        if (isset($_POST['toggle_active'])) {
            $result = $this->handleToggleActive();
            if ($isAjax) { $this->sendJsonResponse($result); } else { $this->handleTraditionalResponse($result); }
            return $result;
        }
        
        if (isset($_POST['update_wifi'])) {
            $result = $this->handleUpdateWifi();
            if ($isAjax) { $this->sendJsonResponse($result); } else { $this->handleTraditionalResponse($result); }
            return $result;
        }
        
        if (isset($_POST['update_device_info'])) {
            $result = $this->handleUpdateDeviceInfo();
            if ($isAjax) { $this->sendJsonResponse($result); } else { $this->handleTraditionalResponse($result); }
            return $result;
        }
        
        if (isset($_POST['touch_last_activity'])) {
            $result = $this->handleTouchLastActivity();
            if ($isAjax) { $this->sendJsonResponse($result); } else { $this->handleTraditionalResponse($result); }
            return $result;
        }

        if (isset($_POST['create_device'])) {
            $result = $this->handleCreateDevice();
            if ($isAjax) { $this->sendJsonResponse($result); } else { $this->handleTraditionalResponse($result); }
            return $result;
        }
        
        return null;
    }
    
    /**
     * Send JSON response for AJAX requests
     * @param array $result
     */
    private function sendJsonResponse($result) {
        header('Content-Type: application/json');
        echo json_encode($result);
        exit();
    }
    
    /**
     * Handle traditional form response (redirect with session messages)
     * @param array $result
     */
    private function handleTraditionalResponse($result) {
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header("Location: ".$_SERVER['PHP_SELF']);
        exit();
    }
    
    /**
     * Handle device assignment
     * @return array
     */
    private function handleAssignDevice() {
        $device_id = $_POST['device_id'];
        $building_id = $_POST['building_id'];
        
        $result = $this->deviceManager->assignDevice($device_id, $building_id);
        
        return $result;
    }
    
    /**
     * Handle device removal
     * @return array
     */
    private function handleRemoveDevice() {
        $device_id = $_POST['device_id'];
        
        $result = $this->deviceManager->removeDevice($device_id);
        
        return $result;
    }
    
    /**
     * Handle update status
     */
    private function handleUpdateStatus() {
        $device_id = $_POST['device_id'];
        $status = $_POST['status'];
        return $this->deviceManager->updateDeviceStatus($device_id, $status);
    }
    
    /**
     * Handle toggle active
     */
    private function handleToggleActive() {
        $device_id = $_POST['device_id'];
        $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
        return $this->deviceManager->toggleDeviceActive($device_id, $is_active);
    }
    
    /**
     * Handle update wifi
     */
    private function handleUpdateWifi() {
        $device_id = $_POST['device_id'];
        $wifi_ssid = $_POST['wifi_ssid'] ?? '';
        $wifi_password = $_POST['wifi_password'] ?? '';
        return $this->deviceManager->updateWifiCredentials($device_id, $wifi_ssid, $wifi_password);
    }
    
    /**
     * Handle update device info
     */
    private function handleUpdateDeviceInfo() {
        $device_id = $_POST['device_id'];
        $fields = [
            'device_type' => $_POST['device_type'] ?? null,
            'device_name' => $_POST['device_name'] ?? null,
            'device_number' => $_POST['device_number'] ?? null,
            'admin_device_id' => isset($_POST['admin_device_id']) ? $_POST['admin_device_id'] : null,
            'building_id' => isset($_POST['building_id']) ? $_POST['building_id'] : null,
        ];
        return $this->deviceManager->updateDeviceInfo($device_id, $fields);
    }
    
    /**
     * Handle touch last activity
     */
    private function handleTouchLastActivity() {
        $device_id = $_POST['device_id'];
        return $this->deviceManager->touchLastActivity($device_id);
    }

    /**
     * Handle create device
     */
    private function handleCreateDevice() {
        $payload = [
            'device_name' => $_POST['device_name'] ?? '',
            'device_number' => $_POST['device_number'] ?? '',
            'serial_number' => $_POST['serial_number'] ?? '',
            'device_type' => $_POST['device_type'] ?? null,
            'is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
            'status' => $_POST['status'] ?? 'offline',
            'wifi_ssid' => $_POST['wifi_ssid'] ?? null,
            'wifi_password' => $_POST['wifi_password'] ?? null,
            'admin_device_id' => $_POST['admin_device_id'] ?? null,
            'building_id' => $_POST['building_id'] ?? null,
        ];
        return $this->deviceManager->createDevice($payload);
    }
} 
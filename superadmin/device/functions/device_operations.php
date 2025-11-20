<?php
require_once 'database.php';
require_once 'validation.php';
require_once 'activity_logger.php';
require_once 'device_display.php';

class DeviceOperations {
    private $pdo;
    
    public function __construct() {
        $this->pdo = Database::getConnection();
    }
    
    /**
     * Check if device number exists
     * @param string $device_number
     * @return bool
     */
    public function checkDeviceExists($device_number) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE device_number = ?");
        $stmt->execute([$device_number]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check if serial number exists
     * @param string $serial_number
     * @return bool
     */
    public function checkSerialExists($serial_number) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE serial_number = ?");
        $stmt->execute([$serial_number]);
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check for duplicate device or serial number excluding current device
     * @param string $device_number
     * @param string $serial_number
     * @param int $exclude_id
     * @return bool
     */
    public function checkDuplicateExcluding($device_number, $serial_number, $exclude_id = null) {
        // Check for individual duplicates (device number OR serial number)
        if ($exclude_id) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE (device_number = ? OR serial_number = ?) AND admin_device_id != ?");
            $stmt->execute([$device_number, $serial_number, $exclude_id]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE device_number = ? OR serial_number = ?");
            $stmt->execute([$device_number, $serial_number]);
        }
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Check for duplicate device number and serial number combination
     * @param string $device_number
     * @param string $serial_number
     * @param int $exclude_id
     * @return bool
     */
    public function checkCombinationDuplicate($device_number, $serial_number, $exclude_id = null) {
        if ($exclude_id) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE device_number = ? AND serial_number = ? AND admin_device_id != ?");
            $stmt->execute([$device_number, $serial_number, $exclude_id]);
        } else {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admin_devices WHERE device_number = ? AND serial_number = ?");
            $stmt->execute([$device_number, $serial_number]);
        }
        return $stmt->fetchColumn() > 0;
    }
    
    /**
     * Add new device
     * @param array $data
     * @return array
     */
    public function addDevice($data) {
        // Validate required fields
        if (!DeviceValidation::validateRequiredFields($data, ['device_number', 'serial_number'])) {
            throw new Exception('Device number and serial number are required');
        }

        // Validate device number
        if (!DeviceValidation::validateDeviceNumber($data['device_number'])) {
            throw new Exception(DeviceValidation::getDeviceNumberErrorMessage());
        }

        // Validate serial number
        if (!DeviceValidation::validateSerialNumber($data['serial_number'])) {
            throw new Exception(DeviceValidation::getSerialNumberErrorMessage());
        }
        
        $device_number = trim($data['device_number']);
        $serial_number = trim($data['serial_number']);
        
        // Check for individual duplicates
        if ($this->checkDuplicateExcluding($device_number, $serial_number)) {
            throw new Exception('Device number or serial number already exists');
        }
        
        // Check for combination duplicates (matches database UNIQUE constraint)
        if ($this->checkCombinationDuplicate($device_number, $serial_number)) {
            throw new Exception('A device with this exact device number and serial number combination already exists');
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Insert the device
            $stmt = $this->pdo->prepare("INSERT INTO admin_devices (device_number, serial_number, device_type, status) VALUES (?, ?, 'Fire Detection Device', ?)");
            $status = isset($data['status']) ? $data['status'] : 'approved';
            $stmt->execute([
                $device_number,
                $serial_number,
                $status
            ]);
            
            // Get the inserted ID
            $admin_device_id = $this->pdo->lastInsertId();
            
            // Log the activity
            $activity = "Added new device: " . $device_number . " (SN: " . $serial_number . ")";
            $logger = new ActivityLogger($this->pdo);
            $logger->logActivity($admin_device_id, $activity, 'device');
            
            // Commit the transaction
            $this->pdo->commit();
            
            return [
                'success' => true, 
                'message' => 'Device added successfully!',
                'device_number' => $device_number,
                'serial_number' => $serial_number
            ];
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $this->pdo->rollBack();
            
            // Check for specific database constraint violations
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'unique_device') !== false) {
                    throw new Exception('A device with this exact device number and serial number combination already exists');
                } else {
                    throw new Exception('Device number or serial number already exists');
                }
            }
            
            throw $e;
        }
    }
    
    /**
     * Update existing device
     * @param array $data
     * @return array
     */
    public function updateDevice($data) {
        if (!DeviceValidation::validateRequiredFields($data, ['device_number', 'serial_number', 'id'])) {
            throw new Exception('Required fields are missing');
        }
        
        // Validate device number
        if (!DeviceValidation::validateDeviceNumber($data['device_number'])) {
            throw new Exception(DeviceValidation::getDeviceNumberErrorMessage());
        }

        // Validate serial number
        if (!DeviceValidation::validateSerialNumber($data['serial_number'])) {
            throw new Exception(DeviceValidation::getSerialNumberErrorMessage());
        }
        
        $device_number = trim($data['device_number']);
        $serial_number = trim($data['serial_number']);
        $device_id = (int)$data['id'];
        
        // Check for individual duplicates excluding current device
        if ($this->checkDuplicateExcluding($device_number, $serial_number, $device_id)) {
            throw new Exception('Another device with this number or serial already exists');
        }
        
        // Check for combination duplicates excluding current device
        if ($this->checkCombinationDuplicate($device_number, $serial_number, $device_id)) {
            throw new Exception('Another device with this exact device number and serial number combination already exists');
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            // Get old device data for logging
            $stmt = $this->pdo->prepare("SELECT device_number, serial_number, status FROM admin_devices WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            $oldDevice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$oldDevice) {
                throw new Exception('Device not found');
            }
            
            // Update the device
            $stmt = $this->pdo->prepare("UPDATE admin_devices SET device_number = ?, serial_number = ?, status = ? WHERE admin_device_id = ?");
            $stmt->execute([
                $device_number,
                $serial_number,
                isset($data['status']) ? $data['status'] : $oldDevice['status'],
                $device_id
            ]);
            
            // Log activity if there are changes
            $changes = [];
            if ($oldDevice['device_number'] != $device_number) {
                $changes[] = "Device#: " . $oldDevice['device_number'] . " → " . $device_number;
            }
            if ($oldDevice['serial_number'] != $serial_number) {
                $changes[] = "SN: " . $oldDevice['serial_number'] . " → " . $serial_number;
            }
            if (isset($data['status']) && $oldDevice['status'] != $data['status']) {
                $changes[] = "Status: " . $oldDevice['status'] . " → " . $data['status'];
            }
            
            if (!empty($changes)) {
                $activity = "Updated device " . $device_number . ": " . implode(", ", $changes);
                $logger = new ActivityLogger($this->pdo);
                $logger->logActivity($device_id, $activity, 'device');
            }
            
            // Commit the transaction
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Device updated successfully!'];
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $this->pdo->rollBack();
            
            // Check for specific database constraint violations
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'unique_device') !== false) {
                    throw new Exception('Another device with this exact device number and serial number combination already exists');
                } else {
                    throw new Exception('Another device with this number or serial already exists');
                }
            }
            
            throw $e;
        }
    }
    
    /**
     * Delete device
     * @param int $device_id
     * @return array
     */
    public function deleteDevice($device_id) {
        if (empty($device_id)) {
            throw new Exception('Device ID is required');
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            $device_id = (int)$device_id;
            
            // Get device data for logging
            $stmt = $this->pdo->prepare("SELECT device_number, serial_number FROM admin_devices WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                throw new Exception('Device not found');
            }
            
            // Log activity before deleting
            $activity = "Deleted device: " . $device['device_number'] . " (SN: " . $device['serial_number'] . ")";
            $logger = new ActivityLogger($this->pdo);
            $logger->logActivity($device_id, $activity, 'device');
            
            // Delete the device
            $stmt = $this->pdo->prepare("DELETE FROM admin_devices WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            
            // Commit the transaction
            $this->pdo->commit();
            
            return ['success' => true, 'message' => 'Device deleted successfully!'];
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get device by ID
     * @param int $device_id
     * @return array|null
     */
    public function getDeviceById($device_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM admin_devices WHERE admin_device_id = ?");
        $stmt->execute([$device_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all devices
     * @return array
     */
    public function getAllDevices() {
        $stmt = $this->pdo->query("SELECT * FROM admin_devices ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Search devices
     * @param string $search_term
     * @return array
     */
    public function searchDevices($search_term) {
        $search = "%{$search_term}%";
        $stmt = $this->pdo->prepare("SELECT * FROM admin_devices 
                                    WHERE device_number LIKE ? OR serial_number LIKE ?
                                    ORDER BY created_at DESC");
        $stmt->execute([$search, $search]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count devices with optional search and status filter
     * @param string|null $searchTerm
     * @param string|null $status
     * @return int
     */
    public function countDevices($searchTerm = null, $status = null) {
        $conditions = [];
        $params = [];

        if ($searchTerm !== null && $searchTerm !== '') {
            $conditions[] = "(device_number LIKE ? OR serial_number LIKE ?)";
            $search = "%{$searchTerm}%";
            $params[] = $search;
            $params[] = $search;
        }

        if ($status !== null && $status !== '' && in_array($status, ['approved','pending','deactivated'])) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT COUNT(*) as cnt FROM admin_devices {$where}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row ? $row['cnt'] : 0);
    }

    /**
     * Get devices with pagination and optional search and status filter
     * @param int $page
     * @param int $perPage
     * @param string|null $searchTerm
     * @param string|null $status
     * @return array [devices, total]
     */
    public function getDevicesPaginated($page, $perPage, $searchTerm = null, $status = null) {
        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        $conditions = [];
        $params = [];

        if ($searchTerm !== null && $searchTerm !== '') {
            $conditions[] = "(device_number LIKE ? OR serial_number LIKE ?)";
            $search = "%{$searchTerm}%";
            $params[] = $search;
            $params[] = $search;
        }

        if ($status !== null && $status !== '' && in_array($status, ['approved','pending','deactivated'])) {
            $conditions[] = "status = ?";
            $params[] = $status;
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        $sql = "SELECT * FROM admin_devices {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $stmt = $this->pdo->prepare($sql);
        $bindParams = array_merge($params, [$perPage, $offset]);

        // For LIMIT/OFFSET as integers, bind explicitly to avoid SQL injection and ensure ints
        $idx = 1;
        foreach ($params as $p) {
            $stmt->bindValue($idx++, $p, PDO::PARAM_STR);
        }
        $stmt->bindValue($idx++, (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue($idx++, (int)$offset, PDO::PARAM_INT);
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = $this->countDevices($searchTerm, $status);
        return [$devices, $total];
    }
    
    /**
     * Generate a unique device number
     * @return string
     */
    public function generateUniqueDeviceNumber() {
        // Use database connection for proper incrementing
        return DeviceValidation::generateDeviceNumber($this->pdo);
    }
    
    /**
     * Generate a unique serial number
     * @return string
     */
    public function generateUniqueSerialNumber() {
        // Use database connection for proper incrementing
        return DeviceValidation::generateSerialNumber($this->pdo);
    }
    
    /**
     * Generate both device number and serial number
     * @return array
     */
    public function generateDeviceData() {
        return [
            'device_number' => $this->generateUniqueDeviceNumber(),
            'serial_number' => $this->generateUniqueSerialNumber()
        ];
    }
    
    /**
     * Deactivate a device
     * @param int $device_id
     * @return array
     */
    public function deactivateDevice($device_id) {
        if (empty($device_id)) {
            throw new Exception('Device ID is required');
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            $device_id = (int)$device_id;
            
            // Get device data for logging
            $stmt = $this->pdo->prepare("SELECT device_number, serial_number, status FROM admin_devices WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                throw new Exception('Device not found');
            }
            
            if ($device['status'] === 'deactivated') {
                throw new Exception('Device is already deactivated');
            }
            
            // Update device status to deactivated
            $stmt = $this->pdo->prepare("UPDATE admin_devices SET status = 'deactivated', updated_at = NOW() WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            
            // Log the activity (optional - won't fail if table doesn't exist)
            try {
                $activity = "Deactivated device: " . $device['device_number'] . " (SN: " . $device['serial_number'] . ")";
                $logger = new ActivityLogger($this->pdo);
                $logger->logActivity($device_id, $activity, 'device');
            } catch (Exception $e) {
                // Log error but don't fail the operation
                error_log("Failed to log activity for device deactivation: " . $e->getMessage());
            }
            
            // Commit the transaction
            $this->pdo->commit();
            
            return [
                'success' => true, 
                'message' => 'Device deactivated successfully!',
                'device_number' => $device['device_number'],
                'serial_number' => $device['serial_number']
            ];
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Set device status to pending
     * @param int $device_id
     * @return array
     */
    public function setDevicePending($device_id) {
        if (empty($device_id)) {
            throw new Exception('Device ID is required');
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            $device_id = (int)$device_id;
            
            // Get device data for logging
            $stmt = $this->pdo->prepare("SELECT device_number, serial_number, status FROM admin_devices WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                throw new Exception('Device not found');
            }
            
            if ($device['status'] === 'pending') {
                throw new Exception('Device is already in pending status');
            }
            
            // Update device status to pending
            $stmt = $this->pdo->prepare("UPDATE admin_devices SET status = 'pending', updated_at = NOW() WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            
            // Log the activity (optional - won't fail if table doesn't exist)
            try {
                $activity = "Set device to pending: " . $device['device_number'] . " (SN: " . $device['serial_number'] . ")";
                $logger = new ActivityLogger($this->pdo);
                $logger->logActivity($device_id, $activity, 'device');
            } catch (Exception $e) {
                // Log error but don't fail the operation
                error_log("Failed to log activity for device pending: " . $e->getMessage());
            }
            
            // Commit the transaction
            $this->pdo->commit();
            
            return [
                'success' => true, 
                'message' => 'Device status set to pending successfully!',
                'device_number' => $device['device_number'],
                'serial_number' => $device['serial_number']
            ];
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Approve a device (set status to approved)
     * @param int $device_id
     * @return array
     */
    public function approveDevice($device_id) {
        if (empty($device_id)) {
            throw new Exception('Device ID is required');
        }
        
        // Start transaction
        $this->pdo->beginTransaction();
        
        try {
            $device_id = (int)$device_id;
            
            // Get device data for logging
            $stmt = $this->pdo->prepare("SELECT device_number, serial_number, status FROM admin_devices WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            $device = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$device) {
                throw new Exception('Device not found');
            }
            
            if ($device['status'] === 'approved') {
                throw new Exception('Device is already approved');
            }
            
            // Update device status to approved
            $stmt = $this->pdo->prepare("UPDATE admin_devices SET status = 'approved', updated_at = NOW() WHERE admin_device_id = ?");
            $stmt->execute([$device_id]);
            
            // Log the activity (optional - won't fail if table doesn't exist)
            try {
                $activity = "Approved device: " . $device['device_number'] . " (SN: " . $device['serial_number'] . ")";
                $logger = new ActivityLogger($this->pdo);
                $logger->logActivity($device_id, $activity, 'device');
            } catch (Exception $e) {
                // Log error but don't fail the operation
                error_log("Failed to log activity for device approval: " . $e->getMessage());
            }
            
            // Commit the transaction
            $this->pdo->commit();
            
            return [
                'success' => true, 
                'message' => 'Device approved successfully!',
                'device_number' => $device['device_number'],
                'serial_number' => $device['serial_number']
            ];
        } catch (Exception $e) {
            // Rollback the transaction if anything fails
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get devices by status
     * @param string $status
     * @return array
     */
    public function getDevicesByStatus($status) {
        $validStatuses = ['approved', 'pending', 'deactivated'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception('Invalid status. Must be one of: ' . implode(', ', $validStatuses));
        }
        
        $stmt = $this->pdo->prepare("SELECT * FROM admin_devices WHERE status = ? ORDER BY created_at DESC");
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get device status summary
     * @return array
     */
    public function getDeviceStatusSummary() {
        $stmt = $this->pdo->query("SELECT status, COUNT(*) as count FROM admin_devices GROUP BY status");
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ensure all statuses are included even if count is 0
        $summary = [
            'approved' => 0,
            'pending' => 0,
            'deactivated' => 0,
            'total' => 0
        ];
        
        foreach ($results as $row) {
            $summary[$row['status']] = (int)$row['count'];
            $summary['total'] += (int)$row['count'];
        }
        
        return $summary;
    }
    
    /**
     * Get the PDO connection (for internal use)
     * @return PDO
     */
    public function getPdo() {
        return $this->pdo;
    }
} 
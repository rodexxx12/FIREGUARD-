<?php
/**
 * Device management component
 */
class DeviceManager {
    private $pdo;
    private $user_id;
    
    public function __construct($pdo, $user_id) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
    }
    
    /**
     * Assign device to building
     * @param int $device_id
     * @param int $building_id
     * @return array
     */
    public function assignDevice($device_id, $building_id) {
        try {
            // Check if device belongs to user
            $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }
            
            // Check if building belongs to user
            $stmt = $this->pdo->prepare("SELECT * FROM buildings WHERE id = ? AND user_id = ?");
            $stmt->execute([$building_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Building not found or doesn't belong to you");
            }
            
			// Get current building assignment
			$stmt = $this->pdo->prepare("SELECT building_id FROM devices WHERE device_id = ?");
			$stmt->execute([$device_id]);
			$current = $stmt->fetch(PDO::FETCH_ASSOC);
			$currentBuildingId = $current ? $current['building_id'] : null;
			
			// If already assigned to the same building, no-op
			if ($currentBuildingId !== null && (int)$currentBuildingId === (int)$building_id) {
				return ['success' => true, 'message' => 'Device already assigned to this building'];
			}
			
			// Update device with new building_id (reassign allowed)
			$stmt = $this->pdo->prepare("UPDATE devices SET building_id = ?, updated_at = CURRENT_TIMESTAMP WHERE device_id = ?");
			$stmt->execute([$building_id, $device_id]);
            
            return ['success' => true, 'message' => 'Device assigned to building successfully!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Remove device from building
     * @param int $device_id
     * @return array
     */
    public function removeDevice($device_id) {
        try {
            // Check if device belongs to user
            $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }
            
            // Remove device from building
            $stmt = $this->pdo->prepare("UPDATE devices SET building_id = NULL WHERE device_id = ?");
            $stmt->execute([$device_id]);
            
            return ['success' => true, 'message' => 'Device removed from building successfully!'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get user's devices (both assigned and unassigned) with pagination
     * @param int $offset
     * @param int $limit
     * @return array
     */
    public function getUserDevices($offset = 0, $limit = 10) {
        $offset = (int)$offset;
        $limit = (int)$limit;
        
        $stmt = $this->pdo->prepare("SELECT d.*, b.building_name 
                                     FROM devices d 
                                     LEFT JOIN buildings b ON d.building_id = b.id 
                                     WHERE d.user_id = ?
                                     ORDER BY d.created_at DESC
                                     LIMIT {$limit} OFFSET {$offset}");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get total count of user's devices
     * @return int
     */
    public function getUserDevicesCount() {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices WHERE user_id = ?");
        $stmt->execute([$this->user_id]);
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Get user's buildings
     * @return array
     */
    public function getUserBuildings() {
        $stmt = $this->pdo->prepare("SELECT * FROM buildings WHERE user_id = ?");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update device status (online|offline)
     * @param int $device_id
     * @param string $status
     * @return array
     */
    public function updateDeviceStatus($device_id, $status) {
        try {
            $allowed = ['online', 'offline'];
            if (!in_array($status, $allowed, true)) {
                throw new Exception('Invalid status value');
            }

            // Ensure device belongs to current user
            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }

            $stmt = $this->pdo->prepare("UPDATE devices SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE device_id = ?");
            $stmt->execute([$status, $device_id]);

            return ['success' => true, 'message' => 'Device status updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Toggle device active flag
     * @param int $device_id
     * @param int $is_active (0|1)
     * @return array
     */
    public function toggleDeviceActive($device_id, $is_active) {
        try {
            $is_active = (int)($is_active ? 1 : 0);

            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }

            $stmt = $this->pdo->prepare("UPDATE devices SET is_active = ?, updated_at = CURRENT_TIMESTAMP WHERE device_id = ?");
            $stmt->execute([$is_active, $device_id]);

            return ['success' => true, 'message' => 'Device active state updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update WiFi credentials
     * @param int $device_id
     * @param string|null $wifi_ssid
     * @param string|null $wifi_password
     * @return array
     */
    public function updateWifiCredentials($device_id, $wifi_ssid, $wifi_password) {
        try {
            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }

            $stmt = $this->pdo->prepare("UPDATE devices SET wifi_ssid = ?, wifi_password = ?, updated_at = CURRENT_TIMESTAMP WHERE device_id = ?");
            $stmt->execute([$wifi_ssid !== '' ? $wifi_ssid : null, $wifi_password !== '' ? $wifi_password : null, $device_id]);

            return ['success' => true, 'message' => 'WiFi credentials updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Update device type and basic info
     * @param int $device_id
     * @param array $fields ['device_type'?, 'device_name'?, 'device_number'?, 'admin_device_id'?, 'building_id'?]
     * @return array
     */
    public function updateDeviceInfo($device_id, array $fields) {
        try {
            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }

            $allowedColumns = ['device_type', 'device_name', 'device_number', 'admin_device_id', 'building_id'];
            $setParts = [];
            $params = [];
            foreach ($allowedColumns as $col) {
                if (array_key_exists($col, $fields)) {
                    $setParts[] = "$col = ?";
                    $params[] = ($fields[$col] === '' ? null : $fields[$col]);
                }
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'No valid fields to update'];
            }

            $sql = "UPDATE devices SET " . implode(', ', $setParts) . ", updated_at = CURRENT_TIMESTAMP WHERE device_id = ?";
            $params[] = $device_id;
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return ['success' => true, 'message' => 'Device information updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Touch last_activity to now
     * @param int $device_id
     * @return array
     */
    public function touchLastActivity($device_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT device_id FROM devices WHERE device_id = ? AND user_id = ?");
            $stmt->execute([$device_id, $this->user_id]);
            if ($stmt->rowCount() === 0) {
                throw new Exception("Device not found or doesn't belong to you");
            }

            $stmt = $this->pdo->prepare("UPDATE devices SET last_activity = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE device_id = ?");
            $stmt->execute([$device_id]);

            return ['success' => true, 'message' => 'Last activity updated'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Create a new device for the current user
     * @param array $data required: device_name, device_number, serial_number; optional: device_type, is_active, status, wifi_ssid, wifi_password, admin_device_id, building_id
     * @return array
     */
    public function createDevice(array $data) {
        try {
            $required = ['device_name', 'device_number', 'serial_number'];
            foreach ($required as $key) {
                if (!isset($data[$key]) || trim((string)$data[$key]) === '') {
                    throw new Exception("Missing required field: $key");
                }
            }

            $deviceType = isset($data['device_type']) && trim((string)$data['device_type']) !== '' ? $data['device_type'] : 'FIREGUARD DEVICE';
            $isActive = isset($data['is_active']) ? (int)(!!$data['is_active']) : 1;
            $status = isset($data['status']) && in_array($data['status'], ['online','offline'], true) ? $data['status'] : 'offline';
            $wifiSsid = isset($data['wifi_ssid']) && $data['wifi_ssid'] !== '' ? $data['wifi_ssid'] : null;
            $wifiPassword = isset($data['wifi_password']) && $data['wifi_password'] !== '' ? $data['wifi_password'] : null;
            $adminDeviceId = isset($data['admin_device_id']) && $data['admin_device_id'] !== '' ? $data['admin_device_id'] : null;
            $buildingId = isset($data['building_id']) && $data['building_id'] !== '' ? $data['building_id'] : null;

            $sql = "INSERT INTO devices (user_id, device_name, device_number, serial_number, device_type, is_active, status, building_id, admin_device_id, wifi_ssid, wifi_password)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $this->user_id,
                $data['device_name'],
                $data['device_number'],
                $data['serial_number'],
                $deviceType,
                $isActive,
                $status,
                $buildingId,
                $adminDeviceId,
                $wifiSsid,
                $wifiPassword
            ]);

            $newId = (int)$this->pdo->lastInsertId();
            return ['success' => true, 'message' => 'Device created successfully', 'device_id' => $newId];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') { // integrity constraint (e.g., duplicate serial)
                return ['success' => false, 'message' => 'Serial number already exists'];
            }
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
} 
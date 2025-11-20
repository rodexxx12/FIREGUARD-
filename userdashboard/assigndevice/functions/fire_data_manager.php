<?php
/**
 * Fire data management component
 */
class FireDataManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get all fire data for user's devices
     * @param array $device_ids
     * @return array
     */
    public function getFireData($device_ids) {
        if (empty($device_ids)) {
            return [];
        }
        
        $placeholders = implode(',', array_fill(0, count($device_ids), '?'));
        $stmt = $this->pdo->prepare("SELECT fd.*, d.device_name, b.building_name 
                                   FROM fire_data fd
                                   JOIN devices d ON fd.device_id = d.device_id
                                   LEFT JOIN buildings b ON d.building_id = b.id
                                   WHERE fd.device_id IN ($placeholders) 
                                   ORDER BY fd.timestamp DESC");
        $stmt->execute($device_ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Prepare map data for buildings and devices
     * @param array $buildings
     * @param array $devices
     * @param array $fire_data
     * @return array
     */
    public function prepareMapData($buildings, $devices, $fire_data) {
        $map_data = [];
        
        // Prepare data for buildings
        foreach ($buildings as $building) {
            $building_devices = array_filter($devices, function($device) use ($building) {
                return isset($device['building_id']) && $device['building_id'] == $building['id'];
            });
            
            $building_fire_data = array_filter($fire_data, function($data) use ($building_devices) {
                foreach ($building_devices as $device) {
                    if ($data['device_id'] == $device['device_id']) {
                        return true;
                    }
                }
                return false;
            });
            
            if ($building['latitude'] && $building['longitude']) {
                $map_data[] = [
                    'type' => 'building',
                    'id' => $building['id'],
                    'name' => $building['building_name'],
                    'lat' => $building['latitude'],
                    'lng' => $building['longitude'],
                    'devices' => array_values($building_devices),
                    'fire_data' => array_values($building_fire_data)
                ];
            }
        }
        
        // Add unassigned devices with their own locations if they have any
        foreach ($devices as $device) {
            if (!$device['building_id']) {
                // Check if device has any fire data with geo coordinates
                $device_fire_data = array_filter($fire_data, function($data) use ($device) {
                    return $data['device_id'] == $device['device_id'] && $data['geo_lat'] && $data['geo_long'];
                });
                
                if (!empty($device_fire_data)) {
                    $latest_data = array_values($device_fire_data)[0];
                    $map_data[] = [
                        'type' => 'device',
                        'id' => $device['device_id'],
                        'name' => $device['device_name'],
                        'lat' => $latest_data['geo_lat'],
                        'lng' => $latest_data['geo_long'],
                        'device' => $device,
                        'fire_data' => array_values($device_fire_data)
                    ];
                }
            }
        }
        
        return $map_data;
    }
} 
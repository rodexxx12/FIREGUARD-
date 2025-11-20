<?php
/**
 * Device Location Validator Utility Functions
 * 
 * Reusable functions to validate device locations within building radius
 * and update building device_id accordingly.
 */

/**
 * Calculate distance between two GPS coordinates using Haversine formula
 * @param float $lat1 Latitude of first point
 * @param float $lon1 Longitude of first point
 * @param float $lat2 Latitude of second point
 * @param float $lon2 Longitude of second point
 * @return float Distance in meters
 */
function calculateDistanceMeters($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;
    
    return $distance;
}

/**
 * Check if device GPS coordinates are within building radius
 * @param float $deviceLat Device latitude
 * @param float $deviceLon Device longitude
 * @param float $buildingLat Building latitude
 * @param float $buildingLon Building longitude
 * @param float $radius Building radius in meters (default 100)
 * @return array Result with 'inside' boolean and 'distance' in meters
 */
function isDeviceInBuildingRadius($deviceLat, $deviceLon, $buildingLat, $buildingLon, $radius = 100.0) {
    $distance = calculateDistanceMeters($deviceLat, $deviceLon, $buildingLat, $buildingLon);
    
    return [
        'inside' => $distance <= $radius,
        'distance' => $distance,
        'radius' => $radius
    ];
}

/**
 * Validate device location and update building device_id if device is inside radius
 * This function can be called without session (for API/background jobs)
 * @param int $device_id Device ID
 * @param int $building_id Building ID
 * @param PDO $pdo Database connection
 * @param int|null $user_id Optional user_id for validation (if null, skips user check)
 * @return array Result with success status and message
 */
function validateAndUpdateDeviceLocation($device_id, $building_id, $pdo, $user_id = null) {
    try {
        // Get device's latest GPS coordinates from fire_data
        if ($user_id) {
            $deviceSql = "SELECT gps_latitude, gps_longitude 
                          FROM fire_data 
                          WHERE device_id = ? 
                          AND user_id = ?
                          AND gps_latitude IS NOT NULL 
                          AND gps_longitude IS NOT NULL
                          ORDER BY timestamp DESC 
                          LIMIT 1";
            $deviceParams = [$device_id, $user_id];
        } else {
            $deviceSql = "SELECT gps_latitude, gps_longitude 
                          FROM fire_data 
                          WHERE device_id = ? 
                          AND gps_latitude IS NOT NULL 
                          AND gps_longitude IS NOT NULL
                          ORDER BY timestamp DESC 
                          LIMIT 1";
            $deviceParams = [$device_id];
        }
        
        $deviceStmt = $pdo->prepare($deviceSql);
        $deviceStmt->execute($deviceParams);
        $deviceData = $deviceStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deviceData) {
            return [
                'success' => false,
                'message' => 'Device GPS coordinates not found. Device must have valid GPS data in fire_data table.'
            ];
        }
        
        $deviceLat = (float)$deviceData['gps_latitude'];
        $deviceLon = (float)$deviceData['gps_longitude'];
        
        // Get building coordinates and radius
        if ($user_id) {
            $buildingSql = "SELECT b.id, b.latitude, b.longitude, b.user_id,
                                   COALESCE(ba.radius, 100.00) as radius
                            FROM buildings b
                            LEFT JOIN building_areas ba ON ba.building_id = b.id
                            WHERE b.id = ? AND b.user_id = ?
                            AND b.latitude IS NOT NULL 
                            AND b.longitude IS NOT NULL";
            $buildingParams = [$building_id, $user_id];
        } else {
            $buildingSql = "SELECT b.id, b.latitude, b.longitude, b.user_id,
                                   COALESCE(ba.radius, 100.00) as radius
                            FROM buildings b
                            LEFT JOIN building_areas ba ON ba.building_id = b.id
                            WHERE b.id = ?
                            AND b.latitude IS NOT NULL 
                            AND b.longitude IS NOT NULL";
            $buildingParams = [$building_id];
        }
        
        $buildingStmt = $pdo->prepare($buildingSql);
        $buildingStmt->execute($buildingParams);
        $buildingData = $buildingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$buildingData) {
            return [
                'success' => false,
                'message' => 'Building not found or does not have valid coordinates.'
            ];
        }
        
        $buildingLat = (float)$buildingData['latitude'];
        $buildingLon = (float)$buildingData['longitude'];
        $radius = (float)$buildingData['radius']; // Radius in meters
        
        // Check if device is within building radius
        $validation = isDeviceInBuildingRadius($deviceLat, $deviceLon, $buildingLat, $buildingLon, $radius);
        
        if ($validation['inside']) {
            // Device is inside the building radius - update both buildings.device_id and devices.building_id
            try {
                $pdo->beginTransaction();
                
                // Update building's device_id
                if ($user_id) {
                    $updateBuildingSql = "UPDATE buildings 
                                         SET device_id = ? 
                                         WHERE id = ? AND user_id = ?";
                    $updateBuildingParams = [$device_id, $building_id, $user_id];
                } else {
                    $updateBuildingSql = "UPDATE buildings 
                                         SET device_id = ? 
                                         WHERE id = ?";
                    $updateBuildingParams = [$device_id, $building_id];
                }
                
                $updateBuildingStmt = $pdo->prepare($updateBuildingSql);
                $updateBuildingStmt->execute($updateBuildingParams);
                $buildingRowsAffected = $updateBuildingStmt->rowCount();
                
                // Update device's building_id
                if ($user_id) {
                    $updateDeviceSql = "UPDATE devices 
                                       SET building_id = ? 
                                       WHERE device_id = ? AND user_id = ?";
                    $updateDeviceParams = [$building_id, $device_id, $user_id];
                } else {
                    $updateDeviceSql = "UPDATE devices 
                                       SET building_id = ? 
                                       WHERE device_id = ?";
                    $updateDeviceParams = [$building_id, $device_id];
                }
                
                $updateDeviceStmt = $pdo->prepare($updateDeviceSql);
                $updateDeviceStmt->execute($updateDeviceParams);
                $deviceRowsAffected = $updateDeviceStmt->rowCount();
                
                // Log the update for debugging
                error_log("Device validation update: device_id={$device_id}, building_id={$building_id}, building_rows={$buildingRowsAffected}, device_rows={$deviceRowsAffected}");
                
                if ($deviceRowsAffected === 0) {
                    error_log("WARNING: devices.building_id update affected 0 rows. device_id={$device_id}, building_id={$building_id}, user_id=" . ($user_id ?? 'null'));
                }
                
                $pdo->commit();
                
                return [
                    'success' => true,
                    'message' => 'Device is within building radius. Both building.device_id and device.building_id have been updated.',
                    'distance' => round($validation['distance'], 2),
                    'radius' => $radius,
                    'device_id' => $device_id,
                    'building_id' => $building_id
                ];
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw $e;
            }
        } else {
            return [
                'success' => false,
                'message' => 'Device is outside building radius. Device must be inside the building radius.',
                'distance' => round($validation['distance'], 2),
                'radius' => $radius,
                'required_distance' => $radius
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Database error in validateAndUpdateDeviceLocation: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Auto-validate device location for a specific device
 * Finds all buildings for the device's user and checks if device is within any building radius
 * @param int $device_id Device ID
 * @param PDO $pdo Database connection
 * @return array Result with matched building info or error
 */
function autoValidateDeviceLocation($device_id, $pdo) {
    try {
        // Get device info and latest GPS coordinates
        $deviceSql = "SELECT d.device_id, d.user_id, 
                             fd.gps_latitude, fd.gps_longitude
                      FROM devices d
                      INNER JOIN fire_data fd ON fd.device_id = d.device_id
                      WHERE d.device_id = ?
                      AND fd.gps_latitude IS NOT NULL 
                      AND fd.gps_longitude IS NOT NULL
                      ORDER BY fd.timestamp DESC 
                      LIMIT 1";
        
        $deviceStmt = $pdo->prepare($deviceSql);
        $deviceStmt->execute([$device_id]);
        $deviceInfo = $deviceStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$deviceInfo) {
            return [
                'success' => false,
                'message' => 'Device GPS coordinates not found.'
            ];
        }
        
        $user_id = $deviceInfo['user_id'];
        $deviceLat = (float)$deviceInfo['gps_latitude'];
        $deviceLon = (float)$deviceInfo['gps_longitude'];
        
        // Get all buildings for this user with coordinates
        $buildingsSql = "SELECT b.id, b.latitude, b.longitude, b.building_name,
                                COALESCE(ba.radius, 100.00) as radius
                         FROM buildings b
                         LEFT JOIN building_areas ba ON ba.building_id = b.id
                         WHERE b.user_id = ?
                         AND b.latitude IS NOT NULL 
                         AND b.longitude IS NOT NULL";
        
        $buildingsStmt = $pdo->prepare($buildingsSql);
        $buildingsStmt->execute([$user_id]);
        $buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($buildings)) {
            return [
                'success' => false,
                'message' => 'No buildings with coordinates found for this user.'
            ];
        }
        
        // Check each building to see if device is within radius
        foreach ($buildings as $building) {
            $buildingLat = (float)$building['latitude'];
            $buildingLon = (float)$building['longitude'];
            $radius = (float)$building['radius'];
            
            $validation = isDeviceInBuildingRadius($deviceLat, $deviceLon, $buildingLat, $buildingLon, $radius);
            
            if ($validation['inside']) {
                // Device is within this building's radius - update both buildings.device_id and devices.building_id
                try {
                    $pdo->beginTransaction();
                    
                    // Update building's device_id
                    $updateBuildingSql = "UPDATE buildings 
                                         SET device_id = ? 
                                         WHERE id = ? AND user_id = ?";
                    $updateBuildingStmt = $pdo->prepare($updateBuildingSql);
                    $updateBuildingStmt->execute([$device_id, $building['id'], $user_id]);
                    $buildingRowsAffected = $updateBuildingStmt->rowCount();
                    
                    // Update device's building_id
                    $updateDeviceSql = "UPDATE devices 
                                       SET building_id = ? 
                                       WHERE device_id = ? AND user_id = ?";
                    $updateDeviceStmt = $pdo->prepare($updateDeviceSql);
                    $updateDeviceStmt->execute([$building['id'], $device_id, $user_id]);
                    $deviceRowsAffected = $updateDeviceStmt->rowCount();
                    
                    // Log the update for debugging
                    error_log("Auto-validation update: device_id={$device_id}, building_id={$building['id']}, building_rows={$buildingRowsAffected}, device_rows={$deviceRowsAffected}");
                    
                    if ($deviceRowsAffected === 0) {
                        error_log("WARNING: devices.building_id update affected 0 rows. device_id={$device_id}, building_id={$building['id']}, user_id={$user_id}");
                    }
                    
                    $pdo->commit();
                    
                    return [
                        'success' => true,
                        'message' => 'Device is within building radius. Both building.device_id and device.building_id have been updated.',
                        'distance' => round($validation['distance'], 2),
                        'radius' => $radius,
                        'device_id' => $device_id,
                        'building_id' => $building['id'],
                        'building_name' => $building['building_name'] ?? null
                    ];
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
        
        // Device is not within any building radius
        return [
            'success' => false,
            'message' => 'Device is not within any building radius.',
            'device_id' => $device_id
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in autoValidateDeviceLocation: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}

/**
 * Auto-validate device location when new GPS data is received
 * This function should be called after inserting fire_data with GPS coordinates
 * It will automatically find the building the device is in and update building.device_id
 * 
 * @param int $device_id Device ID
 * @param float|null $gps_latitude Device GPS latitude (optional, will fetch latest if not provided)
 * @param float|null $gps_longitude Device GPS longitude (optional, will fetch latest if not provided)
 * @param PDO|null $pdo Database connection (optional, will create if not provided)
 * @return array Result with success status and matched building info
 */
function autoValidateDeviceOnNewGPS($device_id, $gps_latitude = null, $gps_longitude = null, $pdo = null) {
    try {
        if (!$pdo) {
            require_once __DIR__ . '/../db/db.php';
            $pdo = getMappingDBConnection();
        }
        
        // If GPS coordinates not provided, get latest from fire_data
        if ($gps_latitude === null || $gps_longitude === null) {
            $deviceSql = "SELECT d.device_id, d.user_id, 
                                 fd.gps_latitude, fd.gps_longitude
                          FROM devices d
                          INNER JOIN fire_data fd ON fd.device_id = d.device_id
                          WHERE d.device_id = ?
                          AND fd.gps_latitude IS NOT NULL 
                          AND fd.gps_longitude IS NOT NULL
                          ORDER BY fd.timestamp DESC 
                          LIMIT 1";
            
            $deviceStmt = $pdo->prepare($deviceSql);
            $deviceStmt->execute([$device_id]);
            $deviceInfo = $deviceStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deviceInfo) {
                return [
                    'success' => false,
                    'message' => 'Device GPS coordinates not found in fire_data table.'
                ];
            }
            
            $user_id = $deviceInfo['user_id'];
            $gps_latitude = (float)$deviceInfo['gps_latitude'];
            $gps_longitude = (float)$deviceInfo['gps_longitude'];
        } else {
            // Get user_id from device
            $deviceSql = "SELECT user_id FROM devices WHERE device_id = ?";
            $deviceStmt = $pdo->prepare($deviceSql);
            $deviceStmt->execute([$device_id]);
            $deviceInfo = $deviceStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$deviceInfo) {
                return [
                    'success' => false,
                    'message' => 'Device not found.'
                ];
            }
            
            $user_id = $deviceInfo['user_id'];
            $gps_latitude = (float)$gps_latitude;
            $gps_longitude = (float)$gps_longitude;
        }
        
        // Get all buildings for this user with coordinates and radius
        $buildingsSql = "SELECT b.id, b.latitude, b.longitude, b.building_name, b.device_id as current_device_id,
                                COALESCE(ba.radius, 100.00) as radius
                         FROM buildings b
                         LEFT JOIN building_areas ba ON ba.building_id = b.id
                         WHERE b.user_id = ?
                         AND b.latitude IS NOT NULL 
                         AND b.longitude IS NOT NULL";
        
        $buildingsStmt = $pdo->prepare($buildingsSql);
        $buildingsStmt->execute([$user_id]);
        $buildings = $buildingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($buildings)) {
            return [
                'success' => false,
                'message' => 'No buildings with coordinates found for this user.'
            ];
        }
        
        $matchedBuilding = null;
        $minDistance = null;
        
        // Check each building to see if device is within radius
        foreach ($buildings as $building) {
            $buildingLat = (float)$building['latitude'];
            $buildingLon = (float)$building['longitude'];
            $radius = (float)$building['radius'];
            
            $validation = isDeviceInBuildingRadius($gps_latitude, $gps_longitude, $buildingLat, $buildingLon, $radius);
            
            if ($validation['inside']) {
                // Device is within this building's radius
                // If multiple buildings match, choose the closest one
                if ($matchedBuilding === null || $validation['distance'] < $minDistance) {
                    $matchedBuilding = $building;
                    $minDistance = $validation['distance'];
                }
            }
        }
        
        if ($matchedBuilding) {
            // Device is within a building's radius - update both buildings.device_id and devices.building_id
            try {
                $pdo->beginTransaction();
                
                // Update building's device_id
                $updateBuildingSql = "UPDATE buildings 
                                     SET device_id = ? 
                                     WHERE id = ? AND user_id = ?";
                $updateBuildingStmt = $pdo->prepare($updateBuildingSql);
                $updateBuildingStmt->execute([$device_id, $matchedBuilding['id'], $user_id]);
                $buildingRowsAffected = $updateBuildingStmt->rowCount();
                
                // Update device's building_id
                $updateDeviceSql = "UPDATE devices 
                                   SET building_id = ? 
                                   WHERE device_id = ? AND user_id = ?";
                $updateDeviceStmt = $pdo->prepare($updateDeviceSql);
                $updateDeviceStmt->execute([$matchedBuilding['id'], $device_id, $user_id]);
                $deviceRowsAffected = $updateDeviceStmt->rowCount();
                
                // Log the update for debugging
                error_log("Auto-validate GPS update: device_id={$device_id}, building_id={$matchedBuilding['id']}, building_rows={$buildingRowsAffected}, device_rows={$deviceRowsAffected}");
                
                if ($deviceRowsAffected === 0) {
                    error_log("WARNING: devices.building_id update affected 0 rows. device_id={$device_id}, building_id={$matchedBuilding['id']}, user_id={$user_id}");
                }
                
                $pdo->commit();
                
                return [
                    'success' => true,
                    'message' => 'Device is within building radius. Both building.device_id and device.building_id have been updated.',
                    'distance' => round($minDistance, 2),
                    'radius' => (float)$matchedBuilding['radius'],
                    'device_id' => $device_id,
                    'building_id' => $matchedBuilding['id'],
                    'building_name' => $matchedBuilding['building_name'] ?? null,
                    'previous_device_id' => $matchedBuilding['current_device_id']
                ];
            } catch (PDOException $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
        
        // Device is not within any building radius
        return [
            'success' => false,
            'message' => 'Device is not within any building radius. Device must be inside a building radius to be assigned.',
            'device_id' => $device_id,
            'gps_latitude' => $gps_latitude,
            'gps_longitude' => $gps_longitude
        ];
        
    } catch (PDOException $e) {
        error_log("Database error in autoValidateDeviceOnNewGPS: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
}
?>


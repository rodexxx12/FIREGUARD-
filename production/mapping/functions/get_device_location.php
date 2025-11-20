<?php
require_once __DIR__ . '/db_utils.php';

// Function to get device location based on most recent fire_data (NOT building location)
// Use GPS coordinates directly from fire_data table (prioritize fire_data's own GPS fields)
// Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
// Returns the LATEST fire_data GPS location for each device
function getDeviceLocation($pdo, $deviceId = null) {
    $sql = "
    SELECT
        d.device_id,
        d.device_name,
        d.device_number,
        d.serial_number,
        d.status as device_status,
        d.last_activity,
        d.building_id,
        f.id as fire_data_id,
        f.status,
        f.timestamp,
        -- Get GPS coordinates from fire_data table (prioritize fire_data's own GPS fields first)
        -- Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table
        COALESCE(
            f.gps_latitude, 
            f.geo_lat,
            g.latitude
        ) as geo_lat,
        COALESCE(
            f.gps_longitude, 
            f.geo_long,
            g.longitude
        ) as geo_long,
        COALESCE(f.gps_altitude, g.altitude) as altitude,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM devices d
    LEFT JOIN (
        -- Get only the latest fire_data per device
        SELECT f1.*
        FROM fire_data f1
        INNER JOIN (
            SELECT device_id, MAX(timestamp) as latest_timestamp
            FROM fire_data
            WHERE device_id IS NOT NULL
            GROUP BY device_id
        ) f2 ON f1.device_id = f2.device_id AND f1.timestamp = f2.latest_timestamp
    ) f ON d.device_id = f.device_id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
        AND g.latitude IS NOT NULL 
        AND g.longitude IS NOT NULL
        AND g.latitude != 0
        AND g.longitude != 0
        AND g.id = (
            SELECT g2.id
            FROM gps_data g2
            WHERE g2.latitude IS NOT NULL 
            AND g2.longitude IS NOT NULL
            AND g2.latitude != 0
            AND g2.longitude != 0
            AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
            LIMIT 1
        )
    WHERE d.is_active = 1";
    
    if ($deviceId) {
        $sql .= " AND d.device_id = :device_id";
    }
    
    $sql .= "
    ORDER BY f.timestamp DESC, d.device_id
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($deviceId) {
            $stmt->bindParam(':device_id', $deviceId, PDO::PARAM_INT);
        }
        $stmt->execute();
        
        if ($deviceId) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

// Function to get all active devices with their latest fire_data GPS location (NOT building location)
// Use GPS coordinates directly from fire_data table (prioritize fire_data's own GPS fields)
// Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
// Uses devices.latest_fire_data_id to get the most recent fire_data per device
function getAllActiveDevices($pdo) {
    $sql = "
    SELECT
        d.device_id,
        d.device_name,
        d.device_number,
        d.serial_number,
        d.status as device_status,
        d.last_activity,
        d.building_id,
        f.id as latest_fire_data_id,
        f.status,
        f.timestamp,
        COALESCE(
            g.latitude, 
            f.gps_latitude, 
            f.geo_lat
        ) as geo_lat,
        COALESCE(
            g.longitude, 
            f.gps_longitude, 
            f.geo_long
        ) as geo_long,
        COALESCE(f.gps_altitude, g.altitude) as altitude,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM devices d
    LEFT JOIN fire_data f ON d.latest_fire_data_id = f.id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
        AND g.latitude IS NOT NULL 
        AND g.longitude IS NOT NULL
        AND g.latitude != 0
        AND g.longitude != 0
        AND g.id = (
            SELECT g2.id
            FROM gps_data g2
            WHERE g2.latitude IS NOT NULL 
            AND g2.longitude IS NOT NULL
            AND g2.latitude != 0
            AND g2.longitude != 0
            AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
            LIMIT 1
        )
    WHERE d.is_active = 1
    ORDER BY f.timestamp DESC, d.device_id
    ";

    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

// Function to get device location by building - returns fire_data GPS location (NOT building location)
// Use GPS coordinates directly from fire_data table (prioritize fire_data's own GPS fields)
// Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
// Returns the LATEST fire_data GPS location for each device in the building
function getDeviceLocationByBuilding($pdo, $buildingId) {
    $sql = "
    SELECT
        d.device_id,
        d.device_name,
        d.device_number,
        d.serial_number,
        d.status as device_status,
        d.last_activity,
        f.id as fire_data_id,
        f.status,
        f.timestamp,
        -- Get GPS coordinates from fire_data table (prioritize fire_data's own GPS fields first)
        -- Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table
        COALESCE(
            f.gps_latitude, 
            f.geo_lat,
            g.latitude
        ) as geo_lat,
        COALESCE(
            f.gps_longitude, 
            f.geo_long,
            g.longitude
        ) as geo_long,
        COALESCE(f.gps_altitude, g.altitude) as altitude,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM devices d
    LEFT JOIN (
        -- Get only the latest fire_data per device
        SELECT f1.*
        FROM fire_data f1
        INNER JOIN (
            SELECT device_id, MAX(timestamp) as latest_timestamp
            FROM fire_data
            WHERE device_id IS NOT NULL
            GROUP BY device_id
        ) f2 ON f1.device_id = f2.device_id AND f1.timestamp = f2.latest_timestamp
    ) f ON d.device_id = f.device_id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
        AND g.latitude IS NOT NULL 
        AND g.longitude IS NOT NULL
        AND g.latitude != 0
        AND g.longitude != 0
        AND g.id = (
            SELECT g2.id
            FROM gps_data g2
            WHERE g2.latitude IS NOT NULL 
            AND g2.longitude IS NOT NULL
            AND g2.latitude != 0
            AND g2.longitude != 0
            AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
            LIMIT 1
        )
    WHERE d.is_active = 1 AND d.building_id = :building_id
    ORDER BY f.timestamp DESC
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':building_id', $buildingId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

// Function to get devices with emergency status - returns fire_data GPS location (NOT building location)
// Use GPS coordinates directly from fire_data table (prioritize fire_data's own GPS fields)
// Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
// Returns fire_data GPS coordinates for emergency incidents
function getEmergencyDevices($pdo) {
    $sql = "
    SELECT
        d.device_id,
        d.device_name,
        d.device_number,
        d.serial_number,
        d.status as device_status,
        d.last_activity,
        f.id as fire_data_id,
        f.status,
        f.timestamp,
        COALESCE(
            g.latitude, 
            f.gps_latitude, 
            f.geo_lat
        ) as geo_lat,
        COALESCE(
            g.longitude, 
            f.gps_longitude, 
            f.geo_long
        ) as geo_long,
        COALESCE(f.gps_altitude, g.altitude) as altitude,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM devices d
    INNER JOIN fire_data f ON d.device_id = f.device_id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
        AND g.latitude IS NOT NULL 
        AND g.longitude IS NOT NULL
        AND g.latitude != 0
        AND g.longitude != 0
        AND g.id = (
            SELECT g2.id
            FROM gps_data g2
            WHERE g2.latitude IS NOT NULL 
            AND g2.longitude IS NOT NULL
            AND g2.latitude != 0
            AND g2.longitude != 0
            AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
            ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
            LIMIT 1
        )
    WHERE d.is_active = 1 AND UPPER(f.status) IN ('EMERGENCY', 'ACKNOWLEDGED')
    ORDER BY f.timestamp DESC
    ";
    
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}
?>

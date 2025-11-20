<?php
require_once __DIR__ . '/db_utils.php';

function getEmergencyBuildings($pdo) {
    // Get all buildings with EMERGENCY status (most recent per building)
    // Use GPS coordinates directly from fire_data table (prioritize fire_data's own GPS fields)
    // Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
    $sql = "
    SELECT
        b.id,
        b.building_name,
        b.address,
        b.latitude,
        b.longitude,
        f.status,
        f.timestamp,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
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
        f.device_id,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        g.ph_time as gps_time
    FROM buildings b
    INNER JOIN (
        SELECT f1.*
        FROM fire_data f1
        INNER JOIN (
            SELECT building_id, MAX(timestamp) AS latest_time
            FROM fire_data
            WHERE building_id IS NOT NULL
            AND UPPER(status) = 'EMERGENCY'
            GROUP BY building_id
        ) f2 ON f1.building_id = f2.building_id AND f1.timestamp = f2.latest_time
    ) f ON b.id = f.building_id
    LEFT JOIN devices d ON f.device_id = d.device_id
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
    WHERE UPPER(f.status) = 'EMERGENCY'
    ORDER BY f.timestamp DESC;
    ";

    try {
        $stmt = $pdo->query($sql);
        $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $buildings;
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

// Function to get the most recent EMERGENCY fire_data specifically
// Query fire_data directly - NOT buildings. Use fire_data GPS coordinates for location
// Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
function getMostRecentEmergencyBuilding($pdo) {
    $sql = "
    SELECT
        f.id as fire_data_id,
        f.status,
        f.timestamp,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        -- Get GPS coordinates from fire_data table (prioritize fire_data's own GPS fields first)
        -- Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table
        COALESCE(
            f.gps_latitude, 
            f.geo_lat,
            g.latitude
        ) as latitude,
        COALESCE(
            f.gps_longitude, 
            f.geo_long,
            g.longitude
        ) as longitude,
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
        f.device_id,
        f.building_id,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        d.device_name,
        d.device_number,
        d.status as device_status,
        b.id as building_id_join,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM fire_data f
    LEFT JOIN devices d ON f.device_id = d.device_id
    LEFT JOIN buildings b ON f.building_id = b.id
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
    WHERE UPPER(f.status) = 'EMERGENCY'
    AND (
        f.gps_latitude IS NOT NULL OR 
        f.geo_lat IS NOT NULL OR 
        g.latitude IS NOT NULL
    )
    ORDER BY f.timestamp DESC
    LIMIT 1;
    ";

    try {
        $stmt = $pdo->query($sql);
        $building = $stmt->fetch(PDO::FETCH_ASSOC);
        return $building;
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

// Function to get the single most recent critical fire_data (EMERGENCY OR ACKNOWLEDGED)
// Query fire_data directly - NOT buildings. Use fire_data GPS coordinates for location
// Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table (NO building coordinates)
function getMostRecentCriticalBuilding($pdo) {
    $sql = "
    SELECT
        f.id as fire_data_id,
        f.status,
        f.building_type,
        f.timestamp,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.notified,
        f.user_id,
        -- Get GPS coordinates from fire_data table (prioritize fire_data's own GPS fields first)
        -- Priority: fire_data.gps_latitude/gps_longitude > fire_data.geo_lat/geo_long > gps_data table
        COALESCE(
            f.gps_latitude, 
            f.geo_lat,
            g.latitude
        ) as latitude,
        COALESCE(
            f.gps_longitude, 
            f.geo_long,
            g.longitude
        ) as longitude,
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
        f.gps_latitude,
        f.gps_longitude,
        f.gps_altitude,
        f.device_id,
        f.building_id,
        f.ml_confidence,
        f.ml_prediction,
        f.ml_fire_probability,
        f.ai_prediction,
        f.acknowledged_at_time,
        f.ml_timestamp,
        f.barangay_id,
        d.device_name,
        d.device_number,
        d.serial_number,
        d.status as device_status,
        b.id as building_id_join,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        g.ph_time as gps_time
    FROM fire_data f
    LEFT JOIN devices d ON f.device_id = d.device_id
    LEFT JOIN buildings b ON f.building_id = b.id
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
    WHERE UPPER(f.status) IN ('EMERGENCY', 'ACKNOWLEDGED')
    AND (
        f.gps_latitude IS NOT NULL OR 
        f.geo_lat IS NOT NULL OR 
        g.latitude IS NOT NULL
    )
    ORDER BY f.timestamp DESC
    LIMIT 1;
    ";

    try {
        $stmt = $pdo->query($sql);
        $building = $stmt->fetch(PDO::FETCH_ASSOC);
        return $building;
    } catch (PDOException $e) {
        throw new Exception('Database query failed: ' . $e->getMessage());
    }
}

// Function to get device location based on most recent fire data
function getDeviceLocation($pdo, $deviceId = null) {
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
        f.geo_lat,
        f.geo_long,
        f.temp,
        f.smoke,
        f.heat,
        f.flame_detected,
        f.ml_confidence,
        f.ml_prediction,
        f.ai_prediction,
        b.id as building_id,
        b.building_name,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng
    FROM devices d
    LEFT JOIN fire_data f ON d.device_id = f.device_id
    LEFT JOIN buildings b ON d.building_id = b.id
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

// Function to get all active devices with their latest fire data
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
        f.geo_lat,
        f.geo_long,
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
        b.longitude as building_lng
    FROM devices d
    LEFT JOIN fire_data f ON d.latest_fire_data_id = f.id
    LEFT JOIN buildings b ON d.building_id = b.id
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
?> 
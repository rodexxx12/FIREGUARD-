<?php
// Include centralized database connection
require_once '../../db/db.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../../index.php");
    exit();
}

// Fetch fire data with device location information
function getFireData($startDate = null, $endDate = null) {
    $conn = getDatabaseConnection();
    
    $query = "
        SELECT 
            fd.id, 
            fd.status, 
            fd.building_type, 
            fd.smoke, 
            fd.temp, 
            fd.heat, 
            fd.flame_detected,
            fd.timestamp,
            fd.geo_lat,
            fd.geo_long,
            fd.gps_latitude,
            fd.gps_longitude,
            fd.ml_confidence,
            fd.ml_prediction,
            fd.ml_fire_probability,
            fd.ai_prediction,
            fd.acknowledged_at_time,
            fd.device_id,
            d.device_name,
            d.device_number,
            br.id AS barangay_id,
            br.barangay_name,
            -- Use GPS coordinates from fire_data (prioritize gps_latitude/gps_longitude, then geo_lat/geo_long)
            COALESCE(fd.gps_latitude, fd.geo_lat) as display_lat,
            COALESCE(fd.gps_longitude, fd.geo_long) as display_long,
            -- Calculate fire risk level for the barangay
            CASE 
                WHEN COALESCE(incident_counts.total_incidents, 0) > 0 THEN 'HIGH'
                ELSE 'SAFE'
            END as fire_risk_level
        FROM fire_data fd
        LEFT JOIN devices d ON fd.device_id = d.device_id
        LEFT JOIN barangay br ON COALESCE(d.barangay_id, fd.barangay_id) = br.id
        LEFT JOIN (
            SELECT 
                COALESCE(d2.barangay_id, fd2.barangay_id) as barangay_id,
                COUNT(*) as total_incidents
            FROM fire_data fd2
            LEFT JOIN devices d2 ON fd2.device_id = d2.device_id
            WHERE COALESCE(d2.barangay_id, fd2.barangay_id) IS NOT NULL
            GROUP BY barangay_id
        ) incident_counts ON br.id = incident_counts.barangay_id
        WHERE (fd.gps_latitude IS NOT NULL AND fd.gps_longitude IS NOT NULL) 
           OR (fd.geo_lat IS NOT NULL AND fd.geo_long IS NOT NULL)
    ";
    
    $params = [];
    
    // Add date filtering if provided
    if ($startDate && $endDate) {
        $query .= " AND DATE(fd.timestamp) BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    } elseif ($startDate) {
        $query .= " AND DATE(fd.timestamp) >= :start_date";
        $params[':start_date'] = $startDate;
    } elseif ($endDate) {
        $query .= " AND DATE(fd.timestamp) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    $query .= " ORDER BY fd.timestamp DESC";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch(PDOException $e) {
        error_log("Error fetching fire data: " . $e->getMessage());
        return [];
    }
}

// Fetch devices with coordinates from fire_data (prioritize GPS coordinates)
function getDevicesWithCoordinates() {
    $conn = getDatabaseConnection();
    
    $query = "
        SELECT 
            d.device_id,
            d.device_name,
            d.device_number,
            d.serial_number,
            d.device_type,
            d.status as device_status,
            d.is_active,
            d.barangay_id,
            d.last_activity,
            -- Get latest fire_data for each device
            latest_fd.id as latest_fire_data_id,
            latest_fd.status as fire_data_status,
            latest_fd.timestamp as latest_timestamp,
            latest_fd.smoke,
            latest_fd.temp,
            latest_fd.heat,
            latest_fd.flame_detected,
            latest_fd.ml_confidence,
            latest_fd.ml_prediction,
            -- Get GPS coordinates from fire_data (prioritize gps_latitude/gps_longitude, then geo_lat/geo_long)
            COALESCE(latest_fd.gps_latitude, latest_fd.geo_lat) as latitude,
            COALESCE(latest_fd.gps_longitude, latest_fd.geo_long) as longitude,
            latest_fd.gps_altitude as altitude,
            -- Barangay info
            br.barangay_name,
            br.latitude AS barangay_lat,
            br.longitude AS barangay_long,
            -- Calculate fire risk level for the barangay
            CASE 
                WHEN COALESCE(incident_counts.total_incidents, 0) > 0 THEN 'HIGH'
                ELSE 'SAFE'
            END as fire_risk_level,
            -- Check if device has emergency status
            CASE 
                WHEN latest_fd.status IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 1
                ELSE 0
            END as has_emergency_status,
            -- Count incidents for this device
            COALESCE(device_incidents.incident_count, 0) as fire_incident_count,
            COALESCE(device_incidents.has_fire_incidents, 0) as has_fire_incidents
        FROM devices d
        LEFT JOIN barangay br ON d.barangay_id = br.id
        LEFT JOIN (
            -- Get latest fire_data per device
            SELECT f1.*
            FROM fire_data f1
            INNER JOIN (
                SELECT device_id, MAX(timestamp) as latest_timestamp
                FROM fire_data
                WHERE device_id IS NOT NULL
                GROUP BY device_id
            ) f2 ON f1.device_id = f2.device_id AND f1.timestamp = f2.latest_timestamp
        ) latest_fd ON d.device_id = latest_fd.device_id
        LEFT JOIN (
            SELECT 
                device_id,
                COUNT(*) as incident_count,
                CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END as has_fire_incidents
            FROM fire_data 
            WHERE device_id IS NOT NULL
            GROUP BY device_id
        ) device_incidents ON d.device_id = device_incidents.device_id
        LEFT JOIN (
            SELECT 
                COALESCE(d2.barangay_id, fd2.barangay_id) as barangay_id,
                COUNT(*) as total_incidents
            FROM fire_data fd2
            LEFT JOIN devices d2 ON fd2.device_id = d2.device_id
            WHERE COALESCE(d2.barangay_id, fd2.barangay_id) IS NOT NULL
            GROUP BY barangay_id
        ) incident_counts ON br.id = incident_counts.barangay_id
        WHERE d.is_active = 1
          AND d.barangay_id IS NOT NULL
          AND (
              (latest_fd.gps_latitude IS NOT NULL AND latest_fd.gps_longitude IS NOT NULL)
              OR (latest_fd.geo_lat IS NOT NULL AND latest_fd.geo_long IS NOT NULL)
              OR (br.latitude IS NOT NULL AND br.longitude IS NOT NULL)
          )
        ORDER BY d.created_at DESC
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $devices = $stmt->fetchAll();
        
        // Use device GPS coordinates if available, otherwise use barangay coordinates
        foreach ($devices as &$device) {
            if (empty($device['latitude']) || empty($device['longitude'])) {
                $device['latitude'] = $device['barangay_lat'];
                $device['longitude'] = $device['barangay_long'];
            }
        }
        
        // Debug: Log the number of devices found
        error_log("Devices with coordinates found: " . count($devices));
        if (count($devices) > 0) {
            error_log("First device: " . json_encode($devices[0]));
        }
        
        return $devices;
    } catch (PDOException $e) {
        error_log("Error fetching devices: " . $e->getMessage());
        return [];
    }
}

// Fetch barangays with fire risk calculations
function getBarangays($forceBarangayId = null) {
    $conn = getDatabaseConnection();
    // Fetch all barangays and calculate fire risk levels
    $query = "
        SELECT 
            br.id,
            br.barangay_name,
            br.latitude,
            br.longitude,
            -- Count total incidents
            COALESCE(incident_counts.total_incidents, 0) as total_incidents,
            -- Count emergency incidents
            COALESCE(incident_counts.emergency_incidents, 0) as emergency_incidents,
            -- Calculate fire risk level (based on incidents)
            CASE 
                WHEN COALESCE(incident_counts.total_incidents, 0) > 0 THEN 'HIGH'
                ELSE 'SAFE'
            END as fire_risk_level
        FROM barangay br
        LEFT JOIN (
            SELECT 
                COALESCE(d.barangay_id, fd.barangay_id) as barangay_id,
                COUNT(*) as total_incidents,
                SUM(CASE WHEN fd.status IN ('EMERGENCY', 'ACKNOWLEDGED') THEN 1 ELSE 0 END) as emergency_incidents
            FROM fire_data fd
            LEFT JOIN devices d ON fd.device_id = d.device_id
            WHERE d.barangay_id IS NOT NULL OR fd.barangay_id IS NOT NULL
            GROUP BY COALESCE(d.barangay_id, fd.barangay_id)
        ) incident_counts ON br.id = incident_counts.barangay_id
        ORDER BY br.barangay_name ASC
    ";
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Error fetching barangays: " . $e->getMessage());
        return [];
    }
}

// Fetch heat data for barangays (latest and overall)
function getBarangayHeatData($barangayId = null) {
    $conn = getDatabaseConnection();
    
    $whereClause = "";
    $params = [];
    
    if ($barangayId) {
        $whereClause = "WHERE br.id = :barangay_id";
        $params[':barangay_id'] = $barangayId;
    }
    
    $query = "
        SELECT 
            br.id as barangay_id,
            br.barangay_name,
            -- Latest heat reading
            COALESCE(latest_heat.latest_heat_value, 0) as latest_heat,
            COALESCE(latest_heat.latest_heat_timestamp, 'N/A') as latest_heat_timestamp,
            -- Overall heat statistics
            COALESCE(heat_stats.avg_heat, 0) as avg_heat,
            COALESCE(heat_stats.max_heat, 0) as max_heat,
            COALESCE(heat_stats.min_heat, 0) as min_heat,
            COALESCE(heat_stats.total_readings, 0) as total_readings,
            -- Monthly heat statistics
            COALESCE(monthly_heat.jan_avg, 0) as jan_avg_heat,
            COALESCE(monthly_heat.feb_avg, 0) as feb_avg_heat,
            COALESCE(monthly_heat.mar_avg, 0) as mar_avg_heat,
            COALESCE(monthly_heat.apr_avg, 0) as apr_avg_heat,
            COALESCE(monthly_heat.may_avg, 0) as may_avg_heat,
            COALESCE(monthly_heat.jun_avg, 0) as jun_avg_heat,
            COALESCE(monthly_heat.jul_avg, 0) as jul_avg_heat,
            COALESCE(monthly_heat.aug_avg, 0) as aug_avg_heat,
            COALESCE(monthly_heat.sep_avg, 0) as sep_avg_heat,
            COALESCE(monthly_heat.oct_avg, 0) as oct_avg_heat,
            COALESCE(monthly_heat.nov_avg, 0) as nov_avg_heat,
            COALESCE(monthly_heat.dec_avg, 0) as dec_avg_heat
        FROM barangay br
        LEFT JOIN (
            SELECT 
                COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id) as barangay_id,
                fd.heat as latest_heat_value,
                fd.timestamp as latest_heat_timestamp,
                ROW_NUMBER() OVER (
                    PARTITION BY COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id) 
                    ORDER BY fd.timestamp DESC
                ) as rn
            FROM fire_data fd
            LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings b ON fd.building_id = b.id
            WHERE d.barangay_id IS NOT NULL 
               OR fd.barangay_id IS NOT NULL 
               OR b.barangay_id IS NOT NULL
        ) latest_heat ON br.id = latest_heat.barangay_id AND latest_heat.rn = 1
        LEFT JOIN (
            SELECT 
                COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id) as barangay_id,
                AVG(fd.heat) as avg_heat,
                MAX(fd.heat) as max_heat,
                MIN(fd.heat) as min_heat,
                COUNT(*) as total_readings
            FROM fire_data fd
            LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings b ON fd.building_id = b.id
            WHERE d.barangay_id IS NOT NULL 
               OR fd.barangay_id IS NOT NULL 
               OR b.barangay_id IS NOT NULL
            GROUP BY COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id)
        ) heat_stats ON br.id = heat_stats.barangay_id
        LEFT JOIN (
            SELECT 
                COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id) as barangay_id,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 1 THEN fd.heat ELSE NULL END) as jan_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 2 THEN fd.heat ELSE NULL END) as feb_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 3 THEN fd.heat ELSE NULL END) as mar_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 4 THEN fd.heat ELSE NULL END) as apr_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 5 THEN fd.heat ELSE NULL END) as may_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 6 THEN fd.heat ELSE NULL END) as jun_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 7 THEN fd.heat ELSE NULL END) as jul_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 8 THEN fd.heat ELSE NULL END) as aug_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 9 THEN fd.heat ELSE NULL END) as sep_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 10 THEN fd.heat ELSE NULL END) as oct_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 11 THEN fd.heat ELSE NULL END) as nov_avg,
                AVG(CASE WHEN MONTH(STR_TO_DATE(fd.timestamp, '%Y-%m-%d %H:%i:%s')) = 12 THEN fd.heat ELSE NULL END) as dec_avg
            FROM fire_data fd
            LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN buildings b ON fd.building_id = b.id
            WHERE (d.barangay_id IS NOT NULL 
                OR fd.barangay_id IS NOT NULL 
                OR b.barangay_id IS NOT NULL)
            AND fd.heat IS NOT NULL 
            AND fd.timestamp IS NOT NULL
            GROUP BY COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id)
        ) monthly_heat ON br.id = monthly_heat.barangay_id
        " . $whereClause . "
        ORDER BY br.barangay_name ASC
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        if ($barangayId) {
            return $stmt->fetch();
        } else {
            return $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching barangay heat data: " . $e->getMessage());
        return $barangayId ? null : [];
    }
}

// Fetch device statistics for barangays
function getBarangayDeviceStats($barangayId = null) {
    $conn = getDatabaseConnection();
    
    $whereClause = "";
    $params = [];
    
    if ($barangayId) {
        $whereClause = "WHERE br.id = :barangay_id";
        $params[':barangay_id'] = $barangayId;
    }
    
    $query = "
        SELECT 
            br.id as barangay_id,
            br.barangay_name,
            -- Device counts by status
            COALESCE(device_stats.total_devices, 0) as total_devices,
            COALESCE(device_stats.online_devices, 0) as online_devices,
            COALESCE(device_stats.offline_devices, 0) as offline_devices,
            COALESCE(device_stats.faulty_devices, 0) as faulty_devices,
            COALESCE(device_stats.active_devices, 0) as active_devices,
            -- Device activity
            COALESCE(device_stats.devices_with_recent_activity, 0) as devices_with_recent_activity,
            COALESCE(device_stats.devices_without_activity, 0) as devices_without_activity,
            -- Latest device activity timestamp
            device_stats.latest_device_activity
        FROM barangay br
        LEFT JOIN (
            SELECT 
                COALESCE(d.barangay_id, fd.barangay_id) as barangay_id,
                COUNT(DISTINCT d.device_id) as total_devices,
                SUM(CASE WHEN d.status = 'online' THEN 1 ELSE 0 END) as online_devices,
                SUM(CASE WHEN d.status = 'offline' THEN 1 ELSE 0 END) as offline_devices,
                SUM(CASE WHEN d.status = 'faulty' THEN 1 ELSE 0 END) as faulty_devices,
                SUM(CASE WHEN d.is_active = 1 THEN 1 ELSE 0 END) as active_devices,
                SUM(CASE WHEN d.last_activity IS NOT NULL AND d.last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as devices_with_recent_activity,
                SUM(CASE WHEN d.last_activity IS NULL OR d.last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as devices_without_activity,
                MAX(d.last_activity) as latest_device_activity
            FROM devices d
            LEFT JOIN fire_data fd ON d.latest_fire_data_id = fd.id
            WHERE d.barangay_id IS NOT NULL 
               OR fd.barangay_id IS NOT NULL
            GROUP BY COALESCE(d.barangay_id, fd.barangay_id)
        ) device_stats ON br.id = device_stats.barangay_id
        " . $whereClause . "
        ORDER BY br.barangay_name ASC
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        if ($barangayId) {
            return $stmt->fetch();
        } else {
            return $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching barangay device stats: " . $e->getMessage());
        return $barangayId ? null : [];
    }
}

// Fetch ML analytics for barangays
function getBarangayMLAnalytics($barangayId = null) {
    $conn = getDatabaseConnection();
    
    $whereClause = "";
    $params = [];
    
    if ($barangayId) {
        $whereClause = "WHERE br.id = :barangay_id";
        $params[':barangay_id'] = $barangayId;
    }
    
    $query = "
        SELECT 
            br.id as barangay_id,
            br.barangay_name,
            -- ML prediction counts
            COALESCE(ml_stats.total_predictions, 0) as total_predictions,
            COALESCE(ml_stats.fire_predictions, 0) as fire_predictions,
            COALESCE(ml_stats.no_fire_predictions, 0) as no_fire_predictions,
            -- ML confidence statistics
            COALESCE(ml_stats.avg_confidence, 0) as avg_confidence,
            COALESCE(ml_stats.max_confidence, 0) as max_confidence,
            COALESCE(ml_stats.min_confidence, 0) as min_confidence,
            -- High confidence predictions
            COALESCE(ml_stats.high_confidence_predictions, 0) as high_confidence_predictions,
            COALESCE(ml_stats.medium_confidence_predictions, 0) as medium_confidence_predictions,
            COALESCE(ml_stats.low_confidence_predictions, 0) as low_confidence_predictions,
            -- Latest ML prediction
            ml_stats.latest_ml_timestamp,
            ml_stats.latest_ml_prediction,
            ml_stats.latest_ml_confidence
        FROM barangay br
        LEFT JOIN (
            SELECT 
                COALESCE(d.barangay_id, fd.barangay_id, b.barangay_id) as barangay_id,
                COUNT(*) as total_predictions,
                SUM(CASE WHEN fd.ml_prediction = 1 THEN 1 ELSE 0 END) as fire_predictions,
                SUM(CASE WHEN fd.ml_prediction = 0 THEN 1 ELSE 0 END) as no_fire_predictions,
                AVG(fd.ml_confidence) as avg_confidence,
                MAX(fd.ml_confidence) as max_confidence,
                MIN(fd.ml_confidence) as min_confidence,
                SUM(CASE WHEN fd.ml_confidence >= 80 THEN 1 ELSE 0 END) as high_confidence_predictions,
                SUM(CASE WHEN fd.ml_confidence >= 50 AND fd.ml_confidence < 80 THEN 1 ELSE 0 END) as medium_confidence_predictions,
                SUM(CASE WHEN fd.ml_confidence < 50 THEN 1 ELSE 0 END) as low_confidence_predictions,
                MAX(fd.ml_timestamp) as latest_ml_timestamp,
                (SELECT fd2.ml_prediction FROM fire_data fd2 
                 LEFT JOIN devices d2 ON fd2.device_id = d2.device_id
                 WHERE COALESCE(d2.barangay_id, fd2.barangay_id) = COALESCE(d.barangay_id, fd.barangay_id) 
                 AND fd2.ml_timestamp IS NOT NULL 
                 ORDER BY fd2.ml_timestamp DESC LIMIT 1) as latest_ml_prediction,
                (SELECT fd2.ml_confidence FROM fire_data fd2 
                 LEFT JOIN devices d2 ON fd2.device_id = d2.device_id
                 WHERE COALESCE(d2.barangay_id, fd2.barangay_id) = COALESCE(d.barangay_id, fd.barangay_id) 
                 AND fd2.ml_timestamp IS NOT NULL 
                 ORDER BY fd2.ml_timestamp DESC LIMIT 1) as latest_ml_confidence
            FROM fire_data fd
            LEFT JOIN devices d ON fd.device_id = d.device_id
            WHERE d.barangay_id IS NOT NULL 
               OR fd.barangay_id IS NOT NULL
            AND fd.ml_timestamp IS NOT NULL
            GROUP BY COALESCE(d.barangay_id, fd.barangay_id)
        ) ml_stats ON br.id = ml_stats.barangay_id
        " . $whereClause . "
        ORDER BY br.barangay_name ASC
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        if ($barangayId) {
            return $stmt->fetch();
        } else {
            return $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching barangay ML analytics: " . $e->getMessage());
        return $barangayId ? null : [];
    }
}

// Fetch building safety features statistics for barangays
function getBarangaySafetyStats($barangayId = null) {
    $conn = getDatabaseConnection();
    
    $whereClause = "";
    $params = [];
    
    if ($barangayId) {
        $whereClause = "WHERE br.id = :barangay_id";
        $params[':barangay_id'] = $barangayId;
    }
    
    $query = "
        SELECT 
            br.id as barangay_id,
            br.barangay_name,
            -- Building counts
            COALESCE(safety_stats.total_buildings, 0) as total_buildings,
            -- Safety features counts
            COALESCE(safety_stats.buildings_with_sprinklers, 0) as buildings_with_sprinklers,
            COALESCE(safety_stats.buildings_with_fire_alarms, 0) as buildings_with_fire_alarms,
            COALESCE(safety_stats.buildings_with_extinguishers, 0) as buildings_with_extinguishers,
            COALESCE(safety_stats.buildings_with_emergency_exits, 0) as buildings_with_emergency_exits,
            COALESCE(safety_stats.buildings_with_emergency_lighting, 0) as buildings_with_emergency_lighting,
            COALESCE(safety_stats.buildings_with_fire_escape, 0) as buildings_with_fire_escape,
            -- Safety compliance
            COALESCE(safety_stats.fully_compliant_buildings, 0) as fully_compliant_buildings,
            COALESCE(safety_stats.partially_compliant_buildings, 0) as partially_compliant_buildings,
            COALESCE(safety_stats.non_compliant_buildings, 0) as non_compliant_buildings,
            -- Inspection status
            COALESCE(safety_stats.recently_inspected, 0) as recently_inspected,
            COALESCE(safety_stats.overdue_inspection, 0) as overdue_inspection,
            COALESCE(safety_stats.never_inspected, 0) as never_inspected
        FROM barangay br
        LEFT JOIN (
            SELECT 
                barangay_id,
                COUNT(*) as total_buildings,
                SUM(has_sprinkler_system) as buildings_with_sprinklers,
                SUM(has_fire_alarm) as buildings_with_fire_alarms,
                SUM(has_fire_extinguishers) as buildings_with_extinguishers,
                SUM(has_emergency_exits) as buildings_with_emergency_exits,
                SUM(has_emergency_lighting) as buildings_with_emergency_lighting,
                SUM(has_fire_escape) as buildings_with_fire_escape,
                SUM(CASE WHEN has_sprinkler_system = 1 AND has_fire_alarm = 1 AND has_fire_extinguishers = 1 
                         AND has_emergency_exits = 1 AND has_emergency_lighting = 1 AND has_fire_escape = 1 
                         THEN 1 ELSE 0 END) as fully_compliant_buildings,
                SUM(CASE WHEN (has_sprinkler_system + has_fire_alarm + has_fire_extinguishers + 
                              has_emergency_exits + has_emergency_lighting + has_fire_escape) >= 3 
                         AND (has_sprinkler_system + has_fire_alarm + has_fire_extinguishers + 
                              has_emergency_exits + has_emergency_lighting + has_fire_escape) < 6 
                         THEN 1 ELSE 0 END) as partially_compliant_buildings,
                SUM(CASE WHEN (has_sprinkler_system + has_fire_alarm + has_fire_extinguishers + 
                              has_emergency_exits + has_emergency_lighting + has_fire_escape) < 3 
                         THEN 1 ELSE 0 END) as non_compliant_buildings,
                SUM(CASE WHEN last_inspected IS NOT NULL AND last_inspected >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) as recently_inspected,
                SUM(CASE WHEN last_inspected IS NOT NULL AND last_inspected < DATE_SUB(CURDATE(), INTERVAL 6 MONTH) THEN 1 ELSE 0 END) as overdue_inspection,
                SUM(CASE WHEN last_inspected IS NULL THEN 1 ELSE 0 END) as never_inspected
            FROM buildings
            WHERE barangay_id IS NOT NULL
            GROUP BY barangay_id
        ) safety_stats ON br.id = safety_stats.barangay_id
        " . $whereClause . "
        ORDER BY br.barangay_name ASC
    ";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        
        if ($barangayId) {
            return $stmt->fetch();
        } else {
            return $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Error fetching barangay safety stats: " . $e->getMessage());
        return $barangayId ? null : [];
    }
}

// Removed: getBuildingStats function - buildings are no longer used

// Get devices data
$devices = getDevicesWithCoordinates();
// Get incidents and barangays with optional date filtering
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
$incidents = getFireData($startDate, $endDate);
$preselectBarangayId = null;
if (isset($_GET['barangay_id']) && ctype_digit((string)$_GET['barangay_id'])) {
    $preselectBarangayId = (int)$_GET['barangay_id'];
} elseif (isset($_GET['user_id']) && ctype_digit((string)$_GET['user_id'])) {
    $preselectBarangayId = getUserBarangayId((int)$_GET['user_id']);
}
$barangays = getBarangays($preselectBarangayId);
// Get comprehensive data for all barangays
$barangayHeatData = getBarangayHeatData();
$barangayDeviceStats = getBarangayDeviceStats();
$barangaySafetyStats = getBarangaySafetyStats();

// Optionally detect user's barangay for preselection
function getUserBarangayId($userId) {
    if (empty($userId)) { return null; }
    $conn = getDatabaseConnection();
    // Try from devices table
    $sql = "SELECT barangay_id FROM devices WHERE user_id = :uid AND barangay_id IS NOT NULL ORDER BY created_at DESC LIMIT 1";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $row = $stmt->fetch();
        if ($row && !empty($row['barangay_id'])) { return (int)$row['barangay_id']; }
    } catch (PDOException $e) {
        error_log('Error fetching user barangay: ' . $e->getMessage());
    }
    // Fallback: try from latest fire_data -> devices
    $sql2 = "
        SELECT COALESCE(d.barangay_id, fd.barangay_id) as barangay_id
        FROM fire_data fd
        LEFT JOIN devices d ON fd.device_id = d.device_id
        WHERE fd.user_id = :uid AND COALESCE(d.barangay_id, fd.barangay_id) IS NOT NULL
        ORDER BY fd.id DESC
        LIMIT 1
    ";
    try {
        $stmt2 = $conn->prepare($sql2);
        $stmt2->execute([':uid' => $userId]);
        $row2 = $stmt2->fetch();
        if ($row2 && !empty($row2['barangay_id'])) { return (int)$row2['barangay_id']; }
    } catch (PDOException $e) {
        error_log('Error fetching user barangay fallback: ' . $e->getMessage());
    }
    return null;
}

// $preselectBarangayId computed above before fetching barangays
?>

<?php include '../../components/header.php'; ?>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" 
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" 
            crossorigin=""></script>
    
    <!-- html2canvas for screenshot functionality -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    
    <!-- FontAwesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
       
        .map-section {
            border-radius: 0px;
            padding: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #f0f0f0;
        }
        
        .section-header {
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .section-title-v2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1a1a1a;
            margin: 0 0 8px 0;
            letter-spacing: -0.025em;
            line-height: 1.2;
        }
        
        .section-subtitle {
            font-size: 0.95rem;
            color: #6b7280;
            margin: 0;
            font-weight: 500;
            opacity: 0.9;
        }
        
        .controls {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;       
        }
        .controls .controls-left { display: flex; align-items: center; }
        .controls .filters-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        
        .controls input[type="text"],
        .controls select {
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid #e8e8e8;
            border-radius: 8px;
            min-width: 200px;
            background: #ffffff;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .controls input[type="text"]:focus,
        .controls select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .controls button {
            padding: 12px 20px;
            background: #667eea;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s ease;
            font-family: inherit;
        }
        
        .controls button:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .controls button.secondary {
            background: #718096;
        }
        
        .controls button.secondary:hover {
            background: #4a5568;
        }
        
        .controls label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
            cursor: pointer;
        }
        
        .map-container {
            width: 100%;
            height: 100vh;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid #e8e8e8;
           
        }
        
        #map {
            width: 100%;
            height: 100%;
        }
        
        
        .section-title-v2 {
            font-size: 2rem;
            font-weight: 900;
            color: #1a1a1a;
        }
        
        .safety-features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 12px;
        }
        
        .safety-item {
            display: flex;
            align-items: center;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .safety-icon {
            margin-right: 8px;
            font-size: 1rem;
            font-weight: 700;
        }
        
        .has-feature {
            color: #38a169;
        }
        
        .no-feature {
            color: #e53e3e;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .stat-item {
            text-align: center;
            padding: 16px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e8e8e8;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .incident-item {
            background: #ffffff;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: all 0.2s ease;
        }
        
        .incident-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            transform: translateY(-1px);
        }
        
        .incident-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-emergency {
            background: #fed7d7;
            color: #c53030;
        }
        
        .status-acknowledged {
            background: #fef5e7;
            color: #dd6b20;
        }
        
        .status-normal {
            background: #e6fffa;
            color: #319795;
        }
        
        .incident-time {
            font-size: 0.75rem;
            color: #718096;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .incident-details {
            font-size: 0.875rem;
            color: #4a5568;
        }
        
        .incident-details p {
            margin-bottom: 4px;
        }
        
        .device-item {
            background: #ffffff;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
        }
        
        .device-name {
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 4px;
            font-size: 0.875rem;
        }
        
        .device-details {
            font-size: 0.75rem;
            color: #718096;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-active {
            background: #c6f6d5;
            color: #22543d;
        }
        
        .status-inactive {
            background: #fed7d7;
            color: #742a2a;
        }
        
        .no-history {
            text-align: center;
            color: #718096;
            font-style: italic;
            padding: 32px;
            font-size: 0.875rem;
        }
        
        .info-window {
            max-width: 300px;
        }
        
        .info-window h3 {
            margin-top: 0;
            color: #1a1a1a;
            font-weight: 700;
        }
        
        .info-window h4 {
            color: #1a1a1a;
            margin: 12px 0 6px 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .info-window hr {
            margin: 12px 0;
            border: none;
            border-top: 1px solid #e8e8e8;
        }
        
        .info-window p {
            margin: 6px 0;
            line-height: 1.5;
            font-size: 0.875rem;
        }
        
        .info-window .brgy-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #1a1a1a;
            margin: 0 0 8px 0;
        }
        
        .enhanced-barangay-circle {
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
            transition: all 0.3s ease;
        }
        
        .enhanced-barangay-circle:hover {
            filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.3));
        }
        
        /* Removed animations for permanent circle display */
        
        .detailed-barangay-popup .leaflet-popup-content-wrapper {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid #e8e8e8;
        }
        
        .detailed-barangay-popup .leaflet-popup-content {
            margin: 0;
            padding: 0;
        }
        
        .detailed-barangay-popup .leaflet-popup-tip {
            background: #ffffff;
            border: 1px solid #e8e8e8;
        }
        
        /* Hover popup styling */
        .barangay-hover-popup .leaflet-popup-content-wrapper {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
        }
        
        .barangay-hover-popup .leaflet-popup-content {
            margin: 0;
            padding: 0;
        }
        
        .barangay-hover-popup .leaflet-popup-tip {
            display: none;
        }
        
        /* Circle styles removed as requested */
        
        /* Permanent device labels styling - readable and static */
        .device-label {
            background: rgba(255, 255, 255, 0.98);
            border: 2px solid #333;
            border-radius: 6px;
            padding: 5px 8px;
            font-size: 11px;
            font-weight: bold;
            color: #333;
            text-align: center;
            white-space: nowrap;
            box-shadow: 0 3px 6px rgba(0, 0, 0, 0.4);
            pointer-events: none;
            z-index: 500; /* Lower z-index so device icons appear on top */
            transform: translate(-50%, -100%);
            margin-top: -80px; /* Move label much further up to prevent overlap */
            min-width: 110px;
            line-height: 1.3;
        }
        
        .device-label.has-incidents {
            border-color: #dc2626;
            color: #dc2626;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 3px 8px rgba(220, 38, 38, 0.3);
        }
        
        .device-label.no-incidents {
            border-color: #10b981;
            color: #10b981;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 3px 8px rgba(16, 185, 129, 0.3);
        }
        
        /* Device icon hover tooltip with blue background */
        .device-hover-tooltip,
        .leaflet-tooltip.device-hover-tooltip {
            background-color: #3b82f6 !important;
            border: 2px solid #2563eb !important;
            color: white !important;
            font-weight: 600;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
        }
        
        .device-hover-tooltip::before,
        .leaflet-tooltip.device-hover-tooltip::before {
            border-top-color: #3b82f6 !important;
        }
        
        .legend {
            display: inline-flex;
            gap: 16px;
            align-items: center;
            font-size: 13px;
            color: #4a5568;
            margin-left: 16px;
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            border: 1px solid #e8e8e8;
            backdrop-filter: blur(10px);
        }
        
        .legend .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid #fff;
            box-shadow: 0 0 4px rgba(0,0,0,0.2);
        }
        
        /* Burger Menu Styles */
        .burger-menu {
            position: absolute;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: all 0.3s ease;
        }
        
        .burger-menu:hover {
            background: rgba(255, 255, 255, 1);
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0,0,0,0.4);
        }
        
        .burger-icon {
            width: 24px;
            height: 18px;
            position: relative;
            transform: rotate(0deg);
            transition: 0.3s ease-in-out;
        }
        
        .burger-icon span {
            display: block;
            position: absolute;
            height: 3px;
            width: 100%;
            background: #333;
            border-radius: 2px;
            opacity: 1;
            left: 0;
            transform: rotate(0deg);
            transition: 0.25s ease-in-out;
        }
        
        .burger-icon span:nth-child(1) {
            top: 0px;
        }
        
        .burger-icon span:nth-child(2) {
            top: 7px;
        }
        
        .burger-icon span:nth-child(3) {
            top: 14px;
        }
        
        .burger-icon.active span:nth-child(1) {
            top: 7px;
            transform: rotate(135deg);
        }
        
        .burger-icon.active span:nth-child(2) {
            opacity: 0;
            left: -60px;
        }
        
        .burger-icon.active span:nth-child(3) {
            top: 7px;
            transform: rotate(-135deg);
        }
        
        .legend-content {
            display: none;
            position: absolute;
            bottom: 80px;
            right: 20px;
            z-index: 999;
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            font-size: 16px;
            line-height: 1.6;
            min-width: 280px;
            animation: slideIn 0.3s ease-out;
        }
        
        .legend-content.show {
            display: block;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Date and Reset Controls */
        .map-date-controls {
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1000;
            background: white;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 240px;
        }
        
        .date-controls-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date-input-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .date-input-group label {
            font-size: 10px;
            color: #666;
            font-weight: 600;
            margin: 0;
        }
        
        .date-input-group input[type="date"] {
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            background: white;
            min-width: 100px;
        }
        
        .reset-btn {
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .reset-btn:hover {
            background: #5a6268;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
        }
        
        .reset-btn i {
            font-size: 14px;
        }
        
        .dot-building { background-color:rgb(120, 234, 102); }
        .dot-incident { background-color: #ef4444; }
        
        @media (max-width: 1200px) {
            .map-container {
                height: 75vh;
            }
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 16px;
            }
            
            .header {
                padding: 24px;
            }
            
            .header h1 {
                font-size: 1.5rem;
            }
            
            .map-container {
                height: 70vh;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .controls input[type="text"],
            .controls select {
                min-width: auto;
                width: 100%;
            }
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 12px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .info-value {
            font-size: 1rem;
            font-weight: 500;
            color: #1a1a1a;
            word-break: break-word;
        }
        
        #monthTitle {
            font-size: 1rem;
            font-weight: 700;
            text-align: left;
            margin: 2x 0 2x;
            letter-spacing: 0.5px;
            font-style: serif;
        }
        @media (max-width: 900px) {
            #incidentList, #deviceList { grid-template-columns: 1fr; }
        }

        /* Maps Navigation Styles */
        .maps-navigation {
            position: fixed;
            top: 50%;
            left: 20px;
            transform: translateY(-50%);
            z-index: 9999;
            background: rgba(255, 0, 0, 0.1); /* Temporary debug background */
        }


        .maps-link-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .maps-link {
            background: #ffffff;
            color: #1a1a1a;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
        }

        .maps-link:hover {
            background: #16a34a;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.3);
        }

        .maps-buttons {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .redirect-btn, .screenshot-btn, .reset-btn {
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            padding: 0;
            aspect-ratio: 1 / 1;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
            font-size: 18px;
            color: #ffffff; /* icon color */
        }

        /* Individual button colors */
        .redirect-btn { background: #10b981; }
        .screenshot-btn { background: #f59e0b; }
        .reset-btn { background: #16a34a; }

        .redirect-btn:hover { background: #059669; transform: translateY(-2px) scale(1.05); }
        .screenshot-btn:hover { background: #d97706; transform: translateY(-2px) scale(1.05); }
        .reset-btn:hover { background: #16a34a; transform: translateY(-2px) scale(1.05); }

        .screenshot-btn:hover {
            background: #f59e0b;
            color: white;
            border-color: #f59e0b;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }

        /* Hide the precise barangay circle that appears on click */
        .precise-barangay-circle {
            display: none !important;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .maps-navigation {
                top: 50%;
                left: 15px;
                transform: translateY(-50%);
            }
            
            .maps-link {
                padding: 10px 16px;
                font-size: 13px;
                min-width: 100px;
            }
            
            .redirect-btn, .screenshot-btn, .reset-btn {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }
        
        /* Safe building icon styling */
        .safe-building-icon {
            filter: brightness(1.2) saturate(0.8);
            border: 2px solid #22c55e !important;
            border-radius: 50%;
            box-shadow: 0 0 8px rgba(34, 197, 94, 0.4);
        }
    </style>
</head>
<?php include('../../components/header.php'); ?>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
                
                    <div class="card" style="background:#ffffff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,0.06);padding:16px;">
                    <div class="header-row" style="display:flex;align-items:center;justify-content:space-between;padding:16px;border-radius:8px;margin-bottom:12px;">
                        <div class="controls-left">
                            <p id="monthTitle" style="margin:0;font-size:20px;font-weight:800;letter-spacing:0.2px;color:#111111;">ALL FIRE INCIDENTS IN BAGO CITY</p>
                        </div>
                        <div class="right-controls" style="display:none;gap:12px;align-items:end;">
                            <button id="resetBtn" type="button" class="btn btn-outline-danger d-inline-flex align-items-center" style="font-weight:600;display:none;"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
                        </div>
                    </div>
                    <div class="filters-row">
                        <input type="text" id="searchInput" placeholder="Search by building name or address" style="display:none;" />
                        <select id="typeFilter" style="display:none;">
                            <option value="">All building types</option>
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="industrial">Industrial</option>
                            <option value="institution">Institution</option>
                        </select>
                        <select id="barangayFilter" style="display:none;">
                            <option value="">All barangays (fire risk)</option>
                            <option value="HIGH">High Risk Barangays</option>
                            <option value="MEDIUM">Medium Risk Barangays</option>
                            <option value="LOW">Low Risk Barangays</option>
                            <option value="SAFE">Safe Barangays</option>
                            <?php foreach ($barangays as $br): ?>
                                <option value="<?php echo (int)$br['id']; ?>"><?php echo htmlspecialchars($br['barangay_name']); ?> (<?php echo $br['fire_risk_level']; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <div style="display:none; align-items:center; gap:8px;" class="date-filter-group">
                            <label for="startDate" style="font-size: 12px; color: #666; margin-right: 5px;">From:</label>
                            <input type="date" id="startDate" style="padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;" />
                            <label for="endDate" style="font-size: 12px; color: #666; margin-left: 10px; margin-right: 5px;">To:</label>
                            <input type="date" id="endDate" style="padding: 6px 8px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;" />
                            <button type="button" onclick="testDateFilter()" style="padding: 6px 12px; background: #007bff; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">Test Filter</button>
                        </div>
                        <label style="display:none;"><input type="checkbox" id="toggleIncidents" checked />Show fire incidents</label>
                        <label style="display:none;"><input type="checkbox" id="toggleBarangays" checked />Show barangays</label>
                  
                
                    <div class="card-body p-0">
                        
                        <div class="map-container">
                            <div id="map"></div>
                        </div>
                    </div>
                    </div>
                </div>
           
                        
        </div>
    </div>


    <script>
        // Initialize the map
        var map = L.map('map').setView([14.5995, 120.9842], 13); // Default to Manila coordinates
        
        // Add OpenStreetMap tiles
        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        });
        
        // Add Satellite layer (ESRI World Imagery)
        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; <a href="https://www.esri.com/">Esri</a> &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });
        
        // Add Hybrid satellite layer (ESRI World Imagery with labels)
        const hybridLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; <a href="https://www.esri.com/">Esri</a> &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });
        
        // Add OpenTopoMap for terrain view
        const topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://opentopomap.org/">OpenTopoMap</a> contributors'
        });
        
        // Function to update title based on date filter
        function updateTitleBasedOnDateFilter(startDate, endDate) {
            const monthTitleElement = document.getElementById('monthTitle');
            if (!monthTitleElement) return;
            
            if (!startDate && !endDate) {
                // No date filter - show current month
                setCurrentMonthTitle();
                return;
            }
            
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            
            if (startDate && endDate) {
                // Both dates provided - show date range
                const start = new Date(startDate);
                const end = new Date(endDate);
                
                if (start.getMonth() === end.getMonth() && start.getFullYear() === end.getFullYear()) {
                    // Same month and year
                    const monthName = monthNames[start.getMonth()];
                    const year = start.getFullYear();
                    monthTitleElement.textContent = `ALL FIRE INCIDENTS IN BAGO CITY - ${monthName} ${year}`;
                } else if (start.getFullYear() === end.getFullYear()) {
                    // Same year, different months
                    const startMonth = monthNames[start.getMonth()];
                    const endMonth = monthNames[end.getMonth()];
                    const year = start.getFullYear();
                    monthTitleElement.textContent = `ALL FIRE INCIDENTS IN BAGO CITY - ${startMonth} to ${endMonth} ${year}`;
                } else {
                    // Different years
                    const startMonth = monthNames[start.getMonth()];
                    const endMonth = monthNames[end.getMonth()];
                    const startYear = start.getFullYear();
                    const endYear = end.getFullYear();
                    monthTitleElement.textContent = `ALL FIRE INCIDENTS IN BAGO CITY - ${startMonth} ${startYear} to ${endMonth} ${endYear}`;
                }
            } else if (startDate) {
                // Only start date provided
                const start = new Date(startDate);
                const monthName = monthNames[start.getMonth()];
                const year = start.getFullYear();
                monthTitleElement.textContent = `ALL FIRE INCIDENTS IN BAGO CITY - From ${monthName} ${year}`;
            } else if (endDate) {
                // Only end date provided
                const end = new Date(endDate);
                const monthName = monthNames[end.getMonth()];
                const year = end.getFullYear();
                monthTitleElement.textContent = `ALL FIRE INCIDENTS IN BAGO CITY - Until ${monthName} ${year}`;
            }
        }

        // Function to set current month as default title
        function setCurrentMonthTitle() {
            const now = new Date();
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            const currentMonth = monthNames[now.getMonth()];
            const currentYear = now.getFullYear();
            
            const monthTitleElement = document.getElementById('monthTitle');
            if (monthTitleElement) {
                monthTitleElement.textContent = `ALL FIRE INCIDENTS IN BAGO CITY - ${currentMonth} ${currentYear}`;
            }
        }

        // Set current month as default when page loads
        setCurrentMonthTitle();

        // Add layer control
        const baseLayers = {
            "OpenStreetMap": osmLayer,
            "Satellite": satelliteLayer,
            "Hybrid": hybridLayer,
            "Terrain": topoLayer
        };
        
        const layerControl = L.control.layers(baseLayers).addTo(map);
        
        // Add default layer
        osmLayer.addTo(map);
        
        // Add burger menu legend to the map container
        var mapContainer = document.querySelector('.map-container');
        var burgerMenu = document.createElement('div');
        burgerMenu.className = 'burger-menu';
        burgerMenu.innerHTML = 
            '<div class="burger-icon">' +
                '<span></span>' +
                '<span></span>' +
                '<span></span>' +
            '</div>';
        mapContainer.appendChild(burgerMenu);
        
        // Add legend content to the map container
        var legendContent = document.createElement('div');
        legendContent.className = 'legend-content';
        legendContent.innerHTML = 
                '<div style="font-weight: bold; margin-bottom: 15px; color: #333; font-size: 18px; text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px;">Map Legend</div>' +
                '<div style="display: flex; align-items: center; margin-bottom: 12px; font-size: 16px;"><span style="width: 20px; height: 20px; background: #dc2626; border: 3px solid #fff; border-radius: 50%; display: inline-block; margin-right: 12px; box-shadow: 0 0 8px rgba(220,38,38,0.5);"></span><strong>Barangays with Fire Incidents</strong></div>' +
                '<div style="display: flex; align-items: center; margin-bottom: 12px; font-size: 16px;"><span style="width: 20px; height: 20px; background: #10b981; border: 3px solid #fff; border-radius: 50%; display: inline-block; margin-right: 12px; box-shadow: 0 0 8px rgba(16,185,129,0.5);"></span><strong>Barangays with No Fire Incidents</strong></div>' +
                '<div style="margin-top: 12px; padding-top: 8px; border-top: 1px solid #ccc;">' +
                '<div style="font-weight: bold; margin-bottom: 6px; color: #333; font-size: 13px; text-align: center;">Risk Levels</div>' +
                '<div style="display: flex; align-items: center; margin-bottom: 4px; font-size: 12px;"><span style="width: 12px; height: 12px; background: #dc2626; border: 2px solid #fff; border-radius: 50%; display: inline-block; margin-right: 6px; box-shadow: 0 0 4px rgba(220,38,38,0.5);"></span><strong>High Risk</strong></div>' +
                '<div style="display: flex; align-items: center; margin-bottom: 4px; font-size: 12px;"><span style="width: 12px; height: 12px; background: #f97316; border: 2px solid #fff; border-radius: 50%; display: inline-block; margin-right: 6px; box-shadow: 0 0 4px rgba(249,115,22,0.5);"></span><strong>Medium Risk</strong></div>' +
                '<div style="display: flex; align-items: center; margin-bottom: 4px; font-size: 12px;"><span style="width: 12px; height: 12px; background: #10b981; border: 2px solid #fff; border-radius: 50%; display: inline-block; margin-right: 6px; box-shadow: 0 0 4px rgba(16,185,129,0.5);"></span><strong>Safe</strong></div>' +
                '</div>';
        mapContainer.appendChild(legendContent);
        
        // Add date and reset controls to the map container
        var dateControls = document.createElement('div');
        dateControls.className = 'map-date-controls';
        dateControls.innerHTML = 
            '<div class="date-controls-row">' +
                '<div class="date-input-group">' +
                    '<label for="mapStartDate">From:</label>' +
                    '<input type="date" id="mapStartDate" />' +
                '</div>' +
                '<div class="date-input-group">' +
                    '<label for="mapEndDate">To:</label>' +
                    '<input type="date" id="mapEndDate" />' +
                '</div>' +
                '<button type="button" class="reset-btn" id="mapResetBtn">' +
                    '<i class="bi bi-arrow-counterclockwise"></i>' +
                '</button>' +
            '</div>';
        mapContainer.appendChild(dateControls);
        
        // Add click event listener to burger menu
        burgerMenu.addEventListener('click', function() {
            var burgerIcon = burgerMenu.querySelector('.burger-icon');
            var isActive = burgerIcon.classList.contains('active');
            
            if (isActive) {
                // Hide legend
                legendContent.classList.remove('show');
                burgerIcon.classList.remove('active');
            } else {
                // Show legend
                legendContent.classList.add('show');
                burgerIcon.classList.add('active');
            }
        });
        
        // Close legend when clicking outside
        mapContainer.addEventListener('click', function(event) {
            if (!burgerMenu.contains(event.target) && !legendContent.contains(event.target)) {
                legendContent.classList.remove('show');
                burgerMenu.querySelector('.burger-icon').classList.remove('active');
            }
        });
        
        // Sync map date controls with original date inputs
        function syncDateInputs() {
            var mapStartDate = document.getElementById('mapStartDate');
            var mapEndDate = document.getElementById('mapEndDate');
            var originalStartDate = document.getElementById('startDate');
            var originalEndDate = document.getElementById('endDate');
            
            if (mapStartDate && originalStartDate) {
                mapStartDate.value = originalStartDate.value;
            }
            if (mapEndDate && originalEndDate) {
                mapEndDate.value = originalEndDate.value;
            }
        }
        
        // Add event listeners for map date controls
        document.getElementById('mapStartDate').addEventListener('change', function() {
            var originalStartDate = document.getElementById('startDate');
            if (originalStartDate) {
                originalStartDate.value = this.value;
                applyFilters();
            }
        });
        
        document.getElementById('mapEndDate').addEventListener('change', function() {
            var originalEndDate = document.getElementById('endDate');
            if (originalEndDate) {
                originalEndDate.value = this.value;
                applyFilters();
            }
        });
        
        // Add event listener for map reset button
        document.getElementById('mapResetBtn').addEventListener('click', function() {
            // Reset original controls
            document.getElementById('searchInput').value = '';
            document.getElementById('typeFilter').value = '';
            document.getElementById('barangayFilter').value = '';
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            var toggleIncidents = document.getElementById('toggleIncidents');
            if (toggleIncidents) toggleIncidents.checked = true;
            var toggleBuildings = document.getElementById('toggleBuildings');
            if (toggleBuildings) toggleBuildings.checked = false;
            var toggleBarangays = document.getElementById('toggleBarangays');
            if (toggleBarangays) toggleBarangays.checked = true;
            
            // Reset map controls
            document.getElementById('mapStartDate').value = '';
            document.getElementById('mapEndDate').value = '';
            
            // Apply filters and reset map state
            applyFilters();
            if (selectedBarangayCircle) { 
                map.removeLayer(selectedBarangayCircle); 
                selectedBarangayCircle = null; 
            }
            hideBarangayHoverInfo();
            toggleAllBarangays(true);
        });
        
        // Initial sync
        syncDateInputs();
        

        // Barangay icons
        var BrgySafeIcon = L.icon({
            iconUrl: '../images/green.png',
            iconSize: [60, 60],
            iconAnchor: [36, 36], // center of icon
            popupAnchor: [0, -40]
        });
        var BrgyAlertIcon = L.icon({
            iconUrl: '../images/medium.png',
            iconSize: [60, 60],
            iconAnchor: [36, 36], // center of icon
            popupAnchor: [0, -40]
        });
        var BrgyRiskIcon = L.icon({
            iconUrl: '../images/risk.png',
            iconSize: [60, 60],
            iconAnchor: [36, 36], // center of icon
            popupAnchor: [0, -40]
        });
        
        var allMarkers = [];
        var barangayMarkers = [];
        var incidentMarkers = [];
        var selectedBarangayCircle = null;
        var barangayCircleColor = '#dc2626'; // red
        var allBarangayLayers = []; // circles + labels
        
        // Device lookup map: device_id -> marker object
        var deviceMarkersMap = {};
        
        // Device icons
        var DeviceNormalIcon = L.icon({ 
            iconUrl: '../images/device_icon.png', 
            iconSize: [40, 40], 
            iconAnchor: [20, 40], 
            popupAnchor: [0, -34] 
        });
        var DeviceEmergencyIcon = L.icon({ 
            iconUrl: '../images/bluefire.png', 
            iconSize: [50, 50], 
            iconAnchor: [25, 50], 
            popupAnchor: [0, -44] 
        });
        
        // Debug: Log devices data
        console.log('Devices data from PHP:', <?php echo json_encode($devices); ?>);
        console.log('Number of devices:', <?php echo count($devices); ?>);
        
        <?php if (empty($devices)): ?>
            console.warn('No devices with coordinates found!');
            // Show a notification to the user
            setTimeout(function() {
                alert('No devices with coordinates found in the database. Please check that devices have been registered with valid GPS coordinates.');
            }, 1000);
        <?php endif; ?>
        
        // Add markers for all devices with status-specific icon
        <?php foreach ($devices as $d): ?>
            <?php if (!empty($d['latitude']) && !empty($d['longitude'])): ?>
                var deviceStatus = <?php echo json_encode($d['fire_data_status'] ?? ''); ?>;
                var hasEmergency = <?php echo json_encode($d['has_emergency_status'] ?? 0); ?> == 1;
                // Use Emergency icon if device has Emergency status, otherwise use normal icon
                var dIcon = hasEmergency ? DeviceEmergencyIcon : DeviceNormalIcon;
                var dMarker = L.marker([<?php echo $d['latitude']; ?>, <?php echo $d['longitude']; ?>], { 
                    icon: dIcon,
                    zIndexOffset: 2000 // Much higher z-index so device icons appear on top
                });
                var dPopup = `
                    <div class="info-window">
                        <h3 style="background-color: #dc2626; color: white; padding: 8px; margin: -8px -8px 12px -8px; border-radius: 4px 4px 0 0;"><?php echo addslashes($d['device_name']); ?></h3>
                        <p style="margin: 6px 0;"><strong>Device Number:</strong> <span style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px;"><?php echo addslashes($d['device_number'] ?? 'N/A'); ?></span></p>
                        <p style="margin: 6px 0;"><strong>Serial Number:</strong> <span style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px;"><?php echo addslashes($d['serial_number'] ?? 'N/A'); ?></span></p>
                        <p style="margin: 6px 0;"><strong>Status:</strong> <span style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px;"><?php echo addslashes($d['device_status'] ?? 'N/A'); ?></span></p>
                        <p style="margin: 6px 0;"><strong>Fire Data Status:</strong> <span style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px;"><?php echo addslashes($d['fire_data_status'] ?? 'N/A'); ?></span></p>
                        <p style="margin: 6px 0;"><strong>Barangay:</strong> <span style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px;"><?php echo addslashes($d['barangay_name'] ?? 'N/A'); ?></span></p>
                        <p style="margin: 6px 0;"><strong>Address:</strong> <span id="device-address-<?php echo (int)$d['device_id']; ?>" style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px; display: inline-block; max-width: 250px; word-wrap: break-word;">Loading...</span></p>
                        <p style="font-size: 11px; color: #666; margin-top: 8px;"><strong>Coordinates:</strong> <?php echo $d['latitude']; ?>, <?php echo $d['longitude']; ?></p>
                        <?php if (!empty($d['latest_timestamp'])): ?>
                        <p style="margin: 6px 0;"><strong>Last Update:</strong> <span style="background-color: #dc2626; color: white; padding: 4px 8px; border-radius: 4px;"><?php echo addslashes($d['latest_timestamp']); ?></span></p>
                        <?php endif; ?>
                    </div>
                `;
                dMarker.bindPopup(dPopup);
                
                // Reverse geocoding to get address when popup opens
                dMarker.on('popupopen', function() {
                    var addressElement = document.getElementById('device-address-<?php echo (int)$d['device_id']; ?>');
                    if (addressElement && addressElement.textContent === 'Loading...') {
                        var lat = <?php echo $d['latitude']; ?>;
                        var lng = <?php echo $d['longitude']; ?>;
                        
                        // Use Nominatim API for reverse geocoding
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
                            .then(response => response.json())
                            .then(data => {
                                if (data && data.display_name) {
                                    addressElement.textContent = data.display_name;
                                } else {
                                    addressElement.textContent = 'Address not available';
                                    addressElement.style.backgroundColor = '#6b7280';
                                }
                            })
                            .catch(error => {
                                console.error('Reverse geocoding error:', error);
                                addressElement.textContent = 'Address not available';
                                addressElement.style.backgroundColor = '#6b7280';
                            });
                    }
                });
                // Add hover tooltip on device icon
                var dTooltip = `
                    <div style="font-weight:600;margin-bottom:2px;">
                        <?php echo addslashes($d['device_name']); ?>
                    </div>
                    <div style="font-size:12px;opacity:0.9;">
                        Status: <?php echo addslashes($d['fire_data_status'] ?? 'N/A'); ?>
                    </div>
                `;
                dMarker.bindTooltip(dTooltip, {
                    direction: 'top',
                    offset: [0, -24],
                    sticky: true,
                    opacity: 0.95,
                    className: 'device-hover-tooltip'
                });
                
                var deviceMarkerObj = {
                    marker: dMarker,
                    type: 'device',
                    name: (<?php echo json_encode($d['device_name'] ?? ''); ?> || '').toString(),
                    address: '',
                    barangayId: <?php echo json_encode($d['barangay_id'] ?? null); ?>,
                    barangayRiskLevel: (<?php echo json_encode($d['fire_risk_level'] ?? ''); ?> || '').toString(),
                    originalIcon: dIcon,
                    deviceData: <?php echo json_encode($d); ?>,
                    hasEmergency: hasEmergency
                };
                
                allMarkers.push(deviceMarkerObj);
                
                // Store device marker in lookup map for easy access by device_id
                deviceMarkersMap[<?php echo (int)$d['device_id']; ?>] = deviceMarkerObj;
                
                // Create permanent label for device - positioned above the icon
                var labelText = '<div style="font-weight: bold; margin-bottom: 2px;"><?php echo addslashes($d['device_name']); ?></div><div style="font-size: 10px;">Incidents: <?php echo $d['fire_incident_count']; ?></div>';
                var labelClass = 'device-label <?php echo ($d['has_fire_incidents'] == 1) ? 'has-incidents' : 'no-incidents'; ?>';
                
                // Position label well above the device icon to prevent overlap
                var labelLat = <?php echo $d['latitude']; ?> + 0.0005; // Much larger offset north
                var labelLng = <?php echo $d['longitude']; ?>;
                
                var deviceLabel = L.marker([labelLat, labelLng], {
                    icon: L.divIcon({
                        className: labelClass,
                        html: labelText,
                        iconSize: [130, 35], // Larger size to ensure all info is visible
                        iconAnchor: [65, 35]
                    }),
                    interactive: false,
                    zIndexOffset: 500 // Lower z-index so device icons appear on top
                });
                
                // Add device label to allMarkers for management
                allMarkers.push({
                    marker: deviceLabel,
                    type: 'device_label',
                    name: 'Device Label',
                    address: '',
                    barangayId: <?php echo json_encode($d['barangay_id'] ?? null); ?>,
                    barangayRiskLevel: (<?php echo json_encode($d['fire_risk_level'] ?? ''); ?> || '').toString(),
                    originalIcon: null,
                    deviceData: <?php echo json_encode($d); ?>
                });
                
                // Add device marker to map when zoom level is sufficient
                if (map.getZoom() >= 12) {
                    dMarker.addTo(map);
                }
            <?php endif; ?>
        <?php endforeach; ?>

        // Fire incident marker style (red pin)
        var IncidentIcon = L.divIcon({
            className: 'custom-incident-icon',
            html: '<div style="width:14px;height:14px;background:#dc2626;border:2px solid #fff;border-radius:50%;box-shadow:0 0 8px rgba(220,38,38,0.6);"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });

        // Add markers for fire incidents (with barangay) - don't add to map immediately
        <?php foreach ($incidents as $in): ?>
            <?php if (!empty($in['display_lat']) && !empty($in['display_long'])): ?>
                var iMarker = L.marker([<?php echo $in['display_lat']; ?>, <?php echo $in['display_long']; ?>], { icon: IncidentIcon });
                var iPopup = `
                    <div class="info-window">
                        <h3>Fire Incident #<?php echo (int)$in['id']; ?></h3>
                        <p><strong>Status:</strong> <?php echo addslashes($in['status']); ?></p>
                        <p><strong>Timestamp:</strong> <?php echo addslashes($in['timestamp']); ?></p>
                        <hr />
                        <p><strong>Barangay:</strong> <?php echo addslashes($in['barangay_name'] ?? 'Unknown'); ?></p>
                        <p><strong>Device:</strong> <?php echo addslashes($in['device_name'] ?? 'N/A'); ?></p>
                        <p><strong>Device Number:</strong> <?php echo addslashes($in['device_number'] ?? 'N/A'); ?></p>
                        <p><strong>ML:</strong> pred=<?php echo (int)$in['ml_prediction']; ?>, conf=<?php echo $in['ml_confidence']; ?>%</p>
                    </div>
                `;
                iMarker.bindPopup(iPopup);
                incidentMarkers.push({
                    marker: iMarker,
                    deviceId: <?php echo json_encode($in['device_id']); ?>,
                    barangayId: <?php echo json_encode($in['barangay_id']); ?>,
                    barangayName: (<?php echo json_encode($in['barangay_name'] ?? ''); ?> || '').toString(),
                    deviceName: (<?php echo json_encode($in['device_name'] ?? ''); ?> || '').toString(),
                    deviceNumber: (<?php echo json_encode($in['device_number'] ?? ''); ?> || '').toString(),
                    status: (<?php echo json_encode($in['status'] ?? ''); ?> || '').toString(),
                    timestamp: (<?php echo json_encode($in['timestamp'] ?? ''); ?> || '').toString(),
                    barangayRiskLevel: (<?php echo json_encode($in['fire_risk_level'] ?? ''); ?> || '').toString()
                });
            <?php endif; ?>
        <?php endforeach; ?>

        // Debug: Log device markers count
        console.log('Total device markers created:', allMarkers.filter(function(m) { return m.type === 'device'; }).length);
        console.log('Total incident markers created:', incidentMarkers.length);
        
        // Apply initial filters (devices will be hidden by default until barangay is selected)
        setTimeout(function() {
            applyFilters();
            console.log('Device markers after filter:', allMarkers.filter(function(m) { return m.type === 'device' && map.hasLayer(m.marker); }).length);
            console.log('Incident markers after filter:', incidentMarkers.filter(function(m) { return map.hasLayer(m.marker); }).length);
        }, 100);
        
        // Center the map on the first device
        <?php if (!empty($devices) && !empty($devices[0]['latitude']) && !empty($devices[0]['longitude'])): ?>
            map.setView([<?php echo $devices[0]['latitude']; ?>, <?php echo $devices[0]['longitude']; ?>], 13);
        <?php endif; ?>

        // Function to update device icons based on Emergency status
        function updateDeviceIconsBasedOnEmergencyStatus() {
            // Update device icons based on their Emergency status
            allMarkers.forEach(function(item) {
                if (item.type === 'device' && item.deviceData && item.originalIcon) {
                    var hasEmergency = item.hasEmergency || false;
                    // Use Emergency icon if device has Emergency status, otherwise use normal icon
                    var newIcon = hasEmergency ? DeviceEmergencyIcon : DeviceNormalIcon;
                    item.marker.setIcon(newIcon);
                }
            });
        }
        
        // Function to pinpoint a device on the map by device_id
        // This function can be called from anywhere, e.g., from a devices table
        function pinpointDevice(deviceId) {
            // Convert deviceId to integer if it's a string
            deviceId = parseInt(deviceId);
            
            // Check if device exists in the lookup map
            if (!deviceMarkersMap[deviceId]) {
                console.warn('Device with ID ' + deviceId + ' not found on map');
                return false;
            }
            
            var deviceMarkerObj = deviceMarkersMap[deviceId];
            var marker = deviceMarkerObj.marker;
            var deviceData = deviceMarkerObj.deviceData;
            
            // Get device coordinates
            var lat = deviceData.latitude;
            var lng = deviceData.longitude;
            
            if (!lat || !lng) {
                console.warn('Device with ID ' + deviceId + ' has no valid coordinates');
                return false;
            }
            
            // Ensure device marker is visible on the map
            if (!map.hasLayer(marker)) {
                marker.addTo(map);
            }
            
            // Set barangay filter to show the device's barangay if not already set
            var barangayFilter = document.getElementById('barangayFilter');
            if (barangayFilter && deviceData.barangay_id) {
                var currentBarangay = barangayFilter.value;
                if (String(currentBarangay) !== String(deviceData.barangay_id)) {
                    barangayFilter.value = deviceData.barangay_id;
                    // Trigger filter update
                    applyFilters();
                }
            }
            
            // Center and zoom to the device location
            // Use a zoom level that ensures the device icon is clearly visible
            map.setView([lat, lng], 16, {
                animate: true,
                duration: 0.8
            });
            
            // Open the device popup to highlight it
            setTimeout(function() {
                marker.openPopup();
                
                // Add a bounce animation effect
                var bounceCount = 0;
                var maxBounces = 3;
                var bounceInterval = setInterval(function() {
                    if (bounceCount >= maxBounces) {
                        clearInterval(bounceInterval);
                        return;
                    }
                    
                    // Temporarily increase icon size for bounce effect
                    var currentIcon = marker.options.icon;
                    var bounceIcon = L.icon({
                        iconUrl: currentIcon.options.iconUrl,
                        iconSize: [currentIcon.options.iconSize[0] * 1.2, currentIcon.options.iconSize[1] * 1.2],
                        iconAnchor: [currentIcon.options.iconAnchor[0] * 1.2, currentIcon.options.iconAnchor[1] * 1.2],
                        popupAnchor: currentIcon.options.popupAnchor
                    });
                    
                    marker.setIcon(bounceIcon);
                    
                    setTimeout(function() {
                        marker.setIcon(currentIcon);
                    }, 200);
                    
                    bounceCount++;
                }, 400);
            }, 500);
            
            return true;
        }
        
        // Make pinpointDevice function globally available
        window.pinpointDevice = pinpointDevice;

        function applyFilters() {
            var q = (document.getElementById('searchInput').value || '').trim().toLowerCase();
            var type = (document.getElementById('typeFilter').value || '').toLowerCase();
            var brgy = (document.getElementById('barangayFilter').value || '').toString();
            var toggleIncidentsEl = document.getElementById('toggleIncidents');
            var showInc = toggleIncidentsEl ? toggleIncidentsEl.checked : true;
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            // Debug: Check if date inputs are found
            if (!document.getElementById('startDate') || !document.getElementById('endDate')) {
                console.error('Date input elements not found!');
                return;
            }
            var z = map.getZoom();
            var showDevicesThreshold = 12;
            var showLabelsThreshold = 17; // Only show device labels at close zoom
            
            allMarkers.forEach(function(item) {
                var matchesType = !type || item.type === type;
                var matchesText = !q || (item.name && item.name.toLowerCase().includes(q)) || (item.address && item.address.toLowerCase().includes(q));
                
                // Handle barangay filtering - support both specific barangay ID and risk level filtering
                var matchesBarangay = true;
                if (brgy) {
                    if (['HIGH', 'MEDIUM', 'LOW', 'SAFE'].includes(brgy)) {
                        // Filter by risk level - need to check if barangay has this risk level
                        matchesBarangay = item.barangayRiskLevel === brgy;
                    } else {
                        // Filter by specific barangay ID
                        matchesBarangay = String(item.barangayId) === brgy;
                    }
                }
                
                // Handle device labels - show them with their associated devices
                if (item.type === 'device_label') {
                    // Show labels only when close enough
                    var shouldShow = matchesBarangay && z >= showLabelsThreshold && brgy;
                    if (shouldShow) {
                        if (!map.hasLayer(item.marker)) { 
                            item.marker.addTo(map); 
                        }
                    } else {
                        if (map.hasLayer(item.marker)) { 
                            map.removeLayer(item.marker); 
                        }
                    }
                } else if (item.type === 'device') {
                    // Only show devices if: zoom level is sufficient, and a barangay is selected
                    var shouldShow = matchesType && matchesText && matchesBarangay && z >= showDevicesThreshold && brgy;
                    if (shouldShow) {
                        if (!map.hasLayer(item.marker)) { item.marker.addTo(map); }
                    } else {
                        if (map.hasLayer(item.marker)) { map.removeLayer(item.marker); }
                    }
                }
            });

            incidentMarkers.forEach(function(item) {
                // Handle barangay filtering - support both specific barangay ID and risk level filtering
                var matchesBrgy = true;
                if (brgy) {
                    if (['HIGH', 'MEDIUM', 'LOW', 'SAFE'].includes(brgy)) {
                        // Filter by risk level - need to check if barangay has this risk level
                        matchesBrgy = item.barangayRiskLevel === brgy;
                    } else {
                        // Filter by specific barangay ID
                        matchesBrgy = String(item.barangayId) === brgy;
                    }
                }
                var matchesText = !q || (item.barangayName && item.barangayName.toLowerCase().includes(q)) || (item.buildingName && item.buildingName.toLowerCase().includes(q)) || (item.address && item.address.toLowerCase().includes(q));
                
                // Date filtering for incidents
                var matchesDate = true;
                if (startDate || endDate) {
                    var incidentDate = new Date(item.timestamp);
                    if (startDate) {
                        var start = new Date(startDate);
                        matchesDate = matchesDate && incidentDate >= start;
                    }
                    if (endDate) {
                        var end = new Date(endDate);
                        end.setHours(23, 59, 59, 999); // Include the entire end date
                        matchesDate = matchesDate && incidentDate <= end;
                    }
                    
                    // Debug: Log first few incidents when date filtering is active
                    if (incidentMarkers.indexOf(item) < 3) {
                        console.log('Date Filter Debug:', {
                            timestamp: item.timestamp,
                            incidentDate: incidentDate,
                            startDate: startDate,
                            endDate: endDate,
                            matchesDate: matchesDate,
                            shouldShow: showInc && matchesBrgy && matchesText && matchesDate && !hideIncidentsWhenZoomed
                        });
                    }
                }
                
                // Hide incidents when zoomed in (when devices are visible)
                var hideIncidentsWhenZoomed = z >= showDevicesThreshold;
                var shouldShow = showInc && matchesBrgy && matchesText && matchesDate && !hideIncidentsWhenZoomed;
                
                
                if (shouldShow) {
                    if (!map.hasLayer(item.marker)) { item.marker.addTo(map); }
                } else {
                    if (map.hasLayer(item.marker)) { map.removeLayer(item.marker); }
                }
            });
            
            // Update device icons based on Emergency status
            updateDeviceIconsBasedOnEmergencyStatus();
            
            // Update barangay icons/popups based on filtered incidents
            if (typeof updateBarangayIconsAndPopups === 'function') { updateBarangayIconsAndPopups(); }
            
            // Hide barangay overlays when devices are being shown
            var devicesBeingShown = brgy && z >= showDevicesThreshold;
            var toggleBarangaysEl = document.getElementById('toggleBarangays');
            var barangayToggle = toggleBarangaysEl ? toggleBarangaysEl.checked : false;
            
            if (devicesBeingShown && barangayToggle) {
                // Hide barangay overlays when devices are displayed
                toggleAllBarangays(false);
                if (toggleBarangaysEl) toggleBarangaysEl.checked = false;
            } else if (!devicesBeingShown && !barangayToggle) {
                // Show barangay overlays when devices are not displayed
                toggleAllBarangays(true);
                if (toggleBarangaysEl) toggleBarangaysEl.checked = true;
            }
            
            // Update title based on date filter
            updateTitleBasedOnDateFilter(startDate, endDate);
        }

        // Test function for date filtering
        function testDateFilter() {
            console.log('=== DATE FILTER TEST ===');
            console.log('Start date:', document.getElementById('startDate').value);
            console.log('End date:', document.getElementById('endDate').value);
            console.log('Total incident markers:', incidentMarkers.length);
            console.log('Visible incident markers before filter:', incidentMarkers.filter(function(m) { return map.hasLayer(m.marker); }).length);
            
            applyFilters();
            
            console.log('Visible incident markers after filter:', incidentMarkers.filter(function(m) { return map.hasLayer(m.marker); }).length);
            console.log('=== END TEST ===');
        }


        document.getElementById('searchInput').addEventListener('input', applyFilters);
        document.getElementById('typeFilter').addEventListener('change', applyFilters);
        document.getElementById('barangayFilter').addEventListener('change', applyFilters);
        document.getElementById('startDate').addEventListener('change', function() {
            console.log('Start date changed to:', this.value);
            // Sync with map controls
            var mapStartDate = document.getElementById('mapStartDate');
            if (mapStartDate) {
                mapStartDate.value = this.value;
            }
            applyFilters();
        });
        document.getElementById('endDate').addEventListener('change', function() {
            console.log('End date changed to:', this.value);
            // Sync with map controls
            var mapEndDate = document.getElementById('mapEndDate');
            if (mapEndDate) {
                mapEndDate.value = this.value;
            }
            applyFilters();
        });
        var toggleIncidentsEl = document.getElementById('toggleIncidents');
        if (toggleIncidentsEl) {
            toggleIncidentsEl.addEventListener('change', applyFilters);
        }
        var toggleBarangaysEl = document.getElementById('toggleBarangays');
        if (toggleBarangaysEl) {
            toggleBarangaysEl.addEventListener('change', function(e){
                var show = e.target.checked;
                toggleAllBarangays(show);
                if (show) { fitToAllBarangays(); }
            });
        }
        document.getElementById('resetBtn').addEventListener('click', function() {
            document.getElementById('searchInput').value = '';
            document.getElementById('typeFilter').value = '';
            document.getElementById('barangayFilter').value = '';
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
            var toggleIncidents = document.getElementById('toggleIncidents');
            if (toggleIncidents) toggleIncidents.checked = true;
            var toggleBarangays = document.getElementById('toggleBarangays');
            if (toggleBarangays) toggleBarangays.checked = true; // Show barangays when reset
            
            // Reset map controls
            var mapStartDate = document.getElementById('mapStartDate');
            var mapEndDate = document.getElementById('mapEndDate');
            if (mapStartDate) mapStartDate.value = '';
            if (mapEndDate) mapEndDate.value = '';
            
            applyFilters();
            // Reset title to current month
            setCurrentMonthTitle();
            if (selectedBarangayCircle) { map.removeLayer(selectedBarangayCircle); selectedBarangayCircle = null; }
            // Hide any existing hover popup
            hideBarangayHoverInfo();
            // Show barangay overlays when resetting
            toggleAllBarangays(true);
        });


        // Fit map to selected barangay's markers or fallback to barangay centroid
        function fitToBarangay(brgyId) {
            if (!brgyId) { return; }
            var bounds = L.latLngBounds([]);
            var hasAny = false;
            // Include device markers in barangay
            allMarkers.forEach(function(item) {
                if (String(item.barangayId || '') === String(brgyId) && map.hasLayer(item.marker)) {
                    bounds.extend(item.marker.getLatLng());
                    hasAny = true;
                }
            });
            // Include incidents in barangay
            incidentMarkers.forEach(function(item) {
                if (String(item.barangayId || '') === String(brgyId) && map.hasLayer(item.marker)) {
                    bounds.extend(item.marker.getLatLng());
                    hasAny = true;
                }
            });
            if (hasAny) {
                map.fitBounds(bounds.pad(0.2));
                return;
            }
            // Fallback to barangay centroid
            var brgyData = <?php echo json_encode($barangays); ?>;
            var found = brgyData.find(function(b) { return String(b.id) === String(brgyId); });
            if (found && found.latitude && found.longitude) {
                map.setView([parseFloat(found.latitude), parseFloat(found.longitude)], 14);
            }
        }

        // Draw a precise circle exactly at the barangay's coordinates
        function drawBarangayCircle(brgyId) {
            // Remove any existing circle first
            if (selectedBarangayCircle) {
                map.removeLayer(selectedBarangayCircle);
                selectedBarangayCircle = null;
            }
            if (!brgyId) { return; }
            var brgyData = <?php echo json_encode($barangays); ?>;
            var found = brgyData.find(function(b) { return String(b.id) === String(brgyId); });
            if (!found || !found.latitude || !found.longitude) { return; }
            var lat = parseFloat(found.latitude);
            var lng = parseFloat(found.longitude);
            
            // Create a small circle exactly at the barangay coordinates
            selectedBarangayCircle = L.circle([lat, lng], {
                radius: 100, // Small radius in meters - just enough to be visible
                color: (found.has_alert ? '#dc2626' : '#16a34a'), // red for alert, blue for safe
                weight: 3, // Visible border
                fillColor: (found.has_alert ? '#dc2626' : '#16a34a'), // red for alert, blue for safe
                fillOpacity: 0.2, // Semi-transparent fill
                className: 'precise-barangay-circle'
            }).addTo(map);
            
            // Circle is now permanent without animation
            
            if (selectedBarangayCircle.bringToFront) { selectedBarangayCircle.bringToFront(); }
        }

        function createBarangayLabel(lat, lng, name) {
            var label = L.divIcon({
                className: 'barangay-label',
                html: '<div style="background:rgba(248,113,113,0.9);color:#fff;padding:2px 6px;border-radius:4px;font-size:12px;border:1px solid #fff;white-space:nowrap;">'+
                      (name ? name.replace(/</g,'&lt;').replace(/>/g,'&gt;') : 'Barangay') + '</div>',
                iconSize: [10, 10],
                iconAnchor: [5, -6]
            });
            return L.marker([lat, lng], { icon: label, interactive: false });
        }

        function toggleAllBarangays(show) {
            // Clear previous
            allBarangayLayers.forEach(function(layer){ if (map.hasLayer(layer)) { map.removeLayer(layer); } });
            allBarangayLayers = [];
            
            // Hide hover popup when hiding barangays
            if (!show) {
                hideBarangayHoverInfo();
                return;
            }
            var brgyData = <?php echo json_encode($barangays); ?>;
            var brgyHeatData = <?php echo json_encode($barangayHeatData); ?>;
            var brgyDeviceStats = <?php echo json_encode($barangayDeviceStats); ?>;
            var brgySafetyStats = <?php echo json_encode($barangaySafetyStats); ?>;
            // Create lookup maps for all data by barangay ID
            var heatDataMap = {};
            brgyHeatData.forEach(function(heatData) {
                heatDataMap[heatData.barangay_id] = heatData;
            });
            
            var deviceStatsMap = {};
            brgyDeviceStats.forEach(function(deviceStats) {
                deviceStatsMap[deviceStats.barangay_id] = deviceStats;
            });
            
            
            var safetyStatsMap = {};
            brgySafetyStats.forEach(function(safetyStats) {
                safetyStatsMap[safetyStats.barangay_id] = safetyStats;
            });
            
            brgyData.forEach(function(b, index){
                if (!b || !b.latitude || !b.longitude) { return; }
                var lat = parseFloat(b.latitude);
                var lng = parseFloat(b.longitude);
                
                // Determine fire risk level and colors
                var fireRiskLevel = b.fire_risk_level || 'SAFE';
                var circleColor;
                
                switch(fireRiskLevel) {
                    case 'HIGH':
                        circleColor = '#dc2626'; // Red
                        break;
                    case 'MEDIUM':
                        circleColor = '#f59e0b'; // Orange
                        break;
                    case 'LOW':
                        circleColor = '#10b981'; // Green
                        break;
                    default:
                        circleColor = '#16a34a'; // Blue
                }
                
                // Create circle with enhanced styling and initial small radius for animation
                var circle = L.circle([lat, lng], {
                    radius: 0, // Start with 0 radius
                    color: circleColor,
                    weight: 0, // Start with no border
                    fillColor: circleColor,
                    fillOpacity: 0.0,
                    className: 'enhanced-barangay-circle'
                }).addTo(map);
                
                // Animate circle expansion with enhanced effects
                setTimeout(function() {
                    animateEnhancedCircleExpansion(circle, 1200, 2500, true); // Expand to 1200m radius over 2.5 seconds for slower, smoother effect
                }, index * 100); // Stagger animations by 100ms each for more gradual wave effect
                
                // Select icon based on fire risk level
                var fireRiskLevel = b.fire_risk_level || 'SAFE';
                var icon, riskText, circleColor;
                
                switch(fireRiskLevel) {
                    case 'HIGH':
                        icon = BrgyRiskIcon;
                        riskText = 'High Fire Risk (Has Incidents)';
                        circleColor = '#dc2626'; // Red
                        break;
                    case 'MEDIUM':
                        icon = BrgyAlertIcon;
                        riskText = 'Medium Fire Risk (Unsafe Buildings)';
                        circleColor = '#f59e0b'; // Orange
                        break;
                    case 'LOW':
                        icon = BrgySafeIcon;
                        riskText = 'Low Fire Risk (Safe Buildings)';
                        circleColor = '#10b981'; // Green
                        break;
                    default:
                        icon = BrgySafeIcon;
                        riskText = 'Safe (No Buildings)';
                        circleColor = '#16a34a'; // Blue
                }
                
                var marker = L.marker([lat, lng], { icon: icon, zIndexOffset: 2000, opacity: 0 }).addTo(map);
                
                // Animate marker fade in with bounce effect
                setTimeout(function() {
                    animateMarkerBounce(marker, 1);
                }, index * 75 + 300); // Start after circle starts expanding
                
                var brgyName = (b.barangay_name || 'Barangay');
                var escapedName = brgyName.replace(/</g,'&lt;').replace(/>/g,'&gt;');
                
                // Count devices and incidents for this barangay
                var deviceCount = allMarkers.filter(function(marker) {
                    return marker.type === 'device' && String(marker.barangayId) === String(b.id);
                }).length;
                
                var incidentCount = incidentMarkers.filter(function(incident) {
                    return String(incident.barangayId) === String(b.id);
                }).length;
                
                var popupHtml = '<div class="info-window">' +
                    '<div class="brgy-title">' + escapedName + '</div>' +
                    '<p><strong>Fire Risk Level:</strong> <span style="color: ' + circleColor + '; font-weight: bold;">' + riskText + '</span></p>' +
                    '<p><strong>Coordinates:</strong> ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</p>' +
                    '<p><strong>Total Incidents:</strong> ' + getFilteredIncidentCountForBarangay(b.id) + '</p>' +
                    '<p><strong>Emergency Incidents:</strong> ' + getFilteredEmergencyIncidentCountForBarangay(b.id) + '</p>' +
                    '</div>';
                marker.bindPopup(popupHtml);
                // Track for month-based updates
                barangayMarkers.push({ 
                    id: b.id, 
                    marker: marker, 
                    circle: circle, 
                    name: brgyName, 
                    lat: lat, 
                    lng: lng,
                    originalRiskLevel: fireRiskLevel
                });
                
                // Add click event to circle to show devices in barangay
                circle.on('click', function(e) {
                    var currentZoom = map.getZoom();
                    var showDevicesThreshold = 12;
                    
                    // Show devices when clicked, regardless of zoom level
                    showDevicesInBarangay(b.id, brgyName);
                    
                    // Also show barangay popup when zoomed out (devices not visible)
                    if (currentZoom < showDevicesThreshold) {
                        showDetailedBarangayPopup(e, brgyName, b.has_alert, lat, lng, deviceCount, incidentCount);
                    }
                });
                
                // Add click event to marker to show devices in barangay
                marker.on('click', function(e) {
                    var currentZoom = map.getZoom();
                    var showDevicesThreshold = 12;
                    
                    // Show devices when clicked, regardless of zoom level
                    showDevicesInBarangay(b.id, brgyName);
                    
                    // Also show barangay popup when zoomed out (devices not visible)
                    if (currentZoom < showDevicesThreshold) {
                        showDetailedBarangayPopup(e, brgyName, b.has_alert, lat, lng, deviceCount, incidentCount);
                    }
                });
                
                // Get comprehensive data for this barangay
                var heatData = heatDataMap[b.id] || {
                    latest_heat: 0,
                    latest_heat_timestamp: 'N/A',
                    avg_heat: 0,
                    max_heat: 0,
                    min_heat: 0,
                    total_readings: 0,
                    jan_avg_heat: 0,
                    feb_avg_heat: 0,
                    mar_avg_heat: 0,
                    apr_avg_heat: 0,
                    may_avg_heat: 0,
                    jun_avg_heat: 0,
                    jul_avg_heat: 0,
                    aug_avg_heat: 0,
                    sep_avg_heat: 0,
                    oct_avg_heat: 0,
                    nov_avg_heat: 0,
                    dec_avg_heat: 0
                };
                
                var deviceStats = deviceStatsMap[b.id] || {
                    total_devices: 0,
                    online_devices: 0,
                    offline_devices: 0,
                    faulty_devices: 0,
                    active_devices: 0,
                    devices_with_recent_activity: 0,
                    devices_without_activity: 0,
                    latest_device_activity: null
                };
                
                
                var safetyStats = safetyStatsMap[b.id] || {
                    total_buildings: 0,
                    buildings_with_sprinklers: 0,
                    buildings_with_fire_alarms: 0,
                    buildings_with_extinguishers: 0,
                    buildings_with_emergency_exits: 0,
                    buildings_with_emergency_lighting: 0,
                    buildings_with_fire_escape: 0,
                    fully_compliant_buildings: 0,
                    partially_compliant_buildings: 0,
                    non_compliant_buildings: 0,
                    recently_inspected: 0,
                    overdue_inspection: 0,
                    never_inspected: 0
                };
                
                // Add hover event to circle to show barangay information
                circle.on('mouseover', function(e) {
                    // Calculate filtered incident count based on current date filter
                    var filteredIncidentCount = getFilteredIncidentCountForBarangay(b.id);
                    showBarangayHoverInfo(e, brgyName, b.has_alert, lat, lng, deviceCount, filteredIncidentCount, b.fire_risk_level, heatData, deviceStats, safetyStats, b.id);
                });
                
                // Add hover event to marker to show barangay information
                marker.on('mouseover', function(e) {
                    // Calculate filtered incident count based on current date filter
                    var filteredIncidentCount = getFilteredIncidentCountForBarangay(b.id);
                    showBarangayHoverInfo(e, brgyName, b.has_alert, lat, lng, deviceCount, filteredIncidentCount, b.fire_risk_level, heatData, deviceStats, safetyStats, b.id);
                });
                
                // Remove hover info when mouse leaves
                circle.on('mouseout', function(e) {
                    hideBarangayHoverInfo();
                });
                
                marker.on('mouseout', function(e) {
                    hideBarangayHoverInfo();
                });
                
                allBarangayLayers.push(circle);
                allBarangayLayers.push(marker);
            });
            
            // Update visibility after a short delay to allow initial animations
            setTimeout(function() {
                updateBarangayOverlaysVisibility();
                if (typeof updateBarangayIconsAndPopups === 'function') { updateBarangayIconsAndPopups(); }
            }, 800);
        }

        // Helper function to get filtered risk level for a specific barangay
        function getFilteredBarangayRiskLevel(barangayId) {
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            // If no date filter is applied, return null to use original risk level
            if (!startDate && !endDate) {
                return null;
            }
            
            // Filter incidents for this barangay within the date range
            var filteredIncidents = incidentMarkers.filter(function(incident) {
                var matchesBarangay = String(incident.barangayId) === String(barangayId);
                var matchesDate = true;
                
                if (startDate || endDate) {
                    var incidentDate = new Date(incident.timestamp);
                    if (startDate) {
                        var start = new Date(startDate);
                        matchesDate = matchesDate && incidentDate >= start;
                    }
                    if (endDate) {
                        var end = new Date(endDate);
                        end.setHours(23, 59, 59, 999);
                        matchesDate = matchesDate && incidentDate <= end;
                    }
                }
                
                return matchesBarangay && matchesDate;
            });
            
            // Calculate risk level based on filtered incidents
            if (filteredIncidents.length > 0) {
                // Has incidents in filtered date range - HIGH RISK
                return 'HIGH';
            } else {
                // No incidents in filtered date range - SAFE
                return 'SAFE';
            }
        }

        // Helper function to get filtered heat data for a specific barangay
        function getFilteredBarangayHeatData(barangayId) {
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            // If no date filter is applied, return null to use original data
            if (!startDate && !endDate) {
                return null;
            }
            
            // Filter incidents for this barangay within the date range
            var filteredIncidents = incidentMarkers.filter(function(incident) {
                var matchesBarangay = String(incident.barangayId) === String(barangayId);
                var matchesDate = true;
                
                if (startDate || endDate) {
                    var incidentDate = new Date(incident.timestamp);
                    if (startDate) {
                        var start = new Date(startDate);
                        matchesDate = matchesDate && incidentDate >= start;
                    }
                    if (endDate) {
                        var end = new Date(endDate);
                        end.setHours(23, 59, 59, 999);
                        matchesDate = matchesDate && incidentDate <= end;
                    }
                }
                
                return matchesBarangay && matchesDate;
            });
            
            // If no incidents in filtered range, return empty heat data
            if (filteredIncidents.length === 0) {
                return {
                    total_readings: 0,
                    latest_heat: 0,
                    latest_heat_timestamp: 'N/A',
                    avg_heat: 0,
                    max_heat: 0,
                    min_heat: 0,
                    jan_avg_heat: 0,
                    feb_avg_heat: 0,
                    mar_avg_heat: 0,
                    apr_avg_heat: 0,
                    may_avg_heat: 0,
                    jun_avg_heat: 0,
                    jul_avg_heat: 0,
                    aug_avg_heat: 0,
                    sep_avg_heat: 0,
                    oct_avg_heat: 0,
                    nov_avg_heat: 0,
                    dec_avg_heat: 0
                };
            }
            
            // Calculate heat statistics from filtered incidents
            var heatValues = filteredIncidents.map(function(incident) {
                return parseFloat(incident.heat) || 0;
            }).filter(function(heat) {
                return heat > 0;
            });
            
            if (heatValues.length === 0) {
                return {
                    total_readings: 0,
                    latest_heat: 0,
                    latest_heat_timestamp: 'N/A',
                    avg_heat: 0,
                    max_heat: 0,
                    min_heat: 0,
                    jan_avg_heat: 0,
                    feb_avg_heat: 0,
                    mar_avg_heat: 0,
                    apr_avg_heat: 0,
                    may_avg_heat: 0,
                    jun_avg_heat: 0,
                    jul_avg_heat: 0,
                    aug_avg_heat: 0,
                    sep_avg_heat: 0,
                    oct_avg_heat: 0,
                    nov_avg_heat: 0,
                    dec_avg_heat: 0
                };
            }
            
            var latestIncident = filteredIncidents[filteredIncidents.length - 1];
            var avgHeat = heatValues.reduce(function(sum, heat) { return sum + heat; }, 0) / heatValues.length;
            var maxHeat = Math.max.apply(Math, heatValues);
            var minHeat = Math.min.apply(Math, heatValues);
            
            return {
                total_readings: heatValues.length,
                latest_heat: latestIncident.heat || 0,
                latest_heat_timestamp: latestIncident.timestamp || 'N/A',
                avg_heat: avgHeat,
                max_heat: maxHeat,
                min_heat: minHeat,
                jan_avg_heat: 0, // Monthly data would need more complex calculation
                feb_avg_heat: 0,
                mar_avg_heat: 0,
                apr_avg_heat: 0,
                may_avg_heat: 0,
                jun_avg_heat: 0,
                jul_avg_heat: 0,
                aug_avg_heat: 0,
                sep_avg_heat: 0,
                oct_avg_heat: 0,
                nov_avg_heat: 0,
                dec_avg_heat: 0
            };
        }

        // Helper function to get filtered emergency incident count for a specific barangay
        function getFilteredEmergencyIncidentCountForBarangay(barangayId) {
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            var filteredIncidents = incidentMarkers.filter(function(incident) {
                var matchesBarangay = String(incident.barangayId) === String(barangayId);
                var matchesDate = true;
                var isEmergency = incident.status === 'EMERGENCY' || incident.status === 'ACKNOWLEDGED' || 
                                 incident.incident_type === 'emergency' || incident.incident_type === 'fire';
                
                // Apply date filtering
                if (startDate || endDate) {
                    var incidentDate = new Date(incident.timestamp);
                    if (startDate) {
                        var start = new Date(startDate);
                        matchesDate = matchesDate && incidentDate >= start;
                    }
                    if (endDate) {
                        var end = new Date(endDate);
                        end.setHours(23, 59, 59, 999);
                        matchesDate = matchesDate && incidentDate <= end;
                    }
                }
                
                return matchesBarangay && matchesDate && isEmergency;
            });
            
            return filteredIncidents.length;
        }

        // Helper function to get filtered incident count for a specific barangay
        function getFilteredIncidentCountForBarangay(barangayId) {
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            var filteredIncidents = incidentMarkers.filter(function(incident) {
                var matchesBarangay = String(incident.barangayId) === String(barangayId);
                var matchesDate = true;
                
                // Apply date filtering
                if (startDate || endDate) {
                    var incidentDate = new Date(incident.timestamp);
                    if (startDate) {
                        var start = new Date(startDate);
                        matchesDate = matchesDate && incidentDate >= start;
                    }
                    if (endDate) {
                        var end = new Date(endDate);
                        end.setHours(23, 59, 59, 999);
                        matchesDate = matchesDate && incidentDate <= end;
                    }
                }
                
                return matchesBarangay && matchesDate;
            });
            
            return filteredIncidents.length;
        }

        // Removed: getFilteredBuildingStats function - buildings are no longer used

        function updateBarangayIconsAndPopups() {
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            barangayMarkers.forEach(function(bm) {
                // Use the helper function to get filtered incident count
                var incidentCount = getFilteredIncidentCountForBarangay(bm.id);

                // Update icon based on filtered incidents
                if (incidentCount > 0) {
                    // Has incidents in filtered date range - show as high risk
                    bm.marker.setIcon(BrgyRiskIcon);
                } else {
                    // No incidents in filtered date range - show as safe
                    bm.marker.setIcon(BrgySafeIcon);
                }

                // Update popup to reflect filtered data
                var escapedName = (bm.name || 'Barangay').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                var dateRangeText = '';
                if (startDate || endDate) {
                    var startText = startDate ? startDate : 'All time';
                    var endText = endDate ? endDate : 'Present';
                    dateRangeText = '<p><strong>Date range:</strong> ' + startText + ' to ' + endText + '</p>';
                }
                
                var popupHtml = '<div class="info-window">' +
                    '<div class="brgy-title">' + escapedName + '</div>' +
                    '<p><strong>Incidents in range:</strong> ' + incidentCount + '</p>' +
                    '<p><strong>Emergency Incidents:</strong> ' + getFilteredEmergencyIncidentCountForBarangay(bm.id) + '</p>' +
                    dateRangeText +
                    '<p><strong>Coordinates:</strong> ' + bm.lat.toFixed(5) + ', ' + bm.lng.toFixed(5) + '</p>' +
                    '</div>';
                bm.marker.bindPopup(popupHtml);
            });
        }

        // Enhanced circle expansion animation with ultra-smooth visual effects
        function animateEnhancedCircleExpansion(circle, targetRadius, duration, hasAlert) {
            if (!circle || typeof circle.setRadius !== 'function') return;
            
            var startRadius = circle.getRadius();
            var steps = 60; // Even more steps for ultra-smooth animation
            var stepDuration = duration / steps;
            var radiusStep = (targetRadius - startRadius) / steps;
            var currentStep = 0;
            
            // Ultra-smooth easing function for natural expansion
            function easeOutQuart(t) {
                return 1 - Math.pow(1 - t, 4);
            }
            
            // Secondary easing for opacity and weight changes
            function easeInOutSine(t) {
                return -(Math.cos(Math.PI * t) - 1) / 2;
            }
            
            function animateStep() {
                if (currentStep >= steps) {
                    circle.setRadius(targetRadius);
                    // Set final enhanced style
                    circle.setStyle({
                        weight: 4, // Bold border
                        fillOpacity: hasAlert ? 0.2 : 0.15,
                        opacity: 0.8,
                        dashArray: hasAlert ? '8, 4' : '12, 6' // Different dash patterns for alert vs safe
                    });
                    return;
                }
                
                currentStep++;
                var progress = currentStep / steps;
                var easedRadiusProgress = easeOutQuart(progress);
                var easedStyleProgress = easeInOutSine(progress);
                
                // Calculate new radius with smooth easing
                var newRadius = startRadius + (radiusStep * easedRadiusProgress);
                circle.setRadius(newRadius);
                
                // Gradually increase border weight and opacity with different timing
                var weightProgress = Math.min(progress * 1.8, 1);
                var opacityProgress = Math.min(progress * 1.3, 1);
                var fillOpacityProgress = Math.min(progress * 1.1, 1);
                
                // Apply easing to style changes for smoother transitions
                var finalWeight = weightProgress * 4;
                var finalOpacity = 0.2 + (opacityProgress * 0.6);
                var finalFillOpacity = fillOpacityProgress * (hasAlert ? 0.2 : 0.15);
                
                circle.setStyle({
                    weight: finalWeight,
                    opacity: finalOpacity,
                    fillOpacity: finalFillOpacity,
                    dashArray: hasAlert ? '8, 4' : '12, 6'
                });
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        // Animate circle radius expansion/contraction (legacy function for compatibility)
        function animateCircleExpansion(circle, targetRadius, duration) {
            if (!circle || typeof circle.setRadius !== 'function') return;
            
            var startRadius = circle.getRadius();
            var steps = 30; // Number of animation steps
            var stepDuration = duration / steps;
            var radiusStep = (targetRadius - startRadius) / steps;
            var currentStep = 0;
            
            function animateStep() {
                if (currentStep >= steps) {
                    circle.setRadius(targetRadius);
                    return;
                }
                
                currentStep++;
                var newRadius = startRadius + (radiusStep * currentStep);
                circle.setRadius(newRadius);
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        // Enhanced marker bounce animation
        function animateMarkerBounce(marker, targetOpacity) {
            if (!marker || typeof marker.setOpacity !== 'function') return;
            
            var currentOpacity = marker.options.opacity || 0;
            var duration = 800; // Animation duration
            var steps = 20;
            var stepDuration = duration / steps;
            var opacityStep = (targetOpacity - currentOpacity) / steps;
            var currentStep = 0;
            
            // Bounce easing function
            function easeOutBounce(t) {
                if (t < (1/2.75)) {
                    return (7.5625*t*t);
                } else if (t < (2/2.75)) {
                    return (7.5625*(t-=(1.5/2.75))*t + 0.75);
                } else if (t < (2.5/2.75)) {
                    return (7.5625*(t-=(2.25/2.75))*t + 0.9375);
                } else {
                    return (7.5625*(t-=(2.625/2.75))*t + 0.984375);
                }
            }
            
            function animateStep() {
                if (currentStep >= steps) {
                    marker.setOpacity(targetOpacity);
                    return;
                }
                
                currentStep++;
                var progress = currentStep / steps;
                var easedProgress = easeOutBounce(progress);
                var newOpacity = currentOpacity + (opacityStep * easedProgress);
                marker.setOpacity(newOpacity);
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        // Ultra-smooth circle expansion for initial load
        function animateUltraSmoothExpansion(circle, targetRadius, duration, hasAlert) {
            if (!circle || typeof circle.setRadius !== 'function') return;
            
            var startRadius = circle.getRadius();
            var steps = 80; // Maximum smoothness
            var stepDuration = duration / steps;
            var radiusStep = (targetRadius - startRadius) / steps;
            var currentStep = 0;
            
            // Ultra-smooth easing for natural growth
            function easeOutExpo(t) {
                return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
            }
            
            // Gentle easing for style changes
            function easeInOutCubic(t) {
                return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
            }
            
            function animateStep() {
                if (currentStep >= steps) {
                    circle.setRadius(targetRadius);
                    // Set final enhanced style
                    circle.setStyle({
                        weight: 6, // Bold border
                        fillOpacity: hasAlert ? 0.25 : 0.15,
                        opacity: 0.8,
                        dashArray: hasAlert ? '8, 4' : '12, 6'
                    });
                    return;
                }
                
                currentStep++;
                var progress = currentStep / steps;
                var easedRadiusProgress = easeOutExpo(progress);
                var easedStyleProgress = easeInOutCubic(progress);
                
                // Calculate new radius with ultra-smooth easing
                var newRadius = startRadius + (radiusStep * easedRadiusProgress);
                circle.setRadius(newRadius);
                
                // Very gradual style changes for clean appearance
                var weightProgress = Math.min(progress * 1.5, 1);
                var opacityProgress = Math.min(progress * 1.2, 1);
                var fillOpacityProgress = Math.min(progress * 1.0, 1);
                
                // Apply gentle easing to style changes
                var finalWeight = weightProgress * 6;
                var finalOpacity = 0.1 + (opacityProgress * 0.7);
                var finalFillOpacity = fillOpacityProgress * (hasAlert ? 0.25 : 0.15);
                
                circle.setStyle({
                    weight: finalWeight,
                    opacity: finalOpacity,
                    fillOpacity: finalFillOpacity,
                    dashArray: hasAlert ? '8, 4' : '12, 6'
                });
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        // Animation functions removed for permanent circle display

        // Update device marker sizes and visibility based on zoom level
        function updateDeviceMarkerSizes() {
            var z = map.getZoom();
            var iconSize, iconAnchor, popupAnchor;
            var showDevicesThreshold = 12; // show device icons when zoomed in at or above this level
            
            if (z <= 13) {
                iconSize = [30, 30];
                iconAnchor = [15, 30];
                popupAnchor = [0, -30];
            } else if (z === 14) {
                iconSize = [35, 35];
                iconAnchor = [17, 35];
                popupAnchor = [0, -35];
            } else if (z === 15) {
                iconSize = [40, 40];
                iconAnchor = [20, 40];
                popupAnchor = [0, -40];
            } else if (z === 16) {
                iconSize = [45, 45];
                iconAnchor = [22, 45];
                popupAnchor = [0, -45];
            } else if (z === 17) {
                iconSize = [50, 50];
                iconAnchor = [25, 50];
                popupAnchor = [0, -50];
            } else { // 18+
                iconSize = [55, 55];
                iconAnchor = [27, 55];
                popupAnchor = [0, -55];
            }
            
            // Update all device markers
            allMarkers.forEach(function(item) {
                if (item.type === 'device' && item.originalIcon && item.marker) {
                    // Show/hide device markers based on zoom level
                    if (z >= showDevicesThreshold) {
                        if (!map.hasLayer(item.marker)) {
                            item.marker.addTo(map);
                        }
                        // Update icon size
                        var newIcon = L.icon({
                            iconUrl: item.originalIcon.options.iconUrl,
                            iconSize: iconSize,
                            iconAnchor: iconAnchor,
                            popupAnchor: popupAnchor
                        });
                        item.marker.setIcon(newIcon);
                    } else {
                        if (map.hasLayer(item.marker)) {
                            map.removeLayer(item.marker);
                        }
                    }
                }
            });
        }
        
        // Update device label visibility based on zoom level (static size)
        function updateDeviceLabelVisibility() {
            var z = map.getZoom();
            var showLabelsThreshold = 17; // Show labels only when zoomed in closely
            
            allMarkers.forEach(function(item) {
                if (item.type === 'device_label' && item.marker) {
                    // Show/hide labels only at closer zoom levels
                    if (z >= showLabelsThreshold) {
                        if (!map.hasLayer(item.marker)) {
                            item.marker.addTo(map);
                        }
                        // Labels stay static size - no resizing
                    } else {
                        if (map.hasLayer(item.marker)) {
                            map.removeLayer(item.marker);
                        }
                    }
                }
            });
        }

        // Hide barangay circles when zooming in, but keep barangay icons visible
        function updateBarangayOverlaysVisibility() {
            var z = map.getZoom();
            var hideCirclesThreshold = 12; // hide circles when zoomed in at or above this level
            
            // Hide circles when zoomed in, but keep barangay icons visible
            if (z >= hideCirclesThreshold) {
                // Remove all barangay circles from map
                allBarangayLayers.forEach(function(layer){
                    if (layer && typeof layer.setStyle === 'function' && typeof layer.getRadius === 'function') {
                        // Remove circle from map
                        if (map.hasLayer(layer)) {
                            map.removeLayer(layer);
                        }
                    }
                    // Keep barangay icon markers visible at all zoom levels
                    else if (layer && layer.getLatLng && typeof layer.setOpacity === 'function') {
                        // Ensure barangay icons remain visible
                        animateOpacity(layer, 1);
                    }
                });
                
                // Remove selected barangay circle if it exists
                if (selectedBarangayCircle && map.hasLayer(selectedBarangayCircle)) {
                    map.removeLayer(selectedBarangayCircle);
                }
            } else {
                // Show circles when zoomed out (below threshold)
                var fillOpacity, weight, strokeOpacity, radius;
                var hideThreshold = 13; // hide when zoomed out at or below this level
                
                if (z <= hideThreshold) {
                    fillOpacity = 0.0; strokeOpacity = 0.0; weight = 0; radius = 50;
                } else if (z === 14) {
                    fillOpacity = 0.08; strokeOpacity = 0.6; weight = 3; radius = 200;
                } else if (z === 15) {
                    fillOpacity = 0.12; strokeOpacity = 0.7; weight = 4; radius = 400;
                } else if (z === 16) {
                    fillOpacity = 0.18; strokeOpacity = 0.8; weight = 5; radius = 600;
                } else if (z === 17) {
                    fillOpacity = 0.22; strokeOpacity = 0.9; weight = 6; radius = 800;
                } else { // 18+
                    fillOpacity = 0.25; strokeOpacity = 1.0; weight = 7; radius = 1200;
                }
                
                // Update barangay circles with smooth animation
                allBarangayLayers.forEach(function(layer){
                    if (layer && typeof layer.setStyle === 'function' && typeof layer.getRadius === 'function') {
                        // Add circle back to map if not already there
                        if (!map.hasLayer(layer)) {
                            layer.addTo(map);
                        }
                        // Animate circle properties including radius with enhanced styling
                        animateEnhancedCircleStyle(layer, { 
                            fillOpacity: fillOpacity, 
                            opacity: strokeOpacity, 
                            weight: weight,
                            radius: radius
                        });
                    }
                    // Keep barangay icon markers visible at all zoom levels
                    else if (layer && layer.getLatLng && typeof layer.setOpacity === 'function') {
                        animateOpacity(layer, 1);
                    }
                });
                
                if (selectedBarangayCircle && typeof selectedBarangayCircle.setStyle === 'function') {
                    animateEnhancedCircleStyle(selectedBarangayCircle, { 
                        fillOpacity: fillOpacity, 
                        opacity: strokeOpacity, 
                        weight: weight,
                        radius: radius
                    });
                }
            }
        }

        // Enhanced smooth animation function for circle style changes
        function animateEnhancedCircleStyle(circle, targetStyle) {
            if (!circle || typeof circle.setStyle !== 'function') return;
            
            var currentStyle = circle.options || {};
            var duration = 1000; // Animation duration in milliseconds
            var steps = 35; // More steps for smoother animation
            var stepDuration = duration / steps;
            
            var startFillOpacity = currentStyle.fillOpacity || 0;
            var startOpacity = currentStyle.opacity || 0;
            var startWeight = currentStyle.weight || 0;
            var startRadius = circle.getRadius ? circle.getRadius() : 0;
            
            var fillOpacityStep = (targetStyle.fillOpacity - startFillOpacity) / steps;
            var opacityStep = (targetStyle.opacity - startOpacity) / steps;
            var weightStep = (targetStyle.weight - startWeight) / steps;
            var radiusStep = ((targetStyle.radius || startRadius) - startRadius) / steps;
            
            var currentStep = 0;
            
            // Easing function for smooth animation
            function easeInOutCubic(t) {
                return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
            }
            
            function animateStep() {
                if (currentStep >= steps) {
                    circle.setStyle({
                        fillOpacity: targetStyle.fillOpacity,
                        opacity: targetStyle.opacity,
                        weight: targetStyle.weight,
                        dashArray: targetStyle.dashArray || '10, 5'
                    });
                    if (targetStyle.radius && circle.setRadius) {
                        circle.setRadius(targetStyle.radius);
                    }
                    return;
                }
                
                currentStep++;
                var progress = currentStep / steps;
                var easedProgress = easeInOutCubic(progress);
                
                var newFillOpacity = startFillOpacity + (fillOpacityStep * easedProgress);
                var newOpacity = startOpacity + (opacityStep * easedProgress);
                var newWeight = startWeight + (weightStep * easedProgress);
                var newRadius = startRadius + (radiusStep * easedProgress);
                
                circle.setStyle({
                    fillOpacity: newFillOpacity,
                    opacity: newOpacity,
                    weight: newWeight,
                    dashArray: targetStyle.dashArray || '10, 5'
                });
                
                if (targetStyle.radius && circle.setRadius) {
                    circle.setRadius(newRadius);
                }
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        // Smooth animation function for circle style changes (legacy function for compatibility)
        function animateCircleStyle(circle, targetStyle) {
            if (!circle || typeof circle.setStyle !== 'function') return;
            
            var currentStyle = circle.options || {};
            var duration = 1200; // Animation duration in milliseconds
            var steps = 30; // Number of animation steps
            var stepDuration = duration / steps;
            
            var startFillOpacity = currentStyle.fillOpacity || 0;
            var startOpacity = currentStyle.opacity || 0;
            var startWeight = currentStyle.weight || 0;
            var startRadius = circle.getRadius ? circle.getRadius() : 0;
            
            var fillOpacityStep = (targetStyle.fillOpacity - startFillOpacity) / steps;
            var opacityStep = (targetStyle.opacity - startOpacity) / steps;
            var weightStep = (targetStyle.weight - startWeight) / steps;
            var radiusStep = ((targetStyle.radius || startRadius) - startRadius) / steps;
            
            var currentStep = 0;
            
            function animateStep() {
                if (currentStep >= steps) {
                    circle.setStyle({
                        fillOpacity: targetStyle.fillOpacity,
                        opacity: targetStyle.opacity,
                        weight: targetStyle.weight
                    });
                    if (targetStyle.radius && circle.setRadius) {
                        circle.setRadius(targetStyle.radius);
                    }
                    return;
                }
                
                currentStep++;
                var newFillOpacity = startFillOpacity + (fillOpacityStep * currentStep);
                var newOpacity = startOpacity + (opacityStep * currentStep);
                var newWeight = startWeight + (weightStep * currentStep);
                var newRadius = startRadius + (radiusStep * currentStep);
                
                circle.setStyle({
                    fillOpacity: newFillOpacity,
                    opacity: newOpacity,
                    weight: newWeight
                });
                
                if (targetStyle.radius && circle.setRadius) {
                    circle.setRadius(newRadius);
                }
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        // Smooth animation function for opacity changes
        function animateOpacity(element, targetOpacity) {
            if (!element || typeof element.setOpacity !== 'function') return;
            
            var currentOpacity = element.options.opacity || 1;
            var duration = 600; // Animation duration in milliseconds
            var steps = 15; // Number of animation steps
            var stepDuration = duration / steps;
            
            var opacityStep = (targetOpacity - currentOpacity) / steps;
            var currentStep = 0;
            
            function animateStep() {
                if (currentStep >= steps) {
                    element.setOpacity(targetOpacity);
                    return;
                }
                
                currentStep++;
                var newOpacity = currentOpacity + (opacityStep * currentStep);
                element.setOpacity(newOpacity);
                
                setTimeout(animateStep, stepDuration);
            }
            
            animateStep();
        }

        map.on('zoomend', function() {
            updateBarangayOverlaysVisibility();
            updateDeviceMarkerSizes();
            updateDeviceLabelVisibility(); // Update label visibility only (no resizing)
            applyFilters(); // Reapply filters to respect zoom level
            
            // Remove barangay circle when user zooms
            if (selectedBarangayCircle) {
                map.removeLayer(selectedBarangayCircle);
                selectedBarangayCircle = null;
            }
        });










        // Maps navigation functions
        function redirectToMaps() {
            window.open('../../mapping/php/map.php', '_blank');
        }

        function takeMapScreenshot() {
            // Hide the floating buttons temporarily for clean screenshot
            const mapsNavigation = document.querySelector('.maps-navigation');
            const originalDisplay = mapsNavigation.style.display;
            mapsNavigation.style.display = 'none';
            
            // Use html2canvas to capture the map
            if (typeof html2canvas !== 'undefined') {
                html2canvas(document.getElementById('map'), {
                    backgroundColor: '#ffffff',
                    scale: 1,
                    useCORS: true,
                    allowTaint: true
                }).then(function(canvas) {
                    // Create download link
                    const link = document.createElement('a');
                    link.download = 'fire-incidents-map-' + new Date().toISOString().slice(0, 10) + '.png';
                    link.href = canvas.toDataURL();
                    link.click();
                    
                    // Restore the floating buttons
                    mapsNavigation.style.display = originalDisplay;
                }).catch(function(error) {
                    console.error('Screenshot failed:', error);
                    alert('Screenshot failed. Please try again.');
                    mapsNavigation.style.display = originalDisplay;
                });
            } else {
                // Fallback: use browser's built-in screenshot capability
                if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
                    navigator.mediaDevices.getDisplayMedia({ video: true })
                        .then(function(stream) {
                            const video = document.createElement('video');
                            video.srcObject = stream;
                            video.play();
                            
                            setTimeout(() => {
                                const canvas = document.createElement('canvas');
                                canvas.width = video.videoWidth;
                                canvas.height = video.videoHeight;
                                const ctx = canvas.getContext('2d');
                                ctx.drawImage(video, 0, 0);
                                
                                const link = document.createElement('a');
                                link.download = 'fire-incidents-map-' + new Date().toISOString().slice(0, 10) + '.png';
                                link.href = canvas.toDataURL();
                                link.click();
                                
                                stream.getTracks().forEach(track => track.stop());
                                mapsNavigation.style.display = originalDisplay;
                            }, 1000);
                        })
                        .catch(function(error) {
                            console.error('Screenshot failed:', error);
                            alert('Screenshot failed. Please try using browser\'s print screen function.');
                            mapsNavigation.style.display = originalDisplay;
                        });
                } else {
                    alert('Screenshot not supported in this browser. Please use browser\'s print screen function.');
                    mapsNavigation.style.display = originalDisplay;
                }
            }
        }

        function fitToAllBarangays() {
            var bounds = L.latLngBounds([]);
            var hasAny = false;
            allBarangayLayers.forEach(function(layer){
                if (layer.getBounds) {
                    bounds.extend(layer.getBounds());
                    hasAny = true;
                } else if (layer.getLatLng) {
                    bounds.extend(layer.getLatLng());
                    hasAny = true;
                }
            });
            if (hasAny) {
                try { map.fitBounds(bounds.pad(0.2)); } catch (e) {}
            }
        }

        // Auto-fit when barangay filter changes
        document.getElementById('barangayFilter').addEventListener('change', function(e) {
            var val = e.target.value;
            applyFilters();
            fitToBarangay(val);
            drawBarangayCircle(val);
        });

        // If a barangay is preselected on load, draw and fit to it
        (function initBarangayCircleOnLoad(){
            var initial = (document.getElementById('barangayFilter').value || '').toString();
            // If server suggests a preselected barangay, override initial
            var preselect = <?php echo json_encode($preselectBarangayId); ?>;
            if (preselect) {
                document.getElementById('barangayFilter').value = String(preselect);
                initial = String(preselect);
            }
            if (initial) {
                applyFilters();
                fitToBarangay(initial);
                drawBarangayCircle(initial);
            }
            // Show barangay overlays by default
            toggleAllBarangays(true);
            if (!initial) { fitToAllBarangays(); }
            // If preselected barangay exists but has no buildings, ensure it is visible from overlays
            if (initial && (!selectedBarangayCircle || !map.hasLayer(selectedBarangayCircle))) {
                drawBarangayCircle(initial);
            }
        })();


        // Removed: showBuildingInformation function - buildings are no longer used

        // Function to show detailed barangay popup
        function showDetailedBarangayPopup(e, barangayName, hasAlert, lat, lng, deviceCount, incidentCount) {
            
            // Create detailed popup content
            var popupContent = 
                '<div class="info-window" style="max-width: 300px; text-align: left;">' +
                '<div class="brgy-title" style="text-align: center; margin-bottom: 15px;">' + barangayName + '</div>' +
                '<div style="margin-bottom: 10px;">' +
                '<strong>Status:</strong> ' + (hasAlert ? 'With fire history' : 'Safe') +
                '</div>' +
                '<div style="margin-bottom: 10px;">' +
                '<strong>Coordinates:</strong> ' + lat.toFixed(5) + ', ' + lng.toFixed(5) +
                '</div>' +
                '<div style="margin-bottom: 10px;">' +
                '<strong>Devices:</strong> ' + deviceCount +
                '</div>' +
                '<div style="margin-bottom: 10px;">' +
                '<strong>Fire Incidents:</strong> ' + incidentCount +
                '</div>' +
                '<div style="text-align: center; margin-top: 15px; font-size: 11px; color: #6b7280;">' +
                'Click anywhere to close' +
                '</div>' +
                '</div>';
            
            // Create and show popup
            var popup = L.popup({
                closeButton: false,
                autoClose: false,
                closeOnClick: true,
                className: 'detailed-barangay-popup'
            })
            .setLatLng([lat, lng])
            .setContent(popupContent)
            .openOn(map);
            
            // Close popup when clicking on map
            map.on('click', function() {
                map.closePopup(popup);
            });
        }

        // Function to show all devices in a specific barangay
        function showDevicesInBarangay(barangayId, barangayName) {
            console.log('Showing devices for barangay:', barangayId, barangayName);
            
            // Set the barangay filter to show only devices from this barangay
            document.getElementById('barangayFilter').value = barangayId;
            
            // Hide barangay overlays when showing devices
            toggleAllBarangays(false);
            
            // Apply filters to show only devices from this barangay
            applyFilters();
            
            // Draw the barangay circle to highlight the selected area
            drawBarangayCircle(barangayId);
            
            // Show a notification or update UI to indicate which barangay is selected
            console.log('Devices in ' + barangayName + ' are now visible');
        }

        // Global variable to track hover popup
        var hoverPopup = null;

        // Function to show barangay information on hover
        function showBarangayHoverInfo(e, barangayName, hasAlert, lat, lng, deviceCount, incidentCount, fireRiskLevel, heatData, deviceStats, safetyStats, barangayId) {
            // Remove any existing hover popup
            if (hoverPopup) {
                map.removeLayer(hoverPopup);
            }
            
            // Determine risk level color - use filtered risk level if date filter is applied
            var displayRiskLevel = getFilteredBarangayRiskLevel(barangayId) || fireRiskLevel;
            var riskColor = '#16a34a'; // Default blue
            var riskText = 'SAFE';
            
            switch(displayRiskLevel) {
                case 'HIGH':
                    riskColor = '#dc2626'; // Red
                    riskText = 'HIGH RISK';
                    break;
                case 'MEDIUM':
                    riskColor = '#f59e0b'; // Orange
                    riskText = 'MEDIUM RISK';
                    break;
                case 'LOW':
                    riskColor = '#10b981'; // Green
                    riskText = 'LOW RISK';
                    break;
                default:
                    riskColor = '#16a34a'; // Blue
                    riskText = 'SAFE';
            }
            
            // Prepare comprehensive data display sections
            var heatDataHtml = '';
            // Use filtered heat data if date filter is applied, otherwise use original data
            var displayHeatData = getFilteredBarangayHeatData(barangayId) || heatData;
            
            if (displayHeatData && displayHeatData.total_readings > 0) {
                var latestHeatColor = displayHeatData.latest_heat > 50 ? '#dc2626' : displayHeatData.latest_heat > 30 ? '#f59e0b' : '#10b981';
                var avgHeatColor = displayHeatData.avg_heat > 50 ? '#dc2626' : displayHeatData.avg_heat > 30 ? '#f59e0b' : '#10b981';
                
                heatDataHtml = 
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🌡️ Heat Data' + (getFilteredBarangayHeatData(barangayId) ? ' (Filtered)' : '') + '</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Latest:</strong> <span style="color: ' + latestHeatColor + '; font-weight: bold;">' + displayHeatData.latest_heat + '°C</span> <span style="font-size: 11px; color: #6b7280;">(' + displayHeatData.latest_heat_timestamp + ')</span></div>' +
                    '<div style="margin-bottom: 4px;"><strong>Average:</strong> <span style="color: ' + avgHeatColor + '; font-weight: bold;">' + Math.round(displayHeatData.avg_heat) + '°C</span></div>' +
                    '<div style="margin-bottom: 4px;"><strong>Range:</strong> ' + displayHeatData.min_heat + '°C - ' + displayHeatData.max_heat + '°C</div>' +
                    '<div style="font-size: 11px; color: #6b7280;">(' + displayHeatData.total_readings + ' readings)</div>' +
                    
                    // Show original data comparison if filtered
                    (getFilteredBarangayHeatData(barangayId) && heatData && heatData.total_readings > 0 ? 
                    '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e5e7eb;">' +
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📊 All-Time Comparison</div>' +
                    '<div style="margin-bottom: 4px;"><strong>All-Time Average:</strong> <span style="color: ' + (heatData.avg_heat > 50 ? '#dc2626' : heatData.avg_heat > 30 ? '#f59e0b' : '#10b981') + '; font-weight: bold;">' + Math.round(heatData.avg_heat) + '°C</span></div>' +
                    '<div style="margin-bottom: 4px;"><strong>All-Time Range:</strong> ' + heatData.min_heat + '°C - ' + heatData.max_heat + '°C</div>' +
                    '<div style="font-size: 11px; color: #6b7280;">(' + heatData.total_readings + ' total readings)</div>' +
                    '</div>' : '') +
                    
                    // Monthly Heat Trends Section - Always show original monthly data
                    (heatData && heatData.jan_avg_heat > 0 ? 
                    '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e5e7eb;">' +
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📅 Monthly Heat Trends (All Time)</div>' +
                    '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; font-size: 11px;">' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.jan_avg_heat > 0 ? (heatData.jan_avg_heat > 50 ? '#dc2626' : heatData.jan_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.jan_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jan</strong><br>' + Math.round(heatData.jan_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.feb_avg_heat > 0 ? (heatData.feb_avg_heat > 50 ? '#dc2626' : heatData.feb_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.feb_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Feb</strong><br>' + Math.round(heatData.feb_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.mar_avg_heat > 0 ? (heatData.mar_avg_heat > 50 ? '#dc2626' : heatData.mar_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.mar_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Mar</strong><br>' + Math.round(heatData.mar_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.apr_avg_heat > 0 ? (heatData.apr_avg_heat > 50 ? '#dc2626' : heatData.apr_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.apr_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Apr</strong><br>' + Math.round(heatData.apr_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.may_avg_heat > 0 ? (heatData.may_avg_heat > 50 ? '#dc2626' : heatData.may_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.may_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>May</strong><br>' + Math.round(heatData.may_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.jun_avg_heat > 0 ? (heatData.jun_avg_heat > 50 ? '#dc2626' : heatData.jun_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.jun_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jun</strong><br>' + Math.round(heatData.jun_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.jul_avg_heat > 0 ? (heatData.jul_avg_heat > 50 ? '#dc2626' : heatData.jul_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.jul_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jul</strong><br>' + Math.round(heatData.jul_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.aug_avg_heat > 0 ? (heatData.aug_avg_heat > 50 ? '#dc2626' : heatData.aug_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.aug_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Aug</strong><br>' + Math.round(heatData.aug_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.sep_avg_heat > 0 ? (heatData.sep_avg_heat > 50 ? '#dc2626' : heatData.sep_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.sep_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Sep</strong><br>' + Math.round(heatData.sep_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.oct_avg_heat > 0 ? (heatData.oct_avg_heat > 50 ? '#dc2626' : heatData.oct_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.oct_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Oct</strong><br>' + Math.round(heatData.oct_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.nov_avg_heat > 0 ? (heatData.nov_avg_heat > 50 ? '#dc2626' : heatData.nov_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.nov_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Nov</strong><br>' + Math.round(heatData.nov_avg_heat) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.dec_avg_heat > 0 ? (heatData.dec_avg_heat > 50 ? '#dc2626' : heatData.dec_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.dec_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Dec</strong><br>' + Math.round(heatData.dec_avg_heat) + '°C</div>' +
                    '</div>' : '') +
                    (getFilteredBarangayHeatData(barangayId) ? '<div style="font-size: 10px; color: #6b7280; margin-top: 4px; text-align: center;">📅 Current data filtered for selected date range</div>' : '');
            } else {
                heatDataHtml = 
                    '<div style="border-top: 1px solid #e5e7eb; margin-top: 8px; padding-top: 8px;">' +
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🌡️ Heat Data</div>' +
                    '<div style="color: #6b7280; font-size: 12px;">No heat data available' + (getFilteredBarangayHeatData(barangayId) ? ' in selected date range' : '') + '</div>' +
                    '</div>';
            }
            
            // Always show monthly calendar if original heatData exists
            if (heatData && heatData.jan_avg_heat !== undefined) {
                heatDataHtml += 
                    '<div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e5e7eb;">' +
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📅 Monthly Heat Trends (All Time)</div>' +
                    '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; font-size: 11px;">' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.jan_avg_heat > 0 ? (heatData.jan_avg_heat > 50 ? '#dc2626' : heatData.jan_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.jan_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jan</strong><br>' + Math.round(heatData.jan_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.feb_avg_heat > 0 ? (heatData.feb_avg_heat > 50 ? '#dc2626' : heatData.feb_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.feb_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Feb</strong><br>' + Math.round(heatData.feb_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.mar_avg_heat > 0 ? (heatData.mar_avg_heat > 50 ? '#dc2626' : heatData.mar_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.mar_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Mar</strong><br>' + Math.round(heatData.mar_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.apr_avg_heat > 0 ? (heatData.apr_avg_heat > 50 ? '#dc2626' : heatData.apr_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.apr_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Apr</strong><br>' + Math.round(heatData.apr_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.may_avg_heat > 0 ? (heatData.may_avg_heat > 50 ? '#dc2626' : heatData.may_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.may_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>May</strong><br>' + Math.round(heatData.may_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.jun_avg_heat > 0 ? (heatData.jun_avg_heat > 50 ? '#dc2626' : heatData.jun_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.jun_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jun</strong><br>' + Math.round(heatData.jun_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.jul_avg_heat > 0 ? (heatData.jul_avg_heat > 50 ? '#dc2626' : heatData.jul_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.jul_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jul</strong><br>' + Math.round(heatData.jul_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.aug_avg_heat > 0 ? (heatData.aug_avg_heat > 50 ? '#dc2626' : heatData.aug_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.aug_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Aug</strong><br>' + Math.round(heatData.aug_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.sep_avg_heat > 0 ? (heatData.sep_avg_heat > 50 ? '#dc2626' : heatData.sep_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.sep_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Sep</strong><br>' + Math.round(heatData.sep_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.oct_avg_heat > 0 ? (heatData.oct_avg_heat > 50 ? '#dc2626' : heatData.oct_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.oct_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Oct</strong><br>' + Math.round(heatData.oct_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.nov_avg_heat > 0 ? (heatData.nov_avg_heat > 50 ? '#dc2626' : heatData.nov_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.nov_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Nov</strong><br>' + Math.round(heatData.nov_avg_heat || 0) + '°C</div>' +
                    '<div style="text-align: center; padding: 2px; background: ' + (heatData.dec_avg_heat > 0 ? (heatData.dec_avg_heat > 50 ? '#dc2626' : heatData.dec_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (heatData.dec_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Dec</strong><br>' + Math.round(heatData.dec_avg_heat || 0) + '°C</div>' +
                    '</div>' +
                    '<div style="font-size: 10px; color: #6b7280; margin-top: 4px; text-align: center;">Monthly averages (red: >50°C, orange: >30°C, green: ≤30°C)</div>' +
                    '</div>';
            }
            
            // Device Statistics Section
            var deviceStatsHtml = '';
            if (deviceStats && deviceStats.total_devices > 0) {
                var onlineColor = deviceStats.online_devices > 0 ? '#10b981' : '#6b7280';
                var offlineColor = deviceStats.offline_devices > 0 ? '#f59e0b' : '#6b7280';
                var faultyColor = deviceStats.faulty_devices > 0 ? '#dc2626' : '#6b7280';
                
                deviceStatsHtml = 
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📱 Device Status</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Total:</strong> ' + deviceStats.total_devices + ' devices</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Online:</strong> <span style="color: ' + onlineColor + '; font-weight: bold;">' + deviceStats.online_devices + '</span> | <strong>Offline:</strong> <span style="color: ' + offlineColor + '; font-weight: bold;">' + deviceStats.offline_devices + '</span> | <strong>Faulty:</strong> <span style="color: ' + faultyColor + '; font-weight: bold;">' + deviceStats.faulty_devices + '</span></div>' +
                    '<div style="margin-bottom: 4px;"><strong>Active:</strong> ' + deviceStats.active_devices + ' | <strong>Recent Activity:</strong> ' + deviceStats.devices_with_recent_activity + '</div>' +
                    (deviceStats.latest_device_activity ? '<div style="font-size: 11px; color: #6b7280;">Last Activity: ' + deviceStats.latest_device_activity + '</div>' : '');
            } else {
                deviceStatsHtml = 
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📱 Device Status</div>' +
                    '<div style="color: #6b7280; font-size: 12px;">No devices registered</div>';
            }
            
            
            // Safety Features Section
            var safetyStatsHtml = '';
            if (safetyStats && safetyStats.total_buildings > 0) {
                var complianceColor = safetyStats.fully_compliant_buildings > 0 ? '#10b981' : safetyStats.partially_compliant_buildings > 0 ? '#f59e0b' : '#dc2626';
                var inspectionColor = safetyStats.recently_inspected > 0 ? '#10b981' : safetyStats.overdue_inspection > 0 ? '#dc2626' : '#f59e0b';
                
                safetyStatsHtml = 
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🛡️ Safety Features</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Buildings:</strong> ' + safetyStats.total_buildings + ' total</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Compliance:</strong> <span style="color: ' + complianceColor + '; font-weight: bold;">' + safetyStats.fully_compliant_buildings + '</span> full | ' + safetyStats.partially_compliant_buildings + ' partial | ' + safetyStats.non_compliant_buildings + ' non-compliant</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Features:</strong> Sprinklers: ' + safetyStats.buildings_with_sprinklers + ' | Alarms: ' + safetyStats.buildings_with_fire_alarms + ' | Extinguishers: ' + safetyStats.buildings_with_extinguishers + '</div>' +
                    '<div style="margin-bottom: 4px;"><strong>Inspection:</strong> <span style="color: ' + inspectionColor + '; font-weight: bold;">' + safetyStats.recently_inspected + '</span> recent | ' + safetyStats.overdue_inspection + ' overdue | ' + safetyStats.never_inspected + ' never inspected</div>';
            } else {
                safetyStatsHtml = 
                    '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🛡️ Safety Features</div>' +
                    '<div style="color: #6b7280; font-size: 12px;">No building safety data available</div>';
            }
            
            // Create comprehensive hover popup content
            var hoverContent = 
                '<div style="background: rgba(255, 255, 255, 0.95); padding: 12px; border-radius: 8px; border: 2px solid ' + riskColor + '; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 12px; line-height: 1.4; min-width: 600px; max-width: 800px; backdrop-filter: blur(5px);">' +
                '<div style="text-align: center; margin-bottom: 10px; font-weight: bold; font-size: 14px; color: ' + riskColor + ';">' + barangayName + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Risk Level:</strong> <span style="color: ' + riskColor + '; font-weight: bold;">' + riskText + '</span>' + (getFilteredBarangayRiskLevel(barangayId) ? ' <span style="font-size: 10px; color: #6b7280;">(Filtered)</span>' : '') + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Devices:</strong> ' + deviceCount + ' | <strong>Fire Incidents:</strong> ' + incidentCount + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Emergency Incidents:</strong> ' + getFilteredEmergencyIncidentCountForBarangay(barangayId) + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Coordinates:</strong> ' + lat.toFixed(4) + ', ' + lng.toFixed(4) + '</div>' +
                
                // Horizontal layout for main sections
                '<div style="display: flex; gap: 12px; margin-top: 8px;">' +
                '<div style="flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                heatDataHtml +
                '</div>' +
                '<div style="flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                deviceStatsHtml +
                '</div>' +
                '<div style="flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                safetyStatsHtml +
                '</div>' +
                '</div>' +
                
                (hasAlert ? '<div style="color: #dc2626; font-weight: bold; text-align: center; margin-top: 8px; padding: 4px; background: rgba(220, 38, 38, 0.1); border-radius: 4px;">⚠️ ALERT ACTIVE</div>' : '') +
                '<div style="text-align: center; margin-top: 8px; font-size: 11px; color: #6b7280;">Click to view buildings</div>' +
                '</div>';
            
            // Create and show hover popup
            hoverPopup = L.popup({
                closeButton: false,
                autoClose: false,
                closeOnClick: false,
                className: 'barangay-hover-popup',
                offset: [0, -10]
            })
            .setLatLng([lat, lng])
            .setContent(hoverContent)
            .openOn(map);
        }

        // Function to hide barangay hover information
        function hideBarangayHoverInfo() {
            if (hoverPopup) {
                map.removeLayer(hoverPopup);
                hoverPopup = null;
            }
        }

        // Global variable to track building hover popup
        var buildingHoverPopup = null;

        // Function to show building information on hover
        function showBuildingHoverInfo(e, buildingData, buildingStats) {
            // Remove any existing building hover popup
            if (buildingHoverPopup) {
                map.removeLayer(buildingHoverPopup);
            }
            
            var lat = parseFloat(buildingData.latitude);
            var lng = parseFloat(buildingData.longitude);
            
            // Determine building risk level based on filtered incidents and safety features
            var riskLevel = 'SAFE';
            var riskColor = '#10b981'; // Green
            
            // Check if there are any incidents in the filtered date range
            if (buildingStats.emergency_incidents > 0 || buildingStats.flame_detections > 0) {
                riskLevel = 'HIGH RISK';
                riskColor = '#dc2626'; // Red
            } else if (buildingStats.total_incidents > 0 || buildingStats.latest_heat > 50) {
                riskLevel = 'MEDIUM RISK';
                riskColor = '#f59e0b'; // Orange
            } else if (buildingStats.total_incidents > 0 || buildingStats.latest_heat > 30) {
                riskLevel = 'LOW RISK';
                riskColor = '#f59e0b'; // Orange
            } else {
                // No incidents in filtered range - show as safe
                riskLevel = 'SAFE';
                riskColor = '#10b981'; // Green
            }
            
            // Safety compliance calculation
            var safetyFeatures = [
                buildingData.has_sprinkler_system,
                buildingData.has_fire_alarm,
                buildingData.has_fire_extinguishers,
                buildingData.has_emergency_exits,
                buildingData.has_emergency_lighting,
                buildingData.has_fire_escape
            ];
            var safetyScore = safetyFeatures.reduce((sum, feature) => sum + (feature ? 1 : 0), 0);
            var compliancePercentage = Math.round((safetyScore / 6) * 100);
            var complianceColor = compliancePercentage >= 80 ? '#10b981' : compliancePercentage >= 50 ? '#f59e0b' : '#dc2626';
            
            // Device status colors
            var onlineColor = buildingStats.online_devices > 0 ? '#10b981' : '#6b7280';
            var offlineColor = buildingStats.offline_devices > 0 ? '#f59e0b' : '#6b7280';
            var faultyColor = buildingStats.faulty_devices > 0 ? '#dc2626' : '#6b7280';
            
            // Sensor data colors
            var heatColor = buildingStats.latest_heat > 50 ? '#dc2626' : buildingStats.latest_heat > 30 ? '#f59e0b' : '#10b981';
            var smokeColor = buildingStats.latest_smoke > 500 ? '#dc2626' : buildingStats.latest_smoke > 200 ? '#f59e0b' : '#10b981';
            var tempColor = buildingStats.latest_temp > 40 ? '#dc2626' : buildingStats.latest_temp > 30 ? '#f59e0b' : '#10b981';
            
            // Create comprehensive building hover popup content
            var hoverContent = 
                '<div style="background: rgba(255, 255, 255, 0.95); padding: 12px; border-radius: 8px; border: 2px solid ' + riskColor + '; box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 12px; line-height: 1.4; min-width: 700px; max-width: 900px; backdrop-filter: blur(5px);">' +
                '<div style="text-align: center; margin-bottom: 10px; font-weight: bold; font-size: 14px; color: ' + riskColor + ';">' + buildingData.building_name + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Type:</strong> ' + buildingData.building_type + ' | <strong>Floors:</strong> ' + buildingData.total_floors + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Risk Level:</strong> <span style="color: ' + riskColor + '; font-weight: bold;">' + riskLevel + '</span></div>' +
                '<div style="margin-bottom: 6px;"><strong>Address:</strong> ' + (buildingData.address || 'No address') + '</div>' +
                '<div style="margin-bottom: 6px;"><strong>Coordinates:</strong> ' + lat.toFixed(4) + ', ' + lng.toFixed(4) + '</div>' +
                
                // Horizontal layout for main sections
                '<div style="display: flex; gap: 12px; margin-top: 8px;">' +
                '<div style="flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                // Device Status Section
                '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📱 Device Status</div>' +
                '<div style="margin-bottom: 4px;"><strong>Total:</strong> ' + buildingStats.total_devices + ' devices</div>' +
                '<div style="margin-bottom: 4px;"><strong>Online:</strong> <span style="color: ' + onlineColor + '; font-weight: bold;">' + buildingStats.online_devices + '</span> | <strong>Offline:</strong> <span style="color: ' + offlineColor + '; font-weight: bold;">' + buildingStats.offline_devices + '</span> | <strong>Faulty:</strong> <span style="color: ' + faultyColor + '; font-weight: bold;">' + buildingStats.faulty_devices + '</span></div>' +
                '<div style="margin-bottom: 4px;"><strong>Active:</strong> ' + buildingStats.active_devices + '</div>' +
                (buildingStats.latest_device_activity ? '<div style="font-size: 11px; color: #6b7280;">Last Activity: ' + buildingStats.latest_device_activity + '</div>' : '') +
                '</div>' +
                
                '<div style="flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                // Fire Incidents Section
                '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🔥 Fire Incidents</div>' +
                '<div style="margin-bottom: 4px;"><strong>Total:</strong> ' + buildingStats.total_incidents + ' incidents</div>' +
                '<div style="margin-bottom: 4px;"><strong>Emergency:</strong> <span style="color: ' + (buildingStats.emergency_incidents > 0 ? '#dc2626' : '#10b981') + '; font-weight: bold;">' + buildingStats.emergency_incidents + '</span> | <strong>Acknowledged:</strong> ' + buildingStats.acknowledged_incidents + '</div>' +
                '<div style="margin-bottom: 4px;"><strong>Flame Detections:</strong> <span style="color: ' + (buildingStats.flame_detections > 0 ? '#dc2626' : '#10b981') + '; font-weight: bold;">' + buildingStats.flame_detections + '</span></div>' +
                (buildingStats.latest_incident_timestamp !== 'N/A' ? '<div style="font-size: 11px; color: #6b7280;">Latest: ' + buildingStats.latest_incident_timestamp + '</div>' : '') +
                '</div>' +
                
                '<div style="flex: 1; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                // Sensor Data Section
                '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🌡️ Sensor Data</div>' +
                '<div style="margin-bottom: 4px;"><strong>Heat:</strong> <span style="color: ' + heatColor + '; font-weight: bold;">' + buildingStats.latest_heat + '°C</span> (Avg: ' + Math.round(buildingStats.avg_heat) + '°C)</div>' +
                '<div style="margin-bottom: 4px;"><strong>Smoke:</strong> <span style="color: ' + smokeColor + '; font-weight: bold;">' + buildingStats.latest_smoke + '</span> (Avg: ' + Math.round(buildingStats.avg_smoke) + ')</div>' +
                '<div style="margin-bottom: 4px;"><strong>Temperature:</strong> <span style="color: ' + tempColor + '; font-weight: bold;">' + buildingStats.latest_temp + '°C</span> (Avg: ' + Math.round(buildingStats.avg_temp) + '°C)</div>' +
                '<div style="font-size: 11px; color: #6b7280;">(' + buildingStats.total_readings + ' readings)</div>' +
                '</div>' +
                '</div>' +
                
                // Monthly Heat Trends Section (full width)
                '<div style="margin-top: 12px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">📅 Monthly Heat Trends</div>' +
                '<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; font-size: 11px;">' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.jan_avg_heat > 0 ? (buildingStats.jan_avg_heat > 50 ? '#dc2626' : buildingStats.jan_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.jan_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jan</strong><br>' + Math.round(buildingStats.jan_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.feb_avg_heat > 0 ? (buildingStats.feb_avg_heat > 50 ? '#dc2626' : buildingStats.feb_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.feb_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Feb</strong><br>' + Math.round(buildingStats.feb_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.mar_avg_heat > 0 ? (buildingStats.mar_avg_heat > 50 ? '#dc2626' : buildingStats.mar_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.mar_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Mar</strong><br>' + Math.round(buildingStats.mar_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.apr_avg_heat > 0 ? (buildingStats.apr_avg_heat > 50 ? '#dc2626' : buildingStats.apr_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.apr_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Apr</strong><br>' + Math.round(buildingStats.apr_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.may_avg_heat > 0 ? (buildingStats.may_avg_heat > 50 ? '#dc2626' : buildingStats.may_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.may_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>May</strong><br>' + Math.round(buildingStats.may_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.jun_avg_heat > 0 ? (buildingStats.jun_avg_heat > 50 ? '#dc2626' : buildingStats.jun_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.jun_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jun</strong><br>' + Math.round(buildingStats.jun_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.jul_avg_heat > 0 ? (buildingStats.jul_avg_heat > 50 ? '#dc2626' : buildingStats.jul_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.jul_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Jul</strong><br>' + Math.round(buildingStats.jul_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.aug_avg_heat > 0 ? (buildingStats.aug_avg_heat > 50 ? '#dc2626' : buildingStats.aug_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.aug_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Aug</strong><br>' + Math.round(buildingStats.aug_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.sep_avg_heat > 0 ? (buildingStats.sep_avg_heat > 50 ? '#dc2626' : buildingStats.sep_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.sep_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Sep</strong><br>' + Math.round(buildingStats.sep_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.oct_avg_heat > 0 ? (buildingStats.oct_avg_heat > 50 ? '#dc2626' : buildingStats.oct_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.oct_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Oct</strong><br>' + Math.round(buildingStats.oct_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.nov_avg_heat > 0 ? (buildingStats.nov_avg_heat > 50 ? '#dc2626' : buildingStats.nov_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.nov_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Nov</strong><br>' + Math.round(buildingStats.nov_avg_heat) + '°C</div>' +
                '<div style="text-align: center; padding: 2px; background: ' + (buildingStats.dec_avg_heat > 0 ? (buildingStats.dec_avg_heat > 50 ? '#dc2626' : buildingStats.dec_avg_heat > 30 ? '#f59e0b' : '#10b981') : '#f3f4f6') + '; color: ' + (buildingStats.dec_avg_heat > 0 ? 'white' : '#6b7280') + '; border-radius: 3px;"><strong>Dec</strong><br>' + Math.round(buildingStats.dec_avg_heat) + '°C</div>' +
                '</div>' +
                '<div style="font-size: 10px; color: #6b7280; margin-top: 4px; text-align: center;">Monthly averages (red: >50°C, orange: >30°C, green: ≤30°C)</div>' +
                '</div>' +
                
                // Safety Features Section (full width)
                '<div style="margin-top: 12px; border: 1px solid #e5e7eb; border-radius: 6px; padding: 8px;">' +
                '<div style="font-weight: bold; margin-bottom: 6px; color: #374151;">🛡️ Safety Features</div>' +
                '<div style="margin-bottom: 4px;"><strong>Compliance:</strong> <span style="color: ' + complianceColor + '; font-weight: bold;">' + compliancePercentage + '%</span> (' + safetyScore + '/6 features)</div>' +
                '<div style="margin-bottom: 4px;"><strong>Features:</strong> ' +
                (buildingData.has_sprinkler_system ? '✅ Sprinklers ' : '❌ Sprinklers ') +
                (buildingData.has_fire_alarm ? '✅ Alarms ' : '❌ Alarms ') +
                (buildingData.has_fire_extinguishers ? '✅ Extinguishers' : '❌ Extinguishers') +
                '</div>' +
                '<div style="margin-bottom: 4px;"><strong>Emergency:</strong> ' +
                (buildingData.has_emergency_exits ? '✅ Exits ' : '❌ Exits ') +
                (buildingData.has_emergency_lighting ? '✅ Lighting ' : '❌ Lighting ') +
                (buildingData.has_fire_escape ? '✅ Escape' : '❌ Escape') +
                '</div>' +
                (buildingData.last_inspected ? '<div style="font-size: 11px; color: #6b7280;">Last Inspected: ' + buildingData.last_inspected + '</div>' : '<div style="font-size: 11px; color: #dc2626;">Never Inspected</div>') +
                '</div>' +
                
                '<div style="text-align: center; margin-top: 8px; font-size: 11px; color: #6b7280;">Click for detailed information</div>' +
                '</div>';
            
            // Create and show hover popup
            buildingHoverPopup = L.popup({
                closeButton: false,
                autoClose: false,
                closeOnClick: false,
                className: 'building-hover-popup',
                offset: [0, -10]
            })
            .setLatLng([lat, lng])
            .setContent(hoverContent)
            .openOn(map);
        }

        // Function to hide building hover information
        function hideBuildingHoverInfo() {
            if (buildingHoverPopup) {
                map.removeLayer(buildingHoverPopup);
                buildingHoverPopup = null;
            }
        }

        // Animation functions removed for permanent circle display
    </script>
<?php include '../../components/scripts.php'; ?>
</body>
</html>
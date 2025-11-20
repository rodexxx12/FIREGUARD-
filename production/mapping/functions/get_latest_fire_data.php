<?php
function getLatestFireData($pdo) {
    $sql = "
    SELECT 
        f.*,
        b.building_name,
        b.building_type,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        d.device_name,
        d.device_number,
        d.status as device_status
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    LEFT JOIN devices d ON f.device_id = d.device_id
    ORDER BY f.timestamp DESC 
    LIMIT 1
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Query execution failed");
        }
    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

function getLatestFireDataByBuilding($pdo, $buildingId) {
    $sql = "
    SELECT 
        f.*,
        b.building_name,
        b.building_type,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        d.device_name,
        d.device_number,
        d.status as device_status
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    LEFT JOIN devices d ON f.device_id = d.device_id
    WHERE f.building_id = :building_id
    ORDER BY f.timestamp DESC 
    LIMIT 1
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':building_id', $buildingId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Query execution failed");
        }
    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
}

function getLatestFireDataByDevice($pdo, $deviceId) {
    $sql = "
    SELECT 
        f.*,
        b.building_name,
        b.building_type,
        b.address,
        b.latitude as building_lat,
        b.longitude as building_lng,
        d.device_name,
        d.device_number,
        d.status as device_status
    FROM fire_data f
    LEFT JOIN buildings b ON f.building_id = b.id
    LEFT JOIN devices d ON f.device_id = d.device_id
    WHERE f.device_id = :device_id
    ORDER BY f.timestamp DESC 
    LIMIT 1
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':device_id', $deviceId, PDO::PARAM_INT);
        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Query execution failed");
        }
    } catch (PDOException $e) {
        throw new Exception("Database query failed: " . $e->getMessage());
    }
} 
<?php
/**
 * Get building areas (100m border circles) for all buildings
 * Creates building areas if they don't exist
 */
function getBuildingAreas($pdo) {
    // First, ensure all buildings have corresponding building_areas entries
    ensureBuildingAreasExist($pdo);
    
    // Then fetch all building areas with building information
    $sql = "SELECT 
                ba.id,
                ba.building_id,
                ba.center_latitude,
                ba.center_longitude,
                ba.radius,
                ba.boundary_coordinates,
                b.building_name,
                b.building_type,
                b.address
            FROM building_areas ba
            INNER JOIN buildings b ON ba.building_id = b.id
            WHERE b.latitude IS NOT NULL 
            AND b.longitude IS NOT NULL
            AND ba.center_latitude IS NOT NULL 
            AND ba.center_longitude IS NOT NULL";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Ensure all buildings have corresponding building_areas entries
 * Creates missing entries with 100m radius circles
 */
function ensureBuildingAreasExist($pdo) {
    // Get all buildings without building_areas
    $sql = "SELECT b.id, b.latitude, b.longitude 
            FROM buildings b
            LEFT JOIN building_areas ba ON b.id = ba.building_id
            WHERE ba.id IS NULL 
            AND b.latitude IS NOT NULL 
            AND b.longitude IS NOT NULL";
    
    $stmt = $pdo->query($sql);
    $buildingsWithoutAreas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create building_areas for each building
    if (!empty($buildingsWithoutAreas)) {
        $insertSql = "INSERT INTO building_areas 
                     (building_id, center_latitude, center_longitude, radius, created_at, updated_at) 
                     VALUES (:building_id, :center_latitude, :center_longitude, 100.00, NOW(), NOW())";
        
        $insertStmt = $pdo->prepare($insertSql);
        
        foreach ($buildingsWithoutAreas as $building) {
            try {
                $insertStmt->execute([
                    ':building_id' => $building['id'],
                    ':center_latitude' => $building['latitude'],
                    ':center_longitude' => $building['longitude']
                ]);
            } catch (PDOException $e) {
                // Log error but continue with other buildings
                error_log("Error creating building area for building ID {$building['id']}: " . $e->getMessage());
            }
        }
    }
    
    // Also update existing building_areas if building coordinates changed
    $updateSql = "UPDATE building_areas ba
                  INNER JOIN buildings b ON ba.building_id = b.id
                  SET ba.center_latitude = b.latitude,
                      ba.center_longitude = b.longitude,
                      ba.updated_at = NOW()
                  WHERE (ba.center_latitude != b.latitude OR ba.center_longitude != b.longitude)
                  AND b.latitude IS NOT NULL 
                  AND b.longitude IS NOT NULL";
    
    try {
        $pdo->exec($updateSql);
    } catch (PDOException $e) {
        error_log("Error updating building areas: " . $e->getMessage());
    }
}


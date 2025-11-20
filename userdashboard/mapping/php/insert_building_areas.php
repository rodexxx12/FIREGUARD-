<?php
/**
 * Script to insert/update building_areas table with latitude and longitude
 * from existing buildings that have coordinates
 * 
 * This script will:
 * 1. Insert new building_areas records for buildings that have coordinates but no area record
 * 2. Update existing building_areas records if the building coordinates have changed
 * 
 * Usage: 
 * - Access via browser: http://localhost/DEFENDED/userdashboard/mapping/php/insert_building_areas.php
 * - Or run via command line: php insert_building_areas.php
 */

// Allow running from command line
if (php_sapi_name() !== 'cli') {
    session_start();
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../db/db.php';

// Check if user is logged in (optional - can be run as admin script)
// Uncomment if you want to restrict access when accessed via browser
// if (php_sapi_name() !== 'cli' && !isset($_SESSION['user_id'])) {
//     http_response_code(401);
//     die(json_encode(['success' => false, 'error' => 'Unauthorized']));
// }

try {
    $pdo = getMappingDBConnection();
    
    // Get all buildings that have latitude and longitude
    // Include both new buildings and existing ones that might need updates
    $sql = "SELECT b.id, b.latitude, b.longitude,
                   ba.id as area_id, ba.center_latitude as existing_lat, ba.center_longitude as existing_lng
            FROM buildings b
            LEFT JOIN building_areas ba ON ba.building_id = b.id
            WHERE b.latitude IS NOT NULL 
            AND b.longitude IS NOT NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($buildings)) {
        $response = [
            'success' => true,
            'message' => 'No buildings with coordinates found',
            'inserted' => 0,
            'updated' => 0
        ];
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];
    
    // Function to calculate circle boundary points
    function calculateCircleBoundary($centerLat, $centerLng, $radiusMeters = 100) {
        $points = [];
        $numPoints = 32;
        $earthRadius = 6371000; // Earth radius in meters
        
        for ($i = 0; $i < $numPoints; $i++) {
            $angle = ($i * 360) / $numPoints;
            $angleRad = deg2rad($angle);
            
            // Calculate point on circle
            $latOffset = ($radiusMeters / $earthRadius) * (180 / M_PI);
            $lngOffset = ($radiusMeters / ($earthRadius * cos(deg2rad($centerLat)))) * (180 / M_PI);
            
            $pointLat = $centerLat + $latOffset * cos($angleRad);
            $pointLng = $centerLng + $lngOffset * sin($angleRad);
            
            $points[] = [$pointLat, $pointLng];
        }
        
        return $points;
    }
    
    // Prepare statements
    $insertSql = "INSERT INTO building_areas 
                  (building_id, center_latitude, center_longitude, radius, boundary_coordinates) 
                  VALUES (?, ?, ?, ?, ?)";
    $insertStmt = $pdo->prepare($insertSql);
    
    $updateSql = "UPDATE building_areas 
                  SET center_latitude = ?, 
                      center_longitude = ?, 
                      radius = ?,
                      boundary_coordinates = ?,
                      updated_at = CURRENT_TIMESTAMP
                  WHERE building_id = ?";
    $updateStmt = $pdo->prepare($updateSql);
    
    foreach ($buildings as $building) {
        try {
            $buildingId = $building['id'];
            $latitude = (float)$building['latitude'];
            $longitude = (float)$building['longitude'];
            $radius = 100.00; // Default radius in meters
            
            // Calculate boundary coordinates
            $boundaryPoints = calculateCircleBoundary($latitude, $longitude, $radius);
            $boundaryJson = json_encode($boundaryPoints);
            
            // Check if area already exists
            if ($building['area_id']) {
                // Area exists - check if coordinates have changed
                $existingLat = (float)$building['existing_lat'];
                $existingLng = (float)$building['existing_lng'];
                
                // Update if coordinates are different (with small tolerance for floating point)
                if (abs($latitude - $existingLat) > 0.00000001 || abs($longitude - $existingLng) > 0.00000001) {
                    $updateStmt->execute([
                        $latitude,
                        $longitude,
                        $radius,
                        $boundaryJson,
                        $buildingId
                    ]);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                // No area exists - insert new record
                $insertStmt->execute([
                    $buildingId,
                    $latitude,
                    $longitude,
                    $radius,
                    $boundaryJson
                ]);
                $inserted++;
            }
        } catch (PDOException $e) {
            $errors[] = [
                'building_id' => $building['id'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    $response = [
        'success' => true,
        'message' => "Processed buildings: {$inserted} inserted, {$updated} updated, {$skipped} skipped",
        'inserted' => $inserted,
        'updated' => $updated,
        'skipped' => $skipped,
        'total_found' => count($buildings),
        'errors' => $errors
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    $errorResponse = [
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ];
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
    }
    echo json_encode($errorResponse, JSON_PRETTY_PRINT);
}
?>


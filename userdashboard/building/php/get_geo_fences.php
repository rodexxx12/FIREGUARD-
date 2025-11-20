<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include('../../db/db.php');

header('Content-Type: application/json');

try {
    $conn = getDatabaseConnection();
    
    // Get active geo-fences
    $stmt = $conn->prepare("SELECT id, city_name, country_code, ST_AsText(polygon) as polygon_wkt FROM geo_fences WHERE is_active = 1");
    $stmt->execute();
    $fences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [];
    foreach ($fences as $fence) {
        // Parse the WKT polygon to extract coordinates
        $polygon_wkt = $fence['polygon_wkt'];
        if (preg_match('/POLYGON\(\(([^)]+)\)\)/', $polygon_wkt, $matches)) {
            $coords_string = $matches[1];
            $coords = [];
            $pairs = explode(',', $coords_string);
            foreach ($pairs as $pair) {
                $pair = trim($pair);
                if (preg_match('/([0-9.-]+)\s+([0-9.-]+)/', $pair, $coord_matches)) {
                    // WKT format: lng lat, so we need to swap them for Leaflet [lat, lng]
                    $coords[] = [floatval($coord_matches[2]), floatval($coord_matches[1])]; // [lat, lng]
                }
            }
            $result[] = [
                'id' => $fence['id'],
                'city_name' => $fence['city_name'],
                'country_code' => $fence['country_code'],
                'polygon' => $coords
            ];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'geo_fences' => $result
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching geo fences: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch geo-fences'
    ]);
}
?>
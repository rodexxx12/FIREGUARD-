<?php
// Enable detailed error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Database connection
require_once 'db_config.php';

function get_active_geo_fences() {
    try {
        $conn = getDatabaseConnection();
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
        return $result;
    } catch (Exception $e) {
        error_log("Error fetching geo fences: " . $e->getMessage());
        return [];
    }
}

try {
    $geo_fences = get_active_geo_fences();
    
    if (empty($geo_fences)) {
        echo json_encode([
            'success' => false,
            'message' => 'No active geo-fences configured. Registration is currently disabled.',
            'fences' => []
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Geo-fences retrieved successfully',
            'fences' => $geo_fences
        ]);
    }
} catch (Exception $e) {
    error_log("Error in get_geo_fences.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving geo-fences: ' . $e->getMessage(),
        'fences' => []
    ]);
}
?>

<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ajax_helpers.php';

header('Content-Type: application/json');

try {
    $pdo = getDatabaseConnection();
    
    // Fetch unique locations from the database
    $stmt = $pdo->query("SELECT DISTINCT location FROM system_logs WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
    $locations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Format locations for better display
    $formattedLocations = [];
    $seenKeys = []; // To prevent duplicate keys
    
    foreach ($locations as $location) {
        // Clean and format the location
        $cleanLocation = trim($location);
        if (!empty($cleanLocation)) {
            // Extract city/area from location (assuming format like "Street, City" or just "City")
            $parts = explode(',', $cleanLocation);
            $city = trim(end($parts)); // Get the last part as city
            
            // If no comma found, use the whole location as city
            if (count($parts) === 1) {
                $city = $cleanLocation;
            }
            
            // Create a clean key for the filter
            $key = strtolower(str_replace([' ', '-', '_', '.', '/', '\\'], '', $city));
            
            // Ensure unique keys
            if (!in_array($key, $seenKeys)) {
                $seenKeys[] = $key;
                
                $formattedLocations[] = [
                    'key' => $key,
                    'value' => $cleanLocation, // Show full location in the dropdown
                    'full_location' => $cleanLocation
                ];
            }
        }
    }
    
    // Sort by city name for better user experience
    usort($formattedLocations, function($a, $b) {
        return strcasecmp($a['value'], $b['value']);
    });
    
    echo json_encode([
        'success' => true,
        'locations' => $formattedLocations,
        'total' => count($formattedLocations)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 
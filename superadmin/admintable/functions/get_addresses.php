<?php
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ajax_helpers.php';

header('Content-Type: application/json');

try {
    // Fetch unique addresses from the database
    $stmt = $pdo->query("SELECT DISTINCT address FROM users WHERE address IS NOT NULL AND address != '' ORDER BY address ASC");
    $addresses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Format addresses for better display
    $formattedAddresses = [];
    $seenKeys = []; // To prevent duplicate keys
    
    foreach ($addresses as $address) {
        // Clean and format the address
        $cleanAddress = trim($address);
        if (!empty($cleanAddress)) {
            // Extract city/area from address (assuming format like "Street, City" or just "City")
            $parts = explode(',', $cleanAddress);
            $city = trim(end($parts)); // Get the last part as city
            
            // If no comma found, use the whole address as city
            if (count($parts) === 1) {
                $city = $cleanAddress;
            }
            
            // Create a clean key for the filter
            $key = strtolower(str_replace([' ', '-', '_', '.', '/', '\\'], '', $city));
            
            // Ensure unique keys
            if (!in_array($key, $seenKeys)) {
                $seenKeys[] = $key;
                
                $formattedAddresses[] = [
                    'key' => $key,
                    'value' => $cleanAddress, // Show full address in the dropdown
                    'full_address' => $cleanAddress
                ];
            }
        }
    }
    
    // Sort by city name for better user experience
    usort($formattedAddresses, function($a, $b) {
        return strcasecmp($a['value'], $b['value']);
    });
    
    echo json_encode([
        'success' => true,
        'addresses' => $formattedAddresses,
        'total' => count($formattedAddresses)
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
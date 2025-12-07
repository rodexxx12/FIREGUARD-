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
    
    // Get latitude and longitude from request
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
    $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
    
    if ($lat === null || $lng === null) {
        // Return all barangays if no coordinates provided
        $stmt = $conn->prepare("SELECT id, barangay_name, latitude, longitude FROM barangay ORDER BY barangay_name");
        $stmt->execute();
        $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'barangays' => $barangays
        ]);
        exit;
    }
    
    // Find the closest barangay to the given coordinates
    $stmt = $conn->prepare("
        SELECT id, barangay_name, latitude, longitude,
        (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance
        FROM barangay 
        ORDER BY distance 
        LIMIT 10
    ");
    $stmt->execute([$lat, $lng, $lat]);
    $barangays = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no nearby barangays found, try to match by address
    if (empty($barangays) && isset($_GET['address']) && !empty($_GET['address'])) {
        $address = $_GET['address'];
        
        // Extract potential barangay names from address
        // Address format: "Street, BarangayName, City, Province, Country"
        $addressParts = array_map('trim', explode(',', $address));
        
        // Try to match barangay name from address parts
        // Usually the second part (index 1) contains the barangay name
        // But we'll check multiple parts to be safe
        $matchedBarangay = null;
        
        // Check address parts starting from index 1 (skip street name)
        for ($i = 1; $i < count($addressParts) && $i < 3; $i++) {
            $potentialBarangayName = trim($addressParts[$i]);
            
            // Skip if it's clearly a city or province (common names)
            if (preg_match('/^(Bago|City|Negros|Occidental|Philippines)$/i', $potentialBarangayName)) {
                continue;
            }
            
            // Remove "Barangay" prefix if present
            $potentialBarangayName = preg_replace('/^Barangay\s+/i', '', $potentialBarangayName);
            
            if (empty($potentialBarangayName)) {
                continue;
            }
            
            // Try exact match first
            $stmt = $conn->prepare("SELECT id, barangay_name, latitude, longitude FROM barangay WHERE barangay_name = ? LIMIT 1");
            $stmt->execute([$potentialBarangayName]);
            $matchedBarangay = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If no exact match, try case-insensitive match
            if (!$matchedBarangay) {
                $stmt = $conn->prepare("SELECT id, barangay_name, latitude, longitude FROM barangay WHERE LOWER(barangay_name) = LOWER(?) LIMIT 1");
                $stmt->execute([$potentialBarangayName]);
                $matchedBarangay = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // If still no match, try partial match (contains)
            if (!$matchedBarangay) {
                $stmt = $conn->prepare("SELECT id, barangay_name, latitude, longitude FROM barangay WHERE barangay_name LIKE ? OR barangay_name LIKE ? LIMIT 1");
                $searchTerm1 = '%' . $potentialBarangayName . '%';
                $searchTerm2 = '%Barangay ' . $potentialBarangayName . '%';
                $stmt->execute([$searchTerm1, $searchTerm2]);
                $matchedBarangay = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // If we found a match, break the loop
            if ($matchedBarangay) {
                break;
            }
        }
        
        if ($matchedBarangay) {
            $barangays = [$matchedBarangay];
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'barangays' => $barangays
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching barangays: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to fetch barangays'
    ]);
}
?>

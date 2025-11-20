<?php
/**
 * Reverse Geocoding Proxy
 * Proxies requests to OpenStreetMap Nominatim API to avoid CORS issues
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get parameters
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;

// Validate parameters
if ($lat === null || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid coordinates']);
    exit;
}

// Check for zero coordinates (invalid GPS data)
if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
    http_response_code(400);
    echo json_encode(['error' => 'Zero coordinates detected']);
    exit;
}

try {
    // Build Nominatim API URL
    $url = sprintf(
        'https://nominatim.openstreetmap.org/reverse?format=json&lat=%.6f&lon=%.6f&zoom=18&addressdetails=1&namedetails=1',
        $lat,
        $lng
    );
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_USERAGENT => 'FireGuard Emergency System/1.0 (Contact: support@fireguard.com)', // Required by Nominatim
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9'
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    // Check for cURL errors
    if ($response === false || !empty($curlError)) {
        throw new Exception('cURL error: ' . $curlError);
    }
    
    // Check HTTP status code
    if ($httpCode !== 200) {
        throw new Exception('Nominatim API returned HTTP ' . $httpCode);
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON response from Nominatim API');
    }
    
    // Check if we have address data
    if (!isset($data['address']) || empty($data['address'])) {
        echo json_encode(['address' => null, 'message' => 'No address found']);
        exit;
    }
    
    // Build readable address from components
    $addr = $data['address'];
    $addressParts = [];
    
    // Add road/street
    if (isset($addr['road'])) {
        $addressParts[] = $addr['road'];
    }
    if (isset($addr['house_number'])) {
        $addressParts[] = $addr['house_number'];
    }
    
    // Add village/neighborhood
    if (isset($addr['village'])) {
        $addressParts[] = $addr['village'];
    } elseif (isset($addr['neighbourhood'])) {
        $addressParts[] = $addr['neighbourhood'];
    } elseif (isset($addr['suburb'])) {
        $addressParts[] = $addr['suburb'];
    }
    
    // Add city/municipality
    if (isset($addr['city'])) {
        $addressParts[] = $addr['city'];
    } elseif (isset($addr['municipality'])) {
        $addressParts[] = $addr['municipality'];
    } elseif (isset($addr['town'])) {
        $addressParts[] = $addr['town'];
    }
    
    // Add state/province
    if (isset($addr['state'])) {
        $addressParts[] = $addr['state'];
    } elseif (isset($addr['province'])) {
        $addressParts[] = $addr['province'];
    }
    
    // Add country
    if (isset($addr['country'])) {
        $addressParts[] = $addr['country'];
    }
    
    // Build formatted address
    $formattedAddress = !empty($addressParts) ? implode(', ', $addressParts) : null;
    
    // Return response
    echo json_encode([
        'success' => true,
        'address' => $formattedAddress,
        'raw' => $data['address'],
        'display_name' => isset($data['display_name']) ? $data['display_name'] : null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}


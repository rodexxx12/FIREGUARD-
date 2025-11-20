<?php
/**
 * Reverse Geocoding Proxy
 * 
 * This file acts as a proxy between the client-side JavaScript and the Nominatim API
 * to avoid CORS (Cross-Origin Resource Sharing) issues.
 * 
 * The Nominatim API doesn't allow direct browser requests due to CORS restrictions,
 * so we make the request server-side using PHP (which doesn't have CORS restrictions)
 * and return the result to the client.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get latitude and longitude from request
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;

// Validate inputs
if ($lat === null || $lon === null) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameters: lat and lon are required'
    ]);
    exit;
}

// Validate coordinate ranges
if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid coordinates: lat must be between -90 and 90, lon must be between -180 and 180'
    ]);
    exit;
}

// Build Nominatim API URL
$url = sprintf(
    'https://nominatim.openstreetmap.org/reverse?format=json&lat=%.8f&lon=%.8f&zoom=18&addressdetails=1',
    $lat,
    $lon
);

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_USERAGENT => 'FireGuard Location Service/1.0 (Contact: support@fireguard.local)',
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Accept-Language: en-US,en;q=0.9'
    ],
    // Respect Nominatim's usage policy
    CURLOPT_REFERER => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'http://localhost'
]);

// Execute request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// Handle cURL errors
if ($response === false || !empty($curlError)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to connect to geocoding service',
        'details' => $curlError ?: 'Unknown error'
    ]);
    exit;
}

// Handle HTTP errors
if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode([
        'error' => 'Geocoding service returned an error',
        'http_code' => $httpCode
    ]);
    exit;
}

// Parse and return the response
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Invalid response from geocoding service',
        'details' => json_last_error_msg()
    ]);
    exit;
}

// Return the geocoding data
echo json_encode($data);


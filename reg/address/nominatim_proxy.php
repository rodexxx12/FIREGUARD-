<?php
/**
 * Simple server-side proxy for Nominatim requests to avoid browser CORS limits.
 * Includes error handling, rate limiting, and retry logic.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$type = $_GET['type'] ?? '';
$userAgent = 'FireGuardRegistration/1.0 (contact@fireguard.local)';
$baseUrl = 'https://nominatim.openstreetmap.org/';

function respondWithError($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if ($type === 'search') {
    $query = trim($_GET['q'] ?? '');
    $limit = intval($_GET['limit'] ?? 1);
    if ($limit <= 0 || $limit > 5) {
        $limit = 1;
    }
    if ($query === '') {
        respondWithError('Missing search query');
    }
    $endpoint = sprintf('search?format=json&addressdetails=1&limit=%d&q=%s', $limit, urlencode($query));
} elseif ($type === 'reverse') {
    $lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
    $lon = filter_var($_GET['lon'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($lat === false || $lon === false) {
        respondWithError('Invalid coordinates');
    }
    $endpoint = sprintf('reverse?format=json&addressdetails=1&lat=%F&lon=%F', $lat, $lon);
} else {
    respondWithError('Invalid request type');
}

// Function to make request with retry logic
function makeNominatimRequest($url, $userAgent, $maxRetries = 3) {
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: en-US,en;q=0.9'
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_errno($ch);
        $curlErrorMessage = curl_error($ch);
        curl_close($ch);

        // Success case
        if ($curlError === 0 && $httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'response' => $response,
                'httpCode' => $httpCode
            ];
        }

        // Handle rate limiting (429) or service unavailable (503) with retry
        if (($httpCode === 429 || $httpCode === 503) && $attempt < $maxRetries) {
            // Wait before retrying (exponential backoff)
            $waitTime = min(pow(2, $attempt) * 0.5, 2); // Max 2 seconds
            usleep($waitTime * 1000000); // Convert to microseconds
            continue;
        }

        // Handle curl errors
        if ($curlError !== 0) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $curlErrorMessage,
                'httpCode' => 503
            ];
        }

        // Handle HTTP errors
        if ($httpCode === 429) {
            return [
                'success' => false,
                'error' => 'Rate limit exceeded. Please wait a moment and try again.',
                'httpCode' => 429
            ];
        }

        if ($httpCode === 503) {
            return [
                'success' => false,
                'error' => 'Geocoding service is temporarily unavailable. Please try again later.',
                'httpCode' => 503
            ];
        }

        // Other HTTP errors
        return [
            'success' => false,
            'error' => 'Geocoding request failed with status ' . $httpCode,
            'httpCode' => $httpCode
        ];
    }

    // All retries exhausted
    return [
        'success' => false,
        'error' => 'Service unavailable after multiple attempts. Please try again later.',
        'httpCode' => 503
    ];
}

$url = $baseUrl . $endpoint;
$result = makeNominatimRequest($url, $userAgent);

if (!$result['success']) {
    respondWithError($result['error'], $result['httpCode']);
}

// Validate and decode JSON response
$decoded = json_decode($result['response'], true);
if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    respondWithError('Invalid response from geocoding service', 502);
}

// Check if response is an error from Nominatim
if (isset($decoded['error'])) {
    respondWithError('Geocoding error: ' . $decoded['error'], 400);
}

echo json_encode(['success' => true, 'data' => $decoded], JSON_UNESCAPED_UNICODE);


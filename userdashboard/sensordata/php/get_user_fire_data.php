<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request for debugging
error_log("DataTables Ajax request received. Session ID: " . session_id());
error_log("Session data: " . print_r($_SESSION, true));
error_log("GET parameters: " . print_r($_GET, true));

if (!isset($_SESSION['user_id'])) {
    error_log("DataTables Ajax error: No user_id in session");
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => 'Please log in to access this data',
        'session_id' => session_id(),
        'has_session' => !empty($_SESSION)
    ]);
    exit;
}

require_once __DIR__ . '/../../db/db.php';

/**
 * Reverse geocoding function to convert latitude/longitude to readable address
 * Uses Nominatim (OpenStreetMap) API
 * Optimized for Philippine addresses
 */
function getAddressFromCoordinates($lat, $lng) {
    if (empty($lat) || empty($lng) || !is_numeric($lat) || !is_numeric($lng)) {
        return null;
    }
    
    // Validate coordinates are within reasonable bounds
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        return null;
    }
    
    // Check for zero coordinates (invalid GPS data)
    if (abs($lat) < 0.000001 && abs($lng) < 0.000001) {
        return null;
    }
    
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=" . urlencode($lat) . "&lon=" . urlencode($lng) . "&zoom=18&addressdetails=1";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: FireGuard Emergency System/1.0',
                'Accept: application/json',
                'Accept-Language: en-US,en;q=0.9'
            ],
            'timeout' => 5
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        error_log("Reverse geocoding failed: Unable to fetch from Nominatim API");
        return null;
    }
    
    $data = json_decode($response, true);
    
    if (!$data || !isset($data['address'])) {
        error_log("Reverse geocoding failed: Invalid response from Nominatim API");
        return null;
    }
    
    $addr = $data['address'];
    $addressParts = [];
    
    // Build readable address from components (optimized for Philippine addresses)
    // Start with house number and road
    if (isset($addr['road'])) {
        if (isset($addr['house_number'])) {
            $addressParts[] = $addr['house_number'] . ' ' . $addr['road'];
        } else {
            $addressParts[] = $addr['road'];
        }
    }
    
    // Barangay/Village (common in Philippines)
    if (isset($addr['village'])) {
        $addressParts[] = $addr['village'];
    } elseif (isset($addr['neighbourhood'])) {
        $addressParts[] = $addr['neighbourhood'];
    } elseif (isset($addr['suburb'])) {
        $addressParts[] = $addr['suburb'];
    }
    
    // City/Municipality
    if (isset($addr['city'])) {
        $addressParts[] = $addr['city'];
    } elseif (isset($addr['municipality'])) {
        $addressParts[] = $addr['municipality'];
    } elseif (isset($addr['town'])) {
        $addressParts[] = $addr['town'];
    }
    
    // Province/State
    if (isset($addr['province'])) {
        $addressParts[] = $addr['province'];
    } elseif (isset($addr['state'])) {
        $addressParts[] = $addr['state'];
    }
    
    // Country (usually Philippines)
    if (isset($addr['country'])) {
        $addressParts[] = $addr['country'];
    }
    
    if (count($addressParts) > 0) {
        return implode(', ', $addressParts);
    }
    
    // Fallback to display_name if available
    if (isset($data['display_name'])) {
        return $data['display_name'];
    }
    
    return null;
}

try {
    $pdo = getDatabaseConnection();
    $userId = (int)$_SESSION['user_id'];
    error_log("Processing request for user ID: " . $userId);
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database Error',
        'message' => 'Unable to connect to database',
        'draw' => isset($_GET['draw']) ? (int)$_GET['draw'] : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

// DataTables parameters
$draw = isset($_GET['draw']) ? (int)$_GET['draw'] : 0;
$start = isset($_GET['start']) ? (int)$_GET['start'] : 0;
$length = isset($_GET['length']) ? (int)$_GET['length'] : 10;

// Ordering
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 1; // default timestamp
$orderDir = isset($_GET['order'][0]['dir']) && in_array(strtolower($_GET['order'][0]['dir']), ['asc','desc']) ? $_GET['order'][0]['dir'] : 'desc';

// Map columns to SQL fields
$columns = [
    'f.id',
    'f.timestamp',
    'd.device_name',
    'b.building_name',
    'b.address',
    'f.building_type',
    'f.status',
    'f.smoke',
    'f.temp',
    'f.heat',
    'f.flame_detected',
    'f.ml_confidence',
    'f.ml_prediction'
];

$orderBy = $columns[$orderColumnIndex] ?? 'f.timestamp';

// Filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$buildingType = isset($_GET['building_type']) ? trim($_GET['building_type']) : '';
$startDate = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';
$deviceId = isset($_GET['device_id']) && $_GET['device_id'] !== '' ? (int)$_GET['device_id'] : null;

$where = ['f.user_id = :user_id'];
$params = ['user_id' => $userId];

if ($status !== '') {
    $where[] = 'f.status = :status';
    $params['status'] = $status;
}
if ($buildingType !== '') {
    $where[] = 'f.building_type LIKE :building_type';
    $params['building_type'] = "%$buildingType%";
}
if (!is_null($deviceId)) {
    $where[] = 'f.device_id = :device_id';
    $params['device_id'] = $deviceId;
}
// The schema uses varchar(50) for timestamp; assume it's in a parseable format (YYYY-MM-DD HH:MM:SS)
if ($startDate !== '') {
    $where[] = 'f.timestamp >= :start_date';
    $params['start_date'] = $startDate . ' 00:00:00';
}
if ($endDate !== '') {
    $where[] = 'f.timestamp <= :end_date';
    $params['end_date'] = $endDate . ' 23:59:59';
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

try {
    // Total records for this user
    $sqlCount = "SELECT COUNT(*) FROM fire_data f $whereSql";
    $stmt = $pdo->prepare($sqlCount);
    $stmt->execute($params);
    $recordsFiltered = (int)$stmt->fetchColumn();

    // Total without filters except user
    $stmtTotal = $pdo->prepare('SELECT COUNT(*) FROM fire_data f WHERE f.user_id = :user_id');
    $stmtTotal->execute(['user_id' => $userId]);
    $recordsTotal = (int)$stmtTotal->fetchColumn();

    // Data query with join to buildings for name and devices for device name
    // Include GPS coordinates from gps_data table (matched by timestamp proximity)
    // Priority: gps_data > device's building coordinates > fire_data's building coordinates > building address
    $sql = "
        SELECT 
            f.id, f.timestamp, f.building_type, f.status, f.smoke, f.temp, f.heat,
            f.flame_detected, f.ml_confidence, f.ml_prediction, f.device_id,
            COALESCE(g.latitude, bd.latitude, b.latitude) as latitude,
            COALESCE(g.longitude, bd.longitude, b.longitude) as longitude,
            COALESCE(b.building_name, bd.building_name) as building_name,
            COALESCE(b.address, bd.address) as building_address,
            COALESCE(d.device_name, CONCAT('Device #', f.device_id)) as device_name,
            g.ph_time as gps_time
        FROM fire_data f
        LEFT JOIN buildings b ON b.id = f.building_id
        LEFT JOIN devices d ON d.device_id = f.device_id
        LEFT JOIN buildings bd ON bd.id = d.building_id
        LEFT JOIN gps_data g ON ABS(TIMESTAMPDIFF(SECOND, g.ph_time, f.timestamp)) <= 300
            AND g.latitude IS NOT NULL 
            AND g.longitude IS NOT NULL
            AND g.latitude != 0
            AND g.longitude != 0
            AND g.id = (
                SELECT g2.id
                FROM gps_data g2
                WHERE g2.latitude IS NOT NULL 
                AND g2.longitude IS NOT NULL
                AND g2.latitude != 0
                AND g2.longitude != 0
                AND ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) <= 300
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, g2.ph_time, f.timestamp)) ASC
                LIMIT 1
            )
        $whereSql
        ORDER BY $orderBy $orderDir
        LIMIT :start, :length
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':start', $start, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process rows to convert coordinates to addresses
    // Note: Nominatim has a rate limit of 1 request per second
    // Cache addresses by coordinates to avoid duplicate API calls
    $addressCache = [];
    $lastApiCall = 0;
    $apiCallCount = 0;
    
    foreach ($rows as &$row) {
        $address = null;
        
        // Priority order for address:
        // 1. Reverse geocode from GPS coordinates (gps_data table)
        // 2. Reverse geocode from building coordinates (buildings table)
        // 3. Use building address from database
        
        $lat = !empty($row['latitude']) ? $row['latitude'] : null;
        $lng = !empty($row['longitude']) ? $row['longitude'] : null;
        
        // Try reverse geocoding if we have valid coordinates
        if (!empty($lat) && !empty($lng) && is_numeric($lat) && is_numeric($lng)) {
            // Create cache key from coordinates (rounded to 5 decimal places for better caching)
            // This allows nearby coordinates to share the same address
            $latRounded = round((float)$lat, 5);
            $lngRounded = round((float)$lng, 5);
            $cacheKey = $latRounded . ',' . $lngRounded;
            
            // Check cache first
            if (isset($addressCache[$cacheKey])) {
                $address = $addressCache[$cacheKey];
            } else {
                // Rate limiting: wait at least 1 second between API calls
                $currentTime = microtime(true);
                $timeSinceLastCall = $currentTime - $lastApiCall;
                if ($timeSinceLastCall < 1.0 && $lastApiCall > 0) {
                    usleep((1.0 - $timeSinceLastCall) * 1000000); // Wait remaining time
                }
                
                // Only make API call if we haven't exceeded reasonable limit (50 calls per request)
                if ($apiCallCount < 50) {
                    $address = getAddressFromCoordinates($latRounded, $lngRounded);
                    $lastApiCall = microtime(true);
                    $apiCallCount++;
                    
                    // Cache the result (even if null, to avoid repeated failed calls)
                    $addressCache[$cacheKey] = $address;
                } else {
                    // Too many API calls, skip reverse geocoding for remaining rows
                    error_log("Reverse geocoding limit reached (50 calls), using fallback addresses");
                    $address = null;
                }
            }
        }
        
        // Fallback to building address if reverse geocoding failed or no coordinates
        if (empty($address) && !empty($row['building_address'])) {
            $address = $row['building_address'];
        }
        
        // Final fallback: show coordinates if no address found
        if (empty($address) && !empty($lat) && !empty($lng)) {
            $address = sprintf('%.6f, %.6f', $lat, $lng);
        }
        
        // Set the address field (keep address for display)
        $row['address'] = $address ?: null;
        
        // Keep latitude and longitude for display in the Address column
        // Format coordinates for display
        if (!empty($lat) && !empty($lng) && is_numeric($lat) && is_numeric($lng)) {
            $row['latitude'] = number_format((float)$lat, 6);
            $row['longitude'] = number_format((float)$lng, 6);
        } else {
            $row['latitude'] = null;
            $row['longitude'] = null;
        }
        
        // Remove fields not needed in the table
        unset($row['building_address']);
        unset($row['gps_time']);
    }
    unset($row); // Break reference

    error_log("DataTables response: " . $recordsTotal . " total, " . $recordsFiltered . " filtered, " . count($rows) . " returned");

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $rows
    ]);

} catch (Exception $e) {
    error_log("DataTables query error: " . $e->getMessage());
    error_log("SQL: " . $sql);
    error_log("Params: " . print_r($params, true));
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Query Error',
        'message' => 'Unable to fetch data',
        'draw' => $draw,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
}
?>



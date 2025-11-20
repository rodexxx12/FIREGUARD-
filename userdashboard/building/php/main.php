<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../../index.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

include('../../db/db.php');

// Create an alias function for getDBConnection to maintain compatibility
function getDBConnection() {
    return getDatabaseConnection();
}

// Geo-fences functions
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

function point_in_any_geo_fence($lat, $lng, $geo_fences) {
    foreach ($geo_fences as $fence) {
        if (point_in_polygon($lat, $lng, $fence['polygon'])) {
            return ['in_fence' => true, 'fence' => $fence];
        }
    }
    return ['in_fence' => false, 'fence' => null];
}

function point_in_polygon($lat, $lng, $polygon) {
    $inside = false;
    $n = count($polygon);
    for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
        // Polygon format is [lat, lng] so xi is latitude, yi is longitude
        $xi = $polygon[$i][0]; $yi = $polygon[$i][1];
        $xj = $polygon[$j][0]; $yj = $polygon[$j][1];
        // Ray casting algorithm: check if horizontal ray intersects edge
        $intersect = (($yi > $lng) != ($yj > $lng)) &&
            ($lat < ($xj - $xi) * ($lng - $yi) / ($yj - $yi + 0.0000001) + $xi);
        if ($intersect) $inside = !$inside;
    }
    return $inside;
}

// Function to get address from coordinates using reverse geocoding
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
    
    if (!$data) {
        error_log("Reverse geocoding failed: Invalid response from Nominatim API");
        return null;
    }
    
    // Try to build address from components first
    if (isset($data['address'])) {
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
    }
    
    // Fallback to display_name if available
    if (isset($data['display_name'])) {
        return $data['display_name'];
    }
    
    return null;
}

// Check if buildings table exists
function checkBuildingsTable($conn) {
    $stmt = $conn->query("SHOW TABLES LIKE 'buildings'");
    if ($stmt->rowCount() === 0) {
        // Create the buildings table if it doesn't exist
        $createTable = "CREATE TABLE buildings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            building_name VARCHAR(255) NOT NULL,
            building_type VARCHAR(50) NOT NULL,
            address TEXT NOT NULL,
            contact_person VARCHAR(255),
            contact_number VARCHAR(50),
            total_floors INT DEFAULT 1,
            has_sprinkler_system BOOLEAN DEFAULT FALSE,
            has_fire_alarm BOOLEAN DEFAULT FALSE,
            has_fire_extinguishers BOOLEAN DEFAULT FALSE,
            has_emergency_exits BOOLEAN DEFAULT FALSE,
            has_emergency_lighting BOOLEAN DEFAULT FALSE,
            has_fire_escape BOOLEAN DEFAULT FALSE,
            last_inspected DATE,
            latitude DECIMAL(10, 8),
            longitude DECIMAL(11, 8),
            construction_year INT,
            building_area DECIMAL(10, 2),
            geo_fence_id BIGINT(20),
            barangay_id INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_building_type (building_type),
            INDEX idx_geo_fence_id (geo_fence_id),
            INDEX idx_barangay_id (barangay_id),
            FOREIGN KEY (geo_fence_id) REFERENCES geo_fences(id) ON UPDATE CASCADE,
            FOREIGN KEY (barangay_id) REFERENCES barangay(id) ON UPDATE CASCADE
        )";
        
        if (!$conn->exec($createTable)) {
            error_log("Failed to create buildings table");
            return false;
        }
    }
    return true;
}

// Check for login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

// Handle AJAX GET request for reverse geocoding
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_address') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['lat']) || !isset($_GET['lng'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Latitude and longitude are required']);
        exit;
    }
    
    $lat = floatval($_GET['lat']);
    $lng = floatval($_GET['lng']);
    
    $address = getAddressFromCoordinates($lat, $lng);
    
    if ($address) {
        echo json_encode(['status' => 'success', 'address' => $address]);
    } else {
        // Fallback address from coordinates
        $address = "Lat: {$lat}, Lng: {$lng}";
        echo json_encode(['status' => 'success', 'address' => $address, 'fallback' => true]);
    }
    exit;
}

// Handle AJAX POST request for adding/editing building
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    // Check if this is a test request
    if (isset($_POST['test_db'])) {
        try {
            $conn = getDBConnection();
            $tableExists = checkBuildingsTable($conn);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Database connection successful',
                'table_exists' => $tableExists
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Database test failed: ' . $e->getMessage()
            ]);
        }
        exit;
    }

    try {
        $conn = getDBConnection();
        
        // Check if buildings table exists, create if not
        if (!checkBuildingsTable($conn)) {
            throw new Exception("Failed to create buildings table");
        }
        
        // Get and sanitize form values
        $user_id = $_SESSION['user_id'];
        $building_name = isset($_POST['building_name']) ? htmlspecialchars(trim($_POST['building_name'])) : '';
        $building_type = isset($_POST['building_type']) ? htmlspecialchars(trim($_POST['building_type'])) : '';
        $address = isset($_POST['address']) ? htmlspecialchars(trim($_POST['address'])) : '';
        $contact_person = isset($_POST['contact_person']) ? htmlspecialchars(trim($_POST['contact_person'])) : '';
        $contact_number = isset($_POST['contact_number']) ? htmlspecialchars(trim($_POST['contact_number'])) : '';
        $total_floors = isset($_POST['total_floors']) ? intval($_POST['total_floors']) : 1;
        $has_sprinkler_system = isset($_POST['has_sprinkler_system']) ? 1 : 0;
        $has_fire_alarm = isset($_POST['has_fire_alarm']) ? 1 : 0;
        $has_fire_extinguishers = isset($_POST['has_fire_extinguishers']) ? 1 : 0;
        $has_emergency_exits = isset($_POST['has_emergency_exits']) ? 1 : 0;
        $has_emergency_lighting = isset($_POST['has_emergency_lighting']) ? 1 : 0;
        $has_fire_escape = isset($_POST['has_fire_escape']) ? 1 : 0;
        $last_inspected = !empty($_POST['last_inspected']) ? $_POST['last_inspected'] : null;
        $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
        $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
        $construction_year = !empty($_POST['construction_year']) ? intval($_POST['construction_year']) : null;
        $building_area = !empty($_POST['building_area']) ? floatval($_POST['building_area']) : null;
        $geo_fence_id = !empty($_POST['geo_fence_id']) ? intval($_POST['geo_fence_id']) : null;
        $barangay_id = !empty($_POST['barangay_id']) ? intval($_POST['barangay_id']) : null;

        // Debug: Log the received data
        error_log("Received building data: " . json_encode($_POST));

        // If address is empty but coordinates are provided, try to get address from coordinates
        if (empty($address) && !empty($latitude) && !empty($longitude)) {
            $address = getAddressFromCoordinates($latitude, $longitude);
            // If reverse geocoding fails, create a fallback address from coordinates
            if (empty($address)) {
                $address = "Lat: {$latitude}, Lng: {$longitude}";
            }
        }
        
        // Validation
        $errors = [];
        if (empty($building_name)) $errors[] = 'Building name is required';
        if (empty($building_type)) $errors[] = 'Building type is required';
        if (empty($address) && (empty($latitude) || empty($longitude))) {
            $errors[] = 'Address is required. Please provide an address or select a location on the map.';
        }
        if (!empty($contact_number) && !preg_match('/^[0-9+\- ]+$/', $contact_number)) {
            $errors[] = 'Invalid contact number format';
        }
        if ($total_floors < 1 || $total_floors > 200) $errors[] = 'Invalid number of floors (1-200)';
        if (!empty($construction_year) && ($construction_year < 1800 || $construction_year > date('Y'))) {
            $errors[] = 'Invalid construction year (1800-' . date('Y') . ')';
        }
        if (!empty($last_inspected)) {
            $inspection_date = DateTime::createFromFormat('Y-m-d', $last_inspected);
            if (!$inspection_date || $inspection_date->format('Y-m-d') !== $last_inspected) {
                $errors[] = 'Invalid inspection date format';
            }
        }

        // GEO-FENCING: Check against active geo-fences from database
        if (empty($errors) && !empty($latitude) && !empty($longitude)) {
            $geo_fences = get_active_geo_fences();
            if (empty($geo_fences)) {
                $errors[] = "No active geo-fences configured. Building registration is currently disabled. Please contact support.";
            } else {
                $fence_check = point_in_any_geo_fence(floatval($latitude), floatval($longitude), $geo_fences);
                if (!$fence_check['in_fence']) {
                    $allowed_cities = array_map(function($fence) {
                        return $fence['city_name'];
                    }, $geo_fences);
                    $errors[] = "Building registration is only allowed within the following areas: " . implode(', ', $allowed_cities) . ". Please select a location within these boundaries.";
                } else {
                    // Set the geo_fence_id based on the fence the point is in
                    $geo_fence_id = $fence_check['fence']['id'];
                }
            }
        }

        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => implode('<br>', $errors)]);
            exit;
        }

        // Check if this is an edit (building_id provided)
        if (!empty($_POST['building_id'])) {
            $building_id = intval($_POST['building_id']);
            
            // Verify the building belongs to the user
            $check_stmt = $conn->prepare("SELECT id FROM buildings WHERE id = ? AND user_id = ?");
            $check_stmt->execute([$building_id, $user_id]);
            
            if ($check_stmt->rowCount() === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'You are not authorized to edit this building']);
                exit;
            }
            
            // Prepare update statement
            $stmt = $conn->prepare("UPDATE buildings SET 
                barangay_id = ?, building_name = ?, building_type = ?, address = ?, contact_person = ?, contact_number = ?, 
                total_floors = ?, has_sprinkler_system = ?, has_fire_alarm = ?, has_fire_extinguishers = ?, 
                has_emergency_exits = ?, has_emergency_lighting = ?, has_fire_escape = ?,
                last_inspected = ?, latitude = ?, longitude = ?, construction_year = ?, building_area = ?,
                geo_fence_id = ?
                WHERE id = ?");
                
            if (!$stmt) {
                throw new Exception("Prepare failed");
            }
            
            $stmt->execute([
                $barangay_id, $building_name, $building_type, $address, $contact_person, $contact_number, 
                $total_floors, $has_sprinkler_system, $has_fire_alarm, $has_fire_extinguishers,
                $has_emergency_exits, $has_emergency_lighting, $has_fire_escape,
                $last_inspected, $latitude, $longitude, $construction_year, $building_area,
                $geo_fence_id, $building_id
            ]);
                
            $success_message = 'Building updated successfully!';
        } else {
            // Prepare insert statement
            $stmt = $conn->prepare("INSERT INTO buildings 
                (user_id, barangay_id, building_name, building_type, address, contact_person, contact_number, 
                total_floors, has_sprinkler_system, has_fire_alarm, has_fire_extinguishers, 
                has_emergency_exits, has_emergency_lighting, has_fire_escape,
                last_inspected, latitude, longitude, construction_year, building_area,
                geo_fence_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                
            if (!$stmt) {
                throw new Exception("Prepare failed");
            }
            
            if (!$stmt->execute([
                $user_id, $barangay_id, $building_name, $building_type, $address, $contact_person, $contact_number, 
                $total_floors, $has_sprinkler_system, $has_fire_alarm, $has_fire_extinguishers,
                $has_emergency_exits, $has_emergency_lighting, $has_fire_escape,
                $last_inspected, $latitude, $longitude, $construction_year, $building_area,
                $geo_fence_id
            ])) {
                throw new Exception("Execute failed");
            }
                
            $success_message = 'Building registered successfully!';
        }

        $building_id = !empty($_POST['building_id']) ? $_POST['building_id'] : $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success', 
            'message' => $success_message,
            'building_id' => $building_id
        ]);
        
    } catch (Exception $e) {
        error_log("Building registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    header('Content-Type: application/json');
    
    try {
        // Get raw input and decode JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input');
        }
        
        $building_id = isset($input['building_id']) ? (int)$input['building_id'] : 0;
        $user_id = $_SESSION['user_id'] ?? 0;
        
        if ($building_id <= 0) {
            throw new Exception('Invalid building ID');
        }
        
        $conn = getDBConnection();
        
        // Verify ownership
        $stmt = $conn->prepare("SELECT id FROM buildings WHERE id = ? AND user_id = ?");
        $stmt->execute([$building_id, $user_id]);
        
        if ($stmt->rowCount() === 0) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
            exit;
        }
        
        // Delete building
        $stmt = $conn->prepare("DELETE FROM buildings WHERE id = ?");
        
        if (!$stmt->execute([$building_id])) {
            throw new Exception('Delete failed');
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Building deleted successfully'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Handle AJAX GET request for fetching building data for editing
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_building') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['building_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Building ID is required']);
        exit;
    }
    
    try {
        $conn = getDBConnection();
        $building_id = intval($_GET['building_id']);
        $user_id = $_SESSION['user_id'];
        
        // Fetch building data with all fields
        $stmt = $conn->prepare("SELECT * FROM buildings WHERE id = ? AND user_id = ?");
        $stmt->execute([$building_id, $user_id]);
        $building = $stmt->fetch();
        
        if (!$building) {
            echo json_encode(['status' => 'error', 'message' => 'Building not found or you are not authorized to edit this building']);
            exit;
        }
        
        // Convert boolean fields to proper format
        $building['has_sprinkler_system'] = (bool)$building['has_sprinkler_system'];
        $building['has_fire_alarm'] = (bool)$building['has_fire_alarm'];
        $building['has_fire_extinguishers'] = (bool)$building['has_fire_extinguishers'];
        $building['has_emergency_exits'] = (bool)$building['has_emergency_exits'];
        $building['has_emergency_lighting'] = (bool)$building['has_emergency_lighting'];
        $building['has_fire_escape'] = (bool)$building['has_fire_escape'];
        
        echo json_encode(['status' => 'success', 'data' => $building]);
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Failed to fetch building data: ' . $e->getMessage()]);
    }
    exit;
}

// Fetch existing buildings for the user
$buildings = [];
if (isset($_SESSION['user_id'])) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT id, building_name, building_type, address, total_floors, last_inspected, barangay_id FROM buildings WHERE user_id = ? ORDER BY building_name");
        $stmt->execute([$_SESSION['user_id']]);
        $buildings = $stmt->fetchAll();
    } catch (Exception $e) {
        // Silently handle error - we'll show empty state
        $buildings = [];
    }
}
?>
<?php include('../../components/header.php'); ?>

<!-- Leaflet CSS already included in header.php -->
<style>
    .spin {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    #refreshMapBtn:hover {
        background: rgba(255, 140, 0, 0.1) !important;
        border-color: #ff8c00 !important;
        color: #ff8c00 !important;
    }
    
    /* Hide auto-selection helper text for geo-fence/barangay */
    #geo_fence_id ~ .live-feedback,
    #barangay_id ~ .live-feedback {
        display: none !important;
    }
    
    /* Gentelella DataTables Styling - Responsive with More Width */
    .dataTables_wrapper {
        font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
        background: #fff;
        padding: 0;
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
    }
    
    #buildingsTable {
        width: 100% !important;
        min-width: 1200px; /* Increased minimum width */
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        background: #fff;
        table-layout: auto;
    }
    
    #buildingsTable thead th {
        background: #f7f7f7;
        border: 1px solid #ddd;
        border-bottom: 2px solid #ddd;
        color: #555;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        padding: 15px 12px; /* Increased padding for more width */
        text-align: left;
        white-space: nowrap;
    }
    
    /* Column width specifications */
    #buildingsTable thead th:nth-child(1) { /* Building Name */
        min-width: 200px;
        width: 25%;
    }
    
    #buildingsTable thead th:nth-child(2) { /* Type */
        min-width: 120px;
        width: 10%;
    }
    
    #buildingsTable thead th:nth-child(3) { /* Address */
        min-width: 300px;
        width: 35%;
    }
    
    #buildingsTable thead th:nth-child(4) { /* Floors */
        min-width: 80px;
        width: 8%;
    }
    
    #buildingsTable thead th:nth-child(5) { /* Last Inspection */
        min-width: 140px;
        width: 12%;
    }
    
    #buildingsTable thead th:nth-child(6) { /* Actions */
        min-width: 150px;
        width: 10%;
    }
    
    #buildingsTable thead th:first-child {
        border-left: 1px solid #ddd;
    }
    
    #buildingsTable thead th:last-child {
        border-right: 1px solid #ddd;
    }
    
    #buildingsTable tbody td {
        border: 1px solid #ddd;
        border-top: none;
        padding: 15px 12px; /* Increased padding for more width */
        vertical-align: middle;
        font-size: 13px;
        color: #555;
        word-wrap: break-word;
    }
    
    /* Responsive table wrapper */
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        -ms-overflow-style: -ms-autohiding-scrollbar;
    }
    
    /* Buildings section full width */
    #buildingsSection {
        width: 100%;
        max-width: 100%;
    }
    
    #buildingsSection .x_content {
        width: 100% !important;
        max-width: 100% !important;
        padding: 20px !important;
    }
    
    /* DataTables scroll container */
    .dataTables_scroll {
        width: 100%;
    }
    
    .dataTables_scrollHead,
    .dataTables_scrollBody {
        width: 100% !important;
    }
    
    .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    
    .dataTables_scrollHeadInner table {
        width: 100% !important;
    }
    
    /* Mobile responsive styles */
    @media screen and (max-width: 1200px) {
        #buildingsTable {
            min-width: 1000px;
        }
        
        #buildingsTable thead th,
        #buildingsTable tbody td {
            padding: 12px 10px;
        }
    }
    
    @media screen and (max-width: 768px) {
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            padding: 10px;
        }
        
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            display: block;
            margin-bottom: 5px;
        }
        
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            width: 100%;
            margin: 5px 0;
        }
        
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            padding: 10px;
            text-align: center;
        }
        
        #buildingsTable {
            min-width: 800px;
        }
        
        #buildingsTable thead th,
        #buildingsTable tbody td {
            padding: 10px 8px;
            font-size: 12px;
        }
    }
    
    @media screen and (max-width: 480px) {
        #buildingsTable {
            min-width: 700px;
        }
        
        #buildingsTable thead th,
        #buildingsTable tbody td {
            padding: 8px 6px;
            font-size: 11px;
        }
        
        .btn-group .btn {
            padding: 4px 6px;
            font-size: 11px;
        }
    }
    
    #buildingsTable tbody tr {
        background: #fff;
    }
    
    #buildingsTable tbody tr:hover {
        background-color: #f5f5f5;
    }
    
    #buildingsTable tbody tr:first-child td {
        border-top: 1px solid #ddd;
    }
    
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        padding: 12px 15px;
        background: #f7f7f7;
        border: 1px solid #ddd;
        border-bottom: none;
    }
    
    .dataTables_wrapper .dataTables_length label,
    .dataTables_wrapper .dataTables_filter label {
        font-weight: normal;
        font-size: 13px;
        color: #555;
        margin: 0;
    }
    
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #ddd;
        padding: 4px 8px;
        background: #fff;
        color: #555;
        font-size: 13px;
        margin: 0 5px;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #ddd;
        padding: 4px 8px;
        margin-left: 5px;
        background: #fff;
        color: #555;
        font-size: 13px;
    }
    
    .dataTables_wrapper .dataTables_filter input:focus {
        border-color: #26B99A;
        outline: none;
    }
    
    .dataTables_wrapper .dataTables_info {
        padding: 12px 15px;
        background: #f7f7f7;
        border: 1px solid #ddd;
        border-top: none;
        color: #555;
        font-size: 13px;
    }
    
    .dataTables_wrapper .dataTables_paginate {
        padding: 12px 15px;
        background: #f7f7f7;
        border: 1px solid #ddd;
        border-top: none;
        text-align: right;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border: 1px solid #ddd;
        padding: 5px 10px;
        margin: 0 2px;
        background: #fff;
        color: #555 !important;
        font-size: 13px;
        cursor: pointer;
        display: inline-block;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #26B99A;
        border-color: #26B99A;
        color: #fff !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background: #26B99A;
        border-color: #26B99A;
        color: #fff !important;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        color: #999 !important;
        background: #f7f7f7;
        border-color: #ddd;
        cursor: default;
    }
    
    .dataTables_wrapper .dataTables_processing {
        background: rgba(255, 255, 255, 0.9);
        border: 1px solid #ddd;
        padding: 20px;
        color: #555;
        font-size: 13px;
    }
    
    /* Gentelella jambo_table styling */
    .jambo_table {
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .jambo_table thead tr.headings th {
        background: #f7f7f7;
        border: 1px solid #ddd;
        border-bottom: 2px solid #ddd;
        color: #555;
        font-weight: 600;
        font-size: 13px;
        text-transform: uppercase;
        padding: 12px 8px;
    }
    
    .jambo_table tbody tr.even {
        background: #fff;
    }
    
    .jambo_table tbody tr.odd {
        background: #f9f9f9;
    }
    
    .jambo_table tbody tr.pointer {
        cursor: pointer;
    }
    
    .jambo_table tbody tr:hover {
        background-color: #f5f5f5 !important;
    }
    
    .jambo_table tbody td {
        border: 1px solid #ddd;
        border-top: none;
        padding: 12px 8px;
        font-size: 13px;
        color: #555;
    }
    
    .jambo_table tbody td.last {
        white-space: nowrap;
    }
    
    .column-title {
        font-weight: 600;
    }
    
    .nobr {
        white-space: nowrap;
    }
    
    /* Badge styling for building types */
    .badge-residential {
        background-color: #28a745 !important;
    }
    
    .badge-commercial {
        background-color: #007bff !important;
    }
    
    .badge-industrial {
        background-color: #fd7e14 !important;
    }
    
    .badge-institutional {
        background-color: #6f42c1 !important;
    }
    
    /* Button group styling */
    .btn-group .btn {
        margin-right: 2px;
        border-radius: 6px;
        transition: all 0.2s ease;
        font-weight: 500;
    }
    
    .btn-group .btn:last-child {
        margin-right: 0;
    }
    
    /* Custom button colors */
    .view-building {
        background-color: #0d6efd !important;
        border-color: #0d6efd !important;
        color: white !important;
    }
    
    .view-building:hover {
        background-color: #0b5ed7 !important;
        border-color: #0a58ca !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
    }
    
    .edit-building {
        background-color: #198754 !important;
        border-color: #198754 !important;
        color: white !important;
    }
    
    .edit-building:hover {
        background-color: #157347 !important;
        border-color: #146c43 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(25, 135, 84, 0.3);
    }
    
    .delete-building {
        background-color: #dc3545 !important;
        border-color: #dc3545 !important;
        color: white !important;
    }
    
    .delete-building:hover {
        background-color: #c82333 !important;
        border-color: #bd2130 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(220, 53, 69, 0.3);
    }
    
    /* Focus states for accessibility */
    .view-building:focus,
    .edit-building:focus,
    .delete-building:focus {
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        outline: none;
    }
    
    /* Card header styling */
    .card-header {
        background-color: white !important;
        border-bottom: 1px solid #e9ecef;
        padding: 1rem 1.5rem;
    }
    
    /* H5 styling - Red color and Arial font */
    h5 {
        color: red !important;
        font-family: Arial, sans-serif !important;
    }
    
</style>
</head>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 

                <!-- Registration Form Content -->
                <div id="registrationForm">
        
                <!-- Gentelella Form Wizard Style -->
                <div class="x_panel">
                    <div class="x_title d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" id="cardTitle">
                        <i class="bi bi-building-add"></i> Building Registration
                    </h5>
                        <a href="buildings-table.php" class="btn btn-primary" id="viewBuildingsBtn">
                            <i class="bi bi-buildings"></i> My Buildings
                        </a>
                    </div>
                    <div class="x_content">
                        <div id="wizard" class="form_wizard wizard_horizontal">
                            <ul class="wizard_steps">
                                <li>
                                    <a href="#step-1" class="selected" isdone="1" rel="1">
                                        <span class="step_no">1</span>
                                        <span class="step_descr">
                                            Step 1<br />
                                            <small>Basic Information</small>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#step-2" class="disabled" isdone="0" rel="2">
                                        <span class="step_no">2</span>
                                        <span class="step_descr">
                                            Step 2<br />
                                            <small>Safety Features</small>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#step-3" class="disabled" isdone="0" rel="3">
                                        <span class="step_no">3</span>
                                        <span class="step_descr">
                                            Step 3<br />
                                            <small>Location Details</small>
                                        </span>
                                    </a>
                                </li>
                                <li>
                                    <a href="#step-4" class="disabled" isdone="0" rel="4">
                                        <span class="step_no">4</span>
                                        <span class="step_descr">
                                            Step 4<br />
                                            <small>Review & Submit</small>
                                        </span>
                                    </a>
                                </li>
                            </ul>

                <form id="buildingForm">
                    <input type="hidden" name="building_id" id="building_id" value="">
                    <!-- Step 1: Basic Information -->
                    <div class="step-content" id="stepContent1">
                        <h2 class="StepTitle">Step 1 Content</h2>
                        <h5 class="mb-4 text-success"><i class="bi bi-info-circle"></i> Basic Information</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="building_name" class="form-label required-field">Building Name</label>
                                <input type="text" class="form-control" name="building_name" id="building_name" required>
                                <div class="invalid-feedback">Please provide a building name</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="building_type" class="form-label required-field">Building Type</label>
                                <select class="form-control" name="building_type" id="building_type" required>
                                    <option value="">-- Select Type --</option>
                                    <option value="residential">Residential</option>
                                    <option value="commercial">Commercial</option>
                                    <option value="institutional">Institutional</option>
                                    <option value="industrial">Industrial</option>
                                </select>
                                <div class="invalid-feedback">Please select a building type</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="total_floors" class="form-label required-field">Total Floors</label>
                                <input type="number" class="form-control" name="total_floors" id="total_floors" min="1" max="200" value="1" required>
                                <div class="invalid-feedback">Please enter a number between 1 and 200</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" name="contact_person" id="contact_person">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="contact_number" class="form-label">Contact Number</label>
                                <input type="text" class="form-control" name="contact_number" id="contact_number" placeholder="e.g., +1 234-567-8900">
                                <div class="invalid-feedback">Please provide a valid contact number</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="construction_year" class="form-label">Construction Year</label>
                                <input type="number" class="form-control" name="construction_year" id="construction_year" min="1800" max="<?php echo date('Y'); ?>" placeholder="YYYY">
                                <div class="invalid-feedback">Please enter a year between 1800 and <?php echo date('Y'); ?></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="building_area" class="form-label">Building Area (m²)</label>
                                <input type="number" class="form-control" name="building_area" id="building_area" min="0" step="0.01" placeholder="0.00">
                                <div class="invalid-feedback">Please enter a valid area</div>
                            </div>
                        </div>
                        
                        <!-- Hidden Address Field -->
                        <input type="hidden" id="address" name="address" />
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="button" class="btn btn-primary next-step" data-next="2">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Safety Features -->
                    <div class="step-content d-none" id="stepContent2">
                        <h2 class="StepTitle">Step 2 Content</h2>
                        <h5 class="mb-4"><i class="bi bi-shield-check"></i> Safety Features</h5>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Fire Protection Systems</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="has_sprinkler_system" id="has_sprinkler_system">
                                        <label class="form-check-label" for="has_sprinkler_system">Sprinkler System</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="has_fire_alarm" id="has_fire_alarm">
                                        <label class="form-check-label" for="has_fire_alarm">Fire Alarm</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="has_fire_extinguishers" id="has_fire_extinguishers">
                                        <label class="form-check-label" for="has_fire_extinguishers">Fire Extinguishers</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <h6 class="mb-3">Emergency Features</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="has_emergency_exits" id="has_emergency_exits">
                                        <label class="form-check-label" for="has_emergency_exits">Marked Emergency Exits</label>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="has_emergency_lighting" id="has_emergency_lighting">
                                        <label class="form-check-label" for="has_emergency_lighting">Emergency Lighting</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="has_fire_escape" id="has_fire_escape">
                                        <label class="form-check-label" for="has_fire_escape">Fire Escape</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                            <label for="last_inspected" class="form-label">Last Fire Safety Inspection Date</label>
                            <input type="date" class="form-control" name="last_inspected" id="last_inspected" max="<?php echo date('Y-m-d'); ?>">
                            <div class="invalid-feedback">Please select a valid date</div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-success prev-step" data-prev="1"><i class="bi bi-arrow-left"></i> Previous</button>
                            <button type="button" class="btn btn-primary next-step" data-next="3">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Location Details -->
                    <div class="step-content d-none" id="stepContent3">
                        <h2 class="StepTitle">Step 3 Content</h2>
                        <h5 class="mb-4"><i class="bi bi-geo-alt"></i> Location Details</h5>       
                        <div class="row" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="text" class="form-control" name="latitude" id="latitude" placeholder="e.g., 14.5995">
                                <div class="invalid-feedback">Please enter a valid latitude (-90 to 90)</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="text" class="form-control" name="longitude" id="longitude" placeholder="e.g., 120.9842">
                                <div class="invalid-feedback">Please enter a valid longitude (-180 to 180)</div>
                            </div>
                        </div>
                        
                        <!-- Location-based dropdowns -->
                        <div class="row mb-4">
                            <div class="col-md-6 mb-3">
                                <label for="geo_fence_id" class="form-label">Geo-Fence Area</label>
                                <select class="form-select" name="geo_fence_id" id="geo_fence_id" disabled>
                                    <option value="">Select location on map first</option>
                                </select>
                                <div class="invalid-feedback">Please select a location on the map</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="barangay_id" class="form-label">Barangay</label>
                                <select class="form-select" name="barangay_id" id="barangay_id" disabled>
                                    <option value="">Select location on map first</option>
                                </select>
                                <div class="invalid-feedback">Please select a location on the map</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3" style="display: none;">
                            <div class="col-12">
                                <button type="button" class="btn btn-outline-info" id="getAddressFromCoordsBtn">
                                    <i class="bi bi-geo-alt"></i> Get Address from Coordinates
                                </button>
                                <small class="form-text text-muted">Click this button to automatically get the address from the entered coordinates</small>
                            </div>
                        </div>
                        
                        <!-- Display retrieved address -->
                        <div class="row mb-3" id="retrievedAddressDisplay" style="display: none;">
                            <div class="col-12">
                                <div class="alert alert-success">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-check-circle-fill me-2 mt-1"></i>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="alert-heading mb-0">Retrieved Address:</h6>
                                                <button type="button" class="btn-close" id="closeAddressDisplay" aria-label="Close"></button>
                                            </div>
                                            <p class="mb-0" id="retrievedAddressText"></p>
                                            <small class="text-muted">This address has been automatically retrieved from the coordinates and will be saved with your building information.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                <!-- Location UI Container -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex gap-2">
                            <button id="refreshMapBtn" class="btn btn-sm btn-danger" title="Refresh Map">
                                <i class="bi bi-arrow-clockwise"></i> Refresh Map
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="getLocationBtnTop">
                                <i class="bi bi-geo-alt"></i> Use My Current Location
                            </button>
                        </div>
                        <div class="text-muted small">
                            <i class="bi bi-info-circle"></i> Click on the map to select location
                        </div>
                    </div>
                <div id="map" style="height: 300px; border-radius: 10px; border: 1px solid #ddd; position: relative; cursor: crosshair; background-color: #f8f9fa; min-height: 500px;">
                    <div class="map-instructions" style="position: absolute; top: 10px; left: 10px; background: rgba(255,255,255,0.95); padding: 10px 15px; border-radius: 8px; font-size: 13px; z-index: 1000; box-shadow: 0 4px 8px rgba(0,0,0,0.15); pointer-events: none; border: 1px solid rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="bi bi-info-circle text-primary" style="font-size: 16px;"></i>
                            <span style="font-weight: 500;">Click inside the green areas to select location</span>
                        </div>
                    </div>
                    <div class="map-loading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 20px; border-radius: 10px; text-align: center; z-index: 999; display: block;">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div style="margin-top: 10px; font-size: 14px; color: #666;">Loading map...</div>
                    </div>
                    <div class="map-error" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); padding: 20px; border-radius: 10px; text-align: center; z-index: 999; display: none; color: #dc3545;">
                        <i class="bi bi-exclamation-triangle" style="font-size: 24px;"></i>
                        <div style="margin-top: 10px; font-size: 14px;">Map failed to load. Please refresh the page.</div>
                        <button class="btn btn-sm btn-danger mt-2" onclick="location.reload()">Refresh Page</button>
                    </div>
                </div>
                    </div>


                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-success prev-step" data-prev="2"><i class="bi bi-arrow-left"></i> Previous</button>
                            <button type="button" class="btn btn-primary next-step" data-next="4">Next <i class="bi bi-arrow-right"></i></button>
                        </div>
                    </div>
                    
                    <!-- Step 4: Review & Submit -->
                    <div class="step-content d-none" id="stepContent4">
                        <h2 class="StepTitle">Step 4 Content</h2>
                        <h5 class="mb-4"><i class="bi bi-check-circle"></i> Review & Submit</h5>
                        
                        <div class="alert alert-warning mb-4">
                            <i class="bi bi-exclamation-triangle"></i> Please review all information before submitting. You won't be able to edit after submission.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="mb-3">Basic Information</h6>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Building Name</p>
                                    <p id="review_building_name">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Building Type</p>
                                    <p id="review_building_type">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Address</p>
                                    <p id="review_address">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Contact Person</p>
                                    <p id="review_contact_person">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Total Floors</p>
                                    <p id="review_total_floors">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Construction Year</p>
                                    <p id="review_construction_year">-</p>
                                </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="mb-3">Details</h6>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Safety Features</p>
                                    <div id="review_safety_features">
                                        <span class="badge bg-secondary">None specified</span>
                                    </div>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Last Inspection</p>
                                    <p id="review_last_inspected">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Geo-Fence Area</p>
                                    <p id="review_geo_fence">-</p>
                                </div>
                                    <div class="col-12 mb-3">
                                    <p class="mb-1 text-muted">Barangay</p>
                                    <p id="review_barangay">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-success prev-step" data-prev="3"><i class="bi bi-arrow-left"></i> Previous</button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-lg"></i> Submit Registration
                            </button>
                        </div>
                    </div>
                </form>
                        </div>
                    </div>
                </div>
                </div>
                </div>

                <!-- My Buildings Section (Hidden by default) -->
                <div class="d-none" id="buildingsSection">
                    <div class="p-4">
                
<?php if (empty($buildings)): ?>
    <div class="empty-state">
        <i class="bi bi-building"></i>
        <h5 class="mt-3">No Buildings Registered</h5>
        <p class="text-muted">You haven't registered any buildings yet. Click the button above to add your first building.</p>
        <button class="btn btn-primary" id="emptyStateAddBtn">
            <i class="bi bi-plus-lg"></i> Add Building
        </button>
    </div>
<?php endif; ?>

<div class="card shadow-sm mt-4">
    <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
        <div class="mb-3 mb-md-0">
            <h6 class="mb-1">Manage Registered Buildings</h6>
            <p class="text-muted mb-0">Open the dedicated table to review, edit, or delete building records.</p>
        </div>
        <a href="buildings-table.php" class="btn btn-outline-success">
            <i class="bi bi-buildings"></i> View Buildings Table
        </a>
    </div>
</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>               
</main>
</div>
</div>
<?php include('../../components/footer.php'); ?>
    <!-- jQuery already included in header.php -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap4.min.js"></script>
    <!-- Bootstrap already included in header.php -->
    
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <!-- Leaflet JS already included in header.php -->
    <!-- SweetAlert2 already included in header.php -->
    <script src="address.js"></script>
    <!-- Autocomplete Script -->
    <script src="realtime-validation.js"></script>
    <script>
    // Global variables for map and marker
    var map = null;
    var marker = null;
    var geoFences = [];
    var geoFencePolygons = [];

    // Global function to load building data for editing
    function loadBuildingForEdit(buildingId) {
        if (!buildingId) {
            return;
        }

        // Switch to form and load data for editing
        $('#buildingsSection').addClass('d-none');
        $('#registrationForm').removeClass('d-none');
        $('#viewBuildingsBtn').removeClass('d-none');
        $('#addBuildingBtn').addClass('d-none');
        $('#cardTitle').html('<i class="bi bi-building-add"></i> Building Registration');
        
        // Reset wizard to step 1 (Gentelella style)
        $('.wizard_steps li a').removeClass('selected done').addClass('disabled').attr('isdone', '0');
        $('.wizard_steps li a[rel="1"]').removeClass('disabled').addClass('selected').attr('isdone', '1');
        $('.step-content').addClass('d-none');
        $('#stepContent1').removeClass('d-none');
        
        // Show loading state
        Swal.fire({
            title: 'Loading Building Data',
            html: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                
                // Fetch real building data from server
                $.ajax({
                    url: window.location.href,
                    method: 'GET',
                    data: {
                        action: 'get_building',
                        building_id: buildingId
                    },
                    success: function(response) {
                        Swal.close();
                        
                        if (response.status === 'success') {
                            const building = response.data;
                            
                            // Populate form with real building data
                            $('#building_id').val(building.id);
                            $('#building_name').val(building.building_name);
                            $('#building_type').val(building.building_type);
                            $('#address').val(building.address);
                            $('#contact_person').val(building.contact_person);
                            $('#contact_number').val(building.contact_number);
                            $('#total_floors').val(building.total_floors);
                            $('#construction_year').val(building.construction_year);
                            $('#building_area').val(building.building_area);
                            $('#has_sprinkler_system').prop('checked', building.has_sprinkler_system);
                            $('#has_fire_alarm').prop('checked', building.has_fire_alarm);
                            $('#has_fire_extinguishers').prop('checked', building.has_fire_extinguishers);
                            $('#has_emergency_exits').prop('checked', building.has_emergency_exits);
                            $('#has_emergency_lighting').prop('checked', building.has_emergency_lighting);
                            $('#has_fire_escape').prop('checked', building.has_fire_escape);
                            $('#last_inspected').val(building.last_inspected);
                            $('#latitude').val(building.latitude);
                            $('#longitude').val(building.longitude);
                            
                            // Populate geo-fence dropdown if geo_fence_id exists and coordinates are available
                            if (building.geo_fence_id && building.latitude && building.longitude) {
                                const lat = parseFloat(building.latitude);
                                const lng = parseFloat(building.longitude);
                                if (!isNaN(lat) && !isNaN(lng)) {
                                    // First, update the dropdown with the geo-fence
                                    updateGeoFenceDropdown(lat, lng);
                                    // Then set the value after dropdown is populated
                                    setTimeout(() => {
                                        $('#geo_fence_id').val(building.geo_fence_id);
                                        // Trigger change event to ensure it's properly set
                                        $('#geo_fence_id').trigger('change');
                                    }, 800);
                                }
                            }
                            
                            // Populate barangay dropdown if barangay_id exists and coordinates are available
                            if (building.barangay_id && building.latitude && building.longitude) {
                                const lat = parseFloat(building.latitude);
                                const lng = parseFloat(building.longitude);
                                if (!isNaN(lat) && !isNaN(lng)) {
                                    // First, update the dropdown with the barangay
                                    updateBarangayDropdown(lat, lng);
                                    // Then set the value after dropdown is populated
                                    setTimeout(() => {
                                        $('#barangay_id').val(building.barangay_id);
                                        // Trigger change event to ensure it's properly set
                                        $('#barangay_id').trigger('change');
                                    }, 800);
                                }
                            }
                            
                            // Initialize map with coordinates if available
                            if (building.latitude && building.longitude) {
                                initMap(parseFloat(building.latitude), parseFloat(building.longitude));
                            } else {
                                initMap();
                            }
                            
                            // Update review section after a short delay to ensure all fields are populated
                            setTimeout(() => {
                                updateReviewSection();
                            }, 1000);
                            
                            // Show success message
                            Swal.fire({
                                title: 'Building Data Loaded',
                                text: 'Building information has been loaded for editing',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: response.message || 'Failed to load building data',
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to load building data. Please try again.',
                            icon: 'error'
                        });
                    }
                });
            }
        });
    }

    // Global function to check if point is in any geo-fence
    function isPointInAnyGeoFence(lat, lng) {
        if (typeof geoFencePolygons === 'undefined' || geoFencePolygons.length === 0) {
            console.log('No geo-fences loaded yet - allowing all locations');
            return { inFence: true, fence: { city_name: 'Loading...' } };
        }
        
        for (let i = 0; i < geoFencePolygons.length; i++) {
            if (isPointInPolygon([lat, lng], geoFencePolygons[i])) {
                return { inFence: true, fence: geoFences[i] };
            }
        }
        return { inFence: false, fence: null };
    }

    // Global helper function to show live feedback
    function showLiveFeedback(element, type, message) {
        const parent = element.parent();
        const feedback = parent.find('.live-feedback');
        
        // Remove existing feedback if any
        if (feedback.length === 0) {
            parent.append(`<div class="live-feedback small mt-1"></div>`);
        }
        
        const feedbackElement = parent.find('.live-feedback');
        feedbackElement.removeClass('text-success text-danger').addClass(`text-${type}`).text(message);
        
        // Update input styling
        element.removeClass('is-valid is-invalid').addClass(`is-${type === 'error' ? 'invalid' : 'valid'}`);
    }

    // Global function to update geo-fence dropdown based on location
    function updateGeoFenceDropdown(lat, lng) {
        const fenceCheck = isPointInAnyGeoFence(lat, lng);
        const geoFenceSelect = $('#geo_fence_id');
        
        if (fenceCheck.inFence) {
            geoFenceSelect.html(`<option value="${fenceCheck.fence.id}" selected>${fenceCheck.fence.city_name}</option>`);
            geoFenceSelect.prop('disabled', false);
            showLiveFeedback(geoFenceSelect, 'success', 'Geo-fence area automatically selected');
        } else {
            geoFenceSelect.html('<option value="">Location outside allowed areas</option>');
            geoFenceSelect.prop('disabled', true);
            showLiveFeedback(geoFenceSelect, 'error', 'Location outside allowed geo-fence areas');
        }
    }

    // Global function to update barangay dropdown based on location
    function updateBarangayDropdown(lat, lng) {
        const barangaySelect = $('#barangay_id');
        
        // Show loading state
        barangaySelect.html('<option value="">Loading nearby barangays...</option>');
        barangaySelect.prop('disabled', true);
        
        // Fetch nearby barangays
        fetch(`get_barangays.php?lat=${lat}&lng=${lng}`)
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success' && data.barangays.length > 0) {
                    let options = '<option value="">Select barangay</option>';
                    data.barangays.forEach((barangay, index) => {
                        const selected = index === 0 ? 'selected' : '';
                        const distance = barangay.distance ? ` (${barangay.distance.toFixed(2)} km)` : '';
                        options += `<option value="${barangay.id}" ${selected}>${barangay.barangay_name}${distance}</option>`;
                    });
                    barangaySelect.html(options);
                    barangaySelect.prop('disabled', false);
                    showLiveFeedback(barangaySelect, 'success', 'Nearby barangays loaded');
                } else {
                    barangaySelect.html('<option value="">No nearby barangays found</option>');
                    barangaySelect.prop('disabled', true);
                    showLiveFeedback(barangaySelect, 'warning', 'No nearby barangays found');
                }
            })
            .catch(error => {
                console.error('Error fetching barangays:', error);
                barangaySelect.html('<option value="">Error loading barangays</option>');
                barangaySelect.prop('disabled', true);
                showLiveFeedback(barangaySelect, 'error', 'Failed to load barangays');
            });
    }

    // Global function to initialize the map
    function initMap(lat = 14.5995, lng = 120.9842) {
        const defaultCoords = [lat, lng];

        // Show loading indicator
        $('.map-loading').show();
        $('.map-error').hide();

        // Check if Leaflet is loaded
        if (typeof L === 'undefined') {
            console.error('Leaflet library not loaded');
            $('.map-loading').hide();
            $('.map-error').show();
            return;
        }
        
        // Check if map container exists
        if ($('#map').length === 0) {
            console.error('Map container not found');
            $('.map-loading').hide();
            $('.map-error').show();
            return;
        }

        // Define base layers with fallback tile servers
        const streetMap = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
            errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        });

        const satelliteMap = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: '&copy; Esri, Maxar, Earthstar Geographics',
            errorTileUrl: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='
        });

        // Initialize map only once
        if (!map) {
            try {
                map = L.map('map', {
                    center: defaultCoords,
                    zoom: 13,
                    layers: [satelliteMap], // Default to satellite view to show green areas
                    preferCanvas: true, // Better performance
                    zoomControl: true,
                    attributionControl: true,
                    zoomControlOptions: {
                        position: 'topright'
                    }
                });

                // Hide loading indicator when map is ready
                map.whenReady(function() {
                    $('.map-loading').hide();
                    console.log('Map initialized successfully');
                });

                // Add error handling for tile loading
                map.on('tileerror', function(e) {
                    console.warn('Tile loading error:', e);
                });
            } catch (error) {
                console.error('Map initialization error:', error);
                $('.map-loading').hide();
                $('.map-error').show();
                return;
            }

            // Add layer control (Street / Satellite) positioned in top-right
            const baseMaps = {
                "Street Map": streetMap,
                "Satellite": satelliteMap
            };
            const layerControl = L.control.layers(baseMaps, null, {
                position: 'topright',
                collapsed: false
            }).addTo(map);

            // Add geo-fence polygons to map (moved to background loading)

            // Add click event to update coordinates
            map.on('click', function(e) {
                const lat = e.latlng.lat;
                const lng = e.latlng.lng;
                const fenceCheck = isPointInAnyGeoFence(lat, lng);
                
                // Always allow manual pinpointing, but show validation feedback
                updateCoordinates(lat, lng);
                
                if (fenceCheck.inFence) {
                    // Show success message for valid location
                    showToast('success', `Location selected in ${fenceCheck.fence.city_name}`);
                    // Update instruction text to show success
                    $('.map-instructions span').text('✓ Location selected in ' + fenceCheck.fence.city_name);
                    $('.map-instructions').css('background', 'rgba(75, 181, 67, 0.95)').css('color', 'white');
                } else {
                    const allowedCities = typeof geoFences !== 'undefined' && geoFences.length > 0 ? geoFences.map(fence => fence.city_name).join(', ') : 'No areas configured';
                    
                    // Show invalid popup modal
                    Swal.fire({
                        title: 'Invalid Location',
                        html: `
                            <div class="text-center">
                                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                                <p class="mb-3">The selected location is outside the allowed areas.</p>
                                <p class="mb-3"><strong>Allowed areas:</strong> ${allowedCities}</p>
                                <p class="text-muted">Please select a location within the allowed boundaries.</p>
                            </div>
                        `,
                        icon: 'warning',
                        confirmButtonText: 'Refresh Map & Try Again',
                        confirmButtonColor: '#ff8c00',
                        showCancelButton: true,
                        cancelButtonText: 'Keep Location',
                        cancelButtonColor: '#6c757d',
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Auto refresh map
                            if (typeof refreshMap === 'function') {
                                refreshMap();
                            }
                        }
                    });
                    
                    // Show warning toast
                    showToast('warning', `Location outside allowed areas: ${allowedCities}`);
                    // Update instruction text to show warning
                    $('.map-instructions span').text('⚠ Location outside allowed areas');
                    $('.map-instructions').css('background', 'rgba(248, 150, 30, 0.95)').css('color', 'white');
                }
            });
            
            // Ensure map is clickable
            map.dragging.enable();
            map.touchZoom.enable();
            map.doubleClickZoom.enable();
            map.scrollWheelZoom.enable();
            map.boxZoom.enable();
            map.keyboard.enable();
        }

        // Add or update marker
        if (marker) {
            marker.setLatLng(defaultCoords);
        } else {
            marker = L.marker(defaultCoords).addTo(map);
        }

        // Update form input fields (if present)
        $('#latitude').val(lat.toFixed(6));
        $('#longitude').val(lng.toFixed(6));
        
        // Automatically trigger reverse geocoding for default coordinates
        setTimeout(() => {
            const currentLat = parseFloat($('#latitude').val());
            const currentLng = parseFloat($('#longitude').val());
            
            if (!isNaN(currentLat) && !isNaN(currentLng)) {
                showLiveFeedback($('#latitude'), 'info', 'Getting address...');
                showLiveFeedback($('#longitude'), 'info', 'Getting address...');
                
                if (typeof getAddressFromCoordinates === 'function') {
                    getAddressFromCoordinates(currentLat, currentLng)
                        .then(address => {
                            $('#address').val(address);
                            showLiveFeedback($('#address'), 'success', 'Address updated automatically');
                            
                            // Display the retrieved address
                            $('#retrievedAddressText').text(address);
                            $('#retrievedAddressDisplay').show();
                            
                            // Clear loading indicators and show success
                            if (typeof clearLiveFeedback === 'function') {
                                clearLiveFeedback($('#latitude'));
                                clearLiveFeedback($('#longitude'));
                            }
                            showLiveFeedback($('#latitude'), 'success', 'Address retrieved');
                            showLiveFeedback($('#longitude'), 'success', 'Address retrieved');
                            
                            if (typeof showToast === 'function') {
                                showToast('success', 'Address automatically updated from coordinates!');
                            }
                        })
                        .catch(error => {
                            console.error('Failed to get address:', error);
                            if (typeof clearLiveFeedback === 'function') {
                                clearLiveFeedback($('#latitude'));
                                clearLiveFeedback($('#longitude'));
                            }
                            showLiveFeedback($('#latitude'), 'warning', 'Could not get address');
                            showLiveFeedback($('#longitude'), 'warning', 'Could not get address');
                        });
                }
            }
        }, 500); // Reduced delay for faster loading
    }

    // Global function to update review section
    function updateReviewSection() {
        // Basic Information
        const buildingName = $('#building_name').val() || '';
        $('#review_building_name').text(buildingName || '-');
        
        const buildingType = $('#building_type').val();
        const buildingTypeText = buildingType ? ($('#building_type option:selected').text() || buildingType) : '';
        $('#review_building_type').text(buildingTypeText || '-');
        
        const address = $('#address').val() || '';
        $('#review_address').text(address || '-');
        
        const contactPerson = $('#contact_person').val() || '';
        $('#review_contact_person').text(contactPerson || '-');
        
        const contactNumber = $('#contact_number').val() || '';
        $('#review_contact_number').text(contactNumber || '-');
        
        const totalFloors = $('#total_floors').val() || '';
        $('#review_total_floors').text(totalFloors || '-');
        
        const constructionYear = $('#construction_year').val() || '';
        $('#review_construction_year').text(constructionYear || '-');
        
        const buildingArea = $('#building_area').val() || '';
        $('#review_building_area').text(buildingArea ? buildingArea + ' m²' : '-');
        
        // Format inspection date
        const lastInspected = $('#last_inspected').val() || '';
        if (lastInspected) {
            try {
                const inspectionDate = new Date(lastInspected);
                if (!isNaN(inspectionDate.getTime())) {
                    $('#review_last_inspected').text(inspectionDate.toLocaleDateString());
                } else {
                    $('#review_last_inspected').text(lastInspected);
                }
            } catch (e) {
                $('#review_last_inspected').text(lastInspected);
            }
        } else {
            $('#review_last_inspected').text('-');
        }
        
        // Update safety features
        const safetyFeatures = [];
        if ($('#has_sprinkler_system').is(':checked')) safetyFeatures.push('<span class="badge bg-primary me-1">Sprinkler System</span>');
        if ($('#has_fire_alarm').is(':checked')) safetyFeatures.push('<span class="badge bg-danger me-1">Fire Alarm</span>');
        if ($('#has_fire_extinguishers').is(':checked')) safetyFeatures.push('<span class="badge bg-warning text-dark me-1">Fire Extinguishers</span>');
        if ($('#has_emergency_exits').is(':checked')) safetyFeatures.push('<span class="badge bg-success me-1">Emergency Exits</span>');
        if ($('#has_emergency_lighting').is(':checked')) safetyFeatures.push('<span class="badge bg-info me-1">Emergency Lighting</span>');
        if ($('#has_fire_escape').is(':checked')) safetyFeatures.push('<span class="badge bg-dark me-1">Fire Escape</span>');
        
        if (safetyFeatures.length > 0) {
            $('#review_safety_features').html(safetyFeatures.join(' '));
        } else {
            $('#review_safety_features').html('<span class="badge bg-secondary">None specified</span>');
        }
        
        // Update geo-fence - handle both selected option text and value
        const geoFenceId = $('#geo_fence_id').val();
        let geoFenceText = '';
        if (geoFenceId) {
            const selectedOption = $('#geo_fence_id option:selected');
            geoFenceText = selectedOption.length > 0 ? selectedOption.text() : '';
            // If no text found, try to get from the option value
            if (!geoFenceText && selectedOption.length > 0) {
                geoFenceText = selectedOption.val() || '';
            }
        }
        $('#review_geo_fence').text(geoFenceText || '-');
        
        // Update barangay - handle both selected option text and value
        const barangayId = $('#barangay_id').val();
        let barangayText = '';
        if (barangayId) {
            const selectedOption = $('#barangay_id option:selected');
            barangayText = selectedOption.length > 0 ? selectedOption.text() : '';
            // If no text found, try to get from the option value
            if (!barangayText && selectedOption.length > 0) {
                barangayText = selectedOption.val() || '';
            }
        }
        $('#review_barangay').text(barangayText || '-');
        
        // Update location (if review_location element exists)
        const latitude = $('#latitude').val();
        const longitude = $('#longitude').val();
        if ($('#review_location').length) {
            if (latitude && longitude) {
                $('#review_location').html(`
                    <p class="mb-1">Latitude: ${latitude}</p>
                    <p class="mb-1">Longitude: ${longitude}</p>
                    <a href="https://www.google.com/maps?q=${latitude},${longitude}" target="_blank" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-map"></i> View on Map
                    </a>
                `);
            } else {
                $('#review_location').html('<span class="badge bg-secondary">Not specified</span>');
            }
        }
    }

      $(document).ready(function() {
    // Initialize Bootstrap 5 tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize DataTables for buildings table (Gentelella style)
    let buildingsTable;
    function initializeBuildingsTable() {
        const $buildingsTable = $('#buildingsTable');
        if (!$buildingsTable.length) {
            return;
        }
        const tableEl = $buildingsTable.get(0);
        if (!tableEl) {
            return;
        }
        
        if ($.fn.DataTable.isDataTable(tableEl)) {
            $(tableEl).DataTable().destroy();
        }
        
        buildingsTable = $(tableEl).DataTable({
            "processing": true,
            "serverSide": false,
            "responsive": true,
            "autoWidth": false,
            "scrollX": true,
            "scrollCollapse": true,
            "pageLength": 10,
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "language": {
                "lengthMenu": "Show _MENU_ entries",
                "search": "Search:",
                "info": "Showing _START_ to _END_ of _TOTAL_ buildings",
                "infoEmpty": "No buildings to display",
                "infoFiltered": "(filtered from _MAX_ total buildings)",
                "zeroRecords": "No matching buildings found",
                "emptyTable": "No buildings available",
                "processing": "Processing...",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            },
            "order": [[0, "asc"]], // Sort by building name by default
            "columnDefs": [
                {
                    "targets": [5], // Actions column
                    "orderable": false,
                    "searchable": false,
                    "width": "150px"
                },
                {
                    "targets": [0], // Building Name
                    "width": "25%"
                },
                {
                    "targets": [1], // Type
                    "width": "10%"
                },
                {
                    "targets": [2], // Address
                    "width": "35%"
                },
                {
                    "targets": [3], // Floors
                    "width": "8%"
                },
                {
                    "targets": [4], // Last Inspection
                    "width": "12%"
                }
            ],
            "dom": '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
                   '<"row"<"col-sm-12"tr>>' +
                   '<"row"<"col-sm-5"i><"col-sm-7"p>>'
        });
    }

    if (!$('#buildingsSection').hasClass('d-none')) {
        initializeBuildingsTable();
    }

    // Initialize DataTables when buildings section is shown
    $(document).on('click', '#emptyStateAddBtn', function() {
        setTimeout(function() {
            if ($('#buildingsTable').length && !$.fn.DataTable.isDataTable('#buildingsTable')) {
                initializeBuildingsTable();
            }
        }, 100);
    });

    // Refresh map button handler
    $('#refreshMapBtn').click(function() {
        $(this).prop('disabled', true);
        $(this).html('<i class="bi bi-arrow-clockwise spin"></i> Refreshing...');
        
        refreshMap();
        
        // Re-enable button after refresh
        setTimeout(() => {
            $('#refreshMapBtn').prop('disabled', false);
            $('#refreshMapBtn').html('<i class="bi bi-arrow-clockwise"></i> Refresh');
        }, 2000);
    });
    
    // Test database connection (for debugging)
    function testDatabaseConnection() {
        $.ajax({
            type: 'POST',
            url: 'main.php',
            data: { test_db: true },
            success: function(response) {
                console.log('Database test response:', response);
                if (response.status === 'success') {
                    showToast('success', 'Database connection successful');
                } else {
                    showToast('error', 'Database connection failed: ' + response.message);
                }
            },
            error: function(xhr) {
                console.error('Database test error:', xhr);
                showToast('error', 'Database test failed');
            }
        });
    }
    

    // Helper function to update wizard steps (Gentelella style)
    function updateWizardSteps(currentStep, nextStep, isForward) {
        // Update wizard step indicators
        const currentLink = $(`.wizard_steps li a[rel="${currentStep}"]`);
        const nextLink = $(`.wizard_steps li a[rel="${nextStep}"]`);
        
        if (isForward) {
            // Mark current step as done
            currentLink.removeClass('selected disabled').addClass('done').attr('isdone', '1');
            // Mark next step as selected
            nextLink.removeClass('disabled done').addClass('selected').attr('isdone', '1');
        } else {
            // Mark current step as disabled
            currentLink.removeClass('selected done').addClass('disabled').attr('isdone', '0');
            // Mark previous step as selected
            nextLink.removeClass('disabled done').addClass('selected').attr('isdone', '1');
        }
    }
    
    // Form step navigation
    $('.next-step').click(function() {
        const nextStep = parseInt($(this).data('next'), 10);
        const currentStep = nextStep - 1;
        
        // Validate current step before proceeding
        let isValid = true;
        
        if (currentStep === 1) {
            isValid = validateStep1();
        } else if (currentStep === 2) {
            isValid = validateStep2();
        } else if (currentStep === 3) {
            isValid = validateStep3();
        }
        
        // Additional comprehensive validation
        if (isValid) {
            isValid = validateAllRequiredFields(currentStep);
        }
        
        if (!isValid) {
            // Show specific error message and scroll to first error
            scrollToFirstError(currentStep);
            return false;
        }
        
        // Update wizard steps (Gentelella style)
        updateWizardSteps(currentStep, nextStep, true);
        
        // Update step content visibility
        $(`.step-content`).addClass('d-none');
        $(`#stepContent${nextStep}`).removeClass('d-none');
        
        // Auto-refresh map when entering step 3 (Location Details)
        if (nextStep === 3) {
            setTimeout(() => {
                refreshMap();
            }, 300);
        }
        
        // Scroll to top of card
        const mainCard = $('#mainCard');
        if (mainCard.length) {
            $('html, body').animate({
                scrollTop: mainCard.offset().top - 20
            }, 300);
        }
        
        // Update review section if going to step 4
        if (nextStep === 4) {
            updateReviewSection();
            // Add a small delay to ensure async dropdowns are fully populated
            setTimeout(() => {
                updateReviewSection();
            }, 300);
        }
    });
    
    $('.prev-step').click(function() {
        const prevStep = parseInt($(this).data('prev'), 10);
        const currentStep = prevStep + 1;
        
        // Update wizard steps (Gentelella style)
        updateWizardSteps(currentStep, prevStep, false);
        
        // Update step content visibility
        $(`.step-content`).addClass('d-none');
        $(`#stepContent${prevStep}`).removeClass('d-none');
        
        // Auto-refresh map when entering step 3 (Location Details) from previous step
        if (prevStep === 3) {
            setTimeout(() => {
                refreshMap();
            }, 300);
        }
        
        // Scroll to top of card
        const mainCard = $('#mainCard');
        if (mainCard.length) {
            $('html, body').animate({
                scrollTop: mainCard.offset().top - 20
            }, 300);
        }
    });
    
    // Toggle between form and buildings list
    $(document).on('click', '#emptyStateAddBtn', function() {
        $('#registrationForm').addClass('d-none');
        $('#buildingsSection').removeClass('d-none').addClass('fade-in');
        $('#viewBuildingsBtn').addClass('d-none');
        $('#addBuildingBtn').removeClass('d-none');
        $('#cardTitle').html('<i class="bi bi-buildings"></i> My Registered Buildings');
        // Smooth scroll to top of card
        const mainCard = $('#mainCard');
        if (mainCard.length) {
            $('html, body').animate({ scrollTop: mainCard.offset().top - 20 }, 300);
        }
    });
    
    // Handle barangay migration
    $('#migrateBarangayBtn').click(function() {
        Swal.fire({
            title: 'Update Barangay Data',
            text: 'This will update barangay information for all your buildings based on their coordinates. Continue?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, update',
            cancelButtonText: 'Cancel',
            showLoaderOnConfirm: true,
            preConfirm: () => {
                return fetch('migrate_barangay_id.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            return data;
                        } else {
                            throw new Error(data.message);
                        }
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Update failed: ${error.message}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Update Complete!',
                    text: result.value.message,
                    icon: 'success',
                    confirmButtonColor: '#28a745'
                }).then(() => {
                    location.reload();
                });
            }
        });
    });
    
    $('#addBuildingBtn').click(function() {
        $('#buildingsSection').addClass('d-none');
        $('#registrationForm').removeClass('d-none').addClass('fade-in');
        $('#viewBuildingsBtn').removeClass('d-none');
        $('#addBuildingBtn').addClass('d-none');
        $('#cardTitle').html('<i class="bi bi-building-add"></i> Building Registration');
        
        // Reset form to step 1 (Gentelella style)
        $('.wizard_steps li a').removeClass('selected done').addClass('disabled').attr('isdone', '0');
        $('.wizard_steps li a[rel="1"]').removeClass('disabled').addClass('selected').attr('isdone', '1');
        $('.step-content').addClass('d-none');
        $('#stepContent1').removeClass('d-none');
        
        // Reset form validation
        $('.is-invalid').removeClass('is-invalid');
        $('#buildingForm')[0].reset();
        $('#building_id').val(''); // Clear building ID for new entry
        
        // Clear any existing address display
        $('#retrievedAddressDisplay').hide();
        
        // Clear any existing feedback
        clearLiveFeedback($('#latitude'));
        clearLiveFeedback($('#longitude'));
        clearLiveFeedback($('#address'));
        
        // Scroll to top of card
        $('html, body').animate({ scrollTop: $('#mainCard').offset().top - 20 }, 300);
    });
    
    // Initialize Leaflet map
    // map, marker, geoFences, and geoFencePolygons are already declared globally

    // Load geo-fences from database
    function loadGeoFences() {
        return fetch('get_geo_fences.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    geoFences = data.geo_fences;
                    geoFencePolygons = geoFences.map(fence => fence.polygon);
                    console.log('Loaded geo-fences:', geoFences.length);
                    return geoFences;
                } else {
                    console.error('Failed to load geo-fences:', data.message);
                    return [];
                }
            })
            .catch(error => {
                console.error('Error loading geo-fences:', error);
                return [];
            });
    }

    // Function to refresh the map
    function refreshMap() {
        if (map) {
            // Remove existing map
            map.remove();
            map = null;
            marker = null;
            
            // Clear geo-fence data
            geoFences = [];
            geoFencePolygons = [];
            
            // Reset instruction text
            $('.map-instructions').html(`
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="bi bi-info-circle text-primary" style="font-size: 16px;"></i>
                    <span style="font-weight: 500;">Click inside the green areas to select location</span>
                </div>
            `);
            $('.map-instructions').css('background', 'rgba(255,255,255,0.95)').css('color', 'inherit');
            
            // Reinitialize map
            setTimeout(() => {
                initMap();
                loadGeoFences().then(() => {
                    console.log('Map refreshed - Geo-fences reloaded');
                    if (map && geoFencePolygons.length > 0) {
                        geoFencePolygons.forEach((polygon, index) => {
                            const fence = geoFences[index];
                            L.polygon(polygon, {
                                color: '#28a745',
                                fillColor: '#28a745',
                                fillOpacity: 0,
                                strokeOpacity: 0,
                                weight: 0,
                                interactive: false,
                                dashArray: ''
                            }).addTo(map).bindPopup(`<strong>${fence.city_name}</strong><br>Allowed Area - Click inside to select location`);
                        });
                        
                        // Fit map to show all polygons with padding
                        if (geoFencePolygons.length === 1) {
                            map.fitBounds(L.polygon(geoFencePolygons[0]).getBounds(), { padding: [20, 20] });
                        } else {
                            const group = new L.featureGroup();
                            geoFencePolygons.forEach(polygon => {
                                group.addLayer(L.polygon(polygon));
                            });
                            map.fitBounds(group.getBounds(), { padding: [20, 20] });
                        }
                        
                        $('.map-instructions').html('<i class="bi bi-check-circle text-success"></i> Click inside the green areas to select location');
                    }
                });
            }, 100);
        }
    }

    function updateCoordinates(lat, lng) {
        $('#latitude').val(lat.toFixed(6));
        $('#longitude').val(lng.toFixed(6));
        
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng]).addTo(map);
        }
        
        // Provide feedback
        showLiveFeedback($('#latitude'), 'success', 'Location updated');
        showLiveFeedback($('#longitude'), 'success', 'Location updated');
        
        // Automatically get address from coordinates
        showLiveFeedback($('#latitude'), 'info', 'Getting address...');
        showLiveFeedback($('#longitude'), 'info', 'Getting address...');
        
        // Update geo-fence dropdown
        updateGeoFenceDropdown(lat, lng);
        
        // Update barangay dropdown
        updateBarangayDropdown(lat, lng);
        
        getAddressFromCoordinates(lat, lng)
            .then(address => {
                $('#address').val(address);
                showLiveFeedback($('#address'), 'success', 'Address updated automatically');
                
                // Display the retrieved address
                $('#retrievedAddressText').text(address);
                $('#retrievedAddressDisplay').show();
                
                // Clear loading indicators and show success
                clearLiveFeedback($('#latitude'));
                clearLiveFeedback($('#longitude'));
                showLiveFeedback($('#latitude'), 'success', 'Location and address updated');
                showLiveFeedback($('#longitude'), 'success', 'Location and address updated');
                
                showToast('success', 'Location and address updated automatically!');
            })
            .catch(error => {
                console.error('Failed to get address:', error);
                clearLiveFeedback($('#latitude'));
                clearLiveFeedback($('#longitude'));
                showLiveFeedback($('#latitude'), 'warning', 'Location updated - Could not get address');
                showLiveFeedback($('#longitude'), 'warning', 'Location updated - Could not get address');
            });
    }
    
    // Function to get address from coordinates using reverse geocoding via PHP endpoint
    function getAddressFromCoordinates(lat, lng) {
        return new Promise((resolve, reject) => {
            // Call PHP endpoint to avoid CORS issues
            const url = `main.php?action=get_address&lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`;
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success' && data.address) {
                        resolve(data.address);
                    } else {
                        reject(new Error(data.message || 'No address found for these coordinates'));
                    }
                })
                .catch(error => {
                    console.error('Reverse geocoding error:', error);
                    reject(error);
                });
        });
    }

    // Get current location button
    $('#getLocationBtn').click(function() {
        if (navigator.geolocation) {
            $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Locating...').prop('disabled', true);
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    // Initialize map if not already done
                    if (!map) {
                        initMap(lat, lng);
                    } else {
                        map.setView([lat, lng], 16);
                        updateCoordinates(lat, lng);
                    }
                    
                    // updateCoordinates function now handles reverse geocoding automatically
                    $('#getLocationBtn').html('<i class="bi bi-geo-alt"></i> Use My Current Location').prop('disabled', false);
                },
                function(error) {
                    let errorMessage = "Unable to retrieve your location: ";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += "You denied the request for geolocation.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += "Location information is unavailable.";
                            break;
                        case error.TIMEOUT:
                            errorMessage += "The request to get location timed out.";
                            break;
                        default:
                            errorMessage += "An unknown error occurred.";
                    }
                    
                    $('#getLocationBtn').html('<i class="bi bi-geo-alt"></i> Use My Current Location').prop('disabled', false);
                    showToast('error', errorMessage);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            showToast('error', 'Geolocation is not supported by your browser');
        }
    });
    
    $('#buildingForm').on('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: 'Confirm Submission',
        text: 'Are you sure you want to register this building?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#4361ee',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, submit it!',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: 'main.php',
                    data: $('#buildingForm').serialize(),
                    success: function(res) {
                        console.log('Form submission response:', res);
                        resolve(res);
                    },
                    error: function(xhr, status, error) {
                        console.error('Form submission error:', {xhr, status, error});
                        let errorMessage = 'Failed to connect to server';
                        if (xhr.responseText) {
                            try {
                                const errorData = JSON.parse(xhr.responseText);
                                errorMessage = errorData.message || errorMessage;
                            } catch (e) {
                                errorMessage = xhr.responseText || errorMessage;
                            }
                        }
                        reject(new Error(errorMessage));
                    }
                });
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed) {
            const res = result.value;
            try {
                const data = typeof res === 'string' ? JSON.parse(res) : res;
                console.log('Parsed response data:', data);
                
                if (data.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        // Switch to buildings view and refresh DataTable
                        $('#registrationForm').addClass('d-none');
                        $('#buildingsSection').removeClass('d-none');
                        $('#viewBuildingsBtn').removeClass('d-none');
                        $('#addBuildingBtn').removeClass('d-none');
                        $('#cardTitle').html('<i class="bi bi-buildings"></i> My Registered Buildings');
                        
                        // Reload the page to get updated data
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        html: data.message || 'Unknown error occurred',
                        icon: 'error',
                        confirmButtonColor: '#4361ee'
                    });
                }
            } catch (e) {
                console.error('Response parsing error:', e);
                Swal.fire({
                    title: 'Error!',
                    text: 'Invalid server response: ' + (typeof res === 'string' ? res : JSON.stringify(res)),
                    icon: 'error'
                });
            }
        }
    }).catch((error) => {
        console.error('Form submission failed:', error);
        Swal.fire({
            title: 'Error!',
            text: error.message || 'Failed to submit form',
            icon: 'error',
            confirmButtonColor: '#4361ee'
        });
    });
});

    // View building details
    $('.view-building').click(function() {
        const buildingId = $(this).data('id');
        
        // Show loading state
        Swal.fire({
            title: 'Loading Building Details',
            html: 'Please wait...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
                
                // Fetch real building data from server
                $.ajax({
                    url: window.location.href,
                    method: 'GET',
                    data: {
                        action: 'get_building',
                        building_id: buildingId
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            const building = response.data;

                            const formatValue = (value, fallback = 'Not provided') => {
                                if (value === null || value === undefined) {
                                    return fallback;
                                }
                                const trimmed = value.toString().trim();
                                return trimmed.length ? trimmed : fallback;
                            };

                            const formatNumberValue = (value, fallback = 'Not set') => {
                                if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
                                    return fallback;
                                }
                                return value;
                            };

                            const formatDateValue = (value) => {
                                if (!value) {
                                    return 'Not inspected';
                                }
                                const parsedDate = new Date(value);
                                if (Number.isNaN(parsedDate.getTime())) {
                                    return value;
                                }
                                return parsedDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                            };

                            const capitalize = (value) => {
                                const formatted = formatValue(value, 'Unspecified');
                                return formatted.charAt(0).toUpperCase() + formatted.slice(1);
                            };

                            const detailItem = (label, value) => `
                                <div class="detail-item">
                                    <span class="detail-label">${label}</span>
                                    <span class="detail-item-value">${value}</span>
                                </div>
                            `;

                            const safetyFeatures = [
                                { label: 'Sprinkler System', active: !!building.has_sprinkler_system },
                                { label: 'Fire Alarm', active: !!building.has_fire_alarm },
                                { label: 'Fire Extinguishers', active: !!building.has_fire_extinguishers },
                                { label: 'Emergency Exits', active: !!building.has_emergency_exits },
                                { label: 'Emergency Lighting', active: !!building.has_emergency_lighting },
                                { label: 'Fire Escape', active: !!building.has_fire_escape }
                            ];

                            const safetyBadges = safetyFeatures.map(feature => `
                                <span class="safety-badge ${feature.active ? 'active' : ''}">${feature.label}</span>
                            `).join('');

                            const locationSection = (building.latitude && building.longitude) ? `
                                <div class="detail-section">
                                    <span class="detail-label">Location</span>
                                    <div class="location-grid">
                                        ${detailItem('Latitude', formatValue(building.latitude, 'Not set'))}
                                        ${detailItem('Longitude', formatValue(building.longitude, 'Not set'))}
                                    </div>
                                </div>
                            ` : '';

                            const buildingDetails = `
                                <div class="building-detail-card">
                                    <div class="building-detail-header">
                                        <div>
                                            <span class="detail-label">Building</span>
                                            <h4>${formatValue(building.building_name, 'Unnamed Building')}</h4>
                                            <p class="detail-meta">${formatValue(building.address, 'Address not provided')}</p>
                                        </div>
                                        <span class="detail-type-badge">${capitalize(building.building_type)}</span>
                                    </div>

                                    <div class="detail-grid">
                                        ${detailItem('Type', capitalize(building.building_type))}
                                        ${detailItem('Floors', formatNumberValue(building.total_floors))}
                                        ${detailItem('Floor Area', building.building_area ? `${building.building_area} sq m` : 'Not set')}
                                        ${detailItem('Construction Year', formatValue(building.construction_year, 'Not set'))}
                                        ${detailItem('Last Inspection', formatDateValue(building.last_inspected))}
                                    </div>

                                    <div class="detail-section">
                                        <span class="detail-label">Contact</span>
                                        <div class="detail-grid">
                                            ${detailItem('Contact Person', formatValue(building.contact_person, 'Not provided'))}
                                            ${detailItem('Contact Number', formatValue(building.contact_number, 'Not provided'))}
                                        </div>
                                    </div>

                                    <div class="detail-divider"></div>

                                    <div class="detail-section">
                                        <span class="detail-label">Safety Features</span>
                                        <div class="safety-badges">
                                            ${safetyBadges}
                                        </div>
                                    </div>

                                    ${locationSection}
                                </div>
                            `;
                            
                            // Close SweetAlert and show Bootstrap modal
                            Swal.close();
                            
                            // Ensure modal is properly reset before showing
                            $('#buildingDetailsModal').removeClass('show');
                            $('body').removeClass('modal-open');
                            $('.modal-backdrop').remove();
                            
                            // Small delay to ensure state is reset
                            setTimeout(function() {
                                // Set modal content and show
                                $('#buildingDetailsModal .modal-body').html(buildingDetails);
                                $('#buildingDetailsModal').modal('show');
                                
                                // Store building ID for edit functionality
                                $('#buildingDetailsModal').data('building-id', buildingId);
                            }, 100);
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: response.message || 'Failed to load building details',
                                icon: 'error'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to load building details. Please try again.',
                            icon: 'error'
                        });
                    }
                });
            }
        });
    });

    // Edit building button handler (delegated for future dynamic lists)
    $(document).on('click', '.edit-building', function() {
        const buildingId = $(this).data('id');
        loadBuildingForEdit(buildingId);
    });

// Delete building handler
$(document).on('click', '.delete-building', function() {
    const buildingId = $(this).data('id');
    const buildingName = $(this).closest('tr').find('td:first').text();
    
    Swal.fire({
        title: 'Delete Building',
        html: `Are you sure you want to delete <strong>${buildingName}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return $.ajax({
                type: 'DELETE',
                url: '',
                contentType: 'application/json',
                data: JSON.stringify({ building_id: buildingId }),
                dataType: 'json'
            }).catch(error => {
                console.error('Delete error:', error);
                let errorMsg = 'Failed to delete building';
                if (error.responseJSON && error.responseJSON.message) {
                    errorMsg = error.responseJSON.message;
                }
                Swal.showValidationMessage(errorMsg);
                return Promise.reject(error);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            if (result.value.status === 'success') {
                Swal.fire({
                    title: 'Deleted!',
                    text: result.value.message,
                    icon: 'success'
                }).then(() => {
                    // Remove the building row from DataTable
                    if (buildingsTable && $.fn.DataTable.isDataTable('#buildingsTable')) {
                        buildingsTable.row($(`tr[data-id="${buildingId}"]`)).remove().draw();
                        
                        // Show empty state if no buildings left
                        if (buildingsTable.data().count() === 0) {
                            $('#buildingsSection .table-responsive').html(`
                                <div class="empty-state">
                                    <i class="bi bi-building"></i>
                                    <h5>No Buildings Found</h5>
                                    <button class="btn btn-primary mt-3" id="addBuildingBtn">
                                        <i class="bi bi-plus-lg"></i> Add Building
                                    </button>
                                </div>
                            `);
                        }
                    } else {
                        // Fallback for non-DataTable scenario
                        $(`tr[data-id="${buildingId}"]`).remove();
                        
                        // Show empty state if no buildings left
                        if ($('tbody tr').length === 0) {
                            $('#buildingsSection .table-responsive').html(`
                                <div class="empty-state">
                                    <i class="bi bi-building"></i>
                                    <h5>No Buildings Found</h5>
                                    <button class="btn btn-primary mt-3" id="addBuildingBtn">
                                        <i class="bi bi-plus-lg"></i> Add Building
                                    </button>
                                </div>
                            `);
                        }
                    }
                });
            } else {
                Swal.fire('Error', result.value.message || 'Unknown error', 'error');
            }
        }
    });
});
    // Live validation for building name
    $('#building_name').on('input', function() {
        const value = $(this).val().trim();
        if (!value) {
            showLiveFeedback($(this), 'error', 'Building name is required');
        } else if (value.length < 2 || value.length > 100) {
            showLiveFeedback($(this), 'error', 'Must be 2-100 characters');
        } else {
            showLiveFeedback($(this), 'success', 'Looks good!');
        }
    });
    
    // Live validation for building type
    $('#building_type').on('change', function() {
        const value = $(this).val();
        if (!value) {
            showLiveFeedback($(this), 'error', 'Building type is required');
        } else {
            showLiveFeedback($(this), 'success', 'Looks good!');
        }
    });
    

    
    // Live validation for total floors
    $('#total_floors').on('input', function() {
        const value = $(this).val();
        if (!value) {
            showLiveFeedback($(this), 'error', 'Total floors is required');
        } else if (isNaN(value)) {
            showLiveFeedback($(this), 'error', 'Must be a number');
        } else if (value < 1 || value > 200) {
            showLiveFeedback($(this), 'error', 'Must be between 1-200');
        } else {
            showLiveFeedback($(this), 'success', 'Looks good!');
        }
    });
    
    // Live validation for contact person
    $('#contact_person').on('input', function() {
        const value = $(this).val().trim();
        if (value && (value.length < 2 || value.length > 100)) {
            showLiveFeedback($(this), 'error', 'Must be 2-100 characters');
        } else if (value) {
            showLiveFeedback($(this), 'success', 'Looks good!');
        } else {
            clearLiveFeedback($(this));
        }
    });
    
    // Live validation for contact number
    $('#contact_number').on('input', function() {
        const value = $(this).val().trim();
        if (value) {
            const phPhoneRegex = /^(?:\+?63|0)?9\d{9}$/;
            const normalizedNumber = value.replace(/[-\s]/g, '');
            if (!phPhoneRegex.test(normalizedNumber)) {
                showLiveFeedback($(this), 'error', 'Invalid Philippine mobile number');
            } else if (value.length > 13) {
                showLiveFeedback($(this), 'error', 'Must not exceed 13 characters');
            } else {
                showLiveFeedback($(this), 'success', 'Looks good!');
            }
        } else {
            clearLiveFeedback($(this));
        }
    });
    
    // Live validation for construction year
    $('#construction_year').on('input', function() {
        const value = $(this).val();
        const currentYear = new Date().getFullYear();
        if (value) {
            if (isNaN(value)) {
                showLiveFeedback($(this), 'error', 'Must be a number');
            } else if (value < 1800 || value > currentYear) {
                showLiveFeedback($(this), 'error', `Must be between 1800-${currentYear}`);
            } else {
                showLiveFeedback($(this), 'success', 'Looks good!');
            }
        } else {
            clearLiveFeedback($(this));
        }
    });
    
    // Live validation for building area
    $('#building_area').on('input', function() {
        const value = $(this).val();
        if (value) {
            if (isNaN(value)) {
                showLiveFeedback($(this), 'error', 'Must be a number');
            } else if (value <= 0) {
                showLiveFeedback($(this), 'error', 'Must be greater than 0');
            } else {
                showLiveFeedback($(this), 'success', 'Looks good!');
            }
        } else {
            clearLiveFeedback($(this));
        }
    });
    
    // Live validation for latitude and longitude with automatic reverse geocoding
    let geocodingTimeout;
    $('#latitude, #longitude').on('input', function() {
        const lat = parseFloat($('#latitude').val());
        const lng = parseFloat($('#longitude').val());
        
        // Clear previous timeout
        if (geocodingTimeout) {
            clearTimeout(geocodingTimeout);
        }
        
        if (!isNaN(lat) && !isNaN(lng)) {
            const fenceCheck = isPointInAnyGeoFence(lat, lng);
            
            // Update map if available
            if (map && marker) marker.setLatLng([lat, lng]);
            
            // Automatically get address from coordinates after a short delay
            geocodingTimeout = setTimeout(() => {
                // Show loading indicator
                showLiveFeedback($('#latitude'), 'info', 'Getting address...');
                showLiveFeedback($('#longitude'), 'info', 'Getting address...');
                
                getAddressFromCoordinates(lat, lng)
                    .then(address => {
                        $('#address').val(address);
                        showLiveFeedback($('#address'), 'success', 'Address updated automatically');
                        
                        // Display the retrieved address
                        $('#retrievedAddressText').text(address);
                        $('#retrievedAddressDisplay').show();
                        
                        // Clear loading indicators and show success
                        clearLiveFeedback($('#latitude'));
                        clearLiveFeedback($('#longitude'));
                        
                        if (fenceCheck.inFence) {
                            showLiveFeedback($('#latitude'), 'success', `Valid location in ${fenceCheck.fence.city_name} - Address retrieved`);
                            showLiveFeedback($('#longitude'), 'success', `Valid location in ${fenceCheck.fence.city_name} - Address retrieved`);
                        } else {
                            const allowedCities = geoFences.length > 0 ? geoFences.map(fence => fence.city_name).join(', ') : 'No areas configured';
                            showLiveFeedback($('#latitude'), 'warning', `Location outside allowed areas: ${allowedCities} - Address retrieved`);
                            showLiveFeedback($('#longitude'), 'warning', `Location outside allowed areas: ${allowedCities} - Address retrieved`);
                        }
                        
                        showToast('success', 'Address automatically updated from coordinates!');
                    })
                    .catch(error => {
                        console.error('Failed to get address:', error);
                        clearLiveFeedback($('#latitude'));
                        clearLiveFeedback($('#longitude'));
                        
                        if (fenceCheck.inFence) {
                            showLiveFeedback($('#latitude'), 'warning', `Valid location in ${fenceCheck.fence.city_name} - Could not get address`);
                            showLiveFeedback($('#longitude'), 'warning', `Valid location in ${fenceCheck.fence.city_name} - Could not get address`);
                        } else {
                            const allowedCities = geoFences.length > 0 ? geoFences.map(fence => fence.city_name).join(', ') : 'No areas configured';
                            showLiveFeedback($('#latitude'), 'warning', `Location outside allowed areas: ${allowedCities} - Could not get address`);
                            showLiveFeedback($('#longitude'), 'warning', `Location outside allowed areas: ${allowedCities} - Could not get address`);
                        }
                    });
                }, 500); // Reduced delay for faster response
            
        } else {
            clearLiveFeedback($('#latitude'));
            clearLiveFeedback($('#longitude'));
        }
    });
    
    // Live validation for longitude
    $('#longitude').on('input', function() {
        const value = $(this).val().trim();
        if (value) {
            const lngNum = parseFloat(value);
            if (isNaN(lngNum)) {
                showLiveFeedback($(this), 'error', 'Must be a number');
            } else if (lngNum < -180 || lngNum > 180) {
                showLiveFeedback($(this), 'error', 'Must be between -180 and 180');
            } else if (!/^-?\d{1,3}(\.\d{1,6})?$/.test(value)) {
                showLiveFeedback($(this), 'error', 'Max 6 decimal places');
            } else {
                showLiveFeedback($(this), 'success', 'Looks good!');
            }
        } else {
            clearLiveFeedback($(this));
        }
    });

    // Add button to get address from coordinates
    $('#getAddressFromCoordsBtn').click(function() {
        const lat = $('#latitude').val().trim();
        const lng = $('#longitude').val().trim();
        
        if (!lat || !lng) {
            showToast('error', 'Please enter both latitude and longitude coordinates');
            return;
        }
        
        const latNum = parseFloat(lat);
        const lngNum = parseFloat(lng);
        
        if (isNaN(latNum) || isNaN(lngNum)) {
            showToast('error', 'Please enter valid numeric coordinates');
            return;
        }
        
        if (latNum < -90 || latNum > 90) {
            showToast('error', 'Latitude must be between -90 and 90');
            return;
        }
        
        if (lngNum < -180 || lngNum > 180) {
            showToast('error', 'Longitude must be between -180 and 180');
            return;
        }
        
        $(this).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Getting Address...').prop('disabled', true);
        
        getAddressFromCoordinates(latNum, lngNum)
            .then(address => {
                $('#address').val(address);
                showLiveFeedback($('#address'), 'success', 'Address updated from coordinates');
                
                // Display the retrieved address
                $('#retrievedAddressText').text(address);
                $('#retrievedAddressDisplay').show();
                
                showToast('success', 'Address obtained successfully!');
            })
            .catch(error => {
                console.error('Failed to get address:', error);
                showToast('error', 'Could not get address for these coordinates. Please enter address manually.');
            })
            .finally(() => {
                $('#getAddressFromCoordsBtn').html('<i class="bi bi-geo-alt"></i> Get Address from Coordinates').prop('disabled', false);
            });
    });

    // Close address display button
    $('#closeAddressDisplay').click(function() {
        $('#retrievedAddressDisplay').hide();
    });


    
    // Live validation for inspection date
    $('#last_inspected').on('change', function() {
        const value = $(this).val();
        if (value) {
            const inspectedDate = new Date(value);
            const currentDate = new Date();
            if (inspectedDate > currentDate) {
                showLiveFeedback($(this), 'error', 'Cannot be in the future');
            } else {
                showLiveFeedback($(this), 'success', 'Looks good!');
            }
        } else {
            clearLiveFeedback($(this));
        }
    });
    
    // Helper function to clear live feedback
    function clearLiveFeedback(element) {
        const parent = element.parent();
        parent.find('.live-feedback').remove();
        element.removeClass('is-valid is-invalid');
    }
    
    function showToast(type, message) {
    const iconMap = {
        success: 'bi-check-circle-fill text-success',
        danger: 'bi-exclamation-triangle-fill text-danger',
        warning: 'bi-exclamation-circle-fill text-warning',
        info: 'bi-info-circle-fill text-info',
        primary: 'bi-info-circle-fill text-primary',
        dark: 'bi-moon-fill text-dark',
        light: 'bi-sun-fill text-muted'
    };

    const icon = iconMap[type] || 'bi-info-circle-fill text-primary';

    const toast = `
        <div class="toast shadow-lg fade show position-fixed top-0 end-0 m-4" role="alert" aria-live="assertive" aria-atomic="true"
             style="min-width: 320px; background-color: #fff; color: #212529; border-radius: 12px; overflow: hidden;">
            <div class="d-flex align-items-center p-3 gap-3">
                <i class="bi ${icon} fs-4"></i>
                <div class="toast-body flex-grow-1 fw-medium">${message}</div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;

    $('.toast').remove(); // Remove any existing toasts
    $('body').append(toast);
    $('.toast').toast('show');

    setTimeout(() => {
        $('.toast').toast('hide');
    }, 5000);
}

    
    // Validation Functions
    function validateStep1() {
        let isValid = true;
        const buildingName = $('#building_name').val().trim();
        const buildingType = $('#building_type').val();
        const totalFloors = $('#total_floors').val();
        
        // Required fields validation
        if (!buildingName) {
            showLiveFeedback($('#building_name'), 'error', 'Building name is required');
            isValid = false;
        }
        
        if (!buildingType) {
            showLiveFeedback($('#building_type'), 'error', 'Building type is required');
            isValid = false;
        }
        
        if (!totalFloors || totalFloors < 1) {
            showLiveFeedback($('#total_floors'), 'error', 'Total floors is required (minimum 1)');
            isValid = false;
        }
        
        // Check for any invalid fields
        if ($('#stepContent1 .is-invalid').length > 0) {
            isValid = false;
        }
        
        if (!isValid) {
            showToast('error', 'Please fix all errors in Step 1 before proceeding');
        }
        
        return isValid;
    }
    
    function validateStep2() {
        let isValid = true;
        const lastInspected = $('#last_inspected').val();
        
        // Validate inspection date if provided
        if (lastInspected) {
            const inspectedDate = new Date(lastInspected);
            const currentDate = new Date();
            if (inspectedDate > currentDate) {
                showLiveFeedback($('#last_inspected'), 'error', 'Cannot be in the future');
                isValid = false;
            }
        }
        
        // Check for any invalid fields
        if ($('#stepContent2 .is-invalid').length > 0) {
            isValid = false;
        }
        
        // Check if at least one safety feature is selected
        const amenitiesSelected = $('#stepContent2 input[type="checkbox"]:checked').length > 0;
        if (!amenitiesSelected) {
            // Show warning but allow continuation
            showToast('warning', 'No safety features selected. You can add them later.');
        }
        
        if (!isValid) {
            showToast('error', 'Please fix all errors in Step 2 before proceeding');
        }
        
        return isValid;
    }
    
    function validateStep3() {
        let isValid = true;
        const latitude = $('#latitude').val().trim();
        const longitude = $('#longitude').val().trim();
        const geoFenceId = $('#geo_fence_id').val();
        const barangayId = $('#barangay_id').val();

        // Check if only one coordinate is provided
        if ((latitude && !longitude) || (!latitude && longitude)) {
            showLiveFeedback($('#latitude'), 'error', 'Provide both or neither');
            showLiveFeedback($('#longitude'), 'error', 'Provide both or neither');
            isValid = false;
        }

        if (latitude && longitude) {
            var latNum = parseFloat(latitude);
            var lngNum = parseFloat(longitude);
            const fenceCheck = isPointInAnyGeoFence(latNum, lngNum);
            
            if (!fenceCheck.inFence) {
                const allowedCities = geoFences.length > 0 ? geoFences.map(fence => fence.city_name).join(', ') : 'No areas configured';
                Swal.fire({
                    title: 'Invalid Location',
                    text: `The building location is outside the allowed areas. Please select a location within: ${allowedCities}`,
                    icon: 'error',
                    confirmButtonColor: '#4361ee'
                });
                isValid = false;
            }
        }

        // Validate geo-fence selection
        if (!geoFenceId) {
            showLiveFeedback($('#geo_fence_id'), 'error', 'Please select a location on the map to determine geo-fence area');
            isValid = false;
        }

        // Validate barangay selection
        if (!barangayId) {
            showLiveFeedback($('#barangay_id'), 'error', 'Please select a barangay');
            isValid = false;
        }

        // Check for any invalid fields
        if ($('#stepContent3 .is-invalid').length > 0) {
            isValid = false;
        }

        if (!isValid) {
            showToast('error', 'Please fix all errors in Step 3 before proceeding');
        }
        
        return isValid;
    }
    
    // Comprehensive validation for all required fields
    function validateAllRequiredFields(step) {
        let isValid = true;
        let errorMessages = [];
        
        if (step === 1) {
            // Step 1: Basic Information - All required fields
            const requiredFields = [
                { id: 'building_name', name: 'Building Name' },
                { id: 'building_type', name: 'Building Type' },
                { id: 'total_floors', name: 'Total Floors' }
            ];
            
            requiredFields.forEach(field => {
                const value = $(`#${field.id}`).val();
                if (!value || (field.id === 'total_floors' && parseInt(value) < 1)) {
                    errorMessages.push(`${field.name} is required`);
                    $(`#${field.id}`).addClass('is-invalid');
                    isValid = false;
                }
            });
            
        } else if (step === 2) {
            // Step 2: Safety Features - Optional but validate if provided
            const optionalFields = [
                { id: 'last_inspected', name: 'Last Inspection Date' },
                { id: 'contact_person', name: 'Contact Person' },
                { id: 'contact_number', name: 'Contact Number' },
                { id: 'construction_year', name: 'Construction Year' },
                { id: 'building_area', name: 'Building Area' }
            ];
            
            optionalFields.forEach(field => {
                const value = $(`#${field.id}`).val();
                if (value) {
                    // Validate format if value is provided
                    if (field.id === 'contact_number' && value) {
                        const digitsOnly = value.replace(/[^0-9]/g, '');
                        if (digitsOnly.length < 7 || digitsOnly.length > 15) {
                            errorMessages.push(`Invalid ${field.name} format`);
                            $(`#${field.id}`).addClass('is-invalid');
                            isValid = false;
                        }
                    }
                    if (field.id === 'construction_year' && value) {
                        const year = parseInt(value);
                        const currentYear = new Date().getFullYear();
                        if (year < 1800 || year > currentYear) {
                            errorMessages.push(`Invalid ${field.name} (1800-${currentYear})`);
                            $(`#${field.id}`).addClass('is-invalid');
                            isValid = false;
                        }
                    }
                }
            });
            
        } else if (step === 3) {
            // Step 3: Location - Required fields
            const latitude = $('#latitude').val().trim();
            const longitude = $('#longitude').val().trim();
            const geoFenceId = $('#geo_fence_id').val();
            const barangayId = $('#barangay_id').val();
            
            // Check if coordinates are provided
            if (!latitude || !longitude) {
                errorMessages.push('Latitude and Longitude are required');
                $('#latitude, #longitude').addClass('is-invalid');
                isValid = false;
            } else {
                // Validate coordinate format
                const latNum = parseFloat(latitude);
                const lngNum = parseFloat(longitude);
                if (isNaN(latNum) || latNum < -90 || latNum > 90) {
                    errorMessages.push('Invalid latitude (-90 to 90)');
                    $('#latitude').addClass('is-invalid');
                    isValid = false;
                }
                if (isNaN(lngNum) || lngNum < -180 || lngNum > 180) {
                    errorMessages.push('Invalid longitude (-180 to 180)');
                    $('#longitude').addClass('is-invalid');
                    isValid = false;
                }
            }
            
            // Check geo-fence and barangay
            if (!geoFenceId) {
                errorMessages.push('Please select a location on the map');
                $('#geo_fence_id').addClass('is-invalid');
                isValid = false;
            }
            
            if (!barangayId) {
                errorMessages.push('Please select a barangay');
                $('#barangay_id').addClass('is-invalid');
                isValid = false;
            }
        }
        
        // Show error message if validation fails
        if (!isValid) {
            const stepNames = ['', 'Basic Information', 'Safety Features', 'Location'];
            showToast('error', `Please fix the following errors in ${stepNames[step]}:<br>• ${errorMessages.join('<br>• ')}`);
        }
        
        return isValid;
    }
    
    // Scroll to first error field
    function scrollToFirstError(step) {
        const firstError = $(`#stepContent${step} .is-invalid`).first();
        if (firstError.length) {
            $('html, body').animate({
                scrollTop: firstError.offset().top - 100
            }, 300);
            firstError.focus();
        }
    }

    // Keep review panel in sync when fields change while Step 4 is visible
    $('#registrationForm').on('input change', 'input, select, textarea', function() {
        if (!$('#stepContent4').hasClass('d-none')) {
            updateReviewSection();
        }
    });
    
    // Initialize review section with any pre-filled values
    updateReviewSection();

    // Unified geolocation handler
    function handleGetLocation(btn) {
        if (navigator.geolocation) {
            btn.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Locating...').prop('disabled', true);
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    if (!map) {
                        initMap(lat, lng);
                    } else {
                        map.setView([lat, lng], 16);
                        updateCoordinates(lat, lng);
                    }
                    getAddressFromCoordinates(lat, lng)
                        .then(address => {
                            $('#address').val(address);
                            showLiveFeedback($('#address'), 'success', 'Address updated from coordinates');
                            $('#retrievedAddressText').text(address);
                            $('#retrievedAddressDisplay').show();
                            showToast('success', 'Location and address obtained successfully!');
                        })
                        .catch(error => {
                            console.error('Failed to get address:', error);
                            showToast('warning', 'Location obtained but could not get address. Please enter address manually.');
                        })
                        .finally(() => {
                            btn.html('<i class="bi bi-geo-alt"></i> Use My Current Location').prop('disabled', false);
                        });
                },
                function(error) {
                    let errorMessage = "Unable to retrieve your location: ";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += "You denied the request for geolocation.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += "Location information is unavailable.";
                            break;
                        case error.TIMEOUT:
                            errorMessage += "The request to get location timed out.";
                            break;
                        default:
                            errorMessage += "An unknown error occurred.";
                    }
                    btn.html('<i class="bi bi-geo-alt"></i> Use My Current Location').prop('disabled', false);
                    showToast('error', errorMessage);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            showToast('error', 'Geolocation is not supported by your browser');
        }
    }

    // Attach to both buttons
    $(document).on('click', '#getLocationBtn, #getLocationBtnTop', function() {
        handleGetLocation($(this));
    });

    // Initialize map with proper DOM ready check
    function initializeMapWithGeoFences() {
        console.log('Initializing map...');
        
        // Check if map container exists
        if ($('#map').length === 0) {
            console.error('Map container not found');
            return;
        }
        
        // Initialize map
        initMap();
        
        // Load geo-fences and zoom to allowed areas
        loadGeoFences().then(() => {
            console.log('Geo-fences loaded, adding to map...');
            // Add geo-fence polygons to map
            if (map && geoFencePolygons.length > 0) {
                    geoFencePolygons.forEach((polygon, index) => {
                            const fence = geoFences[index];
                            L.polygon(polygon, {
                                color: '#28a745',
                                fillColor: '#28a745',
                                fillOpacity: 0,
                                strokeOpacity: 0,
                                weight: 0,
                                interactive: false, // Allow clicks to pass through to map
                                dashArray: ''
                            }).addTo(map).bindPopup(`<strong>${fence.city_name}</strong><br>Allowed Area - Click inside to select location`);
                        });
                
                // Fit map to show all polygons with padding
                if (geoFencePolygons.length === 1) {
                    map.fitBounds(L.polygon(geoFencePolygons[0]).getBounds(), { padding: [20, 20] });
                } else {
                    // Create a group to fit bounds to all polygons
                    const group = new L.featureGroup();
                    geoFencePolygons.forEach(polygon => {
                        group.addLayer(L.polygon(polygon));
                    });
                    map.fitBounds(group.getBounds(), { padding: [20, 20] });
                }
                
                // Update instructions
                $('.map-instructions').html('<i class="bi bi-check-circle text-success"></i> Click inside the green areas to select location');
            } else {
                console.log('No geo-fences to display');
                $('.map-instructions').html('<i class="bi bi-info-circle text-info"></i> Click on the map to select location');
            }
        }).catch((error) => {
            console.log('Geo-fences failed to load, map will work without validation:', error);
            $('.map-instructions').html('<i class="bi bi-info-circle text-info"></i> Click on the map to select location');
        });
    }
    
    // Initialize map when DOM is ready and after a short delay
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(initializeMapWithGeoFences, 1000);
        });
    } else {
        setTimeout(initializeMapWithGeoFences, 1000);
    }
});

// Point in Polygon function (Ray-casting algorithm) - GLOBAL SCOPE
// point format: [lat, lng] where lat is Y, lng is X
// vs (polygon) format: [[lat, lng], [lat, lng], ...]
function isPointInPolygon(point, vs) {
    var lat = point[0], lng = point[1]; // lat is Y coordinate, lng is X coordinate
    var inside = false;
    for (var i = 0, j = vs.length - 1; i < vs.length; j = i++) {
        // vs[i] = [lat, lng], so vs[i][0] is lat (Y), vs[i][1] is lng (X)
        var yi = vs[i][0], xi = vs[i][1];
        var yj = vs[j][0], xj = vs[j][1];
        // Check if ray intersects with edge
        var intersect = ((xi > lng) !== (xj > lng)) &&
            (lat < (yj - yi) * (lng - xi) / (xj - xi + 0.0000001) + yi);
        if (intersect) inside = !inside;
    }
    return inside;
}
</script>
<!-- 
    jQuery -->
    <!-- <script src="../vendors/jquery/dist/jquery.min.js"></script> -->
    <!-- Bootstrap already included in header.php -->
    <!-- FastClick -->
    <script src="../../../vendors/fastclick/lib/fastclick.js"></script>
    <!-- NProgress -->
    <!-- <script src="../../../vendors/nprogress/nprogress.js"></script> -->
    <!-- Chart.js -->
    <script src="../../../vendors/Chart.js/dist/Chart.min.js"></script>
    <!-- gauge.js -->
    <script src="../../../vendors/gauge.js/dist/gauge.min.js"></script>
    <!-- bootstrap-progressbar -->
    <script src="../../../vendors/bootstrap-progressbar/bootstrap-progressbar.min.js"></script>
    <!-- iCheck -->
    <script src="../../../vendors/iCheck/icheck.min.js"></script>
    <!-- Skycons -->
    <script src="../../../vendors/skycons/skycons.js"></script>
    <!-- Flot -->
    <script src="../../../vendors/Flot/jquery.flot.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.pie.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.time.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.stack.js"></script>
    <script src="../../../vendors/Flot/jquery.flot.resize.js"></script>
    <!-- Flot plugins -->
    <script src="../../../vendors/flot.orderbars/js/jquery.flot.orderBars.js"></script>
    <script src="../../../vendors/flot-spline/js/jquery.flot.spline.min.js"></script>
    <script src="../../../vendors/flot.curvedlines/curvedLines.js"></script>
    <!-- DateJS -->
    <script src="../../../vendors/DateJS/build/date.js"></script>
    <!-- JQVMap -->
    <script src="../../../vendors/jqvmap/dist/jquery.vmap.js"></script>
    <script src="../../../vendors/jqvmap/dist/maps/jquery.vmap.world.js"></script>
    <script src="../../../vendors/jqvmap/examples/js/jquery.vmap.sampledata.js"></script>
    <!-- bootstrap-daterangepicker -->
    <script src="../../../vendors/moment/min/moment.min.js"></script>
    <script src="../../../vendors/bootstrap-daterangepicker/daterangepicker.js"></script>

    <!-- Custom Theme Scripts -->
    <script src="../../../build/js/custom.min.js"></script>

    <!-- Building Details Modal -->
    <div class="modal fade" id="buildingDetailsModal" tabindex="-1" aria-labelledby="buildingDetailsModalLabel">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="buildingDetailsModalLabel">Building Details</h5>
                    <button type="button" class="btn-close" id="closeModalBtn" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Content will be loaded dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="closeModalBtnFooter">Close</button>
                    <button type="button" class="btn btn-primary" id="editBuildingBtn">Edit Building</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle edit building button click
        $('#editBuildingBtn').click(function() {
            const buildingId = $('#buildingDetailsModal').data('building-id');
            $('#buildingDetailsModal').modal('hide');
            loadBuildingForEdit(buildingId);
        });

        // Handle close button clicks
        $('#closeModalBtn, #closeModalBtnFooter').click(function() {
            $('#buildingDetailsModal').modal('hide');
        });

        // Handle modal events to fix accessibility issues and state management
        $('#buildingDetailsModal').on('show.bs.modal', function () {
            // Remove aria-hidden when modal is shown
            $(this).removeAttr('aria-hidden');
        });

        $('#buildingDetailsModal').on('hidden.bs.modal', function () {
            // Add aria-hidden when modal is hidden
            $(this).attr('aria-hidden', 'true');
            
            // Reset modal state to ensure it can be shown again
            $(this).removeClass('show');
            $('body').removeClass('modal-open');
            $('.modal-backdrop').remove();
            
            // Reset modal content
            $(this).find('.modal-body').empty();
            $(this).removeData('building-id');
        });

        // Handle ESC key to close modal
        $(document).keydown(function(e) {
            if (e.keyCode === 27) { // ESC key
                $('#buildingDetailsModal').modal('hide');
            }
        });

        // Handle clicking outside modal to close
        $('#buildingDetailsModal').on('click', function(e) {
            if (e.target === this) {
                $(this).modal('hide');
            }
        });

        const editBuildingPrefillId = <?php echo isset($_GET['edit_building']) ? (int)$_GET['edit_building'] : 'null'; ?>;
        if (editBuildingPrefillId) {
            setTimeout(() => {
                loadBuildingForEdit(editBuildingPrefillId);
                if (window.history && window.history.replaceState && typeof URL !== 'undefined') {
                    const urlObj = new URL(window.location.href);
                    urlObj.searchParams.delete('edit_building');
                    window.history.replaceState({}, document.title, urlObj.pathname + urlObj.search + urlObj.hash);
                }
            }, 600);
        }
    </script>
	<?php include('../../../../components/scripts.php'); ?>
</body>
</html>
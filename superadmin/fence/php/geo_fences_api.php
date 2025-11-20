<?php
session_start();
header('Content-Type: application/json');

// Superadmin-only access
if (!isset($_SESSION['superadmin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db_connection.php';

// Database connection with error handling
if (!function_exists('getDatabaseConnection')) {
function getDatabaseConnection() {
    static $conn = null;
    if ($conn === null) {
        $host = "localhost";
        $dbname = "u520834156_DBBagofire"; 
        $username = "u520834156_userBagofire";
        $password = "i[#[GQ!+=C9";
        
        try {
            $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'System temporarily unavailable']));
        }
    }
    return $conn;
}
}

// Get the requested action
$action = $_REQUEST['action'] ?? '';

try {
    $conn = getDatabaseConnection();
    
    switch ($action) {
        case 'getFences':
            $stmt = $conn->prepare("SELECT id, city_name, country_code, AsText(polygon) as polygon, is_active, created_by, created_at FROM geo_fences ORDER BY created_at DESC");
            $stmt->execute();
            $fences = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convert POLYGON text to array of coordinates
            foreach ($fences as &$fence) {
                if ($fence['polygon']) {
                    // Parse POLYGON((lat lng, lat lng, ...)) format
                    preg_match('/POLYGON\(\(([^)]+)\)\)/', $fence['polygon'], $matches);
                    if (isset($matches[1])) {
                        $coords = explode(',', $matches[1]);
                        $polygon = [];
                        foreach ($coords as $coord) {
                            list($lng, $lat) = explode(' ', trim($coord));
                            $polygon[] = [(float)$lat, (float)$lng];
                        }
                        $fence['polygon'] = $polygon;
                    }
                }
            }
            
            echo json_encode(['success' => true, 'data' => $fences]);
            break;
            
        case 'saveFence':
            $cityName = isset($_POST['city_name']) ? trim($_POST['city_name']) : '';
            $countryCode = isset($_POST['country_code']) ? strtoupper(trim($_POST['country_code'])) : '';
            $isActiveRaw = $_POST['is_active'] ?? 1;
            $polygon = isset($_POST['polygon']) ? json_decode($_POST['polygon'], true) : null;

            // Basic validation
            if ($cityName === '' || $countryCode === '' || !is_array($polygon) || count($polygon) < 3) {
                echo json_encode(['success' => false, 'message' => 'Invalid input: require city, country, and at least 3 polygon points']);
                break;
            }

            // Normalize is_active to 0/1
            $isActive = (int) (in_array($isActiveRaw, [1, '1', true, 'true', 'on'], true) ? 1 : 0);
            
            // Ensure polygon ring is closed (first point equals last point)
            $firstPoint = $polygon[0];
            $lastPoint = $polygon[count($polygon) - 1];
            if ($firstPoint[0] !== $lastPoint[0] || $firstPoint[1] !== $lastPoint[1]) {
                $polygon[] = $firstPoint;
            }
            
            // Convert coordinates to POLYGON text format (MySQL expects lng lat order)
            $coords = [];
            foreach ($polygon as $point) {
                // Each $point should be [lat, lng]
                if (!is_array($point) || count($point) !== 2) {
                    echo json_encode(['success' => false, 'message' => 'Invalid polygon point format']);
                    break 2;
                }
                $lat = (float)$point[0];
                $lng = (float)$point[1];
                $coords[] = "$lng $lat";
            }
            $polygonText = 'POLYGON((' . implode(',', $coords) . '))';
            
            // Use logged-in superadmin as creator
            $createdBy = (int)($_SESSION['superadmin_id'] ?? 0);
            if ($createdBy <= 0) {
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                break;
            }
            
            try {
                $stmt = $conn->prepare("INSERT INTO geo_fences (city_name, country_code, polygon, is_active, created_by) VALUES (?, ?, ST_GeomFromText(?), ?, ?)");
                $success = $stmt->execute([$cityName, $countryCode, $polygonText, $isActive, $createdBy]);
            } catch (PDOException $e) {
                error_log('Geo fence insert failed: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to save fence: ' . $e->getMessage()]);
                break;
            }
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Fence saved successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to save fence']);
            }
            break;
        
        case 'getFence':
            $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
            if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }
            $stmt = $conn->prepare("SELECT id, city_name, country_code, AsText(polygon) as polygon, is_active, created_by, created_at FROM geo_fences WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $fence = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$fence) { echo json_encode(['success' => false, 'message' => 'Fence not found']); break; }
            if ($fence['polygon']) {
                preg_match('/POLYGON\(\(([^)]+)\)\)/', $fence['polygon'], $matches);
                if (isset($matches[1])) {
                    $coords = explode(',', $matches[1]);
                    $polygon = [];
                    foreach ($coords as $coord) {
                        list($lng, $lat) = explode(' ', trim($coord));
                        $polygon[] = [(float)$lat, (float)$lng];
                    }
                    $fence['polygon'] = $polygon;
                }
            }
            echo json_encode(['success' => true, 'data' => $fence]);
            break;
        
        case 'updateFence':
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $cityName = isset($_POST['city_name']) ? trim($_POST['city_name']) : '';
            $countryCode = isset($_POST['country_code']) ? strtoupper(trim($_POST['country_code'])) : '';
            $isActiveRaw = $_POST['is_active'] ?? 1;
            $polygon = isset($_POST['polygon']) ? json_decode($_POST['polygon'], true) : null;

            if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }
            if ($cityName === '' || $countryCode === '' || !is_array($polygon) || count($polygon) < 3) {
                echo json_encode(['success' => false, 'message' => 'Invalid input: require city, country, and at least 3 polygon points']);
                break;
            }

            $isActive = (int) (in_array($isActiveRaw, [1, '1', true, 'true', 'on'], true) ? 1 : 0);
            $firstPoint = $polygon[0];
            $lastPoint = $polygon[count($polygon) - 1];
            if ($firstPoint[0] !== $lastPoint[0] || $firstPoint[1] !== $lastPoint[1]) {
                $polygon[] = $firstPoint;
            }
            $coords = [];
            foreach ($polygon as $point) {
                if (!is_array($point) || count($point) !== 2) {
                    echo json_encode(['success' => false, 'message' => 'Invalid polygon point format']);
                    break 2;
                }
                $lat = (float)$point[0];
                $lng = (float)$point[1];
                $coords[] = "$lng $lat";
            }
            $polygonText = 'POLYGON((' . implode(',', $coords) . '))';

            try {
                $stmt = $conn->prepare("UPDATE geo_fences SET city_name = ?, country_code = ?, polygon = ST_GeomFromText(?), is_active = ? WHERE id = ?");
                $success = $stmt->execute([$cityName, $countryCode, $polygonText, $isActive, $id]);
            } catch (PDOException $e) {
                error_log('Geo fence update failed: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to update fence: ' . $e->getMessage()]);
                break;
            }

            echo json_encode(['success' => $success, 'message' => $success ? 'Fence updated successfully' : 'Failed to update fence']);
            break;
        
        case 'setActive':
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $isActiveRaw = $_POST['is_active'] ?? 1;
            if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); break; }
            $isActive = (int) (in_array($isActiveRaw, [1, '1', true, 'true', 'on'], true) ? 1 : 0);
            try {
                $stmt = $conn->prepare("UPDATE geo_fences SET is_active = ? WHERE id = ?");
                $success = $stmt->execute([$isActive, $id]);
            } catch (PDOException $e) {
                error_log('Geo fence setActive failed: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
                break;
            }
            echo json_encode(['success' => $success, 'message' => $success ? 'Status updated' : 'Failed to update status']);
            break;
            
        case 'deleteFence':
            $id = $_POST['id'];
            
            $stmt = $conn->prepare("DELETE FROM geo_fences WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Fence deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete fence']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
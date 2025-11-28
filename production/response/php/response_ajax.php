<?php
require_once '../../db/db.php';
require_once '../../components/security.php';
require_once '../../components/cache.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store, must-revalidate');

// Simple role gate
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['officer_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get database connection
$conn = getDatabaseConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$action = preg_replace('/[^a-z_]/i', '', (string)$action);

function requireCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($token)) {
        http_response_code(419);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

function sanitizeId($value): int
{
    $filtered = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($filtered === false) {
        throw new InvalidArgumentException('Invalid identifier supplied.');
    }
    return (int)$filtered;
}

try {
    switch ($action) {
        case 'get_response_details':
            $responseId = sanitizeId($_GET['response_id'] ?? 0);
            
            $query = "
                SELECT 
                    r.*,
                    f.name as firefighter_name,
                    f.badge_number,
                    f.rank,
                    f.specialization,
                    f.phone as firefighter_phone,
                    bd.building_name,
                    bd.building_type,
                    bd.address,
                    bd.contact_person,
                    bd.contact_number,
                    bd.total_floors,
                    bd.has_sprinkler_system,
                    bd.has_fire_alarm,
                    bd.has_fire_extinguishers,
                    bd.has_emergency_exits,
                    bd.has_emergency_lighting,
                    bd.has_fire_escape,
                    bd.last_inspected,
                    bd.latitude as building_lat,
                    bd.longitude as building_long,
                    bd.construction_year,
                    bd.building_area,
                    br.barangay_name,
                    fd.status as fire_status,
                    fd.smoke,
                    fd.temp,
                    fd.heat,
                    fd.flame_detected,
                    fd.timestamp as fire_timestamp,
                    fd.geo_lat,
                    fd.geo_long,
                    fd.ml_confidence,
                    fd.ml_prediction,
                    fd.ml_fire_probability,
                    fd.ai_prediction,
                    fd.ml_timestamp,
                    fd.acknowledged_at_time
                FROM responses r
                LEFT JOIN firefighters f ON r.firefighter_id = f.id
                LEFT JOIN buildings bd ON r.building_id = bd.id
                LEFT JOIN barangay br ON bd.barangay_id = br.id
                LEFT JOIN fire_data fd ON r.fire_data_id = fd.id
                WHERE r.id = ?
            ";
            
            $stmt = $conn->prepare($query);
            $stmt->execute([$responseId]);
            $response = $stmt->fetch();
            
            if ($response) {
                echo json_encode(['success' => true, 'data' => $response]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Response not found']);
            }
            break;
            
        case 'update_response':
            requireCsrfToken();
            $rate = rateLimitCheck('response_update_' . ($_SESSION['admin_id'] ?? $_SESSION['officer_id']), 20, 300);
            if (!$rate['allowed']) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again later.']);
                break;
            }

            $responseId = sanitizeId($_POST['response_id'] ?? 0);
            $responseType = sanitizeInput($_POST['response_type'] ?? '', true);
            $notes = sanitizeInput($_POST['notes'] ?? '', true);
            $firefighterId = isset($_POST['firefighter_id']) && $_POST['firefighter_id'] !== ''
                ? sanitizeId($_POST['firefighter_id'])
                : null;
            
            $query = "UPDATE responses SET response_type = ?, notes = ?, firefighter_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([$responseType, $notes, $firefighterId, $responseId]);
            
            if ($result) {
                CacheHelper::forget(CacheHelper::buildKey('response:stats', []));
                echo json_encode(['success' => true, 'message' => 'Response updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update response']);
            }
            break;
            
        case 'delete_response':
            requireCsrfToken();
            $rate = rateLimitCheck('response_delete_' . ($_SESSION['admin_id'] ?? $_SESSION['officer_id']), 10, 600);
            if (!$rate['allowed']) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'Rate limit exceeded. Try again later.']);
                break;
            }

            $responseId = sanitizeId($_POST['response_id'] ?? 0);
            
            $query = "DELETE FROM responses WHERE id = ?";
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([$responseId]);
            
            if ($result) {
                CacheHelper::forget(CacheHelper::buildKey('response:stats', []));
                echo json_encode(['success' => true, 'message' => 'Response deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete response']);
            }
            break;
            
        case 'get_firefighters':
            $firefighters = CacheHelper::remember(
                CacheHelper::buildKey('response:firefighters', []),
                300,
                function () use ($conn) {
                    $query = "SELECT id, name, badge_number, rank FROM firefighters WHERE status = 'Active' ORDER BY name";
                    $stmt = $conn->prepare($query);
                    $stmt->execute();
                    return $stmt->fetchAll();
                }
            );
            
            echo json_encode(['success' => true, 'data' => $firefighters]);
            break;
            
        case 'get_response_stats':
            $stats = CacheHelper::remember(
                CacheHelper::buildKey('response:stats', []),
                60,
                function () use ($conn) {
                    $query = "
                        SELECT 
                            COUNT(*) as total_responses,
                            COUNT(CASE WHEN response_type = 'Emergency' THEN 1 END) as emergency_responses,
                            COUNT(CASE WHEN response_type = 'Routine' THEN 1 END) as routine_responses,
                            COUNT(CASE WHEN response_type = 'False Alarm' THEN 1 END) as false_alarms,
                            COUNT(CASE WHEN response_type = 'Training' THEN 1 END) as training_responses,
                            COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as today_responses,
                            COUNT(CASE WHEN DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_responses,
                            COUNT(CASE WHEN DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as month_responses
                        FROM responses
                    ";
                    $stmt = $conn->prepare($query);
                    $stmt->execute();
                    return $stmt->fetch();
                }
            );
            
            echo json_encode(['success' => true, 'data' => $stats, 'cache_ttl' => 60]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

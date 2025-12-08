<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'message' => 'Please log in to access this data',
        'draw' => isset($_GET['draw']) ? (int)$_GET['draw'] : 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

require_once __DIR__ . '/../../../db/db.php';

try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
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
$orderColumnIndex = isset($_GET['order'][0]['column']) ? (int)$_GET['order'][0]['column'] : 0; // default ID
$orderDir = isset($_GET['order'][0]['dir']) && in_array(strtolower($_GET['order'][0]['dir']), ['asc','desc']) ? $_GET['order'][0]['dir'] : 'desc';

// Map columns to SQL fields (matching table structure)
$columns = [
    'fd.id',
    'fd.status',
    'fd.building_type',
    'fd.smoke',
    'fd.temp',
    'fd.heat',
    'fd.flame_detected',
    'fd.timestamp',
    'u.username',
    'br.barangay_name',
    'd.device_name'
];

$orderBy = $columns[$orderColumnIndex] ?? 'fd.id';

// Filters from DataTables and custom filters
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$buildingType = isset($_GET['building_type']) ? trim($_GET['building_type']) : '';
$barangay = isset($_GET['barangay']) ? trim($_GET['barangay']) : '';
$user = isset($_GET['user']) ? trim($_GET['user']) : '';
$device = isset($_GET['device']) ? trim($_GET['device']) : '';
$dateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Global search from DataTables
$searchValue = isset($_GET['search']['value']) ? trim($_GET['search']['value']) : '';

$where = [];
$params = [];

// Apply custom filters
if ($status !== '') {
    $where[] = 'fd.status = :status';
    $params['status'] = $status;
}
if ($buildingType !== '') {
    $where[] = 'fd.building_type = :building_type';
    $params['building_type'] = $buildingType;
}
if ($barangay !== '') {
    $where[] = 'br.barangay_name = :barangay';
    $params['barangay'] = $barangay;
}
if ($user !== '') {
    $where[] = 'u.username = :username';
    $params['username'] = $user;
}
if ($device !== '') {
    $where[] = 'COALESCE(d.device_name, CONCAT("Device #", fd.device_id)) = :device';
    $params['device'] = $device;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(fd.timestamp) >= :date_from';
    $params['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(fd.timestamp) <= :date_to';
    $params['date_to'] = $dateTo;
}

// Global search across multiple columns
if ($searchValue !== '') {
    $searchConditions = [];
    $searchConditions[] = 'fd.id LIKE :search';
    $searchConditions[] = 'fd.status LIKE :search';
    $searchConditions[] = 'fd.building_type LIKE :search';
    $searchConditions[] = 'fd.smoke LIKE :search';
    $searchConditions[] = 'fd.temp LIKE :search';
    $searchConditions[] = 'fd.heat LIKE :search';
    $searchConditions[] = 'fd.timestamp LIKE :search';
    $searchConditions[] = 'u.username LIKE :search';
    $searchConditions[] = 'br.barangay_name LIKE :search';
    $searchConditions[] = 'COALESCE(d.device_name, CONCAT("Device #", fd.device_id)) LIKE :search';
    $where[] = '(' . implode(' OR ', $searchConditions) . ')';
    $params['search'] = "%$searchValue%";
}

$whereSql = '';
if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

try {
    // Base query for counting
    $baseQuery = "
        FROM fire_data fd
        LEFT JOIN users u ON u.user_id = fd.user_id
        LEFT JOIN barangay br ON br.id = fd.barangay_id
        LEFT JOIN devices d ON d.device_id = fd.device_id
        $whereSql
    ";
    
    // Total records (without filters)
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM fire_data fd");
    $stmtTotal->execute();
    $recordsTotal = (int)$stmtTotal->fetchColumn();
    
    // Filtered records (with filters)
    $sqlCount = "SELECT COUNT(*) " . $baseQuery;
    $stmt = $pdo->prepare($sqlCount);
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
    $recordsFiltered = (int)$stmt->fetchColumn();
    
    // Data query with pagination
    $sql = "
        SELECT 
            fd.id, fd.status, fd.building_type, fd.smoke, fd.temp, fd.heat,
            fd.flame_detected, fd.timestamp, fd.user_id, 
            COALESCE(u.username, CONCAT('User #', fd.user_id)) as username,
            COALESCE(br.barangay_name, '') as barangay_name,
            fd.device_id,
            COALESCE(d.device_name, CONCAT('Device #', fd.device_id)) as device_name
        " . $baseQuery . "
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
    
    // Format data for DataTables (matching the table structure)
    $data = [];
    foreach ($rows as $row) {
        // Format status badge
        $status = htmlspecialchars($row['status'] ?? '');
        $statusClass = 'bg-secondary';
        $statusUpper = strtoupper(trim($status));
        if (in_array($statusUpper, ['EMERGENCY', 'FIRE', 'ALERT', 'CRITICAL', 'ACTIVE'])) {
            $statusClass = 'bg-danger';
        } elseif ($statusUpper === 'ACKNOWLEDGED') {
            $statusClass = 'bg-primary';
        } elseif (strpos($statusUpper, 'WARN') !== false) {
            $statusClass = 'bg-warning text-dark';
        } elseif (in_array($statusUpper, ['SAFE', 'NORMAL', 'OK', 'INACTIVE'])) {
            $statusClass = 'bg-success';
        }
        $statusHtml = '<span class="badge ' . $statusClass . '">' . $status . '</span>';
        
        // Format smoke badge
        $smoke = $row['smoke'] ?? '';
        $smokeClass = 'bg-secondary';
        if ($smoke !== null && $smoke !== '' && is_numeric($smoke)) {
            $smokeVal = (float)$smoke;
            if ($smokeVal >= 400) $smokeClass = 'bg-danger';
            elseif ($smokeVal >= 200) $smokeClass = 'bg-warning text-dark';
            else $smokeClass = 'bg-success';
        }
        $smokeHtml = '<span class="badge ' . $smokeClass . '">' . htmlspecialchars($smoke) . '</span>';
        
        // Format temp badge
        $temp = $row['temp'] ?? '';
        $tempClass = 'bg-secondary';
        if ($temp !== null && $temp !== '' && is_numeric($temp)) {
            $tempVal = (float)$temp;
            if ($tempVal >= 80) $tempClass = 'bg-danger';
            elseif ($tempVal >= 50) $tempClass = 'bg-warning text-dark';
            else $tempClass = 'bg-success';
        }
        $tempHtml = '<span class="badge ' . $tempClass . '">' . htmlspecialchars($temp) . '</span>';
        
        // Format heat badge
        $heat = $row['heat'] ?? '';
        $heatClass = 'bg-secondary';
        if ($heat !== null && $heat !== '' && is_numeric($heat)) {
            $heatVal = (float)$heat;
            if ($heatVal >= 85) $heatClass = 'bg-danger';
            elseif ($heatVal >= 60) $heatClass = 'bg-warning text-dark';
            else $heatClass = 'bg-success';
        }
        $heatHtml = '<span class="badge ' . $heatClass . '">' . htmlspecialchars($heat) . '</span>';
        
        // Format flame badge
        $flame = (int)($row['flame_detected'] ?? 0);
        $flameClass = $flame === 1 ? 'bg-danger' : 'bg-secondary';
        $flameHtml = '<span class="badge ' . $flameClass . '">' . ($flame === 1 ? 'Yes' : 'No') . '</span>';
        
        // Format device badge
        $deviceName = $row['device_name'] ?? '';
        $deviceClass = ($deviceName === '' || stripos($deviceName, 'N/A') !== false) ? 'bg-secondary' : 'bg-primary';
        $deviceHtml = '<span class="badge ' . $deviceClass . '">' . htmlspecialchars($deviceName) . '</span>';
        
        $data[] = [
            $row['id'],
            $statusHtml,
            htmlspecialchars($row['building_type'] ?? ''),
            $smokeHtml,
            $tempHtml,
            $heatHtml,
            $flameHtml,
            htmlspecialchars($row['timestamp'] ?? ''),
            htmlspecialchars($row['username'] ?? ''),
            htmlspecialchars($row['barangay_name'] ?? ''),
            $deviceHtml
        ];
    }
    
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data
    ]);
    
} catch (Exception $e) {
    error_log("DataTables query error: " . $e->getMessage());
    error_log("SQL: " . ($sql ?? 'N/A'));
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


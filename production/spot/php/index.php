<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../db/db.php';
require_once 'classes/SecureQueryBuilder.php';
require_once 'classes/InputValidator.php';
require_once 'classes/ErrorHandler.php';
require_once 'classes/SecurityHeaders.php';

// Initialize error handler
$isProduction = (getenv('APP_ENV') === 'production');
ErrorHandler::init($isProduction);

// Set security headers
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
SecurityHeaders::setAll($isHttps);

// Check if user is logged in BEFORE including header.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../../index.php');
    exit();
}

$conn = getDatabaseConnection();

// Get filter parameters and validate
$startDate = isset($_GET['start_date']) && $_GET['start_date'] !== '' ? InputValidator::validateDate($_GET['start_date']) : '';
$endDate = isset($_GET['end_date']) && $_GET['end_date'] !== '' ? InputValidator::validateDate($_GET['end_date']) : '';
$reportStatus = isset($_GET['report_status']) && $_GET['report_status'] !== '' ? InputValidator::validateWhitelist($_GET['report_status'], ['draft', 'pending_review', 'no_report']) : '';
$barangayId = isset($_GET['barangay_id']) && $_GET['barangay_id'] !== '' ? InputValidator::validateInt($_GET['barangay_id'], 1) : 0;
$buildingType = isset($_GET['building_type']) && $_GET['building_type'] !== '' ? InputValidator::validateString($_GET['building_type'], 100) : '';

// Handle validation failures - set to default if validation failed
$startDate = ($startDate === false) ? '' : $startDate;
$endDate = ($endDate === false) ? '' : $endDate;
$reportStatus = ($reportStatus === false) ? '' : $reportStatus;
$barangayId = ($barangayId === false) ? 0 : $barangayId;
$buildingType = ($buildingType === false) ? '' : $buildingType;

// Build query to display ALL records with status = 'ACKNOWLEDGED' from fire_data table
$whereConditions = [];
$params = [];
$paramTypes = [];

// Always filter for ACKNOWLEDGED status - ensures only ACKNOWLEDGED records are displayed
// Using case-insensitive comparison to handle any status variations
$whereConditions[] = "UPPER(TRIM(fd.status)) = UPPER(TRIM(?))";
$params[] = 'ACKNOWLEDGED';
$paramTypes[] = PDO::PARAM_STR;

// Add date filters
if ($startDate) {
    $whereConditions[] = "DATE(fd.timestamp) >= ?";
    $params[] = $startDate;
    $paramTypes[] = PDO::PARAM_STR;
}

if ($endDate) {
    $whereConditions[] = "DATE(fd.timestamp) <= ?";
    $params[] = $endDate;
    $paramTypes[] = PDO::PARAM_STR;
}

// Add report status filter
if ($reportStatus === 'no_report') {
    // Show only no_report (NULL or empty status)
    $whereConditions[] = "(sir.reports_status IS NULL OR TRIM(sir.reports_status) = '' OR UPPER(TRIM(sir.reports_status)) = UPPER('no_report'))";
} elseif ($reportStatus === 'draft') {
    // Show only draft reports
    $whereConditions[] = "UPPER(TRIM(sir.reports_status)) = UPPER('draft')";
} elseif ($reportStatus === 'pending_review') {
    // Show only pending/pending_review reports
    $whereConditions[] = "(UPPER(TRIM(sir.reports_status)) = UPPER('pending') OR UPPER(TRIM(sir.reports_status)) = UPPER('pending_review'))";
} elseif ($reportStatus) {
    $whereConditions[] = "sir.reports_status = ?";
    $params[] = $reportStatus;
    $paramTypes[] = PDO::PARAM_STR;
}

// Exclude Final status reports - hide records with Final status (always applied)
$whereConditions[] = "(sir.reports_status IS NULL OR UPPER(TRIM(sir.reports_status)) != UPPER('FINAL'))";

// Add barangay filter - check both building and fire_data barangay_id
if ($barangayId) {
    $whereConditions[] = "(b.barangay_id = ? OR fd.barangay_id = ?)";
    $params[] = $barangayId;
    $paramTypes[] = PDO::PARAM_INT;
    $params[] = $barangayId;
    $paramTypes[] = PDO::PARAM_INT;
}

// Add building type filter - check both building and fire_data building_type
if ($buildingType) {
    $whereConditions[] = "(b.building_type = ? OR fd.building_type = ?)";
    $params[] = $buildingType;
    $paramTypes[] = PDO::PARAM_STR;
    $params[] = $buildingType;
    $paramTypes[] = PDO::PARAM_STR;
}

// Build the complete query - ensure ALL ACKNOWLEDGED records are included
$baseQuery = "
    SELECT fd.*, 
           b.building_name,
           COALESCE(b.building_type, fd.building_type) as building_type,
           b.address as building_address,
           b.contact_person,
           b.contact_number as building_contact,
           b.total_floors,
           b.has_sprinkler_system,
           b.has_fire_alarm,
           b.has_fire_extinguishers,
           b.has_emergency_exits,
           b.has_emergency_lighting,
           b.has_fire_escape,
           b.building_area,
           u.username as user_name,
           u.fullname as user_fullname,
           u.email_address,
           u.contact_number as user_contact,
           u.device_number,
           u.status as user_status,
           d.device_name,
           COALESCE(br_b.barangay_name, br_fd.barangay_name) AS barangay_name,
           COALESCE(fd.barangay_id, b.barangay_id) AS barangay_id,
           sir.reports_status,
           sir.id as spot_report_id
    FROM fire_data fd
    LEFT JOIN buildings b ON fd.building_id = b.id
    LEFT JOIN users u ON fd.user_id = u.user_id
    LEFT JOIN devices d ON fd.device_id = d.device_id
    LEFT JOIN barangay br_b ON b.barangay_id = br_b.id
    LEFT JOIN barangay br_fd ON fd.barangay_id = br_fd.id
    LEFT JOIN spot_investigation_reports sir ON fd.id = sir.fire_data_id
";

// Build WHERE clause
$whereClause = !empty($whereConditions) ? " WHERE " . implode(' AND ', $whereConditions) : "";
// Order by: NULL reports_status first (no report), then by timestamp descending
$query = $baseQuery . $whereClause . " ORDER BY CASE WHEN sir.reports_status IS NULL THEN 0 ELSE 1 END ASC, fd.timestamp DESC";

try {
    // First, check if there are any ACKNOWLEDGED records at all (case-insensitive)
    $checkStmt = $conn->prepare("SELECT COUNT(*) as total FROM fire_data WHERE UPPER(TRIM(status)) = UPPER('ACKNOWLEDGED')");
    $checkStmt->execute();
    $totalAcknowledged = $checkStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Log for debugging
    error_log("Total ACKNOWLEDGED records in database: " . $totalAcknowledged);
    
    // If no ACKNOWLEDGED records found, check what statuses exist (for debugging)
    if ($totalAcknowledged == 0) {
        $statusCheckStmt = $conn->prepare("SELECT DISTINCT status, COUNT(*) as count FROM fire_data GROUP BY status ORDER BY count DESC LIMIT 10");
        $statusCheckStmt->execute();
        $statuses = $statusCheckStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Available statuses in fire_data: " . json_encode($statuses));
    }
    
    // Execute the main query with parameters
    $stmt = $conn->prepare($query);
    
    // Bind all parameters
    foreach ($params as $index => $value) {
        $stmt->bindValue($index + 1, $value, $paramTypes[$index]);
    }
    
    $stmt->execute();
    $fireData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Log query results for debugging
    error_log("Query returned " . count($fireData) . " records");
    
    // If no results but records exist, try a simpler query without filters as fallback
    if (count($fireData) === 0 && $totalAcknowledged > 0) {
        error_log("Warning: Found {$totalAcknowledged} ACKNOWLEDGED records but filtered query returned 0.");
        
        // Fallback: Get all ACKNOWLEDGED records without additional filters
        $fallbackQuery = "
            SELECT fd.*, 
                   b.building_name,
                   COALESCE(b.building_type, fd.building_type) as building_type,
                   b.address as building_address,
                   b.contact_person,
                   b.contact_number as building_contact,
                   b.total_floors,
                   b.has_sprinkler_system,
                   b.has_fire_alarm,
                   b.has_fire_extinguishers,
                   b.has_emergency_exits,
                   b.has_emergency_lighting,
                   b.has_fire_escape,
                   b.building_area,
                   u.username as user_name,
                   u.fullname as user_fullname,
                   u.email_address,
                   u.contact_number as user_contact,
                   u.device_number,
                   u.status as user_status,
           d.device_name,
                   COALESCE(br_b.barangay_name, br_fd.barangay_name) AS barangay_name,
           COALESCE(fd.barangay_id, b.barangay_id) AS barangay_id,
                   sir.reports_status,
           sir.id as spot_report_id
            FROM fire_data fd
            LEFT JOIN buildings b ON fd.building_id = b.id
            LEFT JOIN users u ON fd.user_id = u.user_id
    LEFT JOIN devices d ON fd.device_id = d.device_id
            LEFT JOIN barangay br_b ON b.barangay_id = br_b.id
            LEFT JOIN barangay br_fd ON fd.barangay_id = br_fd.id
            LEFT JOIN spot_investigation_reports sir ON fd.id = sir.fire_data_id
            WHERE UPPER(TRIM(fd.status)) = UPPER('ACKNOWLEDGED')
            AND (sir.reports_status IS NULL OR UPPER(TRIM(sir.reports_status)) = UPPER('draft') OR UPPER(TRIM(sir.reports_status)) = UPPER('pending') OR UPPER(TRIM(sir.reports_status)) = UPPER('pending_review'))
            ORDER BY CASE WHEN sir.reports_status IS NULL THEN 0 ELSE 1 END ASC, fd.timestamp DESC
        ";
        
        $fallbackStmt = $conn->prepare($fallbackQuery);
        $fallbackStmt->execute();
        $fireData = $fallbackStmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Fallback query (no filters) returned " . count($fireData) . " records");
    }
} catch (PDOException $e) {
    error_log("Error fetching fire data: " . $e->getMessage());
    error_log("Query: " . $query);
    error_log("Params: " . json_encode($params));
    $fireData = [];
}

// Get filter options
$barangayStmt = $conn->prepare("SELECT id, barangay_name FROM barangay ORDER BY barangay_name");
$barangayStmt->execute();
$barangays = $barangayStmt->fetchAll();

$buildingTypeStmt = $conn->prepare("SELECT DISTINCT building_type FROM buildings WHERE building_type IS NOT NULL ORDER BY building_type");
$buildingTypeStmt->execute();
$buildingTypes = $buildingTypeStmt->fetchAll();

// Function to shorten address
function shortenAddress($address) {
    if (empty($address)) return '';
    
    // Split by comma and take only the first 2 parts (usually city/town and province)
    $parts = explode(',', $address);
    $shortParts = array_slice($parts, 0, 2);
    
    // Clean up whitespace
    $shortParts = array_map('trim', $shortParts);
    
    return implode(', ', $shortParts);
}

/**
 * Group incidents by report status
 * @param array $incidents Array of incident data
 * @return array Grouped incidents by status
 */
function groupIncidentsByStatus($incidents) {
    $grouped = [
        'no_report' => [
            'title' => 'No Reports',
            'icon' => 'fa-file-plus',
            'color' => '#28a745',
            'bg_color' => '#d4edda',
            'border_color' => '#c3e6cb',
            'items' => []
        ],
        'draft' => [
            'title' => 'Draft Reports',
            'icon' => 'fa-file-edit',
            'color' => '#856404',
            'bg_color' => '#fff3cd',
            'border_color' => '#ffeaa7',
            'items' => []
        ],
        'pending' => [
            'title' => 'Pending Review',
            'icon' => 'fa-clock',
            'color' => '#856404',
            'bg_color' => '#fff3cd',
            'border_color' => '#ffeaa7',
            'items' => []
        ]
    ];
    
    foreach ($incidents as $incident) {
        $status = strtolower(empty($incident['reports_status']) ? 'no_report' : $incident['reports_status']);
        
        if ($status === 'pending' || $status === 'pending_review') {
            $grouped['pending']['items'][] = $incident;
        } elseif ($status === 'draft') {
            $grouped['draft']['items'][] = $incident;
        } else {
            $grouped['no_report']['items'][] = $incident;
        }
    }
    
    return $grouped;
}

/**
 * Render a status group header row
 * @param array $group Group configuration
 * @param int $colspan Number of columns to span
 * @return string HTML for group header
 */
function renderStatusGroupHeader($group, $colspan) {
    $count = count($group['items']);
    if ($count === 0) return '';
    
    ob_start();
    ?>
    <tr class="status-group-header" data-group="<?php echo htmlspecialchars($group['title']); ?>">
        <td colspan="<?php echo $colspan; ?>" class="status-group-header-cell" style="
            background: linear-gradient(135deg, <?php echo $group['bg_color']; ?> 0%, <?php echo $group['bg_color']; ?> 100%);
            border-left: 4px solid <?php echo $group['color']; ?>;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 0.95rem;
            color: <?php echo $group['color']; ?>;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        ">
            <i class="fas <?php echo $group['icon']; ?>" style="margin-right: 10px; font-size: 1.1rem;"></i>
            <?php echo htmlspecialchars($group['title']); ?>
            <span class="badge" style="
                background-color: <?php echo $group['color']; ?>;
                color: #ffffff;
                margin-left: 12px;
                padding: 4px 10px;
                border-radius: 12px;
                font-size: 0.85rem;
                font-weight: 600;
            "><?php echo $count; ?> <?php echo $count === 1 ? 'incident' : 'incidents'; ?></span>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}

/**
 * Render a single incident row
 * @param array $incident Incident data
 * @param int $rowIndex Row index for alternating styles
 * @return string HTML for incident row
 */
function renderIncidentRow($incident, &$rowIndex) {
    $rowClass = ($rowIndex % 2 == 0) ? 'even pointer' : 'odd pointer';
    $rowIndex++;
    
    $reportStatus = $incident['reports_status'] ?? 'No Report';
    $statusClass = '';
    $statusColor = '';
    switch($reportStatus) {
        case 'Final':
        case 'final':
            $statusClass = 'badge-danger';
            $statusColor = '#dc3545';
            break;
        case 'draft':
            $statusClass = 'badge-warning';
            $statusColor = '#ffc107';
            break;
        case 'pending_review':
        case 'Pending':
        case 'pending':
            $statusClass = 'badge-warning';
            $statusColor = '#ffc107';
            break;
        case 'completed':
            $statusClass = 'badge-success';
            $statusColor = '#28a745';
            break;
        case 'approved':
            $statusClass = 'badge-primary';
            $statusColor = '#007bff';
            break;
        case 'No Report':
        case 'no_report':
            $statusClass = 'badge-success';
            $statusColor = '#28a745';
            break;
        default:
            $statusClass = 'badge-secondary';
            $statusColor = '#6c757d';
    }
    
    $currentReportStatus = $incident['reports_status'] ?? 'No Report';
    $hasReport = !empty($incident['spot_report_id']);
    
    ob_start();
    ?>
    <tr class="<?php echo $rowClass; ?> status-group-row" 
        data-timestamp="<?php echo date('Y-m-d', strtotime($incident['timestamp'])); ?>"
        data-report-status="<?php echo htmlspecialchars(strtolower(empty($incident['reports_status']) ? 'no_report' : $incident['reports_status'])); ?>"
        data-barangay-id="<?php echo htmlspecialchars($incident['barangay_id'] ?? ''); ?>">
        <td style="display: none;" class="a-center"><?php echo str_pad($incident['id'], 6, '0', STR_PAD_LEFT); ?></td>
        <td style="display: none;">
            <span class="badge <?php echo $incident['smoke'] > 50 ? 'badge-danger' : ($incident['smoke'] > 25 ? 'badge-warning' : 'badge-success'); ?>">
                <?php echo $incident['smoke']; ?>
            </span>
        </td>
        <td style="display: none;">
            <span class="badge <?php echo $incident['temp'] > 50 ? 'badge-danger' : ($incident['temp'] > 30 ? 'badge-warning' : 'badge-success'); ?>">
                <?php echo $incident['temp']; ?>°C
            </span>
        </td>
        <td style="display: none;">
            <span class="badge <?php echo $incident['heat'] > 50 ? 'badge-danger' : ($incident['heat'] > 25 ? 'badge-warning' : 'badge-success'); ?>">
                <?php echo $incident['heat']; ?>
            </span>
        </td>
        <td style="display: none;">
            <span class="badge <?php echo $incident['flame_detected'] ? 'badge-danger' : 'badge-success'; ?>">
                <?php echo $incident['flame_detected'] ? 'Yes' : 'No'; ?>
            </span>
        </td>
        <td style="display: none;">
            <span class="badge <?php echo $incident['ml_confidence'] > 80 ? 'badge-danger' : ($incident['ml_confidence'] > 60 ? 'badge-warning' : 'badge-success'); ?>">
                <?php echo number_format($incident['ml_confidence'], 1); ?>%
            </span>
        </td>
        <td style="display: none;">
            <span class="badge badge-info"><?php echo htmlspecialchars($incident['building_type'] ?? 'Unknown'); ?></span>
        </td>
        <td>
            <strong><?php echo htmlspecialchars($incident['barangay_name'] ?? 'Unknown Location'); ?></strong>
        </td>
        <td>
            <?php 
            $fireDataStatus = $incident['status'] ?? 'Unknown';
            $fireDataStatusLower = strtolower($fireDataStatus);
            $fireStatusClass = '';
            $fireStatusColor = '';
            
            switch($fireDataStatusLower) {
                case 'acknowledged':
                    $fireStatusClass = 'badge-info';
                    $fireStatusColor = '#17a2b8';
                    break;
                case 'pending':
                    $fireStatusClass = 'badge-warning';
                    $fireStatusColor = '#ffc107';
                    break;
                case 'resolved':
                case 'completed':
                    $fireStatusClass = 'badge-success';
                    $fireStatusColor = '#28a745';
                    break;
                case 'cancelled':
                case 'rejected':
                    $fireStatusClass = 'badge-danger';
                    $fireStatusColor = '#dc3545';
                    break;
                default:
                    $fireStatusClass = 'badge-secondary';
                    $fireStatusColor = '#6c757d';
            }
            ?>
            <span class="badge <?php echo $fireStatusClass; ?>" style="background-color: <?php echo $fireStatusColor; ?> !important; color: #ffffff !important; font-weight: 600;">
                <?php echo htmlspecialchars(strtoupper($fireDataStatus)); ?>
            </span>
        </td>
        <td>
            <strong><?php echo htmlspecialchars($incident['device_name'] ?? 'Unknown Device'); ?></strong>
        </td>
        <td>
            <strong><?php echo htmlspecialchars($incident['user_fullname'] ?? 'Unknown'); ?></strong>
            <?php if (!empty($incident['user_name'])): ?>
                <br><small class="text-muted">@<?php echo htmlspecialchars($incident['user_name']); ?></small>
            <?php endif; ?>
            <?php if (!empty($incident['user_status'])): ?>
                <br><small class="badge <?php echo ($incident['user_status']) === 'Active' ? 'badge-success' : 'badge-secondary'; ?>">
                    <?php echo htmlspecialchars($incident['user_status']); ?>
                </small>
            <?php endif; ?>
        </td>
        <td style="display: none;">
            <?php if (!empty($incident['user_contact'])): ?>
                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($incident['user_contact']); ?><br>
            <?php endif; ?>
            <?php if (!empty($incident['email_address'])): ?>
                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($incident['email_address']); ?><br>
            <?php endif; ?>
            <?php if (!empty($incident['device_number'])): ?>
                <i class="fas fa-mobile-alt"></i> <?php echo htmlspecialchars($incident['device_number']); ?>
            <?php endif; ?>
        </td>
        <td>
            <div class="d-flex align-items-center">
                <span class="badge <?php echo $statusClass; ?> me-2 report-status-<?php echo strtolower(str_replace(' ', '-', $reportStatus)); ?>" style="background-color: <?php echo $statusColor; ?> !important; color: <?php echo (in_array(strtolower($reportStatus), ['draft', 'pending', 'pending_review'])) ? '#212529' : '#ffffff'; ?> !important; font-weight: 600;">
                    <?php echo htmlspecialchars(ucfirst($reportStatus)); ?>
                </span>
            </div>
        </td>
        <td><?php echo date('M d, Y H:i', strtotime($incident['timestamp'])); ?></td>
        <td>
            <button class="btn btn-sm btn-primary" onclick="viewIncidentDetails(<?php echo htmlspecialchars(json_encode($incident)); ?>)" title="View Details">
                <i class="fas fa-eye"></i>
            </button>
            <?php if ($hasReport): ?>
                <?php if (strtolower($currentReportStatus) === 'final'): ?>
                    <button class="btn btn-sm action-btn-final" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit Final">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                <?php elseif (in_array(strtolower($currentReportStatus), ['pending', 'pending_review'])): ?>
                    <button class="btn btn-sm action-btn-pending" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit Pending">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                <?php elseif ($currentReportStatus === 'draft'): ?>
                    <button class="btn btn-sm action-btn-draft" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit Draft">
                        <i class="fas fa-edit"></i> Edit Draft
                    </button>
                <?php else: ?>
                    <button class="btn btn-sm action-btn-edit" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn btn-sm action-btn-no-report" onclick="createSpotReport(<?php echo $incident['id']; ?>)" title="Create">
                    <i class="fas fa-file-alt"></i> Create
                </button>
            <?php endif; ?>
        </td>
    </tr>
    <?php
    return ob_get_clean();
}
?>

  <!-- Include header with all necessary libraries -->
  <?php include '../../components/header.php'; ?>
  <style>
    /* Burger Menu Toggle Button Styles */
.filter-toggle-btn {
    display: flex !important;
    flex-direction: column;
    justify-content: space-around;
    align-items: center;
    width: 35px;
    height: 35px;
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 8px 5px;
    margin: 0;
    transition: transform 0.3s ease, box-shadow 0.3s ease, opacity 0.3s ease;
    visibility: visible !important;
    opacity: 1 !important;
}
    
.filter-toggle-btn:hover {
    opacity: 0.9;
    transform: translateY(-2px) scale(1.05);
    box-shadow: 0 4px 12px rgba(40, 167, 69, 0.35);
}
    
.filter-toggle-btn:active {
    transform: translateY(0) scale(0.98);
}
    
.filter-toggle-btn.active {
    transform: rotate(90deg) scale(1.05);
    box-shadow: 0 6px 16px rgba(40, 167, 69, 0.35);
}
    
    .burger-line {
    width: 25px;
    height: 3px;
    background-color: #28a745;
    border-radius: 3px;
    transition: all 0.3s ease;
    display: block;
    margin: 2px 0;
    }
    
    .filter-toggle-btn:hover .burger-line {
    background-color: #34c759;
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(1) {
        transform: rotate(45deg) translate(7px, 7px);
    background-color: #28a745;
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(2) {
        opacity: 0;
    }
    
    .filter-toggle-btn.active .burger-line:nth-child(3) {
        transform: rotate(-45deg) translate(7px, -7px);
    background-color: #28a745;
    }
    
    /* Filter Overlay */
    .filter-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .filter-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    
    /* Filter Panel - Side Panel Style */
    .filter-panel {
        position: fixed;
        top: 0;
        right: -400px;
        width: 380px;
        height: 100%;
        background-color: #fff;
        box-shadow: -2px 0 10px rgba(0,0,0,0.2);
        z-index: 1000;
        transition: right 0.3s ease;
        overflow-y: auto;
        padding: 2rem;
    }
    
    .filter-panel.active {
        right: 0;
    }
    
    .filter-panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e0e0e0;
    }
    
    .filter-panel-header h3 {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        color: #333;
    }
    
    .filter-panel-body {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .filter-group label {
        font-weight: 600;
        color: #555;
        font-size: 0.95rem;
    }
    
    .filter-group .form-control,
    .filter-group .form-select,
    .filter-group .btn {
        width: 100%;
    }
    
    @media (max-width: 768px) {
        .filter-panel {
            width: 100%;
            right: -100%;
        }
    }
    
    .panel_toolbox {
        float: right;
        margin: 0;
        list-style: none;
        padding: 0;
        min-width: 70px;
    }
    
    .panel_toolbox li {
        float: left;
        cursor: pointer;
        margin-left: 5px;
    }
    
    .panel_toolbox li a {
        padding: 5px;
        color: #C5C7CB;
        font-size: 14px;
        display: block;
    }
    
    .panel_toolbox li a:hover {
        color: #26B99A;
    }
    
    /* Ensure burger toggle is always visible */
    #filterToggleBtn {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
        position: relative;
        z-index: 10;
    }
    
    .panel_toolbox #filterToggleBtn {
        display: flex !important;
    }
    
    /* Make sure the burger lines are visible */
    #filterToggleBtn .burger-line {
    background-color: #28a745 !important;
        display: block !important;
        visibility: visible !important;
    }
    
    #filterToggleBtn:hover .burger-line {
    background-color: #34c759 !important;
    }
    
    /* Responsive and Wide Table Styles */
    .x_content {
        width: 100%;
        max-width: 100%;
        padding: 20px;
        overflow: visible;
    }
    
    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        margin: 0;
    }
    
    #incidentsTable {
        width: 100% !important;
        min-width: 1000px;
        table-layout: auto;
    }
    
    .dataTables_wrapper {
        width: 100%;
        position: relative;
    }
    
    /* DataTables Top Row - Show entries and Search on same row */
    .dataTables_wrapper > .row:first-child {
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        margin-bottom: 15px !important;
        padding: 0 0 10px 0 !important;
        flex-wrap: nowrap !important;
        width: 100% !important;
    }
    
    .dataTables_wrapper > .row:first-child > div {
        display: flex !important;
        align-items: center !important;
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
    }
    
    .dataTables_wrapper > .row:first-child > div:first-child {
        order: 1;
    }
    
    .dataTables_wrapper > .row:first-child > div:last-child {
        order: 2;
        margin-left: auto;
        text-align: right;
    }
    
    /* Override Bootstrap column classes to keep on same row */
    .dataTables_wrapper > .row:first-child > div[class*="col-"] {
        flex: 0 0 auto !important;
        width: auto !important;
        max-width: none !important;
    }
    
    .dataTables_wrapper .dataTables_length {
        float: none !important;
        margin-bottom: 0 !important;
        padding: 0 !important;
    }
    
    .dataTables_wrapper .dataTables_length label {
        margin-bottom: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px;
        font-weight: normal;
        white-space: nowrap;
    }
    
    .dataTables_wrapper .dataTables_filter {
        float: none !important;
        text-align: right !important;
        margin-bottom: 0 !important;
        padding: 0 !important;
        margin-left: auto;
    }
    
    .dataTables_wrapper .dataTables_filter label {
        margin-bottom: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 8px;
        font-weight: normal;
        white-space: nowrap;
        justify-content: flex-end;
    }
    
    .dataTables_wrapper .dataTables_length select,
    .dataTables_wrapper .dataTables_filter input {
        margin: 0 5px;
        padding: 5px 10px;
        padding-right: 25px;
        border: 1px solid #ddd;
        border-radius: 4px;
        display: inline-block;
        width: auto;
    }
    
    .dataTables_wrapper .dataTables_length select {
        padding-right: 30px;
        transition: all 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        cursor: pointer;
    }
    
    .dataTables_wrapper .dataTables_length select:hover {
        border-color: #26B99A;
        transform: translateY(-1px);
    }
    
    .dataTables_wrapper .dataTables_length select:focus {
        border-color: #26B99A;
        outline: none;
        box-shadow: 0 0 0 2px rgba(38, 185, 154, 0.2);
    }
    
    /* Add transition effect to the select dropdown arrow area */
    .dataTables_wrapper .dataTables_length select {
        -webkit-appearance: none;
        -moz-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 12px;
        transition: all 0.3s ease, background-position 0.3s ease;
    }
    
    .dataTables_wrapper .dataTables_length select:hover {
        background-position: right 8px center;
    }
    
    .dataTables_wrapper .dataTables_length select:active,
    .dataTables_wrapper .dataTables_length select:focus {
        background-position: right 7px center;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        width: 250px;
    }
    
    .dataTables_scroll {
        width: 100%;
        clear: both;
    }
    
    .dataTables_scrollHead,
    .dataTables_scrollBody {
        width: 100%;
    }
    
    .dataTables_scrollHeadInner {
        width: 100%;
    }
    
    .dataTables_scrollHeadInner table,
    .dataTables_scrollBody table {
        width: 100% !important;
        min-width: 1000px;
    }
    
    /* Ensure table columns have adequate width */
    #incidentsTable th,
    #incidentsTable td {
        padding: 8px 10px;
        vertical-align: middle;
        font-size: 0.9rem;
    }
    
    #incidentsTable th.column-title {
        min-width: 100px;
        white-space: nowrap;
    }
    
    /* Allow text wrapping in data cells but keep headers on one line */
    #incidentsTable td {
        word-wrap: break-word;
    }
    
    /* Specific column widths for better layout */
    #incidentsTable td:nth-child(8),
    #incidentsTable th:nth-child(8) {
        min-width: 130px;
    }
    
    #incidentsTable td:nth-child(9),
    #incidentsTable th:nth-child(9) {
        min-width: 130px;
    }
    
    #incidentsTable td:nth-child(12),
    #incidentsTable th:nth-child(12) {
        min-width: 120px;
    }
    
    #incidentsTable td:last-child,
    #incidentsTable th:last-child {
        min-width: 140px;
        text-align: center;
    }
    
    /* Make timestamp column smaller */
    #incidentsTable th:nth-child(13),
    #incidentsTable td:nth-child(13) {
        min-width: 130px;
        white-space: nowrap;
    }
    
    /* Make buttons and badges smaller in table */
    #incidentsTable .btn {
        padding: 4px 8px;
        font-size: 0.85rem;
    }
    
    #incidentsTable .badge {
        font-size: 0.8rem;
        padding: 4px 8px;
    }
    
    #incidentsTable small {
        font-size: 0.8rem;
    }
    
    /* Compact barangay column */
    #incidentsTable td:nth-child(7) {
        max-width: 150px;
    }
    
    /* Compact fire data status column */
    #incidentsTable td:nth-child(8) {
        max-width: 140px;
    }
    
    /* Compact device name column */
    #incidentsTable td:nth-child(9) {
        max-width: 150px;
    }
    
    /* Compact user name column */
    #incidentsTable td:nth-child(10) {
        max-width: 160px;
    }
    
    /* Responsive adjustments for smaller screens */
    @media screen and (max-width: 1200px) {
        #incidentsTable {
            min-width: 900px;
        }
    }
    
    @media screen and (max-width: 768px) {
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            padding: 10px;
            width: 100%;
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
            width: 100%;
        }
    }
    
    /* Pagination Arrow Spacing */
    .dataTables_wrapper .dataTables_paginate {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button {
        margin: 0 2px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.previous {
        margin-right: 10px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.next {
        margin-left: 10px;
    }
    
    .dataTables_wrapper .dataTables_paginate .paginate_button.first,
    .dataTables_wrapper .dataTables_paginate .paginate_button.last {
        margin-left: 5px;
        margin-right: 5px;
    }
    
    /* Action Button Colors - Edit and Create Reports */
    .action-btn-edit {
        background-color: #007bff !important;
        color: #ffffff !important;
        border-color: #007bff !important;
    }
    
    .action-btn-edit:hover {
        background-color: #0056b3 !important;
        border-color: #004085 !important;
        color: #ffffff !important;
    }
    
    .action-btn-create {
        background-color: #28a745 !important;
        color: #ffffff !important;
        border-color: #28a745 !important;
    }
    
    .action-btn-create:hover {
        background-color: #218838 !important;
        border-color: #1e7e34 !important;
        color: #ffffff !important;
    }
    
    .action-btn-draft {
        background-color: #ffc107 !important;
        color: #212529 !important;
        border-color: #ffc107 !important;
    }
    
    .action-btn-draft:hover {
        background-color: #e0a800 !important;
        border-color: #d39e00 !important;
        color: #212529 !important;
    }
    
    .action-btn-pending {
        background-color: #ffc107 !important;
        color: #212529 !important;
        border-color: #ffc107 !important;
    }
    
    .action-btn-pending:hover {
        background-color: #e0a800 !important;
        border-color: #d39e00 !important;
        color: #212529 !important;
    }
    
    .action-btn-final {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        border-color: #dc3545 !important;
    }
    
    .action-btn-final:hover {
        background-color: #c82333 !important;
        border-color: #bd2130 !important;
        color: #ffffff !important;
    }
    
    .action-btn-no-report {
        background-color: #28a745 !important;
        color: #ffffff !important;
        border-color: #28a745 !important;
    }
    
    .action-btn-no-report:hover {
        background-color: #218838 !important;
        border-color: #1e7e34 !important;
        color: #ffffff !important;
    }
    
    /* Report Status Badge Colors - Match Button Colors Exactly */
    .report-status-draft,
    #incidentsTable .report-status-draft,
    #incidentsTable .badge.report-status-draft {
        background-color: #ffc107 !important;
        color: #212529 !important;
        font-weight: 600 !important;
        border: none;
    }
    
    .report-status-final,
    #incidentsTable .report-status-final,
    #incidentsTable .badge.report-status-final {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        border: none;
    }
    
    .report-status-pending,
    .report-status-pending_review,
    #incidentsTable .report-status-pending,
    #incidentsTable .report-status-pending_review,
    #incidentsTable .badge.report-status-pending,
    #incidentsTable .badge.report-status-pending_review {
        background-color: #ffc107 !important;
        color: #212529 !important;
        font-weight: 600 !important;
        border: none;
    }
    
    .report-status-completed,
    #incidentsTable .report-status-completed,
    #incidentsTable .badge.report-status-completed {
        background-color: #28a745 !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        border: none;
    }
    
    .report-status-approved,
    #incidentsTable .report-status-approved,
    #incidentsTable .badge.report-status-approved {
        background-color: #007bff !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        border: none;
    }
    
    .report-status-no-report,
    #incidentsTable .report-status-no-report,
    #incidentsTable .badge.report-status-no-report {
        background-color: #28a745 !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        border: none;
    }
    
    /* Override default badge colors in table to match button colors */
    #incidentsTable td .badge.badge-danger {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }
    
    #incidentsTable td .badge.badge-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }
    
    #incidentsTable td .badge.badge-info {
        background-color: #17a2b8 !important;
        color: #ffffff !important;
    }
    
    #incidentsTable td .badge.badge-success {
        background-color: #28a745 !important;
        color: #ffffff !important;
    }
    
    #incidentsTable td .badge.badge-primary {
        background-color: #007bff !important;
        color: #ffffff !important;
    }
    
    #incidentsTable td .badge.badge-secondary {
        background-color: #6c757d !important;
        color: #ffffff !important;
    }
    
    /* Status Group Header Styles */
    #incidentsTable tr.status-group-header {
        background-color: transparent !important;
    }
    
    #incidentsTable tr.status-group-header td {
        font-size: 0.95rem !important;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: sticky;
        top: 0;
        z-index: 10;
        border-top: 2px solid rgba(0,0,0,0.1);
        border-bottom: 2px solid rgba(0,0,0,0.1);
    }
    
    #incidentsTable tr.status-group-header:hover {
        background-color: inherit !important;
    }
    
    /* Group Row Styling - Add subtle left border to indicate grouping */
    #incidentsTable tr.status-group-row[data-report-status="no_report"] {
        border-left: 3px solid #28a745;
    }
    
    #incidentsTable tr.status-group-row[data-report-status="draft"] {
        border-left: 3px solid #ffc107;
    }
    
    #incidentsTable tr.status-group-row[data-report-status="pending"],
    #incidentsTable tr.status-group-row[data-report-status="pending_review"] {
        border-left: 3px solid #ff9800;
    }
    
    /* Add spacing between groups */
    #incidentsTable tr.status-group-header + tr.status-group-row {
        border-top: 1px solid rgba(0,0,0,0.05);
    }
    
    /* Ensure group headers are not affected by row striping */
    #incidentsTable tr.status-group-header.even,
    #incidentsTable tr.status-group-header.odd {
        background-color: transparent !important;
    }
    
    /* Smooth transition for group headers */
    .status-group-header-cell {
        transition: all 0.3s ease;
    }
    
    .status-group-header-cell:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.15) !important;
    }
    
    /* Table Navigation Styles - Modern & Compact */
    .table-navigation {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        padding: 0;
        background: #f8f9fa;
        border-radius: 8px;
        padding: 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .table-nav-btn {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 12px;
        border: none;
        background: transparent;
        color: #6c757d;
        font-size: 0.875rem;
        font-weight: 500;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        min-width: 0;
    }
    
    .table-nav-btn i {
        font-size: 0.9rem;
        opacity: 0.7;
    }
    
    .table-nav-btn .nav-label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .table-nav-btn .nav-badge {
        background: rgba(108, 117, 125, 0.15);
        color: #6c757d;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 600;
        min-width: 20px;
        text-align: center;
    }
    
    .table-nav-btn:hover {
        background: rgba(0,0,0,0.05);
        color: #495057;
    }
    
    .table-nav-btn:hover i {
        opacity: 1;
    }
    
    .table-nav-btn.active {
        background: #ffffff;
        color: #26B99A;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    .table-nav-btn.active i {
        opacity: 1;
        color: #26B99A;
    }
    
    .table-nav-btn.active .nav-badge {
        background: #26B99A;
        color: #ffffff;
    }
    
    @media (max-width: 768px) {
        .table-navigation {
            flex-wrap: wrap;
        }
        
        .table-nav-btn {
            flex: 1 1 calc(50% - 4px);
            min-width: calc(50% - 4px);
        }
        
        .table-nav-btn .nav-label {
            display: none;
        }
    }
    
    .table-container {
        animation: fadeIn 0.3s ease-in;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
  </style>
  <script>
    // Filter Panel Toggle Function - Must be defined globally for onclick handlers
    function toggleFilterPanel() {
        const filterPanel = document.getElementById('filterPanel');
        const filterOverlay = document.getElementById('filterOverlay');
        const filterToggleBtn = document.getElementById('filterToggleBtn');
        
        if (filterPanel && filterOverlay && filterToggleBtn) {
            filterPanel.classList.toggle('active');
            filterOverlay.classList.toggle('active');
            filterToggleBtn.classList.toggle('active');
        }
    }
  </script>
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
        <!-- Filter Overlay -->
        <div class="filter-overlay" id="filterOverlay" onclick="toggleFilterPanel()"></div>
        
        <!-- Filter Panel - Side Panel -->
        <div class="filter-panel" id="filterPanel">
            <div class="filter-panel-header">
                <h3><i class="fa fa-filter" style="margin-right: 8px;"></i> Filters</h3>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilterPanel()" title="Close Filters">
                    <i class="fa fa-times"></i>
                </button>
                    </div>
            <div class="filter-panel-body">
                <form method="GET" id="filterForm" action="index.php">
                    <div class="filter-group">
                        <label>Start Date</label>
                                    <input type="date" id="filterStartDate" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
                    <div class="filter-group">
                        <label>End Date</label>
                                    <input type="date" id="filterEndDate" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                                </div>
                    <div class="filter-group">
                        <label>Report Status</label>
                                    <select id="filterReportStatus" name="report_status" class="form-control">
                                        <option value="">All Reports</option>
                                        <option value="no_report" <?php echo $reportStatus === 'no_report' ? 'selected' : ''; ?>>No Reports</option>
                                        <option value="draft" <?php echo $reportStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="pending_review" <?php echo $reportStatus === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                                    </select>
                                </div>
                    <div class="filter-group">
                        <label>Barangay</label>
                                    <select id="filterBarangayId" name="barangay_id" class="form-control">
                                        <option value="">All Barangays</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['id']; ?>" <?php echo $barangayId == $barangay['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                    <div class="filter-group">
                        <a href="index.php" class="btn btn-default" style="margin-top: 10px;">
                            <i class="fa fa-refresh"></i> Reset Filters
                        </a>
                    </div>
                    <div class="filter-group">
                        <span id="filterResultCount" style="color: #73879C; font-size: 0.9rem;">
                                Showing <span id="filterCount"><?php echo count($fireData); ?></span> incident(s)
                            </span>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Page Title -->
        <div class="row">
            <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel" style="width: 100%;">
            <div class="x_title">
                        <h2>Create Reports <small>Fire Incidents List</small></h2>
                        <ul class="nav navbar-right panel_toolbox">
                            <li>
                                <a class="filter-toggle-btn" id="filterToggleBtn" title="Toggle Filters">
                                    <span class="burger-line"></span>
                                    <span class="burger-line"></span>
                                    <span class="burger-line"></span>
                                </a>
                            </li>
                            <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                        </ul>
                        <div class="clearfix"></div>
            </div>
            <div class="x_content">
            <?php if (empty($fireData)): ?>
                            <div class="text-center" style="padding: 10px 0;">
                                <i class="fa fa-fire" style="font-size: 48px; color: #ccc;"></i>
                                <h3 style="color: #73879C; margin-top: 15px;">No Acknowledged Fire Incidents Found</h3>
                                <p style="color: #73879C;">There are currently no fire incidents with ACKNOWLEDGED status.</p>
                </div>
            <?php else: 
                            // Prepare data for each table
                            $allReports = $fireData; // All reports
                            $noReports = array_filter($fireData, function($incident) {
                                $status = strtolower(empty($incident['reports_status']) ? '' : $incident['reports_status']);
                                return empty($status) || $status === 'no_report';
                            });
                            $draftReports = array_filter($fireData, function($incident) {
                                $status = strtolower(empty($incident['reports_status']) ? '' : $incident['reports_status']);
                                return $status === 'draft';
                            });
                            $pendingReports = array_filter($fireData, function($incident) {
                                $status = strtolower(empty($incident['reports_status']) ? '' : $incident['reports_status']);
                                return $status === 'pending' || $status === 'pending_review';
                            });
                            
                            $totalColumns = 15; // Total number of columns in the table
            ?>
                            <!-- Navigation Buttons -->
                            <div class="table-navigation">
                                <button class="table-nav-btn active" data-table="all">
                                    <i class="fa fa-list"></i>
                                    <span class="nav-label">All</span>
                                    <span class="nav-badge"><?php echo count($allReports); ?></span>
                                </button>
                                <button class="table-nav-btn" data-table="no-report">
                                    <i class="fa fa-file-plus"></i>
                                    <span class="nav-label">No Report</span>
                                    <span class="nav-badge"><?php echo count($noReports); ?></span>
                                </button>
                                <button class="table-nav-btn" data-table="draft">
                                    <i class="fa fa-file-edit"></i>
                                    <span class="nav-label">Draft</span>
                                    <span class="nav-badge"><?php echo count($draftReports); ?></span>
                                </button>
                                <button class="table-nav-btn" data-table="pending">
                                    <i class="fa fa-clock"></i>
                                    <span class="nav-label">Pending</span>
                                    <span class="nav-badge"><?php echo count($pendingReports); ?></span>
                                </button>
                            </div>
                            
                            <!-- All Reports Table -->
                            <div class="table-container" id="table-all" style="display: block;">
                                <div class="card-box table-responsive">
                                    <table id="incidentsTableAll" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="display: none;">Incident ID</th>
                                                <th style="display: none;">Smoke Level</th>
                                                <th style="display: none;">Temperature</th>
                                                <th style="display: none;">Heat Level</th>
                                                <th style="display: none;">Flame Detected</th>
                                                <th style="display: none;">ML Confidence</th>
                                                <th style="display: none;">Building Type</th>
                                                <th>Barangay</th>
                                                <th>Fire Data Status</th>
                                                <th>Device Name</th>
                                                <th>User Fullname</th>
                                                <th style="display: none;">Contact Info</th>
                                                <th>Report Status</th>
                                                <th>Timestamp</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rowIndex = 0;
                                            $groupedIncidents = groupIncidentsByStatus($allReports);
                                            
                                            // Render No Reports group
                                            if (!empty($groupedIncidents['no_report']['items'])) {
                                                echo renderStatusGroupHeader($groupedIncidents['no_report'], $totalColumns);
                                                foreach ($groupedIncidents['no_report']['items'] as $incident) {
                                                    echo renderIncidentRow($incident, $rowIndex);
                                                }
                                            }
                                            
                                            // Render Draft Reports group
                                            if (!empty($groupedIncidents['draft']['items'])) {
                                                echo renderStatusGroupHeader($groupedIncidents['draft'], $totalColumns);
                                                foreach ($groupedIncidents['draft']['items'] as $incident) {
                                                    echo renderIncidentRow($incident, $rowIndex);
                                                }
                                            }
                                            
                                            // Render Pending Review group
                                            if (!empty($groupedIncidents['pending']['items'])) {
                                                echo renderStatusGroupHeader($groupedIncidents['pending'], $totalColumns);
                                                foreach ($groupedIncidents['pending']['items'] as $incident) {
                                                    echo renderIncidentRow($incident, $rowIndex);
                                                }
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- No Reports Table -->
                            <div class="table-container" id="table-no-report" style="display: none;">
                                <div class="card-box table-responsive">
                                    <table id="incidentsTableNoReport" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="display: none;">Incident ID</th>
                                                <th style="display: none;">Smoke Level</th>
                                                <th style="display: none;">Temperature</th>
                                                <th style="display: none;">Heat Level</th>
                                                <th style="display: none;">Flame Detected</th>
                                                <th style="display: none;">ML Confidence</th>
                                                <th style="display: none;">Building Type</th>
                                                <th>Barangay</th>
                                                <th>Fire Data Status</th>
                                                <th>Device Name</th>
                                                <th>User Fullname</th>
                                                <th style="display: none;">Contact Info</th>
                                                <th>Report Status</th>
                                                <th>Timestamp</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rowIndex = 0;
                                            foreach ($noReports as $incident) {
                                                echo renderIncidentRow($incident, $rowIndex);
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Draft Reports Table -->
                            <div class="table-container" id="table-draft" style="display: none;">
                                <div class="card-box table-responsive">
                                    <table id="incidentsTableDraft" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="display: none;">Incident ID</th>
                                                <th style="display: none;">Smoke Level</th>
                                                <th style="display: none;">Temperature</th>
                                                <th style="display: none;">Heat Level</th>
                                                <th style="display: none;">Flame Detected</th>
                                                <th style="display: none;">ML Confidence</th>
                                                <th style="display: none;">Building Type</th>
                                                <th>Barangay</th>
                                                <th>Fire Data Status</th>
                                                <th>Device Name</th>
                                                <th>User Fullname</th>
                                                <th style="display: none;">Contact Info</th>
                                                <th>Report Status</th>
                                                <th>Timestamp</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rowIndex = 0;
                                            foreach ($draftReports as $incident) {
                                                echo renderIncidentRow($incident, $rowIndex);
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Pending Review Table -->
                            <div class="table-container" id="table-pending" style="display: none;">
                                <div class="card-box table-responsive">
                                    <table id="incidentsTablePending" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="display: none;">Incident ID</th>
                                                <th style="display: none;">Smoke Level</th>
                                                <th style="display: none;">Temperature</th>
                                                <th style="display: none;">Heat Level</th>
                                                <th style="display: none;">Flame Detected</th>
                                                <th style="display: none;">ML Confidence</th>
                                                <th style="display: none;">Building Type</th>
                                                <th>Barangay</th>
                                                <th>Fire Data Status</th>
                                                <th>Device Name</th>
                                                <th>User Fullname</th>
                                                <th style="display: none;">Contact Info</th>
                                                <th>Report Status</th>
                                                <th>Timestamp</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $rowIndex = 0;
                                            foreach ($pendingReports as $incident) {
                                                echo renderIncidentRow($incident, $rowIndex);
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
            <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
  

    <script>
        // Global variables for DataTables
        var tableAll = null;
        var tableNoReport = null;
        var tableDraft = null;
        var tablePending = null;
        var currentTable = null;
        var currentTableType = 'all';
        
        // Initialize DataTable
        $(document).ready(function() {
            // Check if any filters are active on page load
            const urlParams = new URLSearchParams(window.location.search);
            const hasFilters = urlParams.get('start_date') || urlParams.get('end_date') || 
                              urlParams.get('report_status') || urlParams.get('barangay_id');
            
            // Show filter panel if filters are active
            if (hasFilters) {
                toggleFilterPanel();
            }
            
            // Burger Menu Toggle Functionality for Side Panel
            $('#filterToggleBtn').on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleFilterPanel();
            });
            
            // Close filter panel when clicking overlay
            document.addEventListener('click', function(event) {
                const filterPanel = document.getElementById('filterPanel');
                const filterOverlay = document.getElementById('filterOverlay');
                const filterToggleBtn = document.getElementById('filterToggleBtn');
                
                // If clicking outside the panel and overlay is active, close it
                if (filterOverlay && filterOverlay.classList.contains('active') && 
                    filterPanel && !filterPanel.contains(event.target) && 
                    filterToggleBtn && !filterToggleBtn.contains(event.target)) {
                    toggleFilterPanel();
                }
            });
            
            // Table Navigation Button Handlers
            $('.table-nav-btn').on('click', function() {
                const tableType = $(this).data('table');
                switchTable(tableType);
            });
            
            // Check for report saved notification
            if (urlParams.get('status') === 'report_saved') {
                const reportId = urlParams.get('report_id');
                const reportStatus = urlParams.get('report_status');
                const isFinal = urlParams.get('final') === 'true';
                
                let title = 'Report Saved!';
                let text = '';
                let icon = 'success';
                
                if (isFinal && reportStatus === 'final') {
                    title = 'Report Completed Successfully!';
                    text = 'Your fire incident report has been finalized and completed successfully.';
                    icon = 'success';
                } else {
                    switch(reportStatus) {
                        case 'draft':
                            text = 'Your fire incident report has been saved as draft.';
                            break;
                        case 'pending_review':
                            text = 'Your fire incident report has been submitted for review.';
                            break;
                        case 'completed':
                            text = 'Your fire incident report has been completed successfully.';
                            break;
                        case 'approved':
                            text = 'Your fire incident report has been approved.';
                            break;
                        default:
                            text = 'Your fire incident report has been saved successfully.';
                    }
                }
                
                Swal.fire({
                    title: title,
                    text: text,
                    icon: icon,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#28a745',
                    timer: isFinal ? 5000 : 3000,
                    timerProgressBar: true
                }).then(() => {
                    // If this is a final report, redirect to main page
                    if (isFinal && reportStatus === 'final') {
                        window.location.href = 'index.php';
                    } else {
                        // Clean up URL parameters for other cases
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                });
            }
            // Custom sorting function to prioritize "no report" status
            $.fn.dataTable.ext.order['report-status-priority'] = function(settings, col) {
                return this.api().column(col, {order: 'index'}).nodes().map(function(td, i) {
                    var row = $(td).closest('tr');
                    var reportStatus = (row.attr('data-report-status') || '').toLowerCase();
                    // Return 0 for no_report/empty (to sort first), 1 for others
                    return (reportStatus === 'no_report' || reportStatus === '') ? 0 : 1;
                });
            };
            
            // Function to switch between tables
            function switchTable(tableType) {
                // Update button states
                $('.table-nav-btn').removeClass('active');
                $('.table-nav-btn[data-table="' + tableType + '"]').addClass('active');
                
                // Hide all tables
                $('.table-container').hide();
                
                // Show selected table
                $('#table-' + tableType).show();
                
                // Update current table type
                currentTableType = tableType;
                
                // Initialize DataTable for the selected table if not already initialized
                switch(tableType) {
                    case 'all':
                        if (!tableAll) {
                            tableAll = initializeDataTable('#incidentsTableAll', true);
                        }
                        currentTable = tableAll;
                        break;
                    case 'no-report':
                        if (!tableNoReport) {
                            tableNoReport = initializeDataTable('#incidentsTableNoReport', false);
                        }
                        currentTable = tableNoReport;
                        break;
                    case 'draft':
                        if (!tableDraft) {
                            tableDraft = initializeDataTable('#incidentsTableDraft', false);
                        }
                        currentTable = tableDraft;
                        break;
                    case 'pending':
                        if (!tablePending) {
                            tablePending = initializeDataTable('#incidentsTablePending', false);
                        }
                        currentTable = tablePending;
                        break;
                }
                
                // Update filter count for the active table
                if (currentTable) {
                    setTimeout(function() {
                        var visibleRows = 0;
                        currentTable.rows({search: 'applied'}).every(function() {
                            var row = this.node();
                            if (!$(row).hasClass('status-group-header')) {
                                visibleRows++;
                            }
                        });
                        $('#filterCount').text(visibleRows);
                    }, 100);
                } else {
                    // If table not initialized yet, show total count
                    var totalCount = 0;
                    switch(tableType) {
                        case 'all':
                            totalCount = <?php echo count($allReports); ?>;
                            break;
                        case 'no-report':
                            totalCount = <?php echo count($noReports); ?>;
                            break;
                        case 'draft':
                            totalCount = <?php echo count($draftReports); ?>;
                            break;
                        case 'pending':
                            totalCount = <?php echo count($pendingReports); ?>;
                            break;
                    }
                    $('#filterCount').text(totalCount);
                }
                
                // Apply filters to the active table
                if (currentTable) {
                    applyRealTimeFilters();
                }
            }
            
            // Function to initialize DataTable
            function initializeDataTable(tableId, showGroups) {
                var $table = $(tableId);
                if ($table.length === 0) return null;
                
                // Store group headers if showing groups
                var groupHeadersData = [];
                if (showGroups) {
                    $table.find('tbody tr.status-group-header').each(function() {
                        var $header = $(this);
                        var groupTitle = $header.attr('data-group') || '';
                        var groupKey = '';
                        
                        if (groupTitle === 'No Reports') {
                            groupKey = 'no_report';
                        } else if (groupTitle === 'Draft Reports') {
                            groupKey = 'draft';
                        } else if (groupTitle === 'Pending Review') {
                            groupKey = 'pending';
                        }
                        
                        groupHeadersData.push({
                            element: $header.clone(true),
                            groupKey: groupKey,
                            groupTitle: groupTitle
                        });
                    });
                    
                    // Remove group headers temporarily
                    $table.find('tbody tr.status-group-header').remove();
                }
                
                // Verify column count
                var headerCols = $table.find('thead tr:first th').length;
                var firstDataRow = $table.find('tbody tr.status-group-row:first');
                var firstRowCols = firstDataRow.length > 0 ? firstDataRow.find('td').length : 0;
                
                if (firstRowCols === 0) {
                    $table.find('tbody tr').each(function() {
                        if (!$(this).hasClass('status-group-header')) {
                            var tdCount = $(this).find('td').length;
                            if (tdCount > 0) {
                                firstRowCols = tdCount;
                                return false;
                            }
                        }
                    });
                }
                
                if (headerCols === firstRowCols || firstRowCols === 0 || headerCols === 15) {
                    var columnDefs = [
                        { "visible": false, "targets": [0, 1, 2, 3, 4, 5, 6, 10] },
                        { "orderable": false, "targets": 14 },
                        { "type": "report-status-priority", "targets": 12 }
                    ];
                    
                    var table = $table.DataTable({
                        "pageLength": 25,
                        "order": [],
                        "columnDefs": columnDefs,
                        "columns": [
                            null, null, null, null, null, null, null, null, null, null, null, null, null, null, null
                        ],
                        "responsive": true,
                        "scrollX": true,
                        "scrollCollapse": true,
                        "autoWidth": false,
                        "drawCallback": function(settings) {
                            if (showGroups && groupHeadersData.length > 0) {
                                $table.find('tbody tr.status-group-header').remove();
                                
                                var tbody = $table.find('tbody');
                                var insertedGroups = {};
                                var currentGroup = null;
                                
                                tbody.find('tr').each(function() {
                                    var $row = $(this);
                                    var rowStatus = ($row.attr('data-report-status') || '').toLowerCase();
                                    
                                    var groupKey = '';
                                    if (rowStatus === 'no_report' || rowStatus === '') {
                                        groupKey = 'no_report';
                                    } else if (rowStatus === 'draft') {
                                        groupKey = 'draft';
                                    } else if (rowStatus === 'pending' || rowStatus === 'pending_review') {
                                        groupKey = 'pending';
                                    }
                                    
                                    if (groupKey && groupKey !== currentGroup && !insertedGroups[groupKey]) {
                                        var headerData = groupHeadersData.find(function(h) {
                                            return h.groupKey === groupKey;
                                        });
                                        
                                        if (headerData) {
                                            headerData.element.clone(true).insertBefore($row);
                                            insertedGroups[groupKey] = true;
                                            currentGroup = groupKey;
                                        }
                                    }
                                });
                                
                                tbody.find('tr.status-group-header').each(function() {
                                    $(this).css({
                                        'display': 'table-row',
                                        'background-color': 'transparent'
                                    });
                                });
                            }
                        }
                    });
                    
                    return table;
                }
                
                return null;
            }
            
            // Initialize the first table (All Reports) on page load
            if ($('#incidentsTableAll').length > 0) {
                tableAll = initializeDataTable('#incidentsTableAll', true);
                currentTable = tableAll;
            }

            // Real-time filtering function
            function applyRealTimeFilters() {
                if (!currentTable) return; // Exit if table is not initialized
                
                var startDate = $('#filterStartDate').val();
                var endDate = $('#filterEndDate').val();
                var reportStatus = $('#filterReportStatus').val();
                var barangayId = $('#filterBarangayId').val();

                // Clear existing custom search functions
                $.fn.dataTable.ext.search.pop();

                // Custom search function for filtering
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                    var row = currentTable.row(dataIndex).node();
                    
                    // Skip group header rows
                    if ($(row).hasClass('status-group-header')) {
                        return true; // Always show group headers
                    }
                    
                    var rowTimestamp = $(row).attr('data-timestamp') || '';
                    var rowReportStatus = $(row).attr('data-report-status') || '';
                    var rowBarangayId = $(row).attr('data-barangay-id') || '';

                    // Date range filter
                    if (startDate) {
                        if (!rowTimestamp || rowTimestamp < startDate) {
                            return false;
                        }
                    }
                    if (endDate) {
                        if (!rowTimestamp || rowTimestamp > endDate) {
                            return false;
                        }
                    }

                    // Report status filter - only apply if not viewing a specific table
                    if (reportStatus && currentTableType === 'all') {
                        if (reportStatus === 'no_report') {
                            // Show ONLY rows with no report (NULL or empty status)
                            if (rowReportStatus && 
                                rowReportStatus !== 'no_report' && 
                                rowReportStatus !== '') {
                                return false;
                            }
                        } else if (reportStatus === 'draft') {
                            // Show ONLY draft reports (exclude pending, pending_review, and no_report)
                            if (!rowReportStatus || 
                                rowReportStatus === '' || 
                                rowReportStatus === 'no_report' ||
                                rowReportStatus.toLowerCase() !== 'draft') {
                                return false;
                            }
                        } else if (reportStatus === 'pending_review') {
                            // Show rows with pending or pending_review status
                            if (rowReportStatus && 
                                rowReportStatus.toLowerCase() !== 'pending' &&
                                rowReportStatus.toLowerCase() !== 'pending_review') {
                                return false;
                            }
                        } else {
                            // Match exact status (case-insensitive)
                            if (rowReportStatus.toLowerCase() !== reportStatus.toLowerCase()) {
                                return false;
                            }
                        }
                    }

                    // Barangay filter
                    if (barangayId) {
                        if (rowBarangayId !== barangayId.toString()) {
                            return false;
                        }
                    }

                    return true;
                });

                // Apply filters and redraw table
                currentTable.draw();

                // Update result count (excluding group header rows)
                setTimeout(function() {
                    var visibleRows = 0;
                    currentTable.rows({search: 'applied'}).every(function() {
                        var row = this.node();
                        if (!$(row).hasClass('status-group-header')) {
                            visibleRows++;
                        }
                    });
                    $('#filterCount').text(visibleRows);
                }, 100);
            }

            // Real-time event listeners for instant filtering
            $('#filterStartDate, #filterEndDate, #filterReportStatus, #filterBarangayId').on('change', function() {
                applyRealTimeFilters();
            });

            // Prevent form submission - use real-time filtering instead
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                applyRealTimeFilters();
            });

            // Update filter result count on page load
            if (currentTable) {
                setTimeout(function() {
                    var visibleRows = 0;
                    currentTable.rows({search: 'applied'}).every(function() {
                        var row = this.node();
                        if (!$(row).hasClass('status-group-header')) {
                            visibleRows++;
                        }
                    });
                    $('#filterCount').text(visibleRows);
                }, 500);
            }
        });

        function createSpotReport(fireDataId) {
            Swal.fire({
                title: 'Create Report',
                text: 'Do you want to create a report for this fire incident?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#495057',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, create report!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to create report page with fire_data_id parameter
                    window.location.href = `create_report.php?fire_data_id=${fireDataId}`;
                }
            });
        }

        function editSpotReport(reportId) {
            // Redirect to edit report page
            window.location.href = `edit.php?id=${reportId}`;
        }

        function viewSpotReport(reportId) {
            // Redirect to view report page
            window.location.href = `view.php?id=${reportId}`;
        }

        function updateReportStatus(reportId, newStatus) {
            const statusLabels = {
                'draft': 'Draft',
                'pending_review': 'Pending Review',
                'completed': 'Completed',
                'approved': 'Approved'
            };

            Swal.fire({
                title: 'Update Report Status',
                text: `Are you sure you want to change the status to "${statusLabels[newStatus]}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, update status!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Updating...',
                        text: 'Please wait while we update the report status.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    // Make API call to update status
                    fetch('../api/update_report_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            report_id: reportId,
                            status: newStatus
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: 'Report status updated successfully.',
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                // Reload the page to show updated status
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: data.message || 'Failed to update report status.',
                                icon: 'error'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while updating the report status.',
                            icon: 'error'
                        });
                    });
                }
            });
        }

        function viewIncidentDetails(incident) {
            // Populate modal with incident data
            document.getElementById('modal-incident-id').textContent = '#' + String(incident.id).padStart(6, '0');
            document.getElementById('modal-timestamp').textContent = new Date(incident.timestamp).toLocaleString();
            document.getElementById('modal-acknowledged').textContent = incident.acknowledged_at_time || 'N/A';
            
            // Report Status
            const reportStatus = incident.reports_status || 'No Report';
            const statusElement = document.getElementById('modal-report-status');
            statusElement.textContent = reportStatus.charAt(0).toUpperCase() + reportStatus.slice(1);
            statusElement.className = 'badge ';
            switch(reportStatus) {
                case 'Final':
                case 'final':
                    statusElement.className += 'badge-danger report-status-final';
                    statusElement.style.backgroundColor = '#dc3545';
                    statusElement.style.color = '#ffffff';
                    break;
                case 'No Report':
                case 'no_report':
                    statusElement.className += 'badge-success report-status-no-report';
                    statusElement.style.backgroundColor = '#28a745';
                    statusElement.style.color = '#ffffff';
                    break;
                case 'Pending':
                case 'pending':
                case 'pending_review':
                    statusElement.className += 'badge-warning report-status-pending';
                    statusElement.style.backgroundColor = '#ffc107';
                    statusElement.style.color = '#212529';
                    break;
                case 'draft':
                    statusElement.className += 'badge-warning report-status-draft';
                    statusElement.style.backgroundColor = '#ffc107';
                    statusElement.style.color = '#212529';
                    break;
                case 'completed':
                    statusElement.className += 'badge-success report-status-completed';
                    statusElement.style.backgroundColor = '#28a745';
                    statusElement.style.color = '#ffffff';
                    break;
                case 'approved':
                    statusElement.className += 'badge-primary report-status-approved';
                    statusElement.style.backgroundColor = '#007bff';
                    statusElement.style.color = '#ffffff';
                    break;
                default:
                    statusElement.className += 'badge-secondary report-status-no-report';
                    statusElement.style.backgroundColor = '#28a745';
                    statusElement.style.color = '#ffffff';
            }
            statusElement.style.fontWeight = '600';
            
            // Building Information
            document.getElementById('modal-building-name').textContent = incident.building_name || 'Unknown';
            document.getElementById('modal-building-type').textContent = incident.building_type || 'Unknown';
            document.getElementById('modal-building-address').textContent = incident.building_address || 'Not specified';
            document.getElementById('modal-barangay').textContent = incident.barangay_name || 'Unknown Location';
            
            // User Information
            document.getElementById('modal-user-fullname').textContent = incident.user_fullname || incident.user_name || 'Unknown';
            document.getElementById('modal-username').textContent = '@' + (incident.user_name || 'N/A');
            
            // User Status
            const userStatus = incident.user_status || 'Unknown';
            const userStatusElement = document.getElementById('modal-user-status');
            userStatusElement.textContent = userStatus;
            userStatusElement.className = 'badge ' + (userStatus === 'Active' ? 'badge-success' : 'badge-secondary');
            
            document.getElementById('modal-user-contact').textContent = incident.user_contact || 'Not provided';
            document.getElementById('modal-user-email').textContent = incident.email_address || 'Not provided';
            document.getElementById('modal-device-number').textContent = incident.device_number || 'Not provided';
            
            // Sensor Data
            const smokeElement = document.getElementById('modal-smoke');
            smokeElement.textContent = incident.smoke;
            smokeElement.className = 'badge ' + (incident.smoke > 50 ? 'badge-danger' : (incident.smoke > 25 ? 'badge-warning' : 'badge-success'));
            
            const tempElement = document.getElementById('modal-temperature');
            tempElement.textContent = incident.temp + '°C';
            tempElement.className = 'badge ' + (incident.temp > 50 ? 'badge-danger' : (incident.temp > 30 ? 'badge-warning' : 'badge-success'));
            
            const heatElement = document.getElementById('modal-heat');
            heatElement.textContent = incident.heat;
            heatElement.className = 'badge ' + (incident.heat > 50 ? 'badge-danger' : (incident.heat > 25 ? 'badge-warning' : 'badge-success'));
            
            const flameElement = document.getElementById('modal-flame');
            flameElement.textContent = incident.flame_detected ? 'Yes' : 'No';
            flameElement.className = 'badge ' + (incident.flame_detected ? 'badge-danger' : 'badge-success');
            
            const mlElement = document.getElementById('modal-ml-confidence');
            mlElement.textContent = parseFloat(incident.ml_confidence).toFixed(1) + '%';
            mlElement.className = 'badge ' + (incident.ml_confidence > 80 ? 'badge-danger' : (incident.ml_confidence > 60 ? 'badge-warning' : 'badge-success'));
            
            // Set up create/edit report button based on status
            const modalButton = document.getElementById('modal-create-report');
            const modalReportStatus = incident.reports_status || 'No Report';
            const hasReport = incident.spot_report_id;
            const statusLower = modalReportStatus.toLowerCase();
            
            if (hasReport) {
                if (statusLower === 'final') {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit';
                    modalButton.className = 'btn action-btn-final';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                } else if (statusLower === 'pending' || statusLower === 'pending_review') {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit';
                    modalButton.className = 'btn action-btn-pending';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                } else if (modalReportStatus === 'draft') {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit Draft';
                    modalButton.className = 'btn action-btn-draft';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                } else {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit';
                    modalButton.className = 'btn action-btn-edit';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                }
            } else {
                modalButton.innerHTML = '<i class="fas fa-file-alt"></i> Create';
                modalButton.className = 'btn action-btn-no-report';
                modalButton.disabled = false;
                modalButton.onclick = function() {
                    $('#incidentModal').modal('hide');
                    createSpotReport(incident.id);
                };
            }
            
            // Show modal
            $('#incidentModal').modal('show');
        }
    </script>

    <!-- Incident Details Modal -->
    <div class="modal fade" id="incidentModal" tabindex="-1" aria-labelledby="incidentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.15);">
                <div class="modal-header" style="background-color: #ffffff; border-bottom: 1px solid #e9ecef; border-radius: 12px 12px 0 0; padding: 20px 25px;">
                    <h5 class="modal-title" id="incidentModalLabel" style="color: #2c3e50; font-weight: 600; font-size: 1.25rem;">
                        <i class="fas fa-fire" style="color: #e74c3c; margin-right: 8px;"></i> Fire Incident Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="background: none; border: none; font-size: 1.2rem; color: #6c757d;"></button>
                </div>
                <div class="modal-body" style="background-color: #ffffff; padding: 25px; max-height: 70vh; overflow-y: auto;">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <div class="info-section mb-3">
                                <h6 class="section-title-one mb-2" style="color: #495057; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #dee2e6; padding-bottom: 4px;">
                                    <i class="fas fa-info-circle" style="margin-right: 4px; color: #6c757d;"></i>Incident Information
                                </h6>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Incident ID:</strong>
                                    <span id="modal-incident-id" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Timestamp:</strong>
                                    <span id="modal-timestamp" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Acknowledged At:</strong>
                                    <span id="modal-acknowledged" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Report Status:</strong>
                                    <span id="modal-report-status" class="badge badge-secondary ml-2">-</span>
                                </div>
                            </div>
                            
                            <div class="info-section mb-3">
                                <h6 class="section-title-one mb-2" style="color: #495057; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #dee2e6; padding-bottom: 4px;">
                                    <i class="fas fa-building" style="margin-right: 4px; color: #6c757d;"></i>Building Information
                                </h6>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Building Name:</strong>
                                    <span id="modal-building-name" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Building Type:</strong>
                                    <span id="modal-building-type" class="badge badge-info ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Address:</strong>
                                    <span id="modal-building-address" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Barangay:</strong>
                                    <span id="modal-barangay" class="text-muted ml-2">-</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div class="info-section mb-3">
                                <h6 class="section-title-one mb-2" style="color: #495057; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #dee2e6; padding-bottom: 4px;">
                                    <i class="fas fa-user" style="margin-right: 4px; color: #6c757d;"></i>User Information
                                </h6>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Full Name:</strong>
                                    <span id="modal-user-fullname" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Username:</strong>
                                    <span id="modal-username" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Status:</strong>
                                    <span id="modal-user-status" class="badge badge-secondary ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Contact Number:</strong>
                                    <span id="modal-user-contact" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Email:</strong>
                                    <span id="modal-user-email" class="text-muted ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Device Number:</strong>
                                    <span id="modal-device-number" class="text-muted ml-2">-</span>
                                </div>
                            </div>
                            
                            <div class="info-section mb-3">
                                <h6 class="section-title-one mb-2" style="color: #495057; font-weight: 500; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; border-bottom: 1px solid #dee2e6; padding-bottom: 4px;">
                                    <i class="fas fa-microchip" style="margin-right: 4px; color: #6c757d;"></i>Sensor Data
                                </h6>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Smoke Level:</strong>
                                    <span id="modal-smoke" class="badge badge-success ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Temperature:</strong>
                                    <span id="modal-temperature" class="badge badge-success ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Heat Level:</strong>
                                    <span id="modal-heat" class="badge badge-success ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">Flame Detected:</strong>
                                    <span id="modal-flame" class="badge badge-success ml-2">-</span>
                                </div>
                                
                                <div class="detail-item mb-2" style="padding: 6px 0;">
                                    <strong style="color: #495057; font-size: 0.9rem;">ML Confidence:</strong>
                                    <span id="modal-ml-confidence" class="badge badge-success ml-2">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #ffffff; border-top: 1px solid #e9ecef; border-radius: 0 0 12px 12px; padding: 20px 25px;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 6px; padding: 8px 16px;">
                        <i class="fas fa-times"></i> Close
                    </button>
                    <button type="button" class="btn btn-primary" id="modal-create-report" style="border-radius: 6px; padding: 8px 16px;">
                        <i class="fas fa-file-alt"></i> Create
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php include '../../components/scripts.php'; ?>
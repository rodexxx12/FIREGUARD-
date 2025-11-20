<?php
session_start();
require_once '../../../db/db.php';

// Get barangay information
function getBarangayInfo($barangayId) {
    $conn = getDatabaseConnection();
    $query = "SELECT * FROM barangay WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$barangayId]);
    return $stmt->fetch();
}

// Get reports for a specific barangay with pagination
function getBarangayReports($barangayId, $filters = [], $page = 1, $perPage = 12) {
    $conn = getDatabaseConnection();
    
    $query = "
        SELECT 
            sir.*,
            br.barangay_name,
            br.ir_number as barangay_ir_number,
            CASE 
                WHEN sir.date_occurrence IS NULL OR sir.date_occurrence = '' OR sir.date_occurrence = '########' THEN 'N/A'
                ELSE DATE_FORMAT(sir.date_occurrence, '%Y-%m-%d')
            END as formatted_date_occurrence,
            CASE 
                WHEN sir.time_occurrence IS NULL OR sir.time_occurrence = '' THEN 'N/A'
                ELSE TIME_FORMAT(sir.time_occurrence, '%H:%i:%s')
            END as formatted_time_occurrence
        FROM spot_investigation_reports sir
        JOIN fire_data fd ON sir.fire_data_id = fd.id
        LEFT JOIN buildings b ON fd.building_id = b.id
        JOIN barangay br ON br.id = COALESCE(fd.barangay_id, b.barangay_id)
        WHERE br.id = ?
    ";
    
    $params = [$barangayId];
    
    // Handle period-based filtering
    if (!empty($filters['period'])) {
        if ($filters['period'] === 'monthly') {
            $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        } elseif ($filters['period'] === 'yearly') {
            $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        }
    }
    
    // Handle advanced filters
    if (!empty($filters['date_from'])) {
        $query .= " AND sir.date_occurrence >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND sir.date_occurrence <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND sir.reports_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['investigator'])) {
        $query .= " AND sir.investigator_name LIKE ?";
        $params[] = '%' . $filters['investigator'] . '%';
    }
    
    $query .= " ORDER BY sir.date_occurrence DESC, sir.time_occurrence DESC";
    
    // Add pagination
    $offset = ($page - 1) * $perPage;
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get total count of reports for pagination
function getBarangayReportsCount($barangayId, $filters = []) {
    $conn = getDatabaseConnection();
    
    $query = "
        SELECT COUNT(*) as total
        FROM spot_investigation_reports sir
        JOIN fire_data fd ON sir.fire_data_id = fd.id
        LEFT JOIN buildings b ON fd.building_id = b.id
        JOIN barangay br ON br.id = COALESCE(fd.barangay_id, b.barangay_id)
        WHERE br.id = ?
    ";
    
    $params = [$barangayId];
    
    // Handle period-based filtering
    if (!empty($filters['period'])) {
        if ($filters['period'] === 'monthly') {
            $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        } elseif ($filters['period'] === 'yearly') {
            $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        }
    }
    
    // Handle advanced filters
    if (!empty($filters['date_from'])) {
        $query .= " AND sir.date_occurrence >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND sir.date_occurrence <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND sir.reports_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['investigator'])) {
        $query .= " AND sir.investigator_name LIKE ?";
        $params[] = '%' . $filters['investigator'] . '%';
    }
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result['total'];
}

// Get report statistics for a barangay
function getBarangayStats($barangayId, $filters = []) {
    $conn = getDatabaseConnection();
    
    $query = "
        SELECT 
            COUNT(*) as total_reports,
            COALESCE(SUM(sir.fatalities), 0) as total_fatalities,
            COALESCE(SUM(sir.injured), 0) as total_injured,
            COALESCE(SUM(sir.establishments_affected), 0) as total_affected,
            COALESCE(SUM(sir.estimated_damage), 0) as total_damage,
            COUNT(CASE WHEN sir.reports_status = 'final' THEN 1 END) as completed_reports,
            COUNT(CASE WHEN sir.reports_status = 'draft' THEN 1 END) as draft_reports,
            COUNT(CASE WHEN sir.reports_status = 'pending_review' THEN 1 END) as pending_reports
        FROM spot_investigation_reports sir
        JOIN fire_data fd ON sir.fire_data_id = fd.id
        LEFT JOIN buildings b ON fd.building_id = b.id
        JOIN barangay br ON br.id = COALESCE(fd.barangay_id, b.barangay_id)
        WHERE br.id = ?
    ";
    
    $params = [$barangayId];
    
    // Handle period-based filtering
    if (!empty($filters['period'])) {
        if ($filters['period'] === 'monthly') {
            $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
        } elseif ($filters['period'] === 'yearly') {
            $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
        }
    }
    
    // Handle advanced filters
    if (!empty($filters['date_from'])) {
        $query .= " AND sir.date_occurrence >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND sir.date_occurrence <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND sir.reports_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['investigator'])) {
        $query .= " AND sir.investigator_name LIKE ?";
        $params[] = '%' . $filters['investigator'] . '%';
    }
    
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Generate report data for different periods
function generateReportData($barangayId, $period = 'daily', $filters = []) {
    $conn = getDatabaseConnection();
    
    $query = "
        SELECT 
            sir.*,
            br.barangay_name,
            br.ir_number as barangay_ir_number,
            fd.geo_lat as latitude,
            fd.geo_long as longitude,
            fd.temp as temperature,
            fd.smoke as smoke_level,
            fd.heat as heat_level,
            CASE 
                WHEN sir.date_occurrence IS NULL OR sir.date_occurrence = '' OR sir.date_occurrence = '########' THEN 'N/A'
                ELSE DATE_FORMAT(sir.date_occurrence, '%Y-%m-%d')
            END as formatted_date_occurrence,
            CASE 
                WHEN sir.time_occurrence IS NULL OR sir.time_occurrence = '' THEN 'N/A'
                ELSE TIME_FORMAT(sir.time_occurrence, '%H:%i:%s')
            END as formatted_time_occurrence
        FROM spot_investigation_reports sir
        JOIN fire_data fd ON sir.fire_data_id = fd.id
        LEFT JOIN buildings b ON fd.building_id = b.id
        JOIN barangay br ON br.id = COALESCE(fd.barangay_id, b.barangay_id)
        WHERE br.id = ?
    ";
    
    $params = [$barangayId];
    
    // Add period-specific date filtering
    switch ($period) {
        case 'daily':
            if (!empty($filters['specific_date'])) {
                $query .= " AND sir.date_occurrence = ?";
                $params[] = $filters['specific_date'];
            } else {
                $query .= " AND sir.date_occurrence = CURDATE()";
            }
            break;
        case 'monthly':
            if (!empty($filters['specific_month']) && !empty($filters['specific_year'])) {
                $query .= " AND YEAR(sir.date_occurrence) = ? AND MONTH(sir.date_occurrence) = ?";
                $params[] = $filters['specific_year'];
                $params[] = $filters['specific_month'];
            } else {
                $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
            }
            break;
        case 'yearly':
            if (!empty($filters['specific_year'])) {
                $query .= " AND YEAR(sir.date_occurrence) = ?";
                $params[] = $filters['specific_year'];
            } else {
                $query .= " AND sir.date_occurrence >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
            }
            break;
    }
    
    // Handle advanced filters
    if (!empty($filters['date_from'])) {
        $query .= " AND sir.date_occurrence >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $query .= " AND sir.date_occurrence <= ?";
        $params[] = $filters['date_to'];
    }
    
    if (!empty($filters['status'])) {
        $query .= " AND sir.reports_status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['investigator'])) {
        $query .= " AND sir.investigator_name LIKE ?";
        $params[] = '%' . $filters['investigator'] . '%';
    }
    
    
    $query .= " ORDER BY sir.date_occurrence DESC, sir.time_occurrence DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Generate CSV report
function generateCSVReport($barangayId, $barangayName, $period = 'daily', $filters = []) {
    $reports = generateReportData($barangayId, $period, $filters);
    
    // Debug: Log the first report to see what we're getting
    if (!empty($reports)) {
        error_log("CSV Debug - First report data: " . print_r($reports[0], true));
    }
    
    // Create filename with period and date range info
    $dateRange = '';
    if ($period === 'daily' && !empty($filters['specific_date'])) {
        $dateRange = '_' . $filters['specific_date'];
    } elseif ($period === 'monthly' && !empty($filters['specific_month']) && !empty($filters['specific_year'])) {
        $dateRange = '_' . $filters['specific_year'] . '-' . str_pad($filters['specific_month'], 2, '0', STR_PAD_LEFT);
    } elseif ($period === 'yearly' && !empty($filters['specific_year'])) {
        $dateRange = '_' . $filters['specific_year'];
    }
    
    $filename = $barangayName . '_' . $period . '_report' . $dateRange . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for proper UTF-8 encoding in Excel
    fwrite($output, "\xEF\xBB\xBF");
    
    // CSV headers matching the image format
    fputcsv($output, [
        'IR Number',
        'Subject',
        'Date Occu',
        'Time Occu',
        'Place Occu',
        'Establishm',
        'Investigato',
        'Fatalities',
        'Injured',
        'Establishm',
        'Estimated',
        'Reports St',
        'Temperatu',
        'Humidity',
        'Smoke Lev'
    ]);
    
    // CSV data
    foreach ($reports as $report) {
        fputcsv($output, [
            $report['ir_number'] ?? 'N/A',
            $report['subject'] ?? 'Fire Incide',
            $report['formatted_date_occurrence'] ?? 'N/A',
            $report['formatted_time_occurrence'] ?? 'N/A',
            $report['place_occurrence'] ?? 'N/A',
            $report['establishment_name'] ?? 'N/A',
            $report['investigator_name'] ?? 'Unknown',
            $report['fatalities'] ?? 0,
            $report['injured'] ?? 0,
            $report['establishments_affected'] ?? 0,
            $report['estimated_damage'] ?? 0,
            $report['reports_status'] ?? 'final',
            $report['temperature'] ?? 'N/A',
            'N/A', // Humidity not available in fire_data table
            $report['smoke_level'] ?? 'N/A'
        ]);
    }
    
    // Add summary row if there are reports
    if (!empty($reports)) {
        fputcsv($output, []); // Empty row for separation
        
        // Calculate totals
        $totalFatalities = array_sum(array_column($reports, 'fatalities'));
        $totalInjured = array_sum(array_column($reports, 'injured'));
        $totalAffected = array_sum(array_column($reports, 'establishments_affected'));
        $totalDamage = array_sum(array_column($reports, 'estimated_damage'));
        
        fputcsv($output, [
            'TOTAL',
            '',
            '',
            '',
            '',
            '',
            '',
            $totalFatalities,
            $totalInjured,
            $totalAffected,
            $totalDamage,
            '',
            '',
            '',
            ''
        ]);
    }
    
    fclose($output);
    exit;
}


// Helper function to build pagination URLs
function buildPaginationUrl($barangayId, $barangayName, $filters, $page, $perPage) {
    $params = [
        'barangay_id' => $barangayId,
        'name' => urlencode($barangayName),
        'page' => $page,
        'per_page' => $perPage
    ];
    
    // Add filters to URL
    foreach ($filters as $key => $value) {
        if (!empty($value)) {
            $params[$key] = urlencode($value);
        }
    }
    
    return 'reports.php?' . http_build_query($params);
}

$barangayId = isset($_GET['barangay_id']) ? (int)$_GET['barangay_id'] : 0;
$barangayName = isset($_GET['name']) ? $_GET['name'] : '';

// Handle report generation requests
if (isset($_GET['generate']) && isset($_GET['format']) && isset($_GET['period'])) {
    $generate = $_GET['generate'];
    $format = $_GET['format'];
    $period = $_GET['period'];
    
    if ($barangayId > 0 && $format === 'csv' && in_array($period, ['daily', 'monthly', 'yearly'])) {
        $barangay = getBarangayInfo($barangayId);
        if ($barangay) {
            // Collect filters for report generation
            $reportFilters = [
                'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : null,
                'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : null,
                'status' => isset($_GET['status']) ? $_GET['status'] : null,
                'investigator' => isset($_GET['investigator']) ? $_GET['investigator'] : null,
                'specific_date' => isset($_GET['specific_date']) ? $_GET['specific_date'] : null,
                'specific_month' => isset($_GET['specific_month']) ? $_GET['specific_month'] : null,
                'specific_year' => isset($_GET['specific_year']) ? $_GET['specific_year'] : null
            ];
            
            // Remove empty values
            $reportFilters = array_filter($reportFilters, function($value) {
                return !empty($value);
            });
            
            if ($format === 'csv') {
                generateCSVReport($barangayId, $barangay['barangay_name'], $period, $reportFilters);
            }
        }
    }
}

// Collect all filters
$filters = [
    'period' => isset($_GET['period']) ? $_GET['period'] : null,
    'date_from' => isset($_GET['date_from']) ? $_GET['date_from'] : null,
    'date_to' => isset($_GET['date_to']) ? $_GET['date_to'] : null,
    'status' => isset($_GET['status']) ? $_GET['status'] : null,
    'investigator' => isset($_GET['investigator']) ? $_GET['investigator'] : null
];

// Remove empty values
$filters = array_filter($filters, function($value) {
    return !empty($value);
});

// Pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? max(1, min(50, (int)$_GET['per_page'])) : 10; // Default to 10 per page, limit to max 50 per page

if ($barangayId <= 0) {
    header('Location: index.php');
    exit;
}

$barangay = getBarangayInfo($barangayId);
if (!$barangay) {
    header('Location: index.php');
    exit;
}

// Get reports with pagination
$reports = getBarangayReports($barangayId, $filters, $page, $perPage);
$totalReports = getBarangayReportsCount($barangayId, $filters);
$totalPages = ceil($totalReports / $perPage);
$stats = getBarangayStats($barangayId, $filters);
?>

<?php include('../../components/header.php'); ?>
    <style>
        .report-card { 
            transition: all 0.3s ease; 
            cursor: pointer; 
            background: white;
            border-radius: 0;
        }
        .report-card:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1) !important;
            border-color: #007bff !important;
        }
        .report-card-disabled { 
            opacity: 0.6;
            cursor: not-allowed;
        }
        .report-card-disabled:hover { 
            transform: none; 
            box-shadow: 0 1px 2px rgba(0,0,0,0.05) !important;
            border-color: #e9ecef !important;
        }
        .compact-stats { font-size: 0.75rem; }
        .period-btn { font-size: 0.8rem; padding: 0.25rem 0.5rem; }
        
        /* Modern Export Section Styles */
        .bg-gradient-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
        }
        
        .export-period-card {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 0;
            padding: 0.75rem;
            position: relative;
            overflow: hidden;
        }
        
        .period-header {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .period-icon {
            width: 35px;
            height: 35px;
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            font-size: 1rem;
            color: white;
        }
        
        .daily-icon {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .monthly-icon {
            background: linear-gradient(135deg, #fd7e14, #ffc107);
        }
        
        .yearly-icon {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
        }
        
        .btn-modern {
            border-radius: 0;
            padding: 0.4rem 0.6rem;
            font-weight: 500;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .btn-modern.btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }
        
        .bg-light-info {
            background-color: rgba(13, 202, 240, 0.1) !important;
            border-left: 4px solid #0dcaf0;
        }
        
        .export-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Loading animation for export buttons */
        .export-btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        .export-btn.loading .fas {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Compact Filter Styles */
        .form-control-sm:focus, .form-select-sm:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.2);
            border-radius: 0;
        }
        
        .form-control-sm, .form-select-sm {
            transition: all 0.2s ease;
            height: auto;
            font-size: 0.75rem;
        }
        
        .form-control-sm:hover, .form-select-sm:hover {
            border-color: #007bff;
        }
        
        .btn-sm:hover {
            transform: translateY(-0.25px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        /* Pagination Styles */
        .pagination .page-link {
            transition: all 0.2s ease;
            border-radius: 0 !important;
        }
        
        .pagination .page-link:hover {
            background-color: #e9ecef;
            border-color: #007bff;
            transform: translateY(-1px);
        }
        
        .pagination .page-item.active .page-link {
            background-color: #007bff;
            border-color: #007bff;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
        }
        
        .pagination .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination .page-item.disabled .page-link:hover {
            transform: none;
            background-color: transparent;
        }
        
        /* Per page selector styles */
        #perPageSelect {
            transition: all 0.2s ease;
        }
        
        #perPageSelect:hover {
            border-color: #007bff;
            box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.2);
        }
        
        #perPageSelect:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.2);
        }
        
        /* Date Filter Section Styles */
        .date-filter-section {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0;
            padding: 0.5rem;
        }
        
        .date-filter-section .form-control-sm,
        .date-filter-section .form-select-sm {
            border: 1px solid #dee2e6;
            transition: all 0.2s ease;
        }
        
        .date-filter-section .form-control-sm:focus,
        .date-filter-section .form-select-sm:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.1rem rgba(0, 123, 255, 0.2);
        }
        
        .date-filter-section .btn-sm {
            transition: all 0.2s ease;
        }
        
        .date-filter-section .btn-sm:hover {
            transform: translateY(-0.25px);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        
        /* BFP SIR Form Styles */
        .bfp-sir-form {
            font-family: Arial, sans-serif;
            background: white;
            color: black;
            padding: 20px;
        }

        .bfp-header {
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .logo-section {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 15px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo-item {
            text-align: center;
        }

        .logo-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            font-weight: bold;
            margin: 0 auto 5px;
        }

        .memorandum-section {
            margin-bottom: 20px;
        }

        .incident-details-section {
            margin-bottom: 20px;
        }

        .investigation-section {
            margin-bottom: 20px;
        }

        .disposition-section {
            margin-bottom: 20px;
        }

        .signature-section {
            margin-bottom: 20px;
            text-align: right;
        }

        .bfp-footer {
            font-size: 8px;
            color: black;
            border-top: 1px solid #ccc;
            padding-top: 5px;
            text-align: left;
        }
        
        .fire-department-form .form-header {
            text-align: center;
            border-bottom: 2px solid #8B4513;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .fire-department-form .form-title {
            font-size: 18px;
            font-weight: bold;
            color: black;
            margin: 0 0 8px 0;
        }
        
        .fire-department-form .form-subtitle {
            font-size: 10px;
            color: black;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .fire-department-form .form-checkbox {
            margin-top: 8px;
        }
        
        .fire-department-form .form-checkbox input {
            margin-right: 5px;
        }
        
        .fire-department-form .form-checkbox label {
            font-size: 10px;
        }
        
        .fire-department-form .form-label {
            font-weight: bold;
            font-size: 9px;
            color: black;
            margin-bottom: 2px;
        }
        
        .fire-department-form .form-field-line {
            border-bottom: 1px solid black;
            padding: 2px 0;
            font-size: 9px;
            color: black;
            min-height: 14px;
        }
        
        .fire-department-form .form-field-box {
            border: 1px solid black;
            padding: 2px;
            text-align: center;
            font-size: 9px;
            background: #f0f0f0;
            min-height: 14px;
        }
        
        .fire-department-form .section-title-new {
            font-size: 12px;
            font-weight: bold;
            color: black;
            text-align: center;
            margin-bottom: 8px;
        }
        
        .fire-department-form .section-subtitle {
            font-size: 9px;
            color: black;
            text-align: center;
            margin-bottom: 12px;
        }
        
        .fire-department-form .remarks-content {
            border: 1px solid black;
            min-height: 120px;
            padding: 8px;
            font-size: 9px;
        }
        
        .fire-department-form .apparatus-table {
            font-size: 8px;
            border: 1px solid black;
        }
        
        .fire-department-form .apparatus-table th,
        .fire-department-form .apparatus-table td {
            border: 1px solid black;
            padding: 2px;
            text-align: center;
            height: 20px;
        }
        
        .fire-department-form .apparatus-table th {
            background: #f0f0f0;
            font-weight: bold;
        }
        
        .fire-department-form .checkbox-item {
            display: flex;
            align-items: center;
            margin-right: 15px;
        }
        
        .fire-department-form .checkbox-item input {
            margin-right: 3px;
        }
        
        .fire-department-form .checkbox-item label {
            font-size: 9px;
        }
        
        .fire-department-form .form-footer {
            font-size: 8px;
            color: black;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }
        
        /* Modern Modal Styles */
        .modern-modal {
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .modern-modal-header {
            background: #ffffff;
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 1.25rem;
            border-radius: 8px 8px 0 0;
        }
        
        .modern-modal-header .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .modern-modal-body {
            background: #ffffff;
            padding: 1.25rem;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .modern-modal-footer {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 0.75rem 1.25rem;
            border-radius: 0 0 8px 8px;
        }
        
        .modern-modal-footer .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 0.5rem 1rem;
        }
        
        .modern-modal-footer .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        
        .modern-modal-footer .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff;
        }
        
        .modern-modal-footer .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .modern-modal-footer .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .export-period-card {
                padding: 0.5rem;
            }
            
            .period-icon {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
            
            .btn-modern {
                padding: 0.3rem 0.5rem;
                font-size: 0.75rem;
            }
            
            /* Date filter mobile adjustments */
            .date-filter-section {
                padding: 0.3rem;
            }
            
            .date-filter-section .d-flex {
                flex-direction: column;
                gap: 0.25rem !important;
            }
            
            .date-filter-section .form-control-sm,
            .date-filter-section .form-select-sm {
                width: 100% !important;
                font-size: 0.7rem !important;
                padding: 0.2rem 0.3rem !important;
            }
            
            .date-filter-section .btn-sm {
                width: 100% !important;
                font-size: 0.7rem !important;
                padding: 0.2rem 0.4rem !important;
            }
            
            /* Mobile filter adjustments */
            .row.g-2 {
                padding: 0.5rem !important;
            }
            
            .d-flex.gap-1 {
                flex-direction: column;
                gap: 0.25rem !important;
            }
            
            .ms-auto {
                margin-left: 0 !important;
                margin-top: 0.25rem;
            }
            
            .form-control-sm, .form-select-sm {
                padding: 0.2rem 0.3rem !important;
                font-size: 0.7rem !important;
            }
            
            .btn-sm {
                padding: 0.2rem 0.4rem !important;
                font-size: 0.7rem !important;
            }
            
            /* Mobile responsive for right-side filters */
            .card-header .d-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 1rem !important;
            }
            
            .card-header .d-flex .d-flex {
                flex-wrap: wrap !important;
                gap: 0.5rem !important;
            }
            
            .card-header .form-control-sm,
            .card-header .form-select-sm {
                width: 80px !important;
                min-width: 80px !important;
            }
            
            /* Mobile pagination adjustments */
            .pagination {
                font-size: 0.8rem;
            }
            
            .pagination .page-link {
                padding: 0.3rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 0.5rem !important;
            }
            
            .d-flex.align-items-center.gap-2 {
                align-self: flex-end;
            }
            
            /* Mobile modal adjustments */
            .modern-modal-header {
                padding: 0.75rem 1rem;
            }
            
            .modern-modal-header .modal-title {
                font-size: 1rem;
            }
            
            .modern-modal-body {
                padding: 1rem;
                max-height: 60vh;
            }
            
            .modern-modal-footer {
                padding: 0.5rem 1rem;
            }
            
            .modern-modal-footer .btn {
                padding: 0.4rem 0.8rem;
                font-size: 0.9rem;
            }
        }
    </style>
<?php include('../../components/header.php'); ?>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
    <div class="main-card">
    <div class="container-fluid py-2">
        <!-- Single Card Container -->
        <div class="row">
            <div class="col-12">
                <div class="card" style="background: white; border-radius: 0; border: 1px solid #e9ecef;">
                    <!-- Card Header with Title and Back Button -->
                    <div class="card-header py-2" style="background: white; border-bottom: 2px solid #f8f9fa; border-radius: 0;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="text-dark mb-0" style="font-size: 1.1rem;">
                                <i class="fas fa-file-alt me-1"></i><?php echo htmlspecialchars($barangay['barangay_name']); ?> Reports
                            </h5>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>Back
                            </a>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="card-body py-2" style="background: white;">
                        <!-- Export Period Cards -->
                        <div class="row mb-3 g-2">
                            <?php 
                            // Build filter query string for export links
                            $filterParams = '';
                            foreach ($filters as $key => $value) {
                                if (!empty($value)) {
                                    $filterParams .= '&' . $key . '=' . urlencode($value);
                                }
                            }
                            ?>
                            
                            <!-- Daily Reports -->
                            <div class="col-lg-4 col-md-6">
                                <div class="export-period-card h-100">
                                    <div class="period-header">
                                        <div class="period-icon daily-icon">
                                            <i class="fas fa-calendar-day"></i>
                                        </div>
                                        <div class="period-info">
                                            <h6 class="mb-1 fw-bold" style="font-size: 0.9rem;">Daily Reports</h6>
                                            <small class="text-dark" style="font-size: 0.75rem;">Today's investigation reports</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Date Filter for Daily Reports -->
                                    <div class="date-filter-section mt-2 mb-2">
                                        <form id="dailyFilterForm" class="d-flex flex-column gap-2">
                                            <div class="d-flex gap-1">
                                                <input type="date" class="form-control form-control-sm" id="dailyDate" 
                                                       value="<?php echo date('Y-m-d'); ?>" 
                                                       style="border-radius: 0; font-size: 0.75rem; padding: 0.3rem 0.4rem;">
                                                <button type="button" class="btn btn-primary btn-sm" id="applyDailyFilter" 
                                                        style="border-radius: 0; padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="export-actions mt-2">
                                        <div class="d-grid gap-2">
                                            <a href="reports.php?barangay_id=<?php echo $barangayId; ?>&name=<?php echo urlencode($barangay['barangay_name']); ?>&generate=1&format=csv&period=daily<?php echo $filterParams; ?>" 
                                               class="btn btn-success btn-modern export-btn" data-format="csv" data-period="daily" id="dailyCsvBtn">
                                                <i class="fas fa-file-csv me-2"></i>
                                                <span>Download CSV</span>
                                                <i class="fas fa-download ms-auto"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Monthly Reports -->
                            <div class="col-lg-4 col-md-6">
                                <div class="export-period-card h-100">
                                    <div class="period-header">
                                        <div class="period-icon monthly-icon">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                        <div class="period-info">
                                            <h6 class="mb-1 fw-bold" style="font-size: 0.9rem;">Monthly Reports</h6>
                                            <small class="text-dark" style="font-size: 0.75rem;">Last 30 days of reports</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Date Filter for Monthly Reports -->
                                    <div class="date-filter-section mt-2 mb-2">
                                        <form id="monthlyFilterForm" class="d-flex flex-column gap-2">
                                            <div class="d-flex gap-1">
                                                <select class="form-select form-select-sm" id="monthlyMonth" 
                                                        style="border-radius: 0; font-size: 0.75rem; padding: 0.3rem 0.4rem;">
                                                    <option value="">Select Month</option>
                                                    <option value="01" <?php echo date('m') == '01' ? 'selected' : ''; ?>>January</option>
                                                    <option value="02" <?php echo date('m') == '02' ? 'selected' : ''; ?>>February</option>
                                                    <option value="03" <?php echo date('m') == '03' ? 'selected' : ''; ?>>March</option>
                                                    <option value="04" <?php echo date('m') == '04' ? 'selected' : ''; ?>>April</option>
                                                    <option value="05" <?php echo date('m') == '05' ? 'selected' : ''; ?>>May</option>
                                                    <option value="06" <?php echo date('m') == '06' ? 'selected' : ''; ?>>June</option>
                                                    <option value="07" <?php echo date('m') == '07' ? 'selected' : ''; ?>>July</option>
                                                    <option value="08" <?php echo date('m') == '08' ? 'selected' : ''; ?>>August</option>
                                                    <option value="09" <?php echo date('m') == '09' ? 'selected' : ''; ?>>September</option>
                                                    <option value="10" <?php echo date('m') == '10' ? 'selected' : ''; ?>>October</option>
                                                    <option value="11" <?php echo date('m') == '11' ? 'selected' : ''; ?>>November</option>
                                                    <option value="12" <?php echo date('m') == '12' ? 'selected' : ''; ?>>December</option>
                                                </select>
                                                <select class="form-select form-select-sm" id="monthlyYear" 
                                                        style="border-radius: 0; font-size: 0.75rem; padding: 0.3rem 0.4rem;">
                                                    <option value="">Select Year</option>
                                                    <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                                    <option value="<?php echo $year; ?>" <?php echo date('Y') == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <button type="button" class="btn btn-primary btn-sm" id="applyMonthlyFilter" 
                                                        style="border-radius: 0; padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="export-actions mt-2">
                                        <div class="d-grid gap-2">
                                            <a href="reports.php?barangay_id=<?php echo $barangayId; ?>&name=<?php echo urlencode($barangay['barangay_name']); ?>&generate=1&format=csv&period=monthly<?php echo $filterParams; ?>" 
                                               class="btn btn-success btn-modern export-btn" data-format="csv" data-period="monthly" id="monthlyCsvBtn">
                                                <i class="fas fa-file-csv me-2"></i>
                                                <span>Download CSV</span>
                                                <i class="fas fa-download ms-auto"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Yearly Reports -->
                            <div class="col-lg-4 col-md-6">
                                <div class="export-period-card h-100">
                                    <div class="period-header">
                                        <div class="period-icon yearly-icon">
                                            <i class="fas fa-calendar"></i>
                                        </div>
                                        <div class="period-info">
                                            <h6 class="mb-1 fw-bold" style="font-size: 0.9rem;">Yearly Reports</h6>
                                            <small class="text-dark" style="font-size: 0.75rem;">Last 12 months of reports</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Date Filter for Yearly Reports -->
                                    <div class="date-filter-section mt-2 mb-2">
                                        <form id="yearlyFilterForm" class="d-flex flex-column gap-2">
                                            <div class="d-flex gap-1">
                                                <select class="form-select form-select-sm" id="yearlyYear" 
                                                        style="border-radius: 0; font-size: 0.75rem; padding: 0.3rem 0.4rem;">
                                                    <option value="">Select Year</option>
                                                    <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                                                    <option value="<?php echo $year; ?>" <?php echo date('Y') == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <button type="button" class="btn btn-primary btn-sm" id="applyYearlyFilter" 
                                                        style="border-radius: 0; padding: 0.3rem 0.6rem; font-size: 0.75rem;">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                    
                                    <div class="export-actions mt-2">
                                        <div class="d-grid gap-2">
                                            <a href="reports.php?barangay_id=<?php echo $barangayId; ?>&name=<?php echo urlencode($barangay['barangay_name']); ?>&generate=1&format=csv&period=yearly<?php echo $filterParams; ?>" 
                                               class="btn btn-success btn-modern export-btn" data-format="csv" data-period="yearly" id="yearlyCsvBtn">
                                                <i class="fas fa-file-csv me-2"></i>
                                                <span>Download CSV</span>
                                                <i class="fas fa-download ms-auto"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Advanced Filters -->
                        <div class="row mb-2" style="display: none;">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header py-1">
                                        <h6 class="mb-0 text-dark" style="font-size: 0.9rem;">
                                            <i class="fas fa-filter me-1"></i>Advanced Filters
                                        </h6>
                                    </div>
                                    <div class="card-body py-1">
                                            <form method="GET" id="filterForm">
                                                <input type="hidden" name="barangay_id" value="<?php echo $barangayId; ?>">
                                                <input type="hidden" name="name" value="<?php echo htmlspecialchars($barangay['barangay_name']); ?>">
                                                
                                                <div class="row g-2">
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-dark" style="font-size: 0.75rem;">Date From</label>
                                                        <input type="date" class="form-control form-control-sm" name="date_from" 
                                                               value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-dark" style="font-size: 0.75rem;">Date To</label>
                                                        <input type="date" class="form-control form-control-sm" name="date_to" 
                                                               value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-dark" style="font-size: 0.75rem;">Status</label>
                                                        <select class="form-select form-select-sm" name="status">
                                                        <option value="">All Status</option>
                                                        <option value="draft" <?php echo ($filters['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                        <option value="pending_review" <?php echo ($filters['status'] ?? '') === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                                                        <option value="final" <?php echo ($filters['status'] ?? '') === 'final' ? 'selected' : ''; ?>>Final</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <label class="form-label small text-dark" style="font-size: 0.75rem;">Investigator</label>
                                                        <input type="text" class="form-control form-control-sm" name="investigator" 
                                                               placeholder="Search investigator..." 
                                                               value="<?php echo htmlspecialchars($filters['investigator'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                
                                                <div class="row g-2 mt-2">
                                                    <div class="col-12">
                                                        <div class="d-flex gap-2">
                                                            <button type="button" class="btn btn-primary btn-sm" id="applyFiltersBtn" style="display: none;">
                                                                <i class="fas fa-search me-1"></i>Apply Filters
                                                            </button>
                                                            <a href="reports.php?barangay_id=<?php echo $barangayId; ?>&name=<?php echo urlencode($barangay['barangay_name']); ?>" 
                                                               class="btn btn-outline-secondary btn-sm">
                                                                <i class="fas fa-times me-1"></i>Clear Filters
                                                            </a>
                                                            <div class="ms-auto">
                                                                <small class="text-dark">
                                                                    <i class="fas fa-info-circle me-1"></i>Filters apply automatically
                                                                </small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Fire Incident Reports Section -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card" style="background: white; border-radius: 0; border: 1px solid #e9ecef; min-height: 600px; padding: 20px;">
                                    <div class="card-header py-3" style="background: white; border-bottom: 2px solid #f8f9fa; border-radius: 0; padding: 20px;">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="d-flex align-items-center">
                                                <h6 class="mb-0 text-dark fw-bold me-2" style="font-size: 0.95rem;">
                                                    <i class="fas fa-folder-open me-1"></i>Fire Incident Reports
                                                </h6>
                                                <span class="badge bg-dark px-2 py-1" style="border-radius: 0; font-size: 0.75rem;"><?php echo count($reports); ?></span>
                                            </div>
                                            
                                            <!-- Compact Right-Side Filters -->
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="d-flex align-items-center gap-1">
                                                    <label class="form-label mb-0 text-dark" style="font-size: 0.75rem; font-weight: 500;">From:</label>
                                                    <input type="date" class="form-control form-control-sm" name="date_from" id="dateFrom" 
                                                           value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>"
                                                           style="border-radius: 0; border: 1px solid #dee2e6; padding: 0.3rem 0.4rem; font-size: 0.8rem; width: 120px;">
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <label class="form-label mb-0 text-dark" style="font-size: 0.75rem; font-weight: 500;">To:</label>
                                                    <input type="date" class="form-control form-control-sm" name="date_to" id="dateTo" 
                                                           value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>"
                                                           style="border-radius: 0; border: 1px solid #dee2e6; padding: 0.3rem 0.4rem; font-size: 0.8rem; width: 120px;">
                                                </div>
                                                <div class="d-flex align-items-center gap-1">
                                                    <label class="form-label mb-0 text-dark" style="font-size: 0.75rem; font-weight: 500;">Status:</label>
                                                    <select class="form-select form-select-sm" name="status" id="statusFilter" 
                                                            style="border-radius: 0; border: 1px solid #dee2e6; padding: 0.3rem 0.4rem; font-size: 0.8rem; width: 100px;">
                                                        <option value="">All</option>
                                                        <option value="draft" <?php echo ($filters['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                                        <option value="pending_review" <?php echo ($filters['status'] ?? '') === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                                                        <option value="final" <?php echo ($filters['status'] ?? '') === 'final' ? 'selected' : ''; ?>>Final</option>
                                                    </select>
                                                </div>
                                                <div class="d-flex gap-1">
                                                    <button type="button" class="btn btn-primary btn-sm" id="applyQuickFilters" 
                                                            style="border-radius: 0; padding: 0.3rem 0.6rem; font-weight: 500; font-size: 0.75rem;">
                                                        <i class="fas fa-search"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearQuickFilters"
                                                            style="border-radius: 0; padding: 0.3rem 0.6rem; font-weight: 500; font-size: 0.75rem;">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body py-4" style="background: white; padding: 30px;">
                                        <?php if (empty($reports)): ?>
                                        <div class="text-center py-3">
                                            <i class="fas fa-folder-open text-dark" style="font-size: 2rem;"></i>
                                            <h6 class="text-dark mt-2" style="font-size: 1rem;">No Reports Found</h6>
                                            <p class="text-dark small" style="font-size: 0.8rem;">No investigation reports found for this barangay<?php echo !empty($filters['period']) ? ' in the selected period' : ''; ?>.</p>
                                        </div>
                                        <?php else: ?>
                                        <div class="row g-2">
                                            <?php foreach ($reports as $report): ?>
                                            <div class="col-lg-4 col-md-6 col-sm-6">
                                                <div class="card report-card h-100 <?php echo $report['reports_status'] !== 'final' ? 'report-card-disabled' : ''; ?>" onclick="viewReport(<?php echo $report['id']; ?>, '<?php echo $report['reports_status']; ?>')" style="background: white; border-radius: 0; border: 1px solid #e9ecef; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                                    <div class="card-body py-2">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <i class="fas fa-folder text-warning"></i>
                                                            <span class="badge <?php 
                                                                switch($report['reports_status']) {
                                                                    case 'draft': echo 'bg-warning'; break;
                                                                    case 'final': echo 'bg-success'; break;
                                                                    case 'pending_review': echo 'bg-info'; break;
                                                                    default: echo 'bg-secondary';
                                                                }
                                                            ?>" style="border-radius: 0; font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                                                                <?php 
                                                                    switch($report['reports_status']) {
                                                                        case 'pending_review': echo 'Pending Review'; break;
                                                                        default: echo ucfirst($report['reports_status']);
                                                                    }
                                                                ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <h6 class="card-title text-dark mb-2 fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($report['subject']); ?></h6>
                                                        
                                                        <div class="mb-2">
                                                            <small class="text-dark fw-medium" style="font-size: 0.75rem;">IR #<?php echo htmlspecialchars($report['ir_number']); ?></small>
                                                        </div>
                                                        
                                                        <div class="compact-stats text-dark mb-2">
                                                            <div class="mb-1"><i class="fas fa-calendar me-1 text-dark"></i><?php echo date('M d, Y', strtotime($report['formatted_date_occurrence'] ?? $report['date_occurrence'])); ?></div>
                                                            <div class="mb-1"><i class="fas fa-clock me-1 text-dark"></i><?php echo date('g:i A', strtotime($report['formatted_time_occurrence'] ?? $report['time_occurrence'])); ?></div>
                                                        </div>
                                                        
                                                        <div class="compact-stats text-dark mb-2">
                                                            <div class="mb-1"><strong>Establishment:</strong> <?php echo htmlspecialchars($report['establishment_name']); ?></div>
                                                            <div class="mb-1"><strong>Investigator:</strong> <?php echo htmlspecialchars($report['investigator_name']); ?></div>
                                                        </div>
                                                        
                                                        <div class="row g-2">
                                                            <div class="col-3">
                                                                <div class="text-center p-1" style="background: #f8f9fa; border-radius: 0; border: 1px solid #e9ecef;">
                                                                    <div class="small text-dark fw-medium" style="font-size: 0.7rem;">Fatal</div>
                                                                    <div class="fw-bold text-dark" style="font-size: 0.8rem;"><?php echo $report['fatalities']; ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="text-center p-1" style="background: #f8f9fa; border-radius: 0; border: 1px solid #e9ecef;">
                                                                    <div class="small text-dark fw-medium" style="font-size: 0.7rem;">Injured</div>
                                                                    <div class="fw-bold text-dark" style="font-size: 0.8rem;"><?php echo $report['injured']; ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="text-center p-1" style="background: #f8f9fa; border-radius: 0; border: 1px solid #e9ecef;">
                                                                    <div class="small text-dark fw-medium" style="font-size: 0.7rem;">Affected</div>
                                                                    <div class="fw-bold text-dark" style="font-size: 0.8rem;"><?php echo $report['establishments_affected']; ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="col-3">
                                                                <div class="text-center p-1" style="background: #f8f9fa; border-radius: 0; border: 1px solid #e9ecef;">
                                                                    <div class="small text-dark fw-medium" style="font-size: 0.7rem;">Damage</div>
                                                                    <div class="fw-bold text-dark" style="font-size: 0.8rem;">$<?php echo number_format($report['estimated_damage'], 0); ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Pagination Controls -->
                                        <?php if ($totalPages > 1): ?>
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <!-- Results Info -->
                                                    <div class="text-dark small">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Showing <?php echo (($page - 1) * $perPage) + 1; ?> to <?php echo min($page * $perPage, $totalReports); ?> of <?php echo $totalReports; ?> reports
                                                    </div>
                                                    
                                                    <!-- Per Page Selector -->
                                                    <div class="d-flex align-items-center gap-2">
                                                        <label class="form-label mb-0 text-dark small">Per page:</label>
                                                        <select class="form-select form-select-sm" id="perPageSelect" style="width: 70px; border-radius: 0;">
                                                            <option value="5" <?php echo $perPage == 5 ? 'selected' : ''; ?>>5</option>
                                                            <option value="10" <?php echo $perPage == 10 ? 'selected' : ''; ?>>10</option>
                                                            <option value="20" <?php echo $perPage == 20 ? 'selected' : ''; ?>>20</option>
                                                            <option value="30" <?php echo $perPage == 30 ? 'selected' : ''; ?>>30</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <!-- Pagination Navigation -->
                                                <nav aria-label="Reports pagination" class="mt-2">
                                                    <ul class="pagination pagination-sm justify-content-center mb-0" style="border-radius: 0;">
                                                        <!-- Previous Button -->
                                                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo buildPaginationUrl($barangayId, $barangay['barangay_name'], $filters, $page - 1, $perPage); ?>" 
                                                               style="border-radius: 0; border: 1px solid #dee2e6; color: #007bff;">
                                                                <i class="fas fa-chevron-left"></i>
                                                            </a>
                                                        </li>
                                                        
                                                        <?php
                                                        // Calculate page range to show
                                                        $startPage = max(1, $page - 2);
                                                        $endPage = min($totalPages, $page + 2);
                                                        
                                                        // Show first page if not in range
                                                        if ($startPage > 1): ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="<?php echo buildPaginationUrl($barangayId, $barangay['barangay_name'], $filters, 1, $perPage); ?>" 
                                                               style="border-radius: 0; border: 1px solid #dee2e6; color: #007bff;">1</a>
                                                        </li>
                                                        <?php if ($startPage > 2): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link" style="border-radius: 0; border: 1px solid #dee2e6;">...</span>
                                                        </li>
                                                        <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Page Numbers -->
                                                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                            <a class="page-link" href="<?php echo buildPaginationUrl($barangayId, $barangay['barangay_name'], $filters, $i, $perPage); ?>" 
                                                               style="border-radius: 0; border: 1px solid #dee2e6; color: <?php echo $i == $page ? 'white' : '#007bff'; ?>; background-color: <?php echo $i == $page ? '#007bff' : 'white'; ?>;">
                                                                <?php echo $i; ?>
                                                            </a>
                                                        </li>
                                                        <?php endfor; ?>
                                                        
                                                        <!-- Show last page if not in range -->
                                                        <?php if ($endPage < $totalPages): ?>
                                                        <?php if ($endPage < $totalPages - 1): ?>
                                                        <li class="page-item disabled">
                                                            <span class="page-link" style="border-radius: 0; border: 1px solid #dee2e6;">...</span>
                                                        </li>
                                                        <?php endif; ?>
                                                        <li class="page-item">
                                                            <a class="page-link" href="<?php echo buildPaginationUrl($barangayId, $barangay['barangay_name'], $filters, $totalPages, $perPage); ?>" 
                                                               style="border-radius: 0; border: 1px solid #dee2e6; color: #007bff;"><?php echo $totalPages; ?></a>
                                                        </li>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Next Button -->
                                                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                                            <a class="page-link" href="<?php echo buildPaginationUrl($barangayId, $barangay['barangay_name'], $filters, $page + 1, $perPage); ?>" 
                                                               style="border-radius: 0; border: 1px solid #dee2e6; color: #007bff;">
                                                                <i class="fas fa-chevron-right"></i>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </nav>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>

    
    <!-- Fire Department Incident Report Modal -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-labelledby="reportModalLabel">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-modal-header">
                    <h6 class="modal-title" id="reportModalLabel">
                        <i class="fas fa-file-alt me-2"></i>Fire Department Incident Report
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="closeModal()"></button>
                </div>
                <div class="modal-body modern-modal-body" id="reportModalBody">
                    <!-- Fire Department Incident Report Form will be loaded here -->
                </div>
                <div class="modal-footer modern-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" onclick="closeModal()">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" id="printReportBtn">
                        <i class="fas fa-print me-1"></i>Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php include '../../components/scripts.php'; ?>
    <script>
        // Define viewReport function globally so it can be accessed by onclick attributes
        function handleViewReport(reportId, reportStatus) {
            // Check if the report status is 'final' before showing modal
            if (reportStatus !== 'final') {
                Swal.fire({
                    title: 'Report Not Available',
                    text: 'This report is not yet finalized and cannot be viewed.',
                    icon: 'info',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'swal2-modern'
                    },
                    didOpen: () => {
                        const popup = document.querySelector('.swal2-popup');
                        if (popup) {
                            popup.style.borderRadius = '12px';
                            popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                        }
                    }
                });
                return;
            }
            
            // Get modal element
            const modalElement = document.getElementById('reportModal');
            const modalBody = document.getElementById('reportModalBody');
            
            // Show loading state
            modalBody.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">
                        <small class="text-dark">Loading report details...</small>
                    </div>
                </div>
            `;
            
            // Initialize and show the modal properly
            let reportModal;
            if (modalElement._modal) {
                // Modal already exists, just show it
                reportModal = modalElement._modal;
                reportModal.show();
            } else {
                // Create new modal instance
                reportModal = new bootstrap.Modal(modalElement, {
                    backdrop: true,
                    keyboard: true,
                    focus: true
                });
                modalElement._modal = reportModal; // Store reference
                reportModal.show();
            }
            
            // Simulate loading report data (replace with actual AJAX call)
            setTimeout(() => {
                loadReportDetails(reportId);
            }, 1000);
        }
        window.viewReport = handleViewReport;
        
        function loadReportDetails(reportId) {
            // Fetch actual report data from the server
            fetch(`get_report_details.php?id=${reportId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const modalBody = document.getElementById('reportModalBody');
                        window.currentReportData = data.report;
                        modalBody.innerHTML = generateReportHTML(window.currentReportData);
                    } else {
                        const modalBody = document.getElementById('reportModalBody');
                        modalBody.innerHTML = `
                            <div class="text-center py-4">
                                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                                <h6 class="text-dark mt-2">Error Loading Report</h6>
                                <p class="text-dark small">${data.message || 'Unable to load report details'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const modalBody = document.getElementById('reportModalBody');
                    modalBody.innerHTML = `
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-triangle text-danger" style="font-size: 2rem;"></i>
                            <h6 class="text-dark mt-2">Connection Error</h6>
                            <p class="text-dark small">Unable to connect to server. Please try again.</p>
                        </div>
                    `;
                });
        }
        
        function generateReportHTML(report) {
            const statusText = getStatusTextForPrint(report.reports_status);
            const formatBlock = (text, fallback) => {
                const value = text && text.trim() ? text : fallback;
                return value.replace(/\n/g, '<br>');
            };
            
            const memoRows = [
                { label: 'FOR', value: report.report_for || 'Fire Chief' },
                { label: 'SUBJECT', value: report.subject || 'Fire Incident Report' },
                { label: 'DATE', value: formatDateForPrint(report.date_completed) }
            ];
            
            const dtpoDate = report.date_occurrence ? formatDateForPrint(report.date_occurrence) : 'N/A';
            const dtpoTime = report.time_occurrence ? formatTimeForPrint(report.time_occurrence) : '';
            const detailRows = [
                { label: 'DTPO', value: `${dtpoDate}${dtpoTime ? ' ' + dtpoTime : ''} - ${report.place_occurrence || 'N/A'}` },
                { label: 'INVOLVED', value: report.involved || 'Investigator, Owner, Fire Department Personnel' },
                { label: 'ESTABLISHMENT', value: report.establishment_name || 'N/A' },
                { label: 'OWNER', value: report.owner || 'N/A' },
                { label: 'OCCUPANT', value: report.occupant || 'N/A' }
            ];
            
            const casualtyRows = [
                { label: 'FATALITY', value: report.fatalities || 0 },
                { label: 'INJURED', value: report.injured || 0 }
            ];
            
            const metaRows = [
                { label: 'ESTIMATED DAMAGE', value: `₱${formatNumberForPrint(report.estimated_damage || 0)}` },
                { label: 'TIME FIRE STARTED', value: report.time_fire_started ? formatDateTimeForPrint(report.time_fire_started) : 'N/A' },
                { label: 'TIME FIRE OUT', value: report.time_fire_out ? formatDateTimeForPrint(report.time_fire_out) : 'N/A' },
                { label: 'ALARM', value: report.highest_alarm_level || 'N/A' }
            ];
            
            const investigationDetails = formatBlock(report.other_info, 'No additional investigation details provided.');
            const dispositionNotes = formatBlock(report.disposition, 'No disposition provided.');
            const caseStatus = report.turned_over ? 'Turned over to higher office' : 'Under investigation';
            const establishmentsAffected = report.establishments_affected || 0;
            const estimatedArea = formatNumberForPrint(report.estimated_area_sqm || 0);
            const locationFatalities = report.location_of_fatalities || 'N/A';
            const weatherCondition = report.weather_condition || 'Normal';
            const investigatorName = report.investigator_name || 'Unknown Investigator';
            
            const renderRows = (rows, labelWidth = 170, rowGap = 4, fontSize = '11px') => rows.map(row => `
                <div style="display:flex; margin-bottom:${rowGap}px; font-size:${fontSize};">
                    <div style="width:${labelWidth}px; font-weight:bold;">${row.label}</div>
                    <div style="width:10px;">:</div>
                    <div style="flex:1;">${row.value}</div>
                </div>
            `).join('');
            
            const compactNote = `
                <div style="font-size:10px; margin-bottom:8px;">
                    A complete narration of the details of the fire incident as gathered by the Fire Arson Investigator (FAI) during actual response covering: affected establishments, estimated area/damage, identities or locations of fatalities, and weather condition.
                </div>
            `;
            
            return `
                <div class="sir-print" style="font-family:'Arial',sans-serif; background:white; color:black; padding:28px; line-height:1.45; font-size:11.5px;">
                    <div class="sir-header" style="text-align:center; border-bottom:1.5px solid #000; padding-bottom:12px; margin-bottom:16px;">
                        <div style="display:flex; justify-content:center; gap:30px; align-items:center; margin-bottom:8px;">
                            <img src="leftlogo.png" alt="BFP Logo" style="width:60px; height:60px;">
                            <img src="centerlogo.png" alt="Republic of the Philippines Logo" style="width:85px; height:85px;">
                            <img src="rightlogo.png" alt="DILG Logo" style="width:60px; height:60px;">
                        </div>
                        <div style="font-size:10px;">Republic of the Philippines</div>
                        <div style="font-size:10px;">Department of the Interior and Local Government</div>
                        <div style="font-size:12px; font-weight:bold;">BUREAU OF FIRE PROTECTION NATIONAL HEADQUARTERS</div>
                        <div style="font-size:9.5px;">Senator Miriam Defensor-Santiago Avenue, Brgy. Bagong Pag-asa, Quezon City</div>
                        <div style="font-size:8.5px; font-style:italic;">(Regional/Provincial/District/City/Municipal Letterhead)</div>
                    </div>
                    
                    <div class="sir-memo" style="margin-bottom:16px;">
                        <div style="font-size:12px; font-weight:bold; letter-spacing:0.5px; margin-bottom:8px;">MEMORANDUM</div>
                        ${renderRows(memoRows, 170, 6, '11.5px')}
                    </div>
                    
                    <div class="sir-details" style="margin-bottom:16px;">
                        ${renderRows(detailRows, 180, 6, '11.5px')}
                        ${renderRows([{ label: 'CASUALTY', value: casualtyRows.map(r => `${r.label}: ${r.value}`).join('&nbsp;&nbsp;') }], 180, 6, '11.5px')}
                        ${renderRows(metaRows, 180, 6, '11.5px')}
                    </div>
                    
                    <div class="sir-investigation" style="margin-bottom:16px;">
                        <div style="font-size:12px; font-weight:bold; margin-bottom:6px; text-transform:uppercase;">Details of Investigation</div>
                        ${compactNote}
                        <div style="border:1px solid #000; padding:12px; font-size:10.5px;">
                            <div style="margin-bottom:8px;">
                                ${investigationDetails}
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:8px; font-size:10.5px;">
                                <div style="flex:1 1 48%;"><strong>Affected Establishments:</strong> ${establishmentsAffected}</div>
                                <div style="flex:1 1 48%;"><strong>Estimated Area:</strong> ${estimatedArea} sq.m.</div>
                                <div style="flex:1 1 48%;"><strong>Location of Fatalities:</strong> ${locationFatalities}</div>
                                <div style="flex:1 1 48%;"><strong>Weather Condition:</strong> ${weatherCondition}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sir-disposition" style="margin-bottom:18px;">
                        <div style="font-size:12px; font-weight:bold; margin-bottom:6px; text-transform:uppercase;">Disposition</div>
                        <div style="border:1px solid #000; padding:12px; font-size:10.5px;">
                            <div style="margin-bottom:8px;">
                                ${dispositionNotes}
                            </div>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <div style="flex:1 1 48%;"><strong>Case Status:</strong> ${caseStatus}</div>
                                <div style="flex:1 1 48%;"><strong>Report Status:</strong> ${statusText}</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sir-signature" style="display:flex; justify-content:flex-end; margin-bottom:16px; padding-top:24px;">
                        <div style="text-align:center; font-size:10.5px;">
                            <div style="border-top:1px solid #000; width:220px; margin-bottom:4px; padding-top:4px;"></div>
                            <div style="font-weight:bold;">${investigatorName}</div>
                            <div style="font-size:10px;">(Name and signature of the FAI)</div>
                        </div>
                    </div>
                    
                    <div class="sir-footer" style="font-size:8.5px; border-top:1px solid #000; padding-top:5px;">
                        BFP- QSF-FAID-002 Rev. 02 (02.03.25) Page 1 of 1
                    </div>
                </div>
            `;
        }
        
        function getStatusBadge(status) {
            switch(status) {
                case 'draft': return 'bg-warning';
                case 'final': return 'bg-success';
                case 'pending_review': return 'bg-info';
                default: return 'bg-secondary';
            }
        }
        
        function getStatusText(status) {
            switch(status) {
                case 'pending_review': return 'Pending Review';
                default: return status.charAt(0).toUpperCase() + status.slice(1);
            }
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatTime(timeString) {
            const time = new Date('2000-01-01T' + timeString);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatDateTime(dateTimeString) {
            const dateTime = new Date(dateTimeString);
            return dateTime.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatNumber(number) {
            return new Intl.NumberFormat('en-US').format(number);
        }
        
        // Fallback close function for modal
        function closeModal() {
            const modalElement = document.getElementById('reportModal');
            if (modalElement && modalElement._modal) {
                modalElement._modal.hide();
            } else {
                // Fallback: hide modal manually
                modalElement.style.display = 'none';
                modalElement.classList.remove('show');
                document.body.classList.remove('modal-open');
                const backdrop = document.querySelector('.modal-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }
        }
        
        // Helper function to extract report data from modal
        function extractReportDataFromModal() {
            const modalBody = document.getElementById('reportModalBody');
            const reportData = {};
            
            if (!modalBody) return reportData;
            
            // Extract data from form-field-line elements (the actual data fields)
            const fieldLines = modalBody.querySelectorAll('.form-field-line');
            fieldLines.forEach(field => {
                const label = field.previousElementSibling;
                if (label && label.classList.contains('form-label')) {
                    const labelText = label.textContent.trim();
                    const value = field.textContent.trim();
                    
                    switch (labelText) {
                        case 'IR Number':
                            reportData.ir_number = value;
                            break;
                        case 'Report Status':
                            reportData.reports_status = value.toLowerCase().replace(' ', '_');
                            break;
                        case 'Date Occurrence':
                            reportData.date_occurrence = value;
                            break;
                        case 'Time Occurrence':
                            reportData.time_occurrence = value;
                            break;
                        case 'Time Fire Started':
                            reportData.time_fire_started = value;
                            break;
                        case 'Time Fire Out':
                            reportData.time_fire_out = value;
                            break;
                        case 'Place of Occurrence':
                            reportData.place_occurrence = value;
                            break;
                        case 'Establishment Name':
                            reportData.establishment_name = value;
                            break;
                        case 'Owner':
                            reportData.owner = value;
                            break;
                        case 'Occupant':
                            reportData.occupant = value;
                            break;
                        case 'Fatalities':
                        reportData.fatalities = parseInt(value) || 0;
                            break;
                        case 'Injured':
                        reportData.injured = parseInt(value) || 0;
                            break;
                        case 'Estimated Damage':
                            reportData.estimated_damage = parseFloat(value.replace(/[₱,]/g, '')) || 0;
                            break;
                        case 'Damage Computation':
                            reportData.damage_computation = parseFloat(value.replace(/[₱,]/g, '')) || 0;
                            break;
                        case 'Highest Alarm Level':
                            reportData.highest_alarm_level = value;
                            break;
                        case 'Establishments Affected':
                        reportData.establishments_affected = parseInt(value) || 0;
                            break;
                        case 'Estimated Area (SQM)':
                            reportData.estimated_area_sqm = parseInt(value) || 0;
                            break;
                        case 'Weather Condition':
                            reportData.weather_condition = value;
                            break;
                        case 'Involved':
                            reportData.involved = value;
                            break;
                        case 'Location of Fatalities':
                            reportData.location_of_fatalities = value;
                            break;
                        case 'Investigator Name':
                            reportData.investigator_name = value;
                            break;
                        case 'Investigator Signature':
                            reportData.investigator_signature = value;
                            break;
                        case 'Date Completed':
                            reportData.date_completed = value;
                            break;
                    }
                }
            });
            
            // Extract title/subject from form-title
            const titleElement = modalBody.querySelector('.form-title');
            if (titleElement) {
                reportData.subject = titleElement.textContent.trim();
            }
            
            // Extract checkbox state
            const checkbox = modalBody.querySelector('#revisedReport');
            if (checkbox) {
                reportData.turned_over = checkbox.checked;
            }
            
            // Extract remarks content
            const remarksContent = modalBody.querySelector('.remarks-content');
            if (remarksContent) {
                const otherInfoDiv = remarksContent.querySelector('div:first-child');
                if (otherInfoDiv) {
                    const otherInfoText = otherInfoDiv.textContent.replace('Other Information:', '').trim();
                    reportData.other_info = otherInfoText;
                }
                
                const dispositionDiv = remarksContent.querySelector('div:last-child');
                if (dispositionDiv) {
                    const dispositionText = dispositionDiv.textContent.replace('Disposition:', '').trim();
                    reportData.disposition = dispositionText;
                }
            }
            
            return reportData;
        }
        
        // Helper functions for print formatting
        function formatDateForPrint(dateString) {
            if (!dateString || dateString === 'N/A') return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        
        function formatDateTimeForPrint(dateString) {
            if (!dateString || dateString === 'N/A') return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatTimeForPrint(timeString) {
            if (!timeString || timeString === 'N/A') return 'N/A';
            const time = new Date('2000-01-01T' + timeString);
            return time.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatDateTimeForPrint(dateTimeString) {
            if (!dateTimeString || dateTimeString === 'N/A') return 'N/A';
            const dateTime = new Date(dateTimeString);
            return dateTime.toLocaleString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        }
        
        function formatNumberForPrint(number) {
            return new Intl.NumberFormat('en-US').format(number);
        }
        
        function getStatusTextForPrint(status) {
            switch(status) {
                case 'pending_review': return 'Pending Review';
                case 'draft': return 'Draft';
                case 'final': return 'Final';
                default: return status ? status.charAt(0).toUpperCase() + status.slice(1) : 'N/A';
            }
        }
        
        function generateReport(format, period, button) {
            const periodText = period.charAt(0).toUpperCase() + period.slice(1);
            const formatText = format.toUpperCase();
            
            // Add loading state to button
            button.classList.add('loading');
            button.style.pointerEvents = 'none';
            
            // Show modern loading notification
            Swal.fire({
                title: 'Generating Report',
                html: `
                    <div class="d-flex align-items-center">
                        <div class="spinner-border text-primary me-3" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div>
                            <div class="fw-bold">${periodText} ${formatText} Report</div>
                            <small class="text-muted">Please wait while we prepare your export...</small>
                        </div>
                    </div>
                `,
                showConfirmButton: false,
                allowOutsideClick: false,
                customClass: {
                    popup: 'swal2-modern'
                },
                didOpen: () => {
                    // Add modern styling
                    const popup = document.querySelector('.swal2-popup');
                    if (popup) {
                        popup.style.borderRadius = '12px';
                        popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                    }
                }
            });
            
            // For CSV downloads, proceed immediately after showing loading
            if (format === 'csv') {
                setTimeout(() => {
                    Swal.close();
                    button.classList.remove('loading');
                    button.style.pointerEvents = 'auto';
                    
                    // Redirect to download URL
                    window.location.href = button.href;
                    
                    // Show success notification after download starts
                    setTimeout(() => {
                        Swal.fire({
                            title: 'Download Started!',
                            text: `Your ${periodText} ${formatText} report is being downloaded.`,
                            icon: 'success',
                            confirmButtonText: 'Great!',
                            customClass: {
                                popup: 'swal2-modern'
                            },
                            didOpen: () => {
                                const popup = document.querySelector('.swal2-popup');
                                if (popup) {
                                    popup.style.borderRadius = '12px';
                                    popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                                }
                            }
                        });
                    }, 1000);
                }, 1500);
            } else {
                // For other formats, simulate processing time
                setTimeout(() => {
                    Swal.close();
                    button.classList.remove('loading');
                    button.style.pointerEvents = 'auto';
                    
                    // Show success notification
                    Swal.fire({
                        title: 'Export Ready!',
                        text: `Your ${periodText} ${formatText} report has been generated successfully.`,
                        icon: 'success',
                        confirmButtonText: 'Great!',
                        customClass: {
                            popup: 'swal2-modern'
                        },
                        didOpen: () => {
                            const popup = document.querySelector('.swal2-popup');
                            if (popup) {
                                popup.style.borderRadius = '12px';
                                popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                            }
                        }
                    });
                }, 2000);
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Print functionality - moved inside DOMContentLoaded and added null check
            const printReportBtn = document.getElementById('printReportBtn');
            if (printReportBtn) {
                printReportBtn.addEventListener('click', function() {
                    const modalBody = document.getElementById('reportModalBody');
                    
                    const reportData = window.currentReportData || null;
                    const reportHtml = reportData ? generateReportHTML(reportData) : (modalBody ? modalBody.innerHTML : '');
                    
                    if (!reportHtml || !reportHtml.trim()) {
                        Swal.fire({
                            title: 'No report to print',
                            text: 'Please open a finalized Fire Department Incident Report first.',
                            icon: 'info',
                            confirmButtonText: 'OK',
                            customClass: {
                                popup: 'swal2-modern'
                            }
                        });
                        return;
                    }
                    
                    const printWindow = window.open('', '_blank');
                    
                    printWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Fire Department Incident Report</title>
                            <style>
                                body {
                                    font-family: Arial, sans-serif;
                                    background: white;
                                    color: black;
                                    margin: 0;
                                    padding: 20px;
                                }
                                
                                @media print {
                                    body {
                                        padding: 0;
                                    }
                                    
                                    .bfp-sir-form {
                                        border: none !important;
                                        box-shadow: none !important;
                                    }
                                }
                                
                                .bfp-sir-form {
                                    max-width: 900px;
                                    margin: 0 auto;
                                    background: white;
                                }
                                
                                .bfp-sir-form * {
                                    color: black !important;
                                }
                            </style>
                        </head>
                        <body>
                            ${reportHtml}
                        </body>
                        </html>
                    `);
                    
                    printWindow.document.close();
                    printWindow.focus();
                    setTimeout(() => {
                        printWindow.print();
                        printWindow.close();
                    }, 300);
                });
            }
            
            // Modal event listeners for better functionality
            const modalElement = document.getElementById('reportModal');
            if (modalElement) {
                // Ensure modal is properly initialized
                if (!modalElement._modal) {
                    modalElement._modal = new bootstrap.Modal(modalElement, {
                        backdrop: true,
                        keyboard: true,
                        focus: true
                    });
                }
                
                // Add event listeners for modal events
                modalElement.addEventListener('hidden.bs.modal', function () {
                    // Clear modal body when hidden
                    const modalBody = document.getElementById('reportModalBody');
                    if (modalBody) {
                        modalBody.innerHTML = '';
                    }
                });
                
                modalElement.addEventListener('shown.bs.modal', function () {
                    // Ensure focus is properly managed
                    const closeBtn = modalElement.querySelector('[data-bs-dismiss="modal"]');
                    if (closeBtn) {
                        closeBtn.focus();
                    }
                });
            }
            
            const exportButtons = document.querySelectorAll('.export-btn');
            exportButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const format = this.getAttribute('data-format');
                    const period = this.getAttribute('data-period');
                    
                    if (format && period) {
                        e.preventDefault();
                        generateReport(format, period, this);
                    }
                });
            });
            
            // Quick Filters functionality
            const applyQuickFiltersBtn = document.getElementById('applyQuickFilters');
            const clearQuickFiltersBtn = document.getElementById('clearQuickFilters');
            const dateFromInput = document.getElementById('dateFrom');
            const dateToInput = document.getElementById('dateTo');
            const statusFilter = document.getElementById('statusFilter');
            
            function applyQuickFilters() {
                const dateFrom = dateFromInput.value;
                const dateTo = dateToInput.value;
                const status = statusFilter.value;
                
                // Validate date range
                if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
                    Swal.fire({
                        title: 'Invalid Date Range',
                        text: 'Date From cannot be later than Date To',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        customClass: {
                            popup: 'swal2-modern'
                        }
                    });
                    return false;
                }
                
                // Build URL with filters
                const urlParams = new URLSearchParams();
                urlParams.append('barangay_id', '<?php echo $barangayId; ?>');
                urlParams.append('name', '<?php echo urlencode($barangay['barangay_name']); ?>');
                urlParams.append('page', '1'); // Reset to first page when applying filters
                urlParams.append('per_page', '<?php echo $perPage; ?>'); // Preserve current per page setting
                
                if (dateFrom) urlParams.append('date_from', dateFrom);
                if (dateTo) urlParams.append('date_to', dateTo);
                if (status) urlParams.append('status', status);
                
                // Show loading state
                applyQuickFiltersBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Applying...';
                applyQuickFiltersBtn.disabled = true;
                
                // Redirect to filtered results
                setTimeout(() => {
                    window.location.href = `reports.php?${urlParams.toString()}`;
                }, 500);
            }
            
            function clearQuickFilters() {
                // Clear all filter inputs
                dateFromInput.value = '';
                dateToInput.value = '';
                statusFilter.value = '';
                
                // Redirect to unfiltered results
                window.location.href = `reports.php?barangay_id=<?php echo $barangayId; ?>&name=<?php echo urlencode($barangay['barangay_name']); ?>&page=1&per_page=<?php echo $perPage; ?>`;
            }
            
            // Add event listeners
            if (applyQuickFiltersBtn) {
                applyQuickFiltersBtn.addEventListener('click', applyQuickFilters);
            }
            
            if (clearQuickFiltersBtn) {
                clearQuickFiltersBtn.addEventListener('click', clearQuickFilters);
            }
            
            // Auto-apply filters on change (optional)
            [dateFromInput, dateToInput, statusFilter].forEach(input => {
                if (input) {
                    input.addEventListener('change', function() {
                        // Auto-apply after a short delay
                        setTimeout(() => {
                            if (dateFromInput.value || dateToInput.value || statusFilter.value) {
                                applyQuickFilters();
                            }
                        }, 1000);
                    });
                }
            });
            
            // Per page selector functionality
            const perPageSelect = document.getElementById('perPageSelect');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function() {
                    const newPerPage = this.value;
                    
                    // Build URL with new per_page parameter
                    const urlParams = new URLSearchParams(window.location.search);
                    urlParams.set('per_page', newPerPage);
                    urlParams.set('page', '1'); // Reset to first page when changing per page
                    
                    // Show loading state
                    this.disabled = true;
                    this.style.opacity = '0.7';
                    
                    // Redirect to new URL
                    setTimeout(() => {
                        window.location.href = `reports.php?${urlParams.toString()}`;
                    }, 300);
                });
            }
            
            // Date Filter functionality for each report type
            const dailyDateInput = document.getElementById('dailyDate');
            const applyDailyFilterBtn = document.getElementById('applyDailyFilter');
            const dailyCsvBtn = document.getElementById('dailyCsvBtn');
            
            const monthlyMonthSelect = document.getElementById('monthlyMonth');
            const monthlyYearSelect = document.getElementById('monthlyYear');
            const applyMonthlyFilterBtn = document.getElementById('applyMonthlyFilter');
            const monthlyCsvBtn = document.getElementById('monthlyCsvBtn');
            
            const yearlyYearSelect = document.getElementById('yearlyYear');
            const applyYearlyFilterBtn = document.getElementById('applyYearlyFilter');
            const yearlyCsvBtn = document.getElementById('yearlyCsvBtn');
            
            // Daily filter functionality
            function applyDailyFilter() {
                const selectedDate = dailyDateInput.value;
                if (!selectedDate) {
                    Swal.fire({
                        title: 'Date Required',
                        text: 'Please select a date for daily reports',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                updateExportLinks('daily', { specific_date: selectedDate });
                
                Swal.fire({
                    title: 'Filter Applied',
                    text: `Daily reports will be generated for ${new Date(selectedDate).toLocaleDateString()}. The CSV will include all fire incident data for this date with columns: IR Number, Subject, Date/Time Occurrence, Place, Establishment, Investigator, Casualties, Damage, and Environmental Data.`,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'swal2-modern'
                    },
                    didOpen: () => {
                        const popup = document.querySelector('.swal2-popup');
                        if (popup) {
                            popup.style.borderRadius = '12px';
                            popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                        }
                    }
                });
            }
            
            // Monthly filter functionality
            function applyMonthlyFilter() {
                const selectedMonth = monthlyMonthSelect.value;
                const selectedYear = monthlyYearSelect.value;
                
                if (!selectedMonth || !selectedYear) {
                    Swal.fire({
                        title: 'Month and Year Required',
                        text: 'Please select both month and year for monthly reports',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                  'July', 'August', 'September', 'October', 'November', 'December'];
                
                updateExportLinks('monthly', { 
                    specific_month: selectedMonth, 
                    specific_year: selectedYear 
                });
                
                Swal.fire({
                    title: 'Filter Applied',
                    text: `Monthly reports will be generated for ${monthNames[parseInt(selectedMonth) - 1]} ${selectedYear}. The CSV will include all fire incident data for this month with detailed columns matching your spreadsheet format.`,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'swal2-modern'
                    },
                    didOpen: () => {
                        const popup = document.querySelector('.swal2-popup');
                        if (popup) {
                            popup.style.borderRadius = '12px';
                            popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                        }
                    }
                });
            }
            
            // Yearly filter functionality
            function applyYearlyFilter() {
                const selectedYear = yearlyYearSelect.value;
                if (!selectedYear) {
                    Swal.fire({
                        title: 'Year Required',
                        text: 'Please select a year for yearly reports',
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                
                updateExportLinks('yearly', { specific_year: selectedYear });
                
                Swal.fire({
                    title: 'Filter Applied',
                    text: `Yearly reports will be generated for ${selectedYear}. The CSV will include all fire incident data for this year with comprehensive columns including IR Number, Subject, Date/Time, Place, Establishment, Investigator, Casualties, Damage, Temperature, Smoke Level, and Coordinates.`,
                    icon: 'success',
                    confirmButtonText: 'OK',
                    customClass: {
                        popup: 'swal2-modern'
                    },
                    didOpen: () => {
                        const popup = document.querySelector('.swal2-popup');
                        if (popup) {
                            popup.style.borderRadius = '12px';
                            popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.2)';
                        }
                    }
                });
            }
            
            // Update export links with new parameters
            function updateExportLinks(period, filters) {
                const baseUrl = 'reports.php';
                const barangayId = '<?php echo $barangayId; ?>';
                const barangayName = '<?php echo urlencode($barangay['barangay_name']); ?>';
                
                // Build filter parameters
                let filterParams = '';
                Object.keys(filters).forEach(key => {
                    if (filters[key]) {
                        filterParams += `&${key}=${encodeURIComponent(filters[key])}`;
                    }
                });
                
                // Update CSV links for the specific period
                if (period === 'daily') {
                    dailyCsvBtn.href = `${baseUrl}?barangay_id=${barangayId}&name=${barangayName}&generate=1&format=csv&period=daily${filterParams}`;
                } else if (period === 'monthly') {
                    monthlyCsvBtn.href = `${baseUrl}?barangay_id=${barangayId}&name=${barangayName}&generate=1&format=csv&period=monthly${filterParams}`;
                } else if (period === 'yearly') {
                    yearlyCsvBtn.href = `${baseUrl}?barangay_id=${barangayId}&name=${barangayName}&generate=1&format=csv&period=yearly${filterParams}`;
                }
            }
            
            // Add event listeners for date filters
            if (applyDailyFilterBtn) {
                applyDailyFilterBtn.addEventListener('click', applyDailyFilter);
            }
            
            if (applyMonthlyFilterBtn) {
                applyMonthlyFilterBtn.addEventListener('click', applyMonthlyFilter);
            }
            
            if (applyYearlyFilterBtn) {
                applyYearlyFilterBtn.addEventListener('click', applyYearlyFilter);
            }
            
            // Real-time filter functionality
            let filterTimeout;
            const filterForm = document.getElementById('filterForm');
            
            function applyFiltersRealTime() {
                // Clear existing timeout
                clearTimeout(filterTimeout);
                
                // Set a small delay to avoid too many requests
                filterTimeout = setTimeout(() => {
                    const formData = new FormData(filterForm);
                    const params = new URLSearchParams();
                    
                    // Add all form data to params
                    for (let [key, value] of formData.entries()) {
                        if (value.trim() !== '') {
                            params.append(key, value);
                        }
                    }
                    
                    // Preserve pagination parameters
                    params.append('page', '1'); // Reset to first page when applying filters
                    params.append('per_page', '<?php echo $perPage; ?>'); // Preserve current per page setting
                    
                    // Build the new URL with current filters
                    const newUrl = `reports.php?${params.toString()}`;
                    
                    // Show loading indicator
                    const loadingIndicator = document.createElement('div');
                    loadingIndicator.innerHTML = '<div class="spinner-border spinner-border-sm text-dark me-2" role="status"><span class="visually-hidden">Loading...</span></div><small class="text-dark">Applying filters...</small>';
                    loadingIndicator.className = 'd-flex align-items-center';
                    
                    const filterContainer = document.querySelector('.card-body');
                    const existingLoader = filterContainer.querySelector('.filter-loader');
                    if (existingLoader) {
                        existingLoader.remove();
                    }
                    
                    loadingIndicator.className += ' filter-loader mt-2';
                    filterContainer.appendChild(loadingIndicator);
                    
                    // Redirect to new URL with filters
                    window.location.href = newUrl;
                }, 500); // 500ms delay
            }
            
            // Add event listeners for real-time filtering
            if (filterForm) {
                const filterInputs = filterForm.querySelectorAll('input, select');
                
                filterInputs.forEach(input => {
                    if (input.type === 'text') {
                        // For text inputs, use input event with debouncing
                        input.addEventListener('input', function() {
                            applyFiltersRealTime();
                        });
                    } else {
                        // For date inputs and selects, use change event
                        input.addEventListener('change', function() {
                            // Validate date range before applying
                            const dateFrom = document.querySelector('input[name="date_from"]').value;
                            const dateTo = document.querySelector('input[name="date_to"]').value;
                            
                            if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
                                Swal.fire({
                                    title: 'Invalid Date Range',
                                    text: 'Date From cannot be later than Date To',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                                return false;
                            }
                            
                            applyFiltersRealTime();
                        });
                    }
                });
                
                // Keep the manual apply button for fallback (hidden by default)
                const applyBtn = document.getElementById('applyFiltersBtn');
                if (applyBtn) {
                    applyBtn.addEventListener('click', function() {
                        applyFiltersRealTime();
                    });
                }
            }
            
        });
    </script>
    <?php include '../../components/scripts.php'; ?>
</body>
</html>

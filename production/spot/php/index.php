<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../../db/db.php';

// Check if user is logged in BEFORE including header.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../../index.php');
    exit();
}

$conn = getDatabaseConnection();

// Get filter parameters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$reportStatus = $_GET['report_status'] ?? 'no_report';
$barangayId = $_GET['barangay_id'] ?? '';
$buildingType = $_GET['building_type'] ?? '';

// Build dynamic WHERE clause - Only show ACKNOWLEDGED incidents and exclude 'final' status reports
$whereConditions = ["UPPER(fd.status) = 'ACKNOWLEDGED'", "(sir.reports_status IS NULL OR sir.reports_status != 'final')"];
$params = [];

if (!empty($startDate)) {
    $whereConditions[] = "DATE(fd.timestamp) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(fd.timestamp) <= ?";
    $params[] = $endDate;
}

if (!empty($reportStatus)) {
    if ($reportStatus === 'no_report') {
        $whereConditions[] = "sir.reports_status IS NULL";
    } else {
        $whereConditions[] = "sir.reports_status = ?";
        $params[] = $reportStatus;
    }
}

if (!empty($barangayId)) {
    $whereConditions[] = "b.barangay_id = ?";
    $params[] = $barangayId;
}

if (!empty($buildingType)) {
    $whereConditions[] = "b.building_type = ?";
    $params[] = $buildingType;
}

$whereClause = implode(' AND ', $whereConditions);

// Get filtered ACKNOWLEDGED fire data records
$stmt = $conn->prepare("
    SELECT fd.*, 
           b.building_name,
           b.building_type,
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
           COALESCE(br_b.barangay_name, br_fd.barangay_name) AS barangay_name,
           COALESCE(br_b.ir_number, br_fd.ir_number) AS ir_number,
           sir.reports_status,
           sir.id as spot_report_id
    FROM fire_data fd
    LEFT JOIN buildings b ON fd.building_id = b.id
    LEFT JOIN users u ON fd.user_id = u.user_id
    LEFT JOIN barangay br_b ON b.barangay_id = br_b.id
    LEFT JOIN barangay br_fd ON fd.barangay_id = br_fd.id
    LEFT JOIN spot_investigation_reports sir ON fd.id = sir.fire_data_id
    WHERE $whereClause
    ORDER BY fd.timestamp DESC
");

try {
    // Bind parameters dynamically
    $paramIndex = 1;
    foreach ($params as $key => $param) {
        $stmt->bindParam($paramIndex, $params[$key], PDO::PARAM_STR);
        $paramIndex++;
    }
    $stmt->execute();
    $fireData = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching fire data: " . $e->getMessage());
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
?>

  <!-- Include header with all necessary libraries -->
  <?php include '../../components/header.php'; ?>
    <link rel="stylesheet" href="../css/spot.css">
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
    <div class="container-fluid">
        <!-- Page Title -->
   <!-- Main Content -->
   <div class="row">
            <div class="col-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fas fa-list-alt"></i>Create Reports</h2>
                        <div class="clearfix"></div>
                    </div>
        <!-- Filter Panel -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i>Advanced Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" id="filterForm">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <div class="row">
                                <!-- Date Range Filters -->
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($startDate); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($endDate); ?>">
                                </div>
                            </div>
                            
                            <div class="row mt-3">
                                <!-- Report Status Filter -->
                                <div class="col-md-12">
                                    <label class="form-label">Report Status</label>
                                    <select name="report_status" class="form-control">
                                        <option value="">All Reports</option>
                                        <option value="no_report" <?php echo $reportStatus === 'no_report' ? 'selected' : ''; ?>>No Report</option>
                                        <option value="draft" <?php echo $reportStatus === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="pending_review" <?php echo $reportStatus === 'pending_review' ? 'selected' : ''; ?>>Pending Review</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-md-6">
                            <div class="row">
                                <!-- Barangay Filter -->
                                <div class="col-md-12">
                                    <label class="form-label">Barangay</label>
                                    <select name="barangay_id" class="form-control">
                                        <option value="">All Barangays</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['id']; ?>" <?php echo $barangayId == $barangay['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($barangay['barangay_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Building Type Filter - Hidden -->
                            <div class="row mt-3" style="display: none;">
                                <div class="col-md-12">
                                    <label class="form-label">Building Type</label>
                                    <select name="building_type" class="form-control">
                                        <option value="">All Types</option>
                                        <?php foreach ($buildingTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type['building_type']); ?>" <?php echo $buildingType === $type['building_type'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($type['building_type']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            <span class="ml-3 text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Showing <?php echo count($fireData); ?> incident(s)
                            </span>
                            <!-- <?php if (empty($fireData)): ?>
                                <span class="ml-3">
                                    <a href="?debug=1" class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-bug"></i> Debug Info
                                    </a>
                                </span>
                            <?php endif; ?> -->
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Main Panel -->
        <div class="x_panel">
            <div class="x_title">
                <h2><i class="fas fa-list"></i>Fire Incidents List</h2>
            </div>
            <div class="x_content">
            <?php if (empty($fireData)): ?>
                <div class="text-center p-5">
                    <i class="fas fa-fire text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">No Acknowledged Fire Incidents Found</h5>
                    <p class="text-muted">There are currently no fire incidents with ACKNOWLEDGED status.</p>
                    <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
                        <div class="alert alert-info mt-3">
                            <h6>Debug Information:</h6>
                            <p><strong>Query:</strong> <?php echo htmlspecialchars($whereClause); ?></p>
                            <p><strong>Parameters:</strong> <?php echo htmlspecialchars(implode(', ', $params)); ?></p>
                            <?php
                            // Check total fire_data records
                            $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM fire_data");
                            $totalStmt->execute();
                            $totalCount = $totalStmt->fetch()['total'];
                            
                            // Check ACKNOWLEDGED records
                            $ackStatus = 'ACKNOWLEDGED';
                            $ackStmt = $conn->prepare("SELECT COUNT(*) as count FROM fire_data WHERE UPPER(status) = UPPER(?)");
                            $ackStmt->bindParam(1, $ackStatus, PDO::PARAM_STR);
                            $ackStmt->execute();
                            $ackCount = $ackStmt->fetch()['count'];
                            
                            // Check all status values
                            $statusStmt = $conn->prepare("SELECT DISTINCT status, COUNT(*) as count FROM fire_data GROUP BY status ORDER BY count DESC");
                            $statusStmt->execute();
                            $statuses = $statusStmt->fetchAll();
                            ?>
                            <p><strong>Total fire_data records:</strong> <?php echo $totalCount; ?></p>
                            <p><strong>ACKNOWLEDGED records:</strong> <?php echo $ackCount; ?></p>
                            <p><strong>All status values:</strong></p>
                            <ul>
                                <?php foreach ($statuses as $status): ?>
                                    <li><?php echo htmlspecialchars($status['status']); ?>: <?php echo $status['count']; ?> records</li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" id="incidentsTable">
                        <thead>
                            <tr>
                                <th style="display: none;">Incident ID</th>
                                <th style="display: none;">Smoke Level</th>
                                <th style="display: none;">Temperature</th>
                                <th style="display: none;">Heat Level</th>
                                <th style="display: none;">Flame Detected</th>
                                <th style="display: none;">ML Confidence</th>
                                <th>Building Name</th>
                                <th>Building Type</th>
                                <th>Barangay</th>
                                <th>User Name</th>
                                <th>Contact Info</th>
                                <th>IR Number</th>
                                  <th>Report Status</th>
                                  <th>Timestamp</th>
                                  <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fireData as $incident): ?>
                                <tr>
                                    <td style="display: none;"><strong>#<?php echo str_pad($incident['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
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
                                    <td>
                                        <strong><?php echo htmlspecialchars($incident['building_name'] ?? 'Unknown'); ?></strong>
                                        <?php if (!empty($incident['building_address'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(shortenAddress($incident['building_address'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-info"><?php echo htmlspecialchars($incident['building_type'] ?? 'Unknown'); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($incident['barangay_name'] ?? 'Unknown Location'); ?></strong>
                                        <?php // Barangay IR number hidden as requested ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($incident['user_fullname'] ?? $incident['user_name'] ?? 'Unknown'); ?></strong>
                                        <br><small class="text-muted">@<?php echo htmlspecialchars($incident['user_name'] ?? 'N/A'); ?></small>
                                        <br><small class="badge <?php echo ($incident['user_status'] ?? '') === 'Active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo htmlspecialchars($incident['user_status'] ?? 'Unknown'); ?>
                                        </small>
                                    </td>
                                    <td>
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
                                        <?php if (!empty($incident['spot_report_id'])): ?>
                                            <?php
                                            // Get IR number from spot_investigation_reports table
                                            $spotReportId = $incident['spot_report_id'];
                                            $irStmt = $conn->prepare("SELECT ir_number FROM spot_investigation_reports WHERE id = ?");
                                            $irStmt->bindParam(1, $spotReportId, PDO::PARAM_INT);
                                            $irStmt->execute();
                                            $irData = $irStmt->fetch();
                                            ?>
                                            <strong class="text-primary"><?php echo htmlspecialchars($irData['ir_number'] ?? 'N/A'); ?></strong>
                                            <br><small class="text-muted">ID: <?php echo $incident['spot_report_id']; ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">No Report</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $reportStatus = $incident['reports_status'] ?? 'No Report';
                                        $statusClass = '';
                                        $statusColor = '';
                                        switch($reportStatus) {
                                            case 'draft':
                                                $statusClass = 'badge-warning';
                                                $statusColor = '#ffc107'; // Amber/Yellow
                                                break;
                                            case 'pending_review':
                                                $statusClass = 'badge-info';
                                                $statusColor = '#17a2b8'; // Cyan/Blue
                                                break;
                                            case 'completed':
                                                $statusClass = 'badge-success';
                                                $statusColor = '#28a745'; // Green
                                                break;
                                            case 'approved':
                                                $statusClass = 'badge-primary';
                                                $statusColor = '#007bff'; // Blue
                                                break;
                                            case 'No Report':
                                                $statusClass = 'badge-secondary';
                                                $statusColor = '#6c757d'; // Gray
                                                break;
                                            default:
                                                $statusClass = 'badge-secondary';
                                                $statusColor = '#6c757d';
                                        }
                                        ?>
                                        <div class="d-flex align-items-center">
                                            <span class="badge <?php echo $statusClass; ?> me-2 report-status-<?php echo strtolower(str_replace(' ', '-', $reportStatus)); ?>">
                                                <?php echo htmlspecialchars(ucfirst($reportStatus)); ?>
                                            </span>
                                        </div>
                                    </td>
                                      <td><?php echo date('M d, Y H:i', strtotime($incident['timestamp'])); ?></td>
                                      <td>
                                        <button class="btn btn-sm btn-primary" onclick="viewIncidentDetails(<?php echo htmlspecialchars(json_encode($incident)); ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php 
                                        $currentReportStatus = $incident['reports_status'] ?? 'No Report';
                                        $hasReport = !empty($incident['spot_report_id']);
                                        ?>
                                        <?php if ($hasReport): ?>
                                            <?php if ($currentReportStatus === 'draft'): ?>
                                                <button class="btn btn-sm action-btn-draft" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit Draft Report">
                                                    <i class="fas fa-edit"></i> Edit Draft
                                                </button>
                                            <?php elseif ($currentReportStatus === 'pending_review'): ?>
                                                <button class="btn btn-sm action-btn-pending" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit Pending Review Report">
                                                    <i class="fas fa-edit"></i> Edit Pending
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm action-btn-create" onclick="editSpotReport(<?php echo $incident['spot_report_id']; ?>)" title="Edit Report">
                                                    <i class="fas fa-edit"></i> Edit Report
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-sm action-btn-create" onclick="createSpotReport(<?php echo $incident['id']; ?>)" title="Create Report">
                                                <i class="fas fa-file-alt"></i> Create Report
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            // Check for report saved notification
            const urlParams = new URLSearchParams(window.location.search);
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
                    // If this is a final report, redirect to show only "No Report" incidents
                    if (isFinal && reportStatus === 'final') {
                        window.location.href = 'index.php?report_status=no_report';
                    } else {
                        // Clean up URL parameters for other cases
                        window.history.replaceState({}, document.title, window.location.pathname);
                    }
                });
            }
            $('#incidentsTable').DataTable({
                "pageLength": 25,
                "order": [[7, "desc"]],
                "columnDefs": [
                    { "visible": false, "targets": [0, 1, 2, 3, 4, 5] },
                    { "orderable": false, "targets": 9 }
                ],
                "language": {
                    "search": "Search within filtered results:",
                    "lengthMenu": "Show _MENU_ incidents per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ incidents",
                    "infoEmpty": "No incidents available",
                    "infoFiltered": "(filtered from _MAX_ total incidents)"
                }
            });

            // Auto-submit form when date inputs change
            $('input[type="date"]').on('change', function() {
                $('#filterForm').submit();
            });

            // Auto-submit form when select dropdowns change
            $('select').on('change', function() {
                $('#filterForm').submit();
            });

            // Add loading state to form submission
            $('#filterForm').on('submit', function() {
                $('button[type="submit"]').html('<i class="fas fa-spinner fa-spin"></i> Loading...').prop('disabled', true);
            });
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
                case 'draft':
                    statusElement.className += 'badge-warning report-status-draft';
                    break;
                case 'pending_review':
                    statusElement.className += 'badge-info report-status-pending-review';
                    break;
                case 'completed':
                    statusElement.className += 'badge-success report-status-completed';
                    break;
                case 'approved':
                    statusElement.className += 'badge-primary report-status-approved';
                    break;
                default:
                    statusElement.className += 'badge-secondary report-status-no-report';
            }
            
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
            
            if (hasReport) {
                if (modalReportStatus === 'draft') {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit Draft';
                    modalButton.className = 'btn action-btn-draft';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                } else if (modalReportStatus === 'pending_review') {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit Pending';
                    modalButton.className = 'btn action-btn-pending';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                } else {
                    modalButton.innerHTML = '<i class="fas fa-edit"></i> Edit Report';
                    modalButton.className = 'btn action-btn-create';
                    modalButton.disabled = false;
                    modalButton.onclick = function() {
                        $('#incidentModal').modal('hide');
                        editSpotReport(incident.spot_report_id);
                    };
                }
            } else {
                modalButton.innerHTML = '<i class="fas fa-file-alt"></i> Create Report';
                modalButton.className = 'btn action-btn-create';
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
                        <i class="fas fa-file-alt"></i> Create Spot Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php include '../../components/scripts.php'; ?>
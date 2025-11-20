<?php
date_default_timezone_set('Asia/Manila');
session_start();
require_once '../../db/db.php';

// Check if user is logged in BEFORE including header.php
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../../../index.php');
    exit();
}
$conn = getDatabaseConnection();

// Get filter parameters
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$barangayId = $_GET['barangay_id'] ?? '';
$buildingType = $_GET['building_type'] ?? '';
$investigatorName = $_GET['investigator_name'] ?? '';

// Build dynamic WHERE clause for final reports
$whereConditions = ["sir.reports_status = 'final'"];
$params = [];

if (!empty($startDate)) {
    $whereConditions[] = "DATE(sir.date_completed) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(sir.date_completed) <= ?";
    $params[] = $endDate;
}

if (!empty($barangayId)) {
    $whereConditions[] = "fd.barangay_id = ?";
    $params[] = $barangayId;
}

if (!empty($buildingType)) {
    $whereConditions[] = "b.building_type = ?";
    $params[] = $buildingType;
}

if (!empty($investigatorName)) {
    $whereConditions[] = "sir.investigator_name LIKE ?";
    $params[] = "%$investigatorName%";
}

$whereClause = implode(' AND ', $whereConditions);

// Get filtered FINAL spot investigation reports
$stmt = $conn->prepare("
    SELECT sir.*, 
           sir.ir_number as report_ir_number,
           fd.timestamp as fire_timestamp,
           fd.smoke,
           fd.temp,
           fd.heat,
           fd.flame_detected,
           fd.ml_confidence,
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
           br.barangay_name,
           br.ir_number as barangay_ir_number
    FROM spot_investigation_reports sir
    LEFT JOIN fire_data fd ON sir.fire_data_id = fd.id
    LEFT JOIN buildings b ON fd.building_id = b.id
    LEFT JOIN users u ON fd.user_id = u.user_id
    LEFT JOIN barangay br ON fd.barangay_id = br.id
    WHERE $whereClause
    ORDER BY sir.date_completed DESC, sir.created_at DESC
");

// Bind parameters dynamically
$paramIndex = 1;
foreach ($params as $key => $param) {
    $stmt->bindParam($paramIndex, $params[$key], PDO::PARAM_STR);
    $paramIndex++;
}
$stmt->execute();
$finalReports = $stmt->fetchAll();

// Get filter options
$barangayStmt = $conn->prepare("SELECT id, barangay_name FROM barangay ORDER BY barangay_name");
$barangayStmt->execute();
$barangays = $barangayStmt->fetchAll();

$buildingTypeStmt = $conn->prepare("SELECT DISTINCT building_type FROM buildings WHERE building_type IS NOT NULL ORDER BY building_type");
$buildingTypeStmt->execute();
$buildingTypes = $buildingTypeStmt->fetchAll();

$investigatorStmt = $conn->prepare("SELECT DISTINCT investigator_name FROM spot_investigation_reports WHERE investigator_name IS NOT NULL AND reports_status = 'final' ORDER BY investigator_name");
$investigatorStmt->execute();
$investigators = $investigatorStmt->fetchAll();

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

// Function to format currency
function formatCurrency($amount) {
    if (empty($amount) || $amount == 0) return '₱0.00';
    return '₱' . number_format($amount, 2);
}
?>
<?php include '../../components/header.php'; ?>
    <link rel="stylesheet" href="../css/spot.css">
  <!-- Include header with all necessary libraries -->
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
                        <h2><i class="fas fa-file-alt"></i>Final Reports</h2>
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
                                <!-- Investigator Filter -->
                                <div class="col-md-12">
                                    <label class="form-label">Investigator Name</label>
                                    <select name="investigator_name" class="form-control">
                                        <option value="">All Investigators</option>
                                        <?php foreach ($investigators as $investigator): ?>
                                            <option value="<?php echo htmlspecialchars($investigator['investigator_name']); ?>" <?php echo $investigatorName === $investigator['investigator_name'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($investigator['investigator_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
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
                            
                        </div>
                    </div>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="final_reports.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                            <span class="ml-3 text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Showing <?php echo count($finalReports); ?> final report(s)
                            </span>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Main Panel -->
        <div class="x_panel">
            <div class="x_title">
                <h2><i class="fas fa-list"></i>Final Reports List</h2>
            </div>
            <div class="x_content">
            <?php if (empty($finalReports)): ?>
                <div class="text-center p-5">
                    <i class="fas fa-file-alt text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">No Final Reports Found</h5>
                    <p class="text-muted">There are currently no finalized spot investigation reports.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table" id="finalReportsTable">
                        <thead>
                            <tr>
                                <th style="display: none;">Report ID</th>
                                <th style="display: none;">IR Number</th>
                                <th>Establishment</th>
                                <th>Place of Occurrence</th>
                                <th>Date Completed</th>
                                <th>Investigator</th>
                                <th>Fatalities</th>
                                <th>Injured</th>
                                <th>Damage</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($finalReports as $report): ?>
                                <tr>
                                    <td style="display: none;"><strong>#<?php echo str_pad($report['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                    <td style="display: none;">
                                        <strong class="text-primary"><?php echo htmlspecialchars($report['report_ir_number'] ?? $report['ir_number'] ?? 'N/A'); ?></strong>
                                        <br><small class="text-muted">ID: <?php echo $report['id']; ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['establishment_name'] ?? 'Unknown'); ?></strong>
                                        <?php if (!empty($report['building_address'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars(shortenAddress($report['building_address'])); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($report['building_type'])): ?>
                                            <br><span class="badge badge-info"><?php echo htmlspecialchars($report['building_type']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['place_occurrence'] ?? 'Not specified'); ?></strong>
                                        <?php if (!empty($report['barangay_name'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($report['barangay_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['date_completed']); ?></strong>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($report['investigator_name'] ?? 'Unknown'); ?></strong>
                                        <?php if (!empty($report['investigator_signature'])): ?>
                                            <br><small class="text-success"><i class="fas fa-check-circle"></i> Signed</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($report['fatalities'] > 0): ?>
                                            <span class="badge badge-danger"><?php echo $report['fatalities']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($report['injured'] > 0): ?>
                                            <span class="badge badge-warning"><?php echo $report['injured']; ?></span>
                                        <?php else: ?>
                                            <span class="badge badge-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo formatCurrency($report['estimated_damage']); ?></strong>
                                        <?php if ($report['estimated_area_sqm'] > 0): ?>
                                            <br><small class="text-muted"><?php echo number_format($report['estimated_area_sqm'], 2); ?> sqm</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-danger" onclick="viewFinalReportDetails(<?php echo htmlspecialchars(json_encode($report)); ?>)" title="View Details">
                                            <i class="fas fa-file-alt fa-lg"></i>
                                        </button>
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

    <style>
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

        .modern-modal-footer .btn-info {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        
        .modern-modal-footer .btn-info:hover {
            background-color: #138496;
            border-color: #138496;
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
    </style>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#finalReportsTable').DataTable({
                "pageLength": 25,
                "order": [[4, "desc"]], // Order by date completed
                "columnDefs": [
                    { "visible": false, "targets": [0, 1] },
                    { "orderable": false, "targets": 9 }
                ],
                "language": {
                    "search": "Search within filtered results:",
                    "lengthMenu": "Show _MENU_ reports per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ reports",
                    "infoEmpty": "No reports available",
                    "infoFiltered": "(filtered from _MAX_ total reports)"
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


        function viewFinalReportDetails(report) {
            // Generate fire department form HTML
            const modalBody = document.getElementById('finalReportModalBody');
            modalBody.innerHTML = generateFinalReportHTML(report);
            
            // Show modal
            $('#finalReportModal').modal('show');
        }

        function generateFinalReportHTML(report) {
            const statusText = 'Final';
            
            return `
                <div class="bfp-sir-form" style="font-family: Arial, sans-serif; background: white; color: black; padding: 12px; line-height: 1.2; max-width: 100%; width: 100%;">
                    <!-- Official BFP Header Section -->
                    <div class="bfp-header text-center mb-2" style="border-bottom: 1.5px solid #000; padding-bottom: 5px; margin-bottom: 8px;">
                        <!-- Logo Section -->
                        <div class="logo-section mb-1" style="display: flex; justify-content: center; align-items: center; margin-bottom: 5px;">
                            <div class="logo-container" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                                <img src="leftlogo.png" alt="BFP Logo" style="width: 50px; height: 50px;">
                                <img src="centerlogo.png" alt="Republic of the Philippines Logo" style="width: 60px; height: 60px;">
                                <img src="rightlogo.png" alt="DILG Logo" style="width: 50px; height: 50px;">
                            </div>
                        </div>
                        
                        <!-- Official Title -->
                        <div class="official-title mb-1" style="margin-bottom: 3px;">
                            <div style="font-size: 12px; margin-bottom: 1px;">Republic of the Philippines</div>
                            <div style="font-size: 12px; margin-bottom: 1px;">Department of the Interior and Local Government</div>
                            <div style="font-size: 12px; font-weight: bold; margin-bottom: 1px;">BUREAU OF FIRE PROTECTION NATIONAL HEADQUARTERS</div>
                            <div style="font-size: 12px;">Senator Miriam Defensor-Santiago Avenue, Brgy. Bagong Pag-asa, Quezon City</div>
                            <div style="font-size: 12px; font-style: italic; margin-top: 1px;">(Regional/Provincial/District/City/Municipal Letterhead)</div>
                        </div>
                    </div>
                    
                    <!-- Memorandum Section -->
                    <div class="memorandum-section mb-2" style="margin-bottom: 10px;">
                        <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">MEMORANDUM</div>
                        <div style="margin-bottom: 3px; font-size: 12px;">
                            <span style="font-weight: bold;">FOR</span> : ${report.report_for || 'Respective head of office'}
                        </div>
                        <div style="margin-bottom: 3px; font-size: 12px;">
                            <span style="font-weight: bold;">SUBJECT</span> : ${report.subject || 'Spot Investigation Report (SIR)'}
                        </div>
                        <div style="margin-bottom: 3px; font-size: 12px;">
                            <span style="font-weight: bold;">DATE</span> : ${formatDate(report.date_completed)}
                        </div>
                    </div>
                    
                    <!-- Incident Details Section (BFP Format) -->
                    <div class="incident-details-section mb-2" style="margin-bottom: 10px;">
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">DTPO</span> : ${formatDate(report.date_occurrence)} ${formatTime(report.time_occurrence)} - ${report.place_occurrence || 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">INVOLVED</span> : ${report.involved || 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">NAME OF ESTABLISHMENT</span> : ${report.establishment_name || 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">OWNER</span> : ${report.owner || 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">OCCUPANT</span> : ${report.occupant || 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">CASUALTY</span> :
                            <div style="margin-left: 15px; margin-top: 2px;">
                                <div style="margin-bottom: 1px; font-size: 12px;">
                                    <span style="font-weight: bold;">Fatality</span> : ${report.fatalities || 0}
                                </div>
                                <div style="margin-bottom: 1px; font-size: 12px;">
                                    <span style="font-weight: bold;">Injured</span> : ${report.injured || 0}
                                </div>
                            </div>
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">ESTIMATED DAMAGE</span> : ₱${formatNumber(report.estimated_damage || 0)}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">TIME FIRE STARTED</span> : ${report.time_fire_started ? formatDateTime(report.time_fire_started) : 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">TIME OF FIRE OUT</span> : ${report.time_fire_out ? formatDateTime(report.time_fire_out) : 'N/A'}
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            <span style="font-weight: bold;">ALARM</span> : ${report.highest_alarm_level || 'N/A'}
                        </div>
                    </div>
                    
                    <!-- Details of Investigation Section (BFP Format) -->
                    <div class="investigation-section mb-2" style="margin-bottom: 10px;">
                        <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">DETAILS OF INVESTIGATION:</div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            A complete narration of the details of the fire incident as gathered by the Fire Arson Investigator (FAI) during actual response. Details shall include, but are not limited, to the following:
                        </div>
                        <div style="margin-left: 15px; margin-bottom: 4px; font-size: 12px;">
                            <div style="margin-bottom: 1px;">a) Number of establishments and / or affected establishments</div>
                            <div style="margin-bottom: 1px;">b) Estimated area in square meters and the estimated amount of damage based on the computation in the 2015 BFP Operational Procedures Manual</div>
                            <div style="margin-bottom: 1px;">c) Location of fatalities and initial details as to identity</div>
                            <div style="margin-bottom: 1px;">d) Weather condition</div>
                        </div>
                        
                        <!-- Investigation Content -->
                        <div style="min-height: 80px; padding: 6px; font-size: 12px; margin-top: 5px;">
                            <div style="margin-bottom: 5px;">
                                <strong>Investigation Details:</strong><br>
                                ${report.other_info || 'No additional investigation details provided.'}
                            </div>
                            <div style="margin-bottom: 5px;">
                                <strong>Number of Affected Establishments:</strong> ${report.establishment_name ? '1' : '0'}<br>
                                <strong>Estimated Area:</strong> ${report.estimated_area_sqm || 0} square meters<br>
                                <strong>Location of Fatalities:</strong> ${report.location_of_fatalities || 'N/A'}<br>
                                <strong>Weather Condition:</strong> ${report.weather_condition || 'N/A'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Disposition Section (BFP Format) -->
                    <div class="disposition-section mb-2" style="margin-bottom: 10px;">
                        <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">DISPOSITION:</div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            • The disposition and assessment of the FAI regarding the case.
                        </div>
                        <div style="margin-bottom: 4px; font-size: 12px;">
                            • May also contain whether the case will be turned over to the higher office.
                        </div>
                        
                        <!-- Disposition Content -->
                        <div style="min-height: 60px; padding: 6px; font-size: 12px; margin-top: 5px;">
                            <div style="margin-bottom: 5px;">
                                <strong>FAI Assessment:</strong><br>
                                ${report.disposition || 'No disposition provided.'}
                            </div>
                            <div style="margin-bottom: 5px;">
                                <strong>Case Status:</strong> ${report.turned_over ? 'Turned over to higher office' : 'Under investigation'}<br>
                                <strong>Report Status:</strong> ${statusText}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Signature Block (BFP Format) -->
                    <div class="signature-section mb-2" style="margin-bottom: 10px; text-align: right;">
                        <div style="margin-bottom: 10px;">
                            <div style="border-bottom: 1px solid black; width: 150px; margin-left: auto; padding-bottom: 1px;">
                              
                            </div>
                            <div style="font-size: 12px; margin-top: 4px; text-align: center; font-weight: bold; width: 150px; margin-left: auto;">
                                ${report.investigator_name || 'Fire Arson Investigator'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Official BFP Footer -->
                    <div class="bfp-footer" style="font-size: 6px; color: black; border-top: 1px solid #ccc; padding-top: 3px; text-align: left;">
                        <span>BFP- QSF-FAID-002 Rev. 02 (02.03.25) Page 1 of 2</span>
                    </div>
                </div>
            `;
        }

        function formatCurrency(amount) {
            if (!amount || amount == 0) return '₱0.00';
            return '₱' + parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }

        function formatDate(dateString) {
            if (!dateString) return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function formatDateTime(dateString) {
            if (!dateString) return 'N/A';
            return dateString;
        }

        function formatTime(timeString) {
            if (!timeString) return 'N/A';
            
            // Handle different time formats
            if (timeString.includes(':')) {
                // If it's already in HH:MM:SS format, convert to 12-hour format
                const time = new Date('2000-01-01T' + timeString);
                return time.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            // If it's a datetime string, extract time part
            const date = new Date(timeString);
            if (!isNaN(date.getTime())) {
                return date.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            return timeString;
        }


        function formatNumber(number) {
            if (!number || number == 0) return '0.00';
            return parseFloat(number).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
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
        
        function formatTimeForPrint(timeString) {
            if (!timeString || timeString === 'N/A') return 'N/A';
            
            // Handle different time formats
            if (timeString.includes(':')) {
                // If it's already in HH:MM:SS format, convert to 12-hour format
                const time = new Date('2000-01-01T' + timeString);
                return time.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            // If it's a datetime string, extract time part
            const date = new Date(timeString);
            if (!isNaN(date.getTime())) {
                return date.toLocaleTimeString('en-US', {
                    hour: 'numeric',
                    minute: '2-digit',
                    hour12: true
                });
            }
            
            return timeString;
        }
        
        function formatDateTimeForPrint(dateTimeString) {
            if (!dateTimeString || dateTimeString === 'N/A') return 'N/A';
            return dateTimeString;
        }
        
        function formatNumberForPrint(number) {
            return new Intl.NumberFormat('en-US').format(number);
        }

        function printFinalReport(reportId) {
            // Get the current report data from the modal
            const reportData = <?php echo json_encode($finalReports); ?>;
            const currentReport = reportData.find(r => r.id == reportId) || reportData[0];
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>BFP Spot Investigation Report (SIR)</title>
                    <style>
                        @media print {
                            @page {
                                margin: 0;
                                size: A4;
                            }
                            body { 
                                margin: 0; 
                                padding: 0;
                                background: white;
                            }
                            .no-print { display: none !important; }
                            .bfp-sir-form {
                                margin: 0;
                                padding: 15px;
                                width: 100%;
                                max-width: 100%;
                            }
                        }
                        
                        html, body {
                            width: 100%;
                            height: 100%;
                            margin: 0;
                            padding: 0;
                        }
                        
                        body { 
                            font-family: Arial, sans-serif; 
                            background: white;
                            color: black;
                            margin: 0;
                            padding: 0;
                        }
                        
                        .bfp-sir-form {
                            font-family: Arial, sans-serif;
                            background: white;
                            color: black;
                            line-height: 1.2;
                            max-width: 100%;
                            width: 100%;
                            margin: 0;
                            padding: 15px;
                            box-sizing: border-box;
                        }

                        .form-header {
                            text-align: center;
                            border-bottom: 1px solid black;
                            padding-bottom: 12px;
                            margin-bottom: 15px;
                            column-span: all;
                            break-inside: avoid;
                        }

                        .form-title {
                            font-size: 16px;
                            font-weight: bold;
                            color: black;
                            margin: 0 0 6px 0;
                        }

                        .form-subtitle {
                            font-size: 9px;
                            color: black;
                            display: flex;
                            justify-content: space-between;
                            align-items: center;
                            margin-bottom: 6px;
                        }

                        .form-checkbox {
                            margin-top: 6px;
                        }

                        .form-checkbox input {
                            margin-right: 4px;
                        }
                        
                        .form-checkbox label {
                            font-size: 9px;
                            color: black;
                        }

                        .form-label {
                            font-weight: bold;
                            font-size: 8px;
                            color: black;
                            margin-bottom: 1px;
                        }

                        .form-field-line {
                            border-bottom: 1px solid black;
                            padding: 2px 0;
                            font-size: 8px;
                            color: black;
                            min-height: 12px;
                        }
                        
                        .form-field-box {
                            border: 1px solid black;
                            padding: 2px;
                            text-align: center;
                            font-size: 8px;
                            background: white;
                            min-height: 12px;
                        }
                        
                        .section-title-new {
                            font-size: 10px;
                            font-weight: bold;
                            color: black;
                            text-align: center;
                            margin-bottom: 6px;
                        }

                        .section-subtitle {
                            font-size: 8px;
                            color: black;
                            text-align: center;
                            margin-bottom: 8px;
                        }

                        .remarks-content {
                            border: 1px solid black;
                            min-height: 80px;
                            padding: 6px;
                            font-size: 8px;
                            background: white;
                        }
                        
                        .apparatus-table {
                            font-size: 7px;
                            border: 1px solid black;
                            width: 100%;
                            border-collapse: collapse;
                            background: white;
                        }
                        
                        .apparatus-table th,
                        .apparatus-table td {
                            border: 1px solid black;
                            padding: 2px;
                            text-align: center;
                            height: 16px;
                        }
                        
                        .apparatus-table th {
                            background: white;
                            font-weight: bold;
                        }

                        .checkbox-item {
                            display: flex;
                            align-items: center;
                            margin-right: 10px;
                        }

                        .checkbox-item input {
                            margin-right: 2px;
                        }

                        .checkbox-item label {
                            font-size: 8px;
                            color: black;
                        }
                        
                        .form-footer {
                            font-size: 7px;
                            color: black;
                            border-top: 1px solid black;
                            padding-top: 4px;
                        }
                        
                        .row {
                            display: flex;
                            flex-wrap: wrap;
                            margin-bottom: 6px;
                        }
                        
                        .col-1 { flex: 0 0 12%; padding: 0 2px; }
                        .col-2 { flex: 0 0 24%; padding: 0 2px; }
                        .col-3 { flex: 0 0 36%; padding: 0 2px; }
                        .col-4 { flex: 0 0 48%; padding: 0 2px; }
                        .col-6 { flex: 0 0 72%; padding: 0 2px; }
                        .col-8 { flex: 0 0 96%; padding: 0 2px; }
                        .col-12 { flex: 0 0 100%; padding: 0 2px; }
                        
                        .form-group {
                            margin-bottom: 3px;
                                break-inside: avoid;
                        }
                        
                        .mb-2 { margin-bottom: 6px; }
                        .mb-3 { margin-bottom: 8px; }
                        .mb-4 { margin-bottom: 10px; }
                        .mt-2 { margin-top: 6px; }
                        
                        .d-flex { display: flex; }
                        .justify-content-between { justify-content: space-between; }
                        .align-items-center { align-items: center; }
                        .text-center { text-align: center; }
                        
                        /* Column break controls */
                        .incident-data-section,
                        .remarks-section,
                        .response-code-section,
                        .sign-off-section {
                                break-inside: avoid;
                            margin-bottom: 12px;
                            }
                        
                        .row {
                                break-inside: avoid;
                        }
                        
                        .form-footer {
                            column-span: all;
                            break-inside: avoid;
                        }
                        
                        @media print {
                            html, body {
                                width: 100%;
                                margin: 0;
                                padding: 0;
                            }
                            
                            .fire-department-form {
                                border: none;
                                box-shadow: none;
                                background: white;
                                width: 100%;
                                max-width: 100%;
                            }
                            
                            body {
                                background: white;
                            }
                            
                            .bfp-sir-form {
                                width: 100%;
                                max-width: 100%;
                                margin: 0;
                                padding: 15px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="bfp-sir-form">
                        <!-- Official BFP Header Section -->
                        <div class="bfp-header" style="text-align: center; border-bottom: 1.5px solid #000; padding-bottom: 5px; margin-bottom: 8px;">
                            <!-- Logo Section -->
                            <div class="logo-section" style="display: flex; justify-content: center; align-items: center; margin-bottom: 5px;">
                                <div class="logo-container" style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                                    <img src="leftlogo.png" alt="BFP Logo" style="width: 50px; height: 50px;">
                                    <img src="centerlogo.png" alt="Republic of the Philippines Logo" style="width: 60px; height: 60px;">
                                    <img src="rightlogo.png" alt="DILG Logo" style="width: 50px; height: 50px;">
                                </div>
                            </div>
                            
                            <!-- Official Title -->
                            <div class="official-title" style="margin-bottom: 3px;">
                                <div style="font-size: 12px; margin-bottom: 1px;">Republic of the Philippines</div>
                                <div style="font-size: 12px; margin-bottom: 1px;">Department of the Interior and Local Government</div>
                                <div style="font-size: 12px; font-weight: bold; margin-bottom: 1px;">BUREAU OF FIRE PROTECTION NATIONAL HEADQUARTERS</div>
                                <div style="font-size: 12px;">Senator Miriam Defensor-Santiago Avenue, Brgy. Bagong Pag-asa, Quezon City</div>
                                <div style="font-size: 12px; font-style: italic; margin-top: 1px;">(Regional/Provincial/District/City/Municipal Letterhead)</div>
                            </div>
                        </div>
                        
                        <!-- Memorandum Section -->
                        <div class="memorandum-section" style="margin-bottom: 10px;">
                            <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">MEMORANDUM</div>
                            <div style="margin-bottom: 3px; font-size: 12px;">
                                <span style="font-weight: bold;">FOR</span> : ${currentReport.report_for || 'Respective head of office'}
                            </div>
                            <div style="margin-bottom: 3px; font-size: 12px;">
                                <span style="font-weight: bold;">SUBJECT</span> : ${currentReport.subject || 'Spot Investigation Report (SIR)'}
                            </div>
                            <div style="margin-bottom: 3px; font-size: 12px;">
                                <span style="font-weight: bold;">DATE</span> : ${formatDateForPrint(currentReport.date_completed)}
                            </div>
                        </div>
                        
                        <!-- Incident Details Section (BFP Format) -->
                        <div class="incident-details-section" style="margin-bottom: 10px;">
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">DTPO</span> : ${formatDateForPrint(currentReport.date_occurrence)} ${formatTimeForPrint(currentReport.time_occurrence)} - ${currentReport.place_occurrence || 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">INVOLVED</span> : ${currentReport.involved || 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">NAME OF ESTABLISHMENT</span> : ${currentReport.establishment_name || 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">OWNER</span> : ${currentReport.owner || 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">OCCUPANT</span> : ${currentReport.occupant || 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">CASUALTY</span> :
                                <div style="margin-left: 15px; margin-top: 2px;">
                                    <div style="margin-bottom: 1px; font-size: 12px;">
                                        <span style="font-weight: bold;">Fatality</span> : ${currentReport.fatalities || 0}
                                    </div>
                                    <div style="margin-bottom: 1px; font-size: 12px;">
                                        <span style="font-weight: bold;">Injured</span> : ${currentReport.injured || 0}
                                    </div>
                                </div>
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">ESTIMATED DAMAGE</span> : ₱${formatNumberForPrint(currentReport.estimated_damage || 0)}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">TIME FIRE STARTED</span> : ${currentReport.time_fire_started ? formatDateTimeForPrint(currentReport.time_fire_started) : 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">TIME OF FIRE OUT</span> : ${currentReport.time_fire_out ? formatDateTimeForPrint(currentReport.time_fire_out) : 'N/A'}
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                <span style="font-weight: bold;">ALARM</span> : ${currentReport.highest_alarm_level || 'N/A'}
                            </div>
                        </div>
                        
                        <!-- Details of Investigation Section (BFP Format) -->
                        <div class="investigation-section" style="margin-bottom: 10px;">
                            <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">DETAILS OF INVESTIGATION:</div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                A complete narration of the details of the fire incident as gathered by the Fire Arson Investigator (FAI) during actual response. Details shall include, but are not limited, to the following:
                            </div>
                            <div style="margin-left: 15px; margin-bottom: 4px; font-size: 12px;">
                                <div style="margin-bottom: 1px;">a) Number of establishments and / or affected establishments</div>
                                <div style="margin-bottom: 1px;">b) Estimated area in square meters and the estimated amount of damage based on the computation in the 2015 BFP Operational Procedures Manual</div>
                                <div style="margin-bottom: 1px;">c) Location of fatalities and initial details as to identity</div>
                                <div style="margin-bottom: 1px;">d) Weather condition</div>
                            </div>
                            
                            <!-- Investigation Content -->
                            <div style="min-height: 80px; padding: 6px; font-size: 12px; margin-top: 5px;">
                                <div style="margin-bottom: 5px;">
                                    <strong>Investigation Details:</strong><br>
                                    ${currentReport.other_info || 'No additional investigation details provided.'}
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <strong>Number of Affected Establishments:</strong> ${currentReport.establishment_name ? '1' : '0'}<br>
                                    <strong>Estimated Area:</strong> ${currentReport.estimated_area_sqm || 0} square meters<br>
                                    <strong>Location of Fatalities:</strong> ${currentReport.location_of_fatalities || 'N/A'}<br>
                                    <strong>Weather Condition:</strong> ${currentReport.weather_condition || 'N/A'}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Disposition Section (BFP Format) -->
                        <div class="disposition-section" style="margin-bottom: 10px;">
                            <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">DISPOSITION:</div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                • The disposition and assessment of the FAI regarding the case.
                            </div>
                            <div style="margin-bottom: 4px; font-size: 12px;">
                                • May also contain whether the case will be turned over to the higher office.
                            </div>
                            
                            <!-- Disposition Content -->
                            <div style="min-height: 60px; padding: 6px; font-size: 12px; margin-top: 5px;">
                                <div style="margin-bottom: 5px;">
                                    <strong>FAI Assessment:</strong><br>
                                    ${currentReport.disposition || 'No disposition provided.'}
                                </div>
                                <div style="margin-bottom: 5px;">
                                    <strong>Case Status:</strong> ${currentReport.turned_over ? 'Turned over to higher office' : 'Under investigation'}<br>
                                    <strong>Report Status:</strong> Final
                                </div>
                            </div>
                        </div>
                        
                        <!-- Signature Block (BFP Format) -->
                        <div class="signature-section" style="margin-bottom: 10px; text-align: right;">
                            <div style="margin-bottom: 10px;">
                                <div style="border-bottom: 1px solid black; width: 150px; margin-left: auto; padding-bottom: 1px;">
                                    <div style="font-size: 12px; text-align: center;">(Name and signature of the FAI)</div>
                                </div>
                                <div style="font-size: 12px; margin-top: 4px; text-align: center; font-weight: bold; width: 150px; margin-left: auto;">
                                    ${currentReport.investigator_name || 'Fire Arson Investigator'}
                                </div>
                            </div>
                        </div>
                        
                        <!-- Official BFP Footer -->
                        <div class="bfp-footer" style="font-size: 6px; color: black; border-top: 1px solid #ccc; padding-top: 3px; text-align: left;">
                            <span>BFP- QSF-FAID-002 Rev. 02 (02.03.25) Page 1 of 2</span>
                        </div>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
                printWindow.close();
            }, 250);
        }
    </script>

    <!-- Final Report Details Modal -->
    <div class="modal fade" id="finalReportModal" tabindex="-1" aria-labelledby="finalReportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content modern-modal">
                <div class="modal-header modern-modal-header">
                    <h6 class="modal-title" id="finalReportModalLabel">
                        <i class="fas fa-file-alt me-2"></i>Final Report Details
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modern-modal-body" id="finalReportModalBody">
                    <!-- Fire Department Incident Report Form will be loaded here -->
                </div>
                <div class="modal-footer modern-modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="printFinalReport(<?php echo $report['id']; ?>)">
                        <i class="fas fa-print me-1"></i>Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php include '../../components/scripts.php'; ?>
</body>
</html>

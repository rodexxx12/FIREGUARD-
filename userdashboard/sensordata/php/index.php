<?php
session_start();
// Protect page: require logged-in user
if (!isset($_SESSION['user_id'])) {
	header('Location: ../../index.php');
	exit;
}
?>
<?php
// Fetch user's devices for the device filter
require_once __DIR__ . '/../../db/db.php';
$pdo = getDatabaseConnection();
$userId = (int)$_SESSION['user_id'];
$userDevices = [];
$buildingTypes = [];
try {
	$stmt = $pdo->prepare('SELECT device_id, device_name, serial_number FROM devices WHERE user_id = :user_id ORDER BY device_name');
	$stmt->execute(['user_id' => $userId]);
	$userDevices = $stmt->fetchAll();
} catch (Exception $e) {
	$userDevices = [];
}
// Fetch distinct building types for this user
try {
	$stmt = $pdo->prepare('SELECT DISTINCT building_type FROM buildings WHERE user_id = :user_id AND building_type IS NOT NULL AND building_type <> "" ORDER BY building_type');
	$stmt->execute(['user_id' => $userId]);
	$buildingTypes = array_map(function($row){ return $row['building_type']; }, $stmt->fetchAll());
} catch (Exception $e) {
	$buildingTypes = [];
}
?>
<?php include('../../components/header.php'); ?>
<style>
	/* Lightweight styles to match the provided gauge cards */
	.gauge-card-title { font-size: .85rem; letter-spacing: .03em; font-weight: 600; color: #6c757d; text-transform: uppercase; }
	.gauge-value { font-size: 1.75rem; font-weight: 700; color: #343a40; }
	.gauge-icon { width: 44px; height: 44px; border-radius: 12px; display: inline-block; }
	.gauge-legend { font-weight: 600; color: #495057; }
	.legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: .5rem; }
	.legend-danger { background-color: #dc3545; }
	.legend-warn { background-color: #f0ad4e; }
	.legend-ok { background-color: #28a745; }
	.legend-info { background-color: #3b82f6; }

	/* SVG semicircle gauge */
	.gauge-svg { width: 100%; height: auto; }
	.gauge-arc-track { stroke: #f1f3f5; stroke-width: 14; fill: none; }
	.gauge-arc-value { stroke-width: 14; fill: none; stroke-linecap: round; transition: stroke-dashoffset .4s ease; }

	/* Card spacing harmony - compact version */
	.gauge-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: .25rem; }
	.gauge-footer { margin-top: .25rem; display: flex; align-items: center; }
	.gauge-item { padding: 0.75rem; border: 1px solid #f1f3f5; border-radius: 0; background: #fafbfc; }
	.gauge-item:hover { background: #f8f9fa; }

	/* Modern white card & layout polish - compact version */
	.card-modern { border: 1px solid #eef1f5; border-radius: 0; box-shadow: 0 1px 4px rgba(16,24,40,.04); }
	.card-modern .card-body { padding: 0.75rem; }
	.card-modern:hover { box-shadow: 0 2px 8px rgba(16,24,40,.06); }
	.section-title { font-weight: 700; color: #101828; }
	.filters-wrap .form-label { font-size: .85rem; color: #6b7280; font-weight: 600; }
	.filters-wrap .form-select, .filters-wrap .form-control { border-radius: 0; border-color: #e5e7eb; }
	.filters-actions .btn { border-radius: 0; }
	
	/* Modern compact filter styling */
	.filter-field { margin-bottom: 0; }
	.filter-field .form-label { 
		font-size: .75rem; 
		color: #6b7280; 
		font-weight: 600; 
		text-transform: uppercase; 
		letter-spacing: 0.5px;
		margin-bottom: 0.25rem;
	}
	.filter-field .form-select-sm, .filter-field .form-control-sm { 
		border-radius: 0; 
		border-color: #e5e7eb; 
		font-size: .875rem;
		padding: 0.375rem 0.75rem;
		height: auto;
		min-height: 2rem;
		width: 100%;
	}
	.filter-field .form-select-sm:focus, .filter-field .form-control-sm:focus {
		border-color: #0d6efd;
		box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
	}
	.btn-primary { background: #0d6efd; border-color: #0d6efd; }
	.btn-outline-secondary { color: #475467; border-color: #e5e7eb; }
	.table-modern thead th { background: #f8fafc; color: #475467; font-weight: 700; border-bottom-color: #eef2f7; }
	.table-modern tbody td { vertical-align: middle; }
	/* Modern Bootstrap DataTables styling */
	.dataTables_wrapper {
		padding: 1rem;
		background: white;
		border-radius: 0.5rem;
		box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
	}
	.dataTables_wrapper .dataTables_length select { 
		border-radius: 0.375rem; 
		border: 1px solid #dee2e6;
		padding: 0.375rem 0.75rem;
		font-size: 0.875rem;
	}
	.dataTables_wrapper .dataTables_filter input { 
		border-radius: 0.375rem; 
		border: 1px solid #dee2e6; 
		padding: 0.375rem 0.75rem;
		font-size: 0.875rem;
		width: 250px;
	}
	.dataTables_wrapper .dataTables_filter input:focus,
	.dataTables_wrapper .dataTables_length select:focus {
		border-color: #86b7fe;
		box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
		outline: 0;
	}
	.dataTables_wrapper .dataTables_info {
		color: #6c757d;
		font-size: 0.875rem;
		font-weight: 500;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button { 
		border-radius: 0.375rem !important; 
		margin: 0 0.125rem;
		padding: 0.375rem 0.75rem !important;
		border: 1px solid #dee2e6 !important;
		background: white !important;
		color: #0d6efd !important;
		font-weight: 500 !important;
		transition: all 0.15s ease-in-out !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
		background: white !important;
		border-color: #0d6efd !important;
		color: #0a58ca !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.current {
		background: white !important;
		border-color: #0d6efd !important;
		color: #0d6efd !important;
		font-weight: 700 !important;
	}
	.dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
		background: white !important;
		color: #6c757d !important;
		border-color: #dee2e6 !important;
		cursor: not-allowed !important;
	}
	
	/* Modern Bootstrap table styling */
	.table {
		background: white;
		border-collapse: separate;
		border-spacing: 0;
	}
	.table thead th {
		background: #f8f9fa;
		color: #495057;
		font-weight: 600;
		font-size: 0.875rem;
		border-bottom: 2px solid #dee2e6;
		padding: 1rem 0.75rem;
		vertical-align: middle;
	}
	.table tbody td {
		padding: 0.75rem;
		border-bottom: 1px solid #dee2e6;
		font-size: 0.875rem;
		vertical-align: middle;
		background: white;
	}
	.table tbody tr:hover {
		background-color: #f8f9fa;
	}
	.table tbody tr:hover td {
		background-color: #f8f9fa;
	}
	
	/* Modern Bootstrap status badges */
	.status-badge {
		padding: 0.375rem 0.75rem;
		border-radius: 0.375rem;
		font-size: 0.75rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		display: inline-block;
	}
	.status-normal { 
		background-color: #d1e7dd; 
		color: #0f5132; 
		border: 1px solid #badbcc;
	}
	.status-monitoring { 
		background-color: #fff3cd; 
		color: #664d03; 
		border: 1px solid #ffecb5;
	}
	.status-emergency { 
		background-color: #f8d7da; 
		color: #721c24; 
		border: 1px solid #f5c2c7;
	}
	.status-warning { 
		background-color: #fff3cd; 
		color: #664d03; 
		border: 1px solid #ffecb5;
	}
	.status-critical { 
		background-color: #f8d7da; 
		color: #721c24; 
		border: 1px solid #f5c2c7;
	}
	
	/* Modern Bootstrap sensor value styling */
	.sensor-value {
		font-weight: 600;
		font-size: 0.875rem;
		padding: 0.25rem 0.5rem;
		border-radius: 0.25rem;
		background-color: #f8f9fa;
		border: 1px solid #dee2e6;
		display: inline-block;
	}
	.sensor-smoke { 
		color: #0d6efd; 
		background-color: #cfe2ff;
		border-color: #9ec5fe;
	}
	.sensor-temp { 
		color: #dc3545; 
		background-color: #f8d7da;
		border-color: #f5c2c7;
	}
	.sensor-heat { 
		color: #fd7e14; 
		background-color: #ffeaa7;
		border-color: #fdcb6e;
	}
	.sensor-flame { 
		color: #198754; 
		background-color: #d1e7dd;
		border-color: #badbcc;
	}
	
	/* Modern Bootstrap device badge styling */
	.device-badge {
		display: inline-flex;
		align-items: center;
		padding: 0.375rem 0.75rem;
		border-radius: 0.375rem;
		font-size: 0.75rem;
		font-weight: 500;
		background-color: #f8f9fa;
		color: #495057;
		border: 1px solid #dee2e6;
		transition: all 0.15s ease-in-out;
	}
	.device-badge::before {
		content: '';
		width: 8px;
		height: 8px;
		border-radius: 50%;
		background-color: #198754;
		margin-right: 0.5rem;
		box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.2);
	}
	
	/* Device badge color variants based on status */
	.device-badge.device-normal {
		background-color: #d1e7dd;
		color: #0f5132;
		border-color: #badbcc;
	}
	.device-badge.device-normal::before {
		background-color: #198754;
		box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.2);
	}
	
	.device-badge.device-monitoring {
		background-color: #fff3cd;
		color: #664d03;
		border-color: #ffecb5;
	}
	.device-badge.device-monitoring::before {
		background-color: #fd7e14;
		box-shadow: 0 0 0 2px rgba(253, 126, 20, 0.2);
	}
	
	.device-badge.device-emergency {
		background-color: #f8d7da;
		color: #721c24;
		border-color: #f5c2c7;
	}
	.device-badge.device-emergency::before {
		background-color: #dc3545;
		box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.2);
	}
</style>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
        <div class="col-md-3 left_col">
          <div class="left_col scroll-view">

            <!-- /menu footer buttons -->
            <?php include(__DIR__ . '/../../components/sidebar.php'); ?>
            
            </div>
            <!-- /menu footer buttons -->
          </div>
        </div>

        <!-- top navigation -->
        <?php include(__DIR__ . '/../../components/navigation.php')?>
        <!-- /top navigation -->

        <div class="right_col" role="main">        
    <main class="main-content">
	

		<!-- Combined Sensor Data Dashboard Card -->
		<div class="card card-modern">
			<div class="card-body">
				<!-- Sensor Data Gauges Section -->
			
				<div class="row g-2 mb-4" id="sensorGauges">
					<!-- Smoke -->
					<div class="col-12 col-md-3">
						<div class="gauge-item">
							<div class="gauge-head">
								<div class="gauge-icon" style="background-color:#3b82f6"></div>
								<div class="text-end">
									<div class="gauge-card-title">Smoke Level</div>
									<div class="gauge-value"><span id="smokeValueNum">--</span> <span class="text-muted" style="font-weight:600;">ppm</span></div>
								</div>
							</div>
							<svg class="gauge-svg" viewBox="0 0 160 90" preserveAspectRatio="xMidYMid meet">
								<path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
								<path id="smokeArc" class="gauge-arc-value" stroke="#3b82f6" d="M20,80 A60,60 0 0 1 140,80" />
							</svg>
							<div class="gauge-footer"><span class="legend-dot legend-info"></span><span id="smokeLegend" class="gauge-legend">Dangerous Level</span></div>
							<!-- Latest Reading Info for Smoke -->
							<div class="mt-1 text-start">
								<div class="small text-muted" style="font-size: 0.6rem;">Latest: <span id="smokeLatestTime" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Device: <span id="smokeLatestDevice" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Status: <span id="smokeLatestStatus" style="font-size: 0.6rem;">--</span></div>
							</div>
						</div>
					</div>
					<!-- Temperature -->
					<div class="col-12 col-md-3">
						<div class="gauge-item">
							<div class="gauge-head">
								<div class="gauge-icon" style="background-color:#dc3545"></div>
								<div class="text-end">
									<div class="gauge-card-title">Temperature</div>
									<div class="gauge-value"><span id="tempValueNum">--</span> <span class="text-muted" style="font-weight:600;">°C</span></div>
								</div>
							</div>
							<svg class="gauge-svg" viewBox="0 0 160 90" preserveAspectRatio="xMidYMid meet">
								<path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
								<path id="tempArc" class="gauge-arc-value" stroke="#dc3545" d="M20,80 A60,60 0 0 1 140,80" />
							</svg>
							<div class="gauge-footer"><span class="legend-dot legend-danger"></span><span id="tempLegend" class="gauge-legend">Critical</span></div>
							<!-- Latest Reading Info for Temperature -->
							<div class="mt-1 text-start">
								<div class="small text-muted" style="font-size: 0.6rem;">Latest: <span id="tempLatestTime" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Device: <span id="tempLatestDevice" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Status: <span id="tempLatestStatus" style="font-size: 0.6rem;">--</span></div>
							</div>
						</div>
					</div>
					<!-- Heat -->
					<div class="col-12 col-md-3">
						<div class="gauge-item">
							<div class="gauge-head">
								<div class="gauge-icon" style="background-color:#f0ad4e"></div>
								<div class="text-end">
									<div class="gauge-card-title">Heat Index</div>
									<div class="gauge-value"><span id="heatValueNum">--</span> <span class="text-muted" style="font-weight:600;">°C</span></div>
								</div>
							</div>
							<svg class="gauge-svg" viewBox="0 0 160 90" preserveAspectRatio="xMidYMid meet">
								<path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
								<path id="heatArc" class="gauge-arc-value" stroke="#f0ad4e" d="M20,80 A60,60 0 0 1 140,80" />
							</svg>
							<div class="gauge-footer"><span class="legend-dot legend-warn"></span><span id="heatLegend" class="gauge-legend">Dangerous</span></div>
							<!-- Latest Reading Info for Heat -->
							<div class="mt-1 text-start">
								<div class="small text-muted" style="font-size: 0.6rem;">Latest: <span id="heatLatestTime" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Device: <span id="heatLatestDevice" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Status: <span id="heatLatestStatus" style="font-size: 0.6rem;">--</span></div>
							</div>
						</div>
					</div>
					<!-- Flame -->
					<div class="col-12 col-md-3">
						<div class="gauge-item">
							<div class="gauge-head">
								<div class="gauge-icon" style="background-color:#28a745"></div>
								<div class="text-end">
									<div class="gauge-card-title">Flame Detection</div>
									<div class="gauge-value"><span id="flameValueText">Not Detected</span></div>
								</div>
							</div>
							<svg class="gauge-svg" viewBox="0 0 160 90" preserveAspectRatio="xMidYMid meet">
								<path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
								<path id="flameArc" class="gauge-arc-value" stroke="#28a745" d="M20,80 A60,60 0 0 1 140,80" />
							</svg>
							<div class="gauge-footer"><span id="flameLegendDot" class="legend-dot legend-ok"></span><span id="flameLegend" class="gauge-legend">No Flame</span></div>
							<!-- Latest Reading Info for Flame -->
							<div class="mt-1 text-start">
								<div class="small text-muted" style="font-size: 0.6rem;">Latest: <span id="flameLatestTime" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Device: <span id="flameLatestDevice" style="font-size: 0.6rem;">--</span></div>
								<div class="small text-muted" style="font-size: 0.6rem;">Status: <span id="flameLatestStatus" style="font-size: 0.6rem;">--</span></div>
							</div>
						</div>
					</div>
				</div>

				<!-- Data Filters Section -->
				<div class="card shadow-sm border-0 mb-4">
					<div class="card-body">
						<h6 class="text-muted mb-3 fw-semibold text-uppercase" style="letter-spacing: 0.5px;">
							<i class="bi bi-funnel me-2"></i>Data Filters
						</h6>
						<div class="d-flex align-items-center justify-content-between mb-2">
							<div class="d-flex gap-2">
						
							</div>
						</div>
						<div class="row g-3">
							<!-- First Column -->
							<div class="col-md-6">
								<div class="row g-2">
									<div class="col-12">
										<div class="filter-field">
											<label for="filterDevice" class="form-label small fw-semibold text-muted mb-1">Device</label>
											<select id="filterDevice" class="form-select form-select-sm">
												<option value="">All devices</option>
												<?php foreach ($userDevices as $d): ?>
													<option value="<?php echo htmlspecialchars($d['device_id']); ?>">
														<?php echo htmlspecialchars($d['device_name'] !== '' ? $d['device_name'] : ('Device #' . $d['device_id'])); ?>
													</option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
									<div class="col-12">
										<div class="filter-field">
											<label for="filterStatus" class="form-label small fw-semibold text-muted mb-1">Status</label>
											<select id="filterStatus" class="form-select form-select-sm">
												<option value="">All</option>
												<option value="normal">Normal</option>
												<option value="warning">Warning</option>
												<option value="critical">Critical</option>
											</select>
										</div>
									</div>
									<div class="col-12">
										<div class="filter-field">
											<label for="filterType" class="form-label small fw-semibold text-muted mb-1">Building Type</label>
											<select id="filterType" class="form-select form-select-sm">
												<option value="">All types</option>
												<?php foreach ($buildingTypes as $t): ?>
													<option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($t); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>
								</div>
							</div>
							<!-- Second Column -->
							<div class="col-md-6">
								<div class="row g-2">
									<div class="col-12">
										<div class="filter-field">
											<label for="startDate" class="form-label small fw-semibold text-muted mb-1">Start Date</label>
											<input type="date" id="startDate" class="form-control form-control-sm">
										</div>
									</div>
									<div class="col-12">
										<div class="filter-field">
											<label for="endDate" class="form-label small fw-semibold text-muted mb-1">End Date</label>
											<input type="date" id="endDate" class="form-control form-control-sm">
										</div>
									</div>
									<div class="col-12 mt-3">
										<button id="resetFilters" class="btn btn-danger btn-sm px-3 py-2" style="border-radius: 8px; font-weight: 600; letter-spacing: 0.5px; transition: all 0.3s ease;">
											<i class="bi bi-arrow-clockwise me-1"></i>Reset
										</button>
									</div>
								</div>
							</div>
							
						</div>
					</div>
				</div>


				<!-- Data Table Section -->
				<div class="x_panel">
					<div class="x_title">
						<h2>Sensor Data <small>Live Feed</small></h2>
						<div class="clearfix"></div>
					</div>
					<div class="x_content">
						<div class="card-box table-responsive">
							<table id="fireDataTable" class="table table-striped table-bordered">
								<thead>
									<tr>
										<th style="display:none;">ID</th>
										<th>Timestamp</th>
										<th>Device</th>
										<th style="display:none;">Building</th>
										<th style="display:none;">Address</th>
										<th>Type</th>
										<th>Status</th>
										<th>Smoke</th>
										<th>Temp</th>
										<th>Heat</th>
										<th>Flame</th>
										<th style="display:none;">ML Conf.%</th>
										<th style="display:none;">ML Pred.</th>
									</tr>
								</thead>
								<tbody></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php include(__DIR__ . '/../../components/footer.php'); ?>
<!-- DataTables (JS) -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function() {
	const table = $('#fireDataTable').DataTable({
		processing: true,
		serverSide: true,
		order: [[1, 'desc']],
		ajax: {
			url: 'get_user_fire_data.php',
			type: 'GET',
			data: function(d) {
				return $.extend({}, d, {
							device_id: $('#filterDevice').val(),
					status: $('#filterStatus').val(),
					building_type: $('#filterType').val(),
					start_date: $('#startDate').val(),
					end_date: $('#endDate').val()
				});
			},
			error: function(xhr, error, thrown) {
				console.error('DataTables Ajax error:', error, thrown);
				console.error('Response:', xhr.responseText);
				
				let errorMessage = 'Unable to load data. ';
				if (xhr.status === 401) {
					errorMessage += 'Please refresh the page and log in again.';
					Swal.fire({
						icon: 'error',
						title: 'Session Expired',
						text: 'Your session has expired. Please refresh the page and log in again.',
						confirmButtonText: 'Refresh Page',
						allowOutsideClick: false
					}).then(() => {
						window.location.reload();
					});
				} else if (xhr.status === 500) {
					errorMessage += 'Server error occurred. Please try again later.';
					Swal.fire({
						icon: 'error',
						title: 'Server Error',
						text: 'A server error occurred while loading data. Please try again later.',
						confirmButtonText: 'OK'
					});
				} else {
					errorMessage += 'Please check your connection and try again.';
					Swal.fire({
						icon: 'warning',
						title: 'Connection Error',
						text: 'Unable to connect to the server. Please check your connection and try again.',
						confirmButtonText: 'OK'
					});
				}
				
				// Show error in console for debugging
				console.error('DataTables Error Details:', {
					status: xhr.status,
					statusText: xhr.statusText,
					responseText: xhr.responseText,
					error: error,
					thrown: thrown
				});
			}
		},
		columns: [
			{ data: 'id' },
			{ data: 'timestamp', render: function(data) {
				if (!data) return '<span class="text-muted">-</span>';
				const date = new Date(data);
				const dateStr = date.toLocaleDateString();
				const timeStr = date.toLocaleTimeString();
				return '<div class="small text-muted">' + dateStr + '</div><div class="small">' + timeStr + '</div>';
			}},
			{ data: 'device_name', render: function(data, type, row) {
				const deviceName = data || ('Device #' + (row.device_id || 'Unknown'));
				const status = row.status || 'normal';
				const deviceClass = 'device-' + status;
				return '<span class="device-badge ' + deviceClass + '">' + deviceName + '</span>';
			}},
			{ data: 'building_name', defaultContent: '<span class="text-muted">-</span>' },
			{ data: 'address', render: function(data, type, row) {
				if (!data && (!row.latitude || !row.longitude)) {
					return '<span class="text-muted">-</span>';
				}
				
				let html = '';
				
				// Display address if available
				if (data) {
					html += '<div class="small text-muted mb-1" style="font-size: 0.75rem;">' + data + '</div>';
				}
				
				// Display coordinates
				if (row.latitude && row.longitude) {
					const coords = row.latitude + ', ' + row.longitude;
					html += '<div class="small" style="font-size: 0.75rem; color: #0d6efd; font-weight: 500;">';
					html += '<i class="bi bi-geo-alt-fill me-1"></i>' + coords;
					html += '</div>';
				} else if (!data) {
					html = '<span class="text-muted">-</span>';
				}
				
				return html || '<span class="text-muted">-</span>';
			}},
			{ data: 'building_type', render: function(data) {
				return data ? '<span class="badge bg-light text-dark">' + data + '</span>' : '<span class="text-muted">-</span>';
			}},
			{ data: 'status', render: function(data) {
				const status = data || 'normal';
				const statusClass = 'status-' + status;
				return '<span class="status-badge ' + statusClass + '">' + status + '</span>';
			}},
			{ data: 'smoke', render: function(data) {
				return data !== null ? '<span class="sensor-value sensor-smoke">' + data + ' ppm</span>' : '<span class="text-muted">-</span>';
			}},
			{ data: 'temp', render: function(data) {
				return data !== null ? '<span class="sensor-value sensor-temp">' + data + '°C</span>' : '<span class="text-muted">-</span>';
			}},
			{ data: 'heat', render: function(data) {
				return data !== null ? '<span class="sensor-value sensor-heat">' + data + '°C</span>' : '<span class="text-muted">-</span>';
			}},
			{ data: 'flame_detected', render: function(data) {
				const isDetected = data == 1;
				const colorClass = isDetected ? 'sensor-flame' : 'text-muted';
				const text = isDetected ? 'Detected' : 'None';
				return '<span class="sensor-value ' + colorClass + '">' + text + '</span>';
			}},
			{ data: 'ml_confidence', render: d => d !== null ? Number(d).toFixed(2) : '' },
			{ data: 'ml_prediction', render: d => d == 1 ? 'Fire' : 'No Fire' }
		],
		columnDefs: [
			{ targets: 0, visible: false, searchable: false },
			{ targets: 3, visible: false, searchable: false },
			{ targets: 4, visible: false, searchable: false },
			{ targets: 11, visible: false, searchable: false },
			{ targets: 12, visible: false, searchable: false }
		],
		lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
		pageLength: 10,
		language: {
			emptyTable: "No sensor data available. Data will appear here when your devices start sending readings.",
			zeroRecords: "No matching records found. Try adjusting your filters.",
			processing: "Loading sensor data...",
			loadingRecords: "Loading sensor data...",
			error: "An error occurred while loading data"
		}
	});

			// Auto-refresh every 5 seconds without resetting pagination
			const REFRESH_INTERVAL_MS = 5000;
			let autoRefreshId = setInterval(function() {
				// Only refresh when tab is visible
				if (!document.hidden) {
					table.ajax.reload(null, false);
				refreshGauges();
				}
			}, REFRESH_INTERVAL_MS);

			// Also refresh once when the tab becomes visible again
			document.addEventListener('visibilitychange', function() {
				if (!document.hidden) {
					table.ajax.reload(null, false);
				refreshGauges();
				}
			});

		function clamp(val, min, max) {
			return Math.max(min, Math.min(max, val));
		}

		// Render semicircle by adjusting stroke-dashoffset on the SVG path
		function renderGauge(pathEl, value, maxValue) {
			const v = (value === null || value === undefined) ? 0 : Number(value);
			const max = maxValue > 0 ? maxValue : 100;
			// Compute arc length dynamically for current path size
			const pathLength = pathEl.getTotalLength ? pathEl.getTotalLength() : 180;
			const pct = clamp(v / max, 0, 1);
			const offset = pathLength * (1 - pct);
			pathEl.setAttribute('stroke-dasharray', String(pathLength));
			pathEl.setAttribute('stroke-dashoffset', String(offset));
		}

		function refreshGauges() {
			const deviceId = $('#filterDevice').val();
			$.get('get_latest_user_sensor_readings.php', { device_id: deviceId })
				.done(function(resp) {
					if (!resp || !resp.data) {
						console.warn('No gauge data available');
						return;
					}
					const r = resp.data;
					
					// Format timestamp for display
					let timeDisplay = '--';
					let deviceDisplay = r.device_name ?? '--';
					let statusDisplay = r.status ?? '--';
					
					if (r.timestamp) {
						const timestamp = new Date(r.timestamp);
						const dateStr = timestamp.toLocaleDateString();
						const timeStr = timestamp.toLocaleTimeString();
						timeDisplay = `${dateStr} ${timeStr}`;
					}
					
					// Latest Reading Info (main section) - removed references to non-existent elements
					
					// Update individual gauge latest info
					// Smoke gauge
					document.getElementById('smokeLatestTime').textContent = timeDisplay;
					document.getElementById('smokeLatestDevice').textContent = deviceDisplay;
					document.getElementById('smokeLatestStatus').textContent = statusDisplay;
					
					// Temperature gauge
					document.getElementById('tempLatestTime').textContent = timeDisplay;
					document.getElementById('tempLatestDevice').textContent = deviceDisplay;
					document.getElementById('tempLatestStatus').textContent = statusDisplay;
					
					// Heat gauge
					document.getElementById('heatLatestTime').textContent = timeDisplay;
					document.getElementById('heatLatestDevice').textContent = deviceDisplay;
					document.getElementById('heatLatestStatus').textContent = statusDisplay;
					
					// Flame gauge
					document.getElementById('flameLatestTime').textContent = timeDisplay;
					document.getElementById('flameLatestDevice').textContent = deviceDisplay;
					document.getElementById('flameLatestStatus').textContent = statusDisplay;
					
					// Numbers
					document.getElementById('smokeValueNum').textContent = (r.smoke ?? '--');
					document.getElementById('heatValueNum').textContent = (r.heat ?? '--');
					document.getElementById('tempValueNum').textContent = (r.temp ?? '--');

					// Arcs
					renderGauge(document.getElementById('smokeArc'), r.smoke, 30000);
					renderGauge(document.getElementById('heatArc'), r.heat, 30000);
					renderGauge(document.getElementById('tempArc'), r.temp, 30000);

					// Legends based on simple thresholds (tune to your real ranges)
					document.getElementById('smokeLegend').textContent = (Number(r.smoke) >= 15000 ? 'Dangerous Level' : 'Normal');
					document.getElementById('heatLegend').textContent = (Number(r.heat) >= 15000 ? 'Dangerous' : 'Normal');
					document.getElementById('tempLegend').textContent = (Number(r.temp) >= 20000 ? 'Critical' : 'Normal');

					// Flame
					const flameDetected = Number(r.flame_detected) === 1;
					document.getElementById('flameValueText').textContent = flameDetected ? 'Detected' : 'Not Detected';
					renderGauge(document.getElementById('flameArc'), flameDetected ? 1 : 0, 1);
					const flameDot = document.getElementById('flameLegendDot');
					flameDot.className = 'legend-dot ' + (flameDetected ? 'legend-danger' : 'legend-ok');
					document.getElementById('flameLegend').textContent = flameDetected ? 'Flame' : 'No Flame';
				})
				.fail(function(xhr, status, error) {
					console.error('Gauge refresh failed:', status, error);
					console.error('Response:', xhr.responseText);
					
					// Show a subtle notification for gauge refresh failures
					if (xhr.status === 401) {
						console.warn('Session expired during gauge refresh');
					} else {
						console.warn('Gauge refresh failed, but continuing with existing data');
					}
				});
		}

		// Initial load
		refreshGauges();

	$('#applyFilters').on('click', function() {
		table.ajax.reload();
			refreshGauges();
	});

	$('#resetFilters').on('click', function() {
		$('#filterDevice').val('');
		$('#filterStatus').val('');
		$('#filterType').val('');
		$('#startDate').val('');
		$('#endDate').val('');
		table.ajax.reload();
		refreshGauges();
		
		// Show success message
		Swal.fire({
			icon: 'success',
			title: 'Reset Complete!',
			timer: 1500,
			showConfirmButton: false
		});
	});
});
</script>
 <?php include('../../../../components/scripts.php'); ?>
</body>
</html>



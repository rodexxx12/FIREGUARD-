<?php require_once '../functions/functions.php';?>
<?php
// Handle migration trigger (admin only)
if (isset($_POST['migrate_incidents']) && isset($_SESSION['admin_id'])) {
    $result = migrateAllIncidentsToIncidentReports();
    if ($result['success']) {
        $msg = "Successfully migrated {$result['inserted']} incidents.";
        if (!empty($result['errors'])) {
            $msg .= " Errors: " . implode('; ', $result['errors']);
        }
        echo "<div class='alert alert-success'>{$msg}</div>";
    } else {
        echo "<div class='alert alert-danger'>Migration failed: {$result['error']}</div>";
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'search') {
    require_once '../functions/functions.php';
    $status = $_POST['status'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
    $perPage = isset($_POST['perPage']) ? (int)$_POST['perPage'] : 10;
    $incidents = getAcknowledgedIncidentsFiltered($status, $start_date, $end_date, $page, $perPage);
    header('Content-Type: application/json');
    echo json_encode($incidents);
    exit;
}
?>
<?php include('../../components/header.php'); ?>
<link rel="stylesheet" href="../css/style.css">
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
        
          
        <?php if (!isset($_GET['incident_id'])): ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="metric-card metric-card-1">
                    <div class="metric-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Total Incidents</div>
                        <div class="metric-value" id="total-incidents"><?= number_format($totalIncidents) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card metric-card-2">
                    <div class="metric-icon">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Active Incidents</div>
                        <div class="metric-value" id="active-incidents"><?= number_format(rand(1, $totalIncidents)) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card metric-card-3">
                    <div class="metric-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Avg Response Time</div>
                        <div class="metric-value" id="avg-response-time"><?= rand(5, 15) ?> min</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card metric-card-4">
                    <div class="metric-icon">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="metric-content">
                        <div class="metric-title">Critical Incidents</div>
                        <div class="metric-value" id="critical-incidents"><?= number_format(rand(1, (int)ceil($totalIncidents/4))) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-section">
            <div class="row">
                <!-- Monthly Trends Bar Chart -->
                <div class="col-md-8">
                    <div class="chart-container">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="chart-title mb-0">
                                <span class="real-time-indicator"></span>
                                Incident Trends
                            </div>
                            <div style="min-width: 180px;">
                                <select id="monthly-period-filter" class="form-select form-select-sm" aria-label="Select time granularity">
                                    <option value="daily">Daily (current month)</option>
                                    <option value="monthly" selected>Monthly (last 12 months)</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="chart-wrapper large">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                        <div class="chart-stats">
                            <div class="chart-stat">
                                <div class="chart-stat-value" id="total-monthly">0</div>
                                <div class="chart-stat-label">Total This Month</div>
                            </div>
                            <div class="chart-stat">
                                <div class="chart-stat-value" id="flame-monthly">0</div>
                                <div class="chart-stat-label">Flame Incidents</div>
                            </div>
                            <div class="chart-stat">
                                <div class="chart-stat-value" id="smoke-monthly">0</div>
                                <div class="chart-stat-label">Smoke Incidents</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Building Type Pie Chart -->
                <div class="col-md-4">
                    <div class="chart-container">
                                                    <div class="d-flex justify-content-between align-items-center">
                                <div class="chart-title mb-0">
                                    <span class="real-time-indicator"></span>
                                    Incidents by Building Type
                                </div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <div id="building-time-filter-chip" style="display:none;">
                                    <span class="badge bg-dark" id="building-time-filter-label"></span>
                                    <button id="clear-building-time-filter" class="btn btn-sm btn-outline-secondary ms-2">Clear</button>
                                </div>
                                <div id="building-incident-type-chip" style="display:none;">
                                    <span class="badge bg-secondary" id="building-incident-type-label"></span>
                                    <button id="clear-building-incident-type" class="btn btn-sm btn-outline-secondary ms-2">Clear</button>
                                </div>
                            </div>
                            <div class="chart-wrapper">
                                <canvas id="buildingTypeChart"></canvas>
                            </div>
                            <div class="chart-stats">
                                <div class="chart-stat">
                                    <div class="chart-stat-value" id="building-types-count">0</div>
                                    <div class="chart-stat-label">Building Types</div>
                                </div>
                            </div>
                    </div>
                </div>
            </div>
            
            <!-- Removed Severity Level and Real-time Statistics sections as requested -->
        </div>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-list"></i> Acknowledged Incidents
                </div>
                <div class="d-flex align-items-center gap-2">
                    <!-- Date Range Filter -->
                    <input type="date" id="incident-date-start" class="form-control form-control-sm" style="min-width: 150px;" placeholder="Start date">
                    <span>to</span>
                    <input type="date" id="incident-date-end" class="form-control form-control-sm" style="min-width: 150px;" placeholder="End date">
                    <!-- Status Filter -->
                    <select id="incident-filter" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <option value="ACKNOWLEDGED">Acknowledged</option>
                    </select>
                    <span class="badge bg-dark">Total: <?= number_format($totalIncidents) ?></span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($incidents)): ?>
                    <div class="alert alert-info">No acknowledged incidents found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Incident ID</th>
                                    <th>Date & Time</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th>Readings</th>
                                    <th>Acknowledged</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="incidents-table-body">
                                <?php foreach (
                                    $incidents as $incident): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">#<?= htmlspecialchars($incident['id']) ?></span>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($incident['incident_time'])) ?><br>
                                            <small class="text-muted"><?= date('g:i A', strtotime($incident['incident_time'])) ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($incident['building_name'] ?? 'N/A') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($incident['building_address'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge severity-<?= strtolower($incident['status']) ?>" aria-label="Status: <?= htmlspecialchars($incident['status']) ?>">
                                                <?= htmlspecialchars($incident['status']) ?: '<span class="text-muted">No status</span>' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <span class="badge bg-danger bg-opacity-10 text-white" aria-label="Temperature">
                                                    <i class="fas fa-temperature-high"></i> <?= htmlspecialchars($incident['temp']) !== '' ? htmlspecialchars($incident['temp']) . '°C' : '<span class=\'text-muted\'>N/A</span>' ?>
                                                </span>
                                                <span class="badge bg-warning bg-opacity-10 text-warning" aria-label="Smoke Level">
                                                    <i class="fas fa-smog"></i> <?= htmlspecialchars($incident['smoke']) !== '' ? htmlspecialchars($incident['smoke']) . ' ppm' : '<span class=\'text-muted\'>N/A</span>' ?>
                                                </span>
                                                <?php if (isset($incident['flame_detected']) && $incident['flame_detected']): ?>
                                                    <span class="badge bg-danger bg-opacity-10 text-white" aria-label="Flame Detected">
                                                        <i class="fas fa-fire"></i> Flame
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium"><?= htmlspecialchars($incident['admin_name'] ?? $incident['acknowledged_by']) ?></div>
                                            <small class="text-muted"><?= date('M j, g:i A', strtotime($incident['acknowledged_at'])) ?></small>
                                        </td>
                                        <td>
                                            <a href="?incident_id=<?= $incident['id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Incident pagination">
                        <ul class="pagination">
                            <?php if ($currentPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $currentPage - 1 ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $currentPage ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($currentPage < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $currentPage + 1 ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
            <?php if ($incidentDetails): ?>
                <div class="row mb-4">
                    <div class="col-md-8">
                        <h2 class="section-title">
                            <i class="fas fa-file-alt"></i> Incident Report #<?= htmlspecialchars($incidentDetails['id'] ?? '') ?>
                        </h2>
                        <p class="text-muted">Detailed information about this fire incident</p>
                    </div> 
                    <div class="col-md-4 text-end">
                        <form action="" method="post" style="display: inline-block;">
                            <input type="hidden" name="incident_id" value="<?= $incidentDetails['id'] ?>">
                            <button type="submit" name="generate_pdf" class="btn btn-danger">
                                <i class="fas fa-file-pdf"></i> Generate PDF
                            </button>
                            <!-- <button type="button" onclick="history.back()" class="btn btn-secondary">
                                Back
                            </button> -->
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-info-circle"></i> Incident Overview
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h6 class="fw-semibold text-muted">INCIDENT ID</h6>
                                            <p>#<?= htmlspecialchars($incidentDetails['id'] ?? '') ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-semibold text-muted">DATE & TIME</h6>
                                            <p><?= date('F j, Y g:i A', strtotime($incidentDetails['timestamp'])) ?></p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-semibold text-muted">LOCATION</h6>
                                            <p>
                                                <?= htmlspecialchars($incidentDetails['building_name'] ?? '') ?><br>
                                                <?= htmlspecialchars($incidentDetails['building_address'] ?? '') ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <h6 class="fw-semibold text-muted">SEVERITY</h6>
                                            <p>
                                                <span class="badge severity-<?= strtolower($incidentDetails['status']) ?>">
                                                    <?= htmlspecialchars($incidentDetails['status']) ?>
                                                </span>
                                            </p>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-semibold text-muted">TRIGGERS</h6>
                                            <div class="d-flex gap-2">
                                                <?= ($incidentDetails['smoke'] > 0 ? '<span class="badge bg-danger bg-opacity-10 text-white">Smoke</span>' : '') ?>
                                                <?= ($incidentDetails['heat'] > 0 ? '<span class="badge bg-warning bg-opacity-10 text-warning">Heat</span>' : '') ?>
                                                <?= ($incidentDetails['flame_detected'] ? '<span class="badge bg-danger bg-opacity-10 text-white">Flame</span>' : '') ?>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <h6 class="fw-semibold text-muted">COORDINATES</h6>
                                            <p>
                                                <?= htmlspecialchars($incidentDetails['geo_lat'] ?? '') ?>, <?= htmlspecialchars($incidentDetails['geo_long'] ?? '') ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="divider"></div>
                                
                                <div class="row">
                                    <div class="col-md-4 text-center">
                                        <h6 class="fw-semibold text-muted">TEMPERATURE</h6>
                                        <h3 class="text-danger"><?= htmlspecialchars($incidentDetails['temp']) ?>°C</h3>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <h6 class="fw-semibold text-muted">SMOKE LEVEL</h6>
                                        <h3 class="text-warning"><?= htmlspecialchars($incidentDetails['smoke']) ?> ppm</h3>
                                    </div>
                                    <div class="col-md-4 text-center">
                                        <h6 class="fw-semibold text-muted">HEAT LEVEL</h6>
                                        <h3 class="text-danger"><?= htmlspecialchars($incidentDetails['heat']) ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-bell"></i> Alert Timeline
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-dot bg-danger">
                                            <i class="fas fa-fire"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?= date('F j, Y g:i A', strtotime($incidentDetails['timestamp'])) ?></div>
                                            <h5 class="timeline-title">Fire Incident Detected</h5>
                                            <p class="timeline-description">
                                                <?= $incidentDetails['device_name'] ?? 'Unknown device' ?> detected 
                                                <?= ($incidentDetails['smoke'] > 0 ? 'smoke ' : '') ?>
                                                <?= ($incidentDetails['heat'] > 0 ? 'heat ' : '') ?>
                                                <?= ($incidentDetails['flame_detected'] ? 'flame' : '') ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item">
                                        <div class="timeline-dot bg-primary">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?= date('F j, Y g:i A', strtotime($incidentDetails['timestamp'] . ' +1 minute')) ?></div>
                                            <h5 class="timeline-title">Alerts Sent</h5>
                                            <p class="timeline-description">
                                                Notifications sent to <?= htmlspecialchars($incidentDetails['owner_name']) ?> and Bago City Fire Station
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item">
                                        <div class="timeline-dot bg-success">
                                            <i class="fas fa-user-check"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?= date('F j, Y g:i A', strtotime($incidentDetails['acknowledged_at'])) ?></div>
                                            <h5 class="timeline-title">Incident Acknowledged</h5>
                                            <p class="timeline-description">
                                                By <?= htmlspecialchars($incidentDetails['admin_name'] ?? $incidentDetails['acknowledged_by']) ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($incidentDetails['response_time'])): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-dot bg-info">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <div class="timeline-content">
                                            <div class="timeline-date"><?= date('F j, Y g:i A', strtotime($incidentDetails['response_time'])) ?></div>
                                            <h5 class="timeline-title">Response Team Dispatched</h5>
                                            <p class="timeline-description">
                                                <?= htmlspecialchars($incidentDetails['response_type']) ?> response
                                                <?php if (isset($incidentDetails['firefighter_name'])): ?>
                                                    by <?= htmlspecialchars($incidentDetails['firefighter_name']) ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($sensorData)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-microchip"></i> Sensor Data
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Sensor</th>
                                                <th>Type</th>
                                                <th>Temp</th>
                                                <th>Smoke</th>
                                                <th>Status</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sensorData as $sensor): ?>
                                                <tr>
                                                    <td>
                                                        <div class="fw-semibold"><?= htmlspecialchars($sensor['sensor_id']) ?></div>
                                                        <small class="text-muted"><?= htmlspecialchars($sensor['location']) ?></small>
                                                    </td>
                                                    <td><?= htmlspecialchars($sensor['sensor_type']) ?></td>
                                                    <td><?= htmlspecialchars($sensor['temperature']) ?>°C</td>
                                                    <td><?= htmlspecialchars($sensor['smoke_level']) ?> ppm</td>
                                                    <td>
                                                        <span class="log-<?= strtolower($sensor['log_level']) ?>">
                                                            <?= htmlspecialchars($sensor['log_level']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('H:i', strtotime($sensor['created_at'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-building"></i> Building Details
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">BUILDING NAME</h6>
                                    <p><?= htmlspecialchars($incidentDetails['building_name'] ?? '') ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">ADDRESS</h6>
                                    <p><?= htmlspecialchars($incidentDetails['building_address'] ?? '') ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">TYPE</h6>
                                    <p><?= htmlspecialchars($incidentDetails['building_type'] ?? '') ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">CONTACT</h6>
                                    <p>
                                        <?= htmlspecialchars($incidentDetails['contact_person'] ?? 'N/A') ?><br>
                                        <?= htmlspecialchars($incidentDetails['contact_number'] ?? 'N/A') ?>
                                    </p>
                                </div>
                                
                                <div class="divider"></div>
                                
                                <h6 class="fw-semibold text-muted mb-3">SAFETY FEATURES</h6>
                                <div class="d-flex flex-wrap gap-3 mb-3">
                                    <div class="safety-feature <?= $incidentDetails['has_sprinkler_system'] ? 'safety-yes' : 'safety-no' ?>">
                                        <i class="fas <?= $incidentDetails['has_sprinkler_system'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> Sprinklers
                                    </div>
                                    <div class="safety-feature <?= $incidentDetails['has_fire_alarm'] ? 'safety-yes' : 'safety-no' ?>">
                                        <i class="fas <?= $incidentDetails['has_fire_alarm'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> Alarm
                                    </div>
                                    <div class="safety-feature <?= $incidentDetails['has_fire_extinguishers'] ? 'safety-yes' : 'safety-no' ?>">
                                        <i class="fas <?= $incidentDetails['has_fire_extinguishers'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> Extinguishers
                                    </div>
                                    <div class="safety-feature <?= $incidentDetails['has_emergency_exits'] ? 'safety-yes' : 'safety-no' ?>">
                                        <i class="fas <?= $incidentDetails['has_emergency_exits'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> Exits
                                    </div>
                                    <div class="safety-feature <?= $incidentDetails['has_emergency_lighting'] ? 'safety-yes' : 'safety-no' ?>">
                                        <i class="fas <?= $incidentDetails['has_emergency_lighting'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> Lighting
                                    </div>
                                    <div class="safety-feature <?= $incidentDetails['has_fire_escape'] ? 'safety-yes' : 'safety-no' ?>">
                                        <i class="fas <?= $incidentDetails['has_fire_escape'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i> Escape
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">LAST INSPECTED</h6>
                                    <p><?= htmlspecialchars($incidentDetails['last_inspected'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-user"></i> Owner Information
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">OWNER NAME</h6>
                                    <p><?= htmlspecialchars($incidentDetails['owner_name']) ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">EMAIL</h6>
                                    <p><?= htmlspecialchars($incidentDetails['owner_email']) ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">PHONE</h6>
                                    <p><?= htmlspecialchars($incidentDetails['owner_phone'] ?? 'N/A') ?></p>
                                </div>
                                
                                <div class="divider"></div>
                                
                                <h6 class="fw-semibold text-muted mb-3">DEVICE INFORMATION</h6>
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">DEVICE NAME</h6>
                                    <p><?= htmlspecialchars($incidentDetails['device_name'] ?? 'N/A') ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">SERIAL NUMBER</h6>
                                    <p><?= htmlspecialchars($incidentDetails['serial_number'] ?? 'N/A') ?></p>
                                </div>
                                
                                <div class="mb-3">
                                    <h6 class="fw-semibold text-muted">STATUS</h6>
                                    <p>
                                        <span class="status-indicator <?= $incidentDetails['device_status'] == 'active' ? 'status-active' : ($incidentDetails['device_status'] == 'alert' ? 'status-alert' : 'status-inactive') ?>"></span>
                                        <?= htmlspecialchars($incidentDetails['device_status'] ?? 'N/A') ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-4"></i>
                        <h3>Incident Not Found</h3>
                        <p class="text-muted">The requested incident report could not be found in our records.</p>
                        <a href="incident_reports.php" class="btn btn-primary mt-3">
                            <i class="fas fa-arrow-left"></i> Back to Incident List
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- <form method="post" style="margin: 20px 0;">
        <button type="submit" name="migrate_incidents" class="btn btn-warning" onclick="return confirm('Are you sure you want to migrate all incidents? This may create duplicates if run multiple times.')">Migrate All Incidents to incident_reports</button>
    </form> -->


    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php include('../../components/scripts.php'); ?>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 (must be after jQuery) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Fallback checks
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 is not loaded.');
        }
        // Show success message if redirected from successful action
        <?php if (isset($_GET['success'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= htmlspecialchars($_GET['success']) ?>',
                timer: 3000,
                showConfirmButton: false
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        <?php endif; ?>
        // Show error message if redirected from failed action
        <?php if (isset($_GET['error'])): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?= htmlspecialchars($_GET['error']) ?>'
            });
            window.history.replaceState({}, document.title, window.location.pathname);
        <?php endif; ?>

    </script>
    <script src="../js/incident_reports_search.js"></script>
    <!-- Load our charts.js after all other scripts to avoid conflicts -->
    <script>
        // Prevent custom.min.js from interfering with our charts
        window.addEventListener('load', function() {
            // Override any existing chart initialization
            if (window.init_charts) {
                console.log('Charts.js: Overriding existing init_charts function');
                const originalInitCharts = window.init_charts;
                window.init_charts = function() {
                    console.log('Charts.js: Skipping custom.min.js chart initialization');
                    // Don't call the original function to prevent conflicts
                };
            }
        });
    </script>
    <script src="../js/charts.js"></script>
    <script>
        // Additional chart initialization safety
        document.addEventListener('DOMContentLoaded', function() {
            // Ensure Chart.js is loaded
            if (typeof Chart !== 'undefined') {
                console.log('Chart.js library is available');
            } else {
                console.error('Chart.js library is not available');
            }
        });
    </script>
</body>
</html>
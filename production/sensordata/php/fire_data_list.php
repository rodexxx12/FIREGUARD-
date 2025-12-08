<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header("Location: ../../../index.php");
  exit();
}

require_once __DIR__ . '/../../../db/db.php';

$pdo = getDatabaseConnection();

// Only fetch the latest record for gauges (much faster)
$stmt = $pdo->prepare(
  "SELECT fd.id, fd.status, fd.building_type, fd.smoke, fd.temp, fd.heat,
            fd.flame_detected, fd.timestamp, fd.user_id, u.username,
            br.barangay_name,
            fd.geo_lat, fd.geo_long, fd.notified,
            fd.device_id, d.device_name,
            fd.ml_confidence, fd.ml_prediction, fd.ml_fire_probability,
            fd.ai_prediction, fd.ml_timestamp
     FROM fire_data fd
     LEFT JOIN users u ON u.user_id = fd.user_id
     LEFT JOIN barangay br ON br.id = fd.barangay_id
     LEFT JOIN devices d ON d.device_id = fd.device_id
     ORDER BY fd.id DESC
     LIMIT 1"
);
$stmt->execute();
$latest = $stmt->fetch() ?: null;
?>
<?php include '../../components/header.php'; ?>
<link rel="stylesheet" href="../css/style.css">
<style>
  /* Gauges */
  .gauge-card-title {
    font-size: .85rem;
    letter-spacing: .03em;
    font-weight: 600;
    color: #6c757d;
    text-transform: uppercase;
  }

  .gauge-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #343a40;
  }

  .gauge-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: inline-block;
  }

  .gauge-legend {
    font-weight: 600;
    color: #495057;
  }

  .legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
    margin-right: .5rem;
  }

  .legend-danger {
    background-color: #dc3545;
  }

  .legend-warn {
    background-color: #f0ad4e;
  }

  .legend-ok {
    background-color: #28a745;
  }

  .legend-info {
    background-color: #3b82f6;
  }

  .gauge-svg {
    width: 100%;
    height: auto;
  }

  .gauge-arc-track {
    stroke: #f1f3f5;
    stroke-width: 12;
    fill: none;
  }

  .gauge-arc-value {
    stroke-width: 12;
    fill: none;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1), stroke 0.3s ease;
  }

  .card-modern {
    border: 1px solid #eef1f5;
    border-radius: 14px;
    box-shadow: 0 2px 8px rgba(16, 24, 40, .06);
  }

  .card-modern .card-body {
    padding: 1rem 1rem;
  }

  /* Date filter active state */
  .date-filter-active {
    border-color: #007bff !important;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, .25) !important;
  }

  .date-filter-error {
    border-color: #dc3545 !important;
    box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, .25) !important;
  }

  /* DataTables Pagination and Search Visibility */
  .dataTables_wrapper {
    margin-top: 1rem;
  }

  .dataTables_length {
    margin-bottom: 1rem;
    display: block !important;
    visibility: visible !important;
  }

  .dataTables_filter {
    display: none !important;
    visibility: hidden !important;
  }

  .dataTables_paginate {
    margin-top: 1rem;
    text-align: right;
    display: block !important;
    visibility: visible !important;
    padding: 0.5rem 0;
  }

  .dataTables_info {
    margin-top: 1rem;
    padding-top: 0.75rem;
    display: block !important;
    visibility: visible !important;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button {
    padding: 0.5rem 0.75rem;
    margin: 0 0.25rem;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    cursor: pointer;
    display: inline-block;
    color: #495057;
    background-color: #fff;
    transition: all 0.2s ease;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #007bff !important;
    color: #fff !important;
    border-color: #007bff !important;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: #007bff !important;
    color: #fff !important;
    border-color: #007bff !important;
    font-weight: 600;
  }

  .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
  }

  .dataTables_filter input {
    margin-left: 0.5rem;
    padding: 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
  }

  .dataTables_length select {
    margin-left: 0.5rem;
    margin-right: 0.5rem;
    padding: 0.375rem 1.75rem 0.375rem 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 0.25rem;
  }

  .dataTables_filter input {
    display: none !important;
  }

  /* Ensure pagination wrapper is visible */
  .dataTables_wrapper .row {
    display: flex !important;
    flex-wrap: wrap !important;
  }

  .dataTables_wrapper .row>div {
    display: block !important;
  }

  /* Burger Menu Toggle Button - Simple Green Style */
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
    transition: all 0.3s ease;
    visibility: visible !important;
    opacity: 1 !important;
  }

  .filter-toggle-btn:hover {
    opacity: 0.8;
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
    background-color: #218838;
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
    box-shadow: -2px 0 10px rgba(0, 0, 0, 0.2);
    z-index: 1000;
    transition: right 0.3s ease;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 1rem;
  }

  .filter-panel.active {
    right: 0;
  }

  /* Filter overlay */
  .filter-overlay {
    transition: opacity 0.3s ease, visibility 0.3s ease;
  }

  .filter-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e0e0e0;
    flex-shrink: 0;
  }

  .filter-panel-header h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #333;
  }

  .filter-panel-body {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    padding-right: 0.5rem;
    margin-right: -0.5rem;
  }

  /* Custom scrollbar styling */
  .filter-panel-body::-webkit-scrollbar {
    width: 8px;
  }

  .filter-panel-body::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 4px;
  }

  .filter-panel-body::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 4px;
  }

  .filter-panel-body::-webkit-scrollbar-thumb:hover {
    background: #555;
  }

  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
  }

  .filter-group label {
    font-weight: 600;
    color: #555;
    font-size: 0.8rem;
    margin-bottom: 0;
  }

  .filter-group .form-control,
  .filter-group .form-select {
    width: 100%;
    padding: 0.35rem 0.5rem;
    font-size: 0.85rem;
    line-height: 1.4;
  }

  .filter-group .btn {
    width: 100%;
    padding: 0.4rem 0.75rem;
    font-size: 0.85rem;
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

  /* Make sure the burger lines are visible and green */
  #filterToggleBtn .burger-line {
    background-color: #28a745 !important;
    display: block !important;
    visibility: visible !important;
  }

  #filterToggleBtn:hover .burger-line {
    background-color: #218838 !important;
  }

  /* Card Header Styling */
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.25rem;
    background-color: #fff;
    border-bottom: 1px solid rgba(0, 0, 0, .125);
  }

  .card-header h5 {
    margin: 0;
    font-weight: 600;
    color: #333;
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
  <?php include('../../components/navigation.php') ?>
  <div class="right_col" role="main">

    <?php
    // Optimized unified badge rendering function
    function renderBadgeClass($type, $value, $warn = null, $danger = null)
    {
      switch ($type) {
        case 'status':
          $t = strtoupper(trim((string)$value));
          if (in_array($t, ['EMERGENCY', 'FIRE', 'ALERT', 'CRITICAL', 'ACTIVE'])) return 'bg-danger';
          if ($t === 'ACKNOWLEDGED') return 'bg-primary';
          if (strpos($t, 'WARN') !== false) return 'bg-warning text-dark';
          if (in_array($t, ['SAFE', 'NORMAL', 'OK', 'INACTIVE'])) return 'bg-success';
          return 'bg-secondary';
        case 'level':
          if ($value === null || $value === '' || !is_numeric($value)) return 'bg-secondary';
          $n = (float)$value;
          if ($n >= $danger) return 'bg-danger';
          if ($n >= $warn) return 'bg-warning text-dark';
          return 'bg-success';
        case 'yesno':
          return ((int)$value === 1) ? 'bg-danger' : 'bg-secondary';
        case 'device':
          $label = trim((string)$value);
          return ($label === '' || stripos($label, 'N/A') !== false) ? 'bg-secondary' : 'bg-primary';
        default:
          return 'bg-secondary';
      }
    }
    ?>

    <?php
    $smokeLatest = isset($latest['smoke']) ? (float)$latest['smoke'] : null;
    $tempLatest = isset($latest['temp']) ? (float)$latest['temp'] : null;
    $heatLatest = isset($latest['heat']) ? (float)$latest['heat'] : null;
    $flameLatest = isset($latest['flame_detected']) ? (int)$latest['flame_detected'] : null;
    $timestampLatest = isset($latest['timestamp']) ? $latest['timestamp'] : null;
    $deviceNameLatest = isset($latest['device_name']) ? $latest['device_name'] : null;
    ?>
    <!-- Main container card -->
    <div class="card shadow-sm">
      <div class="card-body">
        <!-- Gauges row (latest data) -->
        <div id="latestSensorData"
          data-smoke="<?php echo htmlspecialchars($smokeLatest ?? ''); ?>"
          data-temp="<?php echo htmlspecialchars($tempLatest ?? ''); ?>"
          data-heat="<?php echo htmlspecialchars($heatLatest ?? ''); ?>"
          data-flame="<?php echo htmlspecialchars($flameLatest ?? ''); ?>"
          data-timestamp="<?php echo htmlspecialchars($timestampLatest ?? ''); ?>"
          data-device-name="<?php echo htmlspecialchars($deviceNameLatest ?? ''); ?>"
          class="row g-3 mb-3">
          <div class="col-12 col-md-3">
            <div class="card card-modern h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="gauge-icon" style="background-color:#3b82f6"></div>
                  <div class="text-end">
                    <div class="gauge-card-title">Smoke Level</div>
                    <div class="gauge-value"><span id="smokeValueNum">--</span> <span class="text-muted" style="font-weight:600;">ppm</span></div>
                  </div>
                </div>
                <svg class="gauge-svg" viewBox="0 0 160 120" preserveAspectRatio="xMidYMid meet">
                  <path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
                  <path id="smokeArc" class="gauge-arc-value" stroke="#3b82f6" d="M20,80 A60,60 0 0 1 140,80" />
                </svg>
                <div class="mt-2"><span class="legend-dot legend-info"></span><span id="smokeLegend" class="gauge-legend">--</span></div>
                <div class="mt-2 small text-muted">
                  <div><strong>Device:</strong> <span id="smokeDeviceName">--</span></div>
                  <div><strong>Date:</strong> <span id="smokeDate">--</span></div>
                  <div><strong>Time:</strong> <span id="smokeTime">--</span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card card-modern h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="gauge-icon" style="background-color:#28a745"></div>
                  <div class="text-end">
                    <div class="gauge-card-title">Temperature</div>
                    <div class="gauge-value"><span id="tempValueNum">--</span> <span class="text-muted" style="font-weight:600;">°C</span></div>
                  </div>
                </div>
                <svg class="gauge-svg" viewBox="0 0 160 120" preserveAspectRatio="xMidYMid meet">
                  <path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
                  <path id="tempArc" class="gauge-arc-value" stroke="#28a745" d="M20,80 A60,60 0 0 1 140,80" />
                </svg>
                <div class="mt-2"><span class="legend-dot legend-ok"></span><span id="tempLegend" class="gauge-legend">--</span></div>
                <div class="mt-2 small text-muted">
                  <div><strong>Device:</strong> <span id="tempDeviceName">--</span></div>
                  <div><strong>Date:</strong> <span id="tempDate">--</span></div>
                  <div><strong>Time:</strong> <span id="tempTime">--</span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card card-modern h-120">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="gauge-icon" style="background-color:#f0ad4e"></div>
                  <div class="text-end">
                    <div class="gauge-card-title">Heat Index</div>
                    <div class="gauge-value"><span id="heatValueNum">--</span> <span class="text-muted" style="font-weight:600;">°C</span></div>
                  </div>
                </div>
                <svg class="gauge-svg" viewBox="0 0 160 120" preserveAspectRatio="xMidYMid meet">
                  <path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
                  <path id="heatArc" class="gauge-arc-value" stroke="#f0ad4e" d="M20,80 A60,60 0 0 1 140,80" />
                </svg>
                <div class="mt-2"><span class="legend-dot legend-warn"></span><span id="heatLegend" class="gauge-legend">--</span></div>
                <div class="mt-2 small text-muted">
                  <div><strong>Device:</strong> <span id="heatDeviceName">--</span></div>
                  <div><strong>Date:</strong> <span id="heatDate">--</span></div>
                  <div><strong>Time:</strong> <span id="heatTime">--</span></div>
                </div>
              </div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="card card-modern h-100">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                  <div class="gauge-icon" style="background-color:#dc3545"></div>
                  <div class="text-end">
                    <div class="gauge-card-title">Flame Detection</div>
                    <div class="gauge-value"><span id="flameValueText">--</span></div>
                  </div>
                </div>
                <svg class="gauge-svg" viewBox="0 0 160 120" preserveAspectRatio="xMidYMid meet">
                  <path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
                  <path id="flameArc" class="gauge-arc-value" stroke="#dc3545" d="M20,80 A60,60 0 0 1 140,80" />
                </svg>
                <div class="mt-2"><span id="flameLegendDot" class="legend-dot legend-danger"></span><span id="flameLegend" class="gauge-legend">--</span></div>
                <div class="mt-2 small text-muted">
                  <div><strong>Device:</strong> <span id="flameDeviceName">--</span></div>
                  <div><strong>Date:</strong> <span id="flameDate">--</span></div>
                  <div><strong>Time:</strong> <span id="flameTime">--</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Overlay -->
        <div class="filter-overlay" id="filterOverlay" onclick="toggleFilterPanel()"></div>

        <!-- Filter Panel - Side Panel -->
        <div class="filter-panel" id="filterPanel">
          <div class="filter-panel-header">
            <h3><i class="fa fa-filter" style="margin-right: 6px;"></i> Filters</h3>
            <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilterPanel()" title="Close Filters" style="padding: 0.25rem 0.5rem; font-size: 0.8rem;">
              <i class="fa fa-times"></i>
            </button>
          </div>
          <div class="filter-panel-body">
            <div class="filter-group">
              <label>Fire Status</label>
              <select id="filterStatus" class="form-control">
                <option value="">All Statuses</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Building Type</label>
              <select id="filterBuildingType" class="form-control">
                <option value="">All Building Types</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Barangay</label>
              <select id="filterBarangay" class="form-control">
                <option value="">All Barangays</option>
              </select>
            </div>
            <div class="filter-group">
              <label>User</label>
              <select id="filterUser" class="form-control">
                <option value="">All Users</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Device</label>
              <select id="filterDevice" class="form-control">
                <option value="">All Devices</option>
              </select>
            </div>
            <div class="filter-group">
              <label>Start Date</label>
              <input id="dateFrom" type="date" class="form-control" title="Filter records from this date">
            </div>
            <div class="filter-group">
              <label>End Date</label>
              <input id="dateTo" type="date" class="form-control" title="Filter records until this date">
            </div>
            <div class="filter-group">
              <button id="resetFilters" class="btn btn-default">
                <i class="fa fa-refresh"></i> Reset Filters
              </button>
            </div>
            <div class="filter-group">
              <button id="exportCSV" class="btn btn-success">
                <i class="fa fa-download"></i> Export CSV
              </button>
            </div>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Fire Data</h5>
            <ul class="nav navbar-right panel_toolbox">
              <li>
                <a class="filter-toggle-btn" id="filterToggleBtn" title="Toggle Filters" onclick="toggleFilterPanel()">
                  <span class="burger-line"></span>
                  <span class="burger-line"></span>
                  <span class="burger-line"></span>
                </a>
              </li>
            </ul>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="fireDataTable" class="table table-hover align-middle" style="width:100%">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Building Type</th>
                    <th>Smoke</th>
                    <th>Temp</th>
                    <th>Heat</th>
                    <th>Flame</th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Barangay</th>
                    <th>Device</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Data will be loaded via server-side processing -->
                </tbody>
                <tfoot>
                  <tr>
                    <th>ID</th>
                    <th>Status</th>
                    <th>Building Type</th>
                    <th>Smoke</th>
                    <th>Temp</th>
                    <th>Heat</th>
                    <th>Flame</th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Barangay</th>
                    <th>Device</th>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script>
      // Defer initialization until after scripts.php loads
      // This script will be executed after scripts.php includes jQuery
      window.dataTableInitPending = true;

      // Main initialization function
      function initializeApp() {
        // Declare table variable in outer scope
        var table;

        // Function to initialize DataTable
        function initializeDataTable() {
          // Double-check DataTables is loaded
          if (typeof $ === 'undefined' || typeof $.fn === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            console.error('DataTables not available. Initialization aborted.');
            console.error('jQuery version:', typeof $ !== 'undefined' && typeof $.fn !== 'undefined' ? $.fn.jquery : 'not available');
            console.error('$.fn.DataTable:', typeof $.fn !== 'undefined' ? typeof $.fn.DataTable : '$.fn not available');
            // Try one more time after a delay
            setTimeout(function() {
              if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && typeof $.fn.DataTable !== 'undefined') {
                console.log('DataTables now available. Retrying initialization...');
                initializeDataTable();
              }
            }, 1000);
            return;
          }

          // Wait for table element to be ready
          var $table = $('#fireDataTable');
          if ($table.length === 0) {
            console.error('Table #fireDataTable not found');
            return;
          }

          // Destroy existing DataTable if it exists
          if ($.fn.DataTable.isDataTable('#fireDataTable')) {
            $table.DataTable().destroy();
          }

          // Initialize DataTable with server-side processing for faster loading
          table = $('#fireDataTable').DataTable({
            processing: true,
            serverSide: true,
            paging: true,
            pageLength: 25,
            lengthMenu: [
              [10, 25, 50, 100],
              [10, 25, 50, 100]
            ],
            order: [
              [0, 'desc']
            ],
            stateSave: false,
            searching: true,
            info: true,
            responsive: true,
            pagingType: 'full_numbers',
            dom: '<"row"<"col-sm-12 col-md-6"l>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            ajax: {
              url: 'get_fire_data.php',
              type: 'GET',
              data: function(d) {
                // Add custom filters to DataTables request
                // Read filter values directly from DOM elements each time this function is called
                const filterData = {
                  status: ($('#filterStatus').length ? $('#filterStatus').val() : '') || '',
                  building_type: ($('#filterBuildingType').length ? $('#filterBuildingType').val() : '') || '',
                  barangay: ($('#filterBarangay').length ? $('#filterBarangay').val() : '') || '',
                  user: ($('#filterUser').length ? $('#filterUser').val() : '') || '',
                  device: ($('#filterDevice').length ? $('#filterDevice').val() : '') || '',
                  date_from: ($('#dateFrom').length ? $('#dateFrom').val() : '') || '',
                  date_to: ($('#dateTo').length ? $('#dateTo').val() : '') || ''
                };
                console.log('Ajax data function called with filters:', filterData);
                // Merge with DataTables default parameters
                return $.extend({}, d, filterData);
              },
              error: function(xhr, error, thrown) {
                console.error('DataTables Ajax error:', error, thrown);
                if (xhr.status === 401) {
                  alert('Session expired. Please refresh and log in again.');
                  window.location.reload();
                }
              }
            },
            language: {
              search: "Search:",
              lengthMenu: "Show _MENU_ entries",
              info: "Showing _START_ to _END_ of _TOTAL_ entries",
              infoEmpty: "Showing 0 to 0 of 0 entries",
              infoFiltered: "(filtered from _MAX_ total entries)",
              paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
              },
              emptyTable: "No data available in table",
              zeroRecords: "No matching records found",
              processing: "Loading data..."
            },
            columnDefs: [{
              targets: 0,
              visible: false,
              searchable: true
            }],
            initComplete: function() {
              console.log('DataTable initialized successfully with server-side processing');
              // Force show pagination controls (search is hidden)
              $('.dataTables_length').css({
                'display': 'block',
                'visibility': 'visible'
              });
              $('.dataTables_filter').css({
                'display': 'none',
                'visibility': 'hidden'
              });
              $('.dataTables_paginate').css({
                'display': 'block',
                'visibility': 'visible'
              });
              $('.dataTables_info').css({
                'display': 'block',
                'visibility': 'visible'
              });

              // Ensure pagination buttons are visible
              $('.dataTables_paginate .paginate_button').css({
                'display': 'inline-block',
                'visibility': 'visible'
              });

              // Set up filter event handlers after table is initialized
              setupFilterHandlers();

              // Initialize filter options after table loads
              setTimeout(function() {
                if (typeof initializeFilters === 'function') {
                  initializeFilters();
                }
              }, 800);
            },
            drawCallback: function(settings) {
              // Ensure pagination is visible on every draw
              $('.dataTables_paginate').css({
                'display': 'block',
                'visibility': 'visible'
              });
              $('.dataTables_info').css({
                'display': 'block',
                'visibility': 'visible'
              });
              $('.dataTables_paginate .paginate_button').css({
                'display': 'inline-block',
                'visibility': 'visible'
              });

              // Update gauges with first row data if available
              var api = this.api();
              var firstRow = api.row(0).data();
              if (firstRow && firstRow.length > 0) {
                // Extract data from HTML badges
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = firstRow[3] || ''; // Smoke
                var smokeText = (tempDiv.textContent || tempDiv.innerText || '').trim();
                tempDiv.innerHTML = firstRow[4] || ''; // Temp
                var tempText = (tempDiv.textContent || tempDiv.innerText || '').trim();
                tempDiv.innerHTML = firstRow[5] || ''; // Heat
                var heatText = (tempDiv.textContent || tempDiv.innerText || '').trim();
                tempDiv.innerHTML = firstRow[6] || ''; // Flame
                var flameText = (tempDiv.textContent || tempDiv.innerText || '').trim();
                var timestampText = firstRow[7] || '';
                tempDiv.innerHTML = firstRow[10] || ''; // Device
                var deviceText = (tempDiv.textContent || tempDiv.innerText || '').trim();

                var cont = document.getElementById('latestSensorData');
                if (cont) {
                  cont.setAttribute('data-smoke', smokeText || '');
                  cont.setAttribute('data-temp', tempText || '');
                  cont.setAttribute('data-heat', heatText || '');
                  cont.setAttribute('data-flame', (flameText && flameText.toLowerCase() === 'yes') ? '1' : '0');
                  cont.setAttribute('data-timestamp', timestampText || '');
                  cont.setAttribute('data-device-name', deviceText || '');
                }
                if (typeof updateGaugesFromDataset === 'function') {
                  updateGaugesFromDataset();
                }
              }
            }
          });

          // Force show controls after initialization (search is hidden)
          setTimeout(function() {
            $('.dataTables_length').css({
              'display': 'block',
              'visibility': 'visible'
            });
            $('.dataTables_filter').css({
              'display': 'none',
              'visibility': 'hidden'
            });
            $('.dataTables_paginate').css({
              'display': 'block',
              'visibility': 'visible'
            });
            $('.dataTables_info').css({
              'display': 'block',
              'visibility': 'visible'
            });
            $('.dataTables_paginate .paginate_button').css({
              'display': 'inline-block',
              'visibility': 'visible'
            });
          }, 100);

          // Additional check after a longer delay to ensure pagination is visible
          setTimeout(function() {
            if (table && typeof table.page !== 'undefined') {
              $('.dataTables_paginate').css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
              });
              $('.dataTables_info').css({
                'display': 'block',
                'visibility': 'visible',
                'opacity': '1'
              });
            }
          }, 500);

        }

        // Start initialization
        initializeDataTable();

        // Optimized utility functions
        const clamp = (val, min, max) => Math.max(min, Math.min(max, val));
        const cellText = (v) => {
          if (v == null) return '';
          const d = document.createElement('div');
          d.innerHTML = v;
          return (d.textContent || d.innerText || '').trim();
        };

        // Enhanced gauge rendering with animations
        function renderGauge(pathEl, value, maxValue, isCritical = false) {
          if (!pathEl) return;
          const v = (value == null || value === '') ? 0 : Number(value);
          const max = maxValue > 0 ? maxValue : 100;
          const length = (typeof pathEl.getTotalLength === 'function') ? pathEl.getTotalLength() : 180;
          const pct = clamp(v / max, 0, 1);
          const offset = length * (1 - pct);

          // Add animation classes
          pathEl.classList.add('gauge-updating', 'animating');
          if (isCritical) {
            pathEl.classList.add('critical');
          } else {
            pathEl.classList.remove('critical');
          }

          pathEl.setAttribute('stroke-dasharray', String(length));
          pathEl.setAttribute('stroke-dashoffset', String(offset));

          setTimeout(() => {
            pathEl.classList.remove('gauge-updating', 'animating');
          }, 1200);
        }

        // Animated number counter
        function animateNumber(element, startValue, endValue, duration = 500) {
          if (!element) return;
          const start = parseFloat(startValue) || 0;
          const end = parseFloat(endValue) || 0;
          const startTime = performance.now();

          element.classList.add('updating');

          function update(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeOut = 1 - Math.pow(1 - progress, 3); // Ease-out cubic
            const current = start + (end - start) * easeOut;

            if (end % 1 === 0) {
              element.textContent = Math.round(current);
            } else {
              element.textContent = current.toFixed(1);
            }

            if (progress < 1) {
              requestAnimationFrame(update);
            } else {
              element.textContent = end % 1 === 0 ? Math.round(end) : parseFloat(end).toFixed(1);
              setTimeout(() => element.classList.remove('updating'), 500);
            }
          }

          requestAnimationFrame(update);
        }

        // Optimized timestamp parsing
        function parseTimestamp(timestamp) {
          if (!timestamp || timestamp.trim() === '') return new Date(NaN);

          const cleanTimestamp = timestamp.trim();
          const formats = [
            cleanTimestamp,
            cleanTimestamp.replace(' ', 'T'),
            cleanTimestamp.replace(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2}:\d{2})$/, '$1T$2'),
            cleanTimestamp + 'T00:00:00',
            cleanTimestamp + 'T00:00:00.000Z'
          ];

          for (let format of formats) {
            const date = new Date(format);
            if (!isNaN(date.getTime()) && date.getFullYear() > 1900) {
              return date;
            }
          }

          // Manual MySQL format parsing
          const mysqlMatch = cleanTimestamp.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/);
          if (mysqlMatch) {
            const [, year, month, day, hour, minute, second] = mysqlMatch;
            const date = new Date(parseInt(year), parseInt(month) - 1, parseInt(day), parseInt(hour), parseInt(minute), parseInt(second));
            if (!isNaN(date.getTime())) return date;
          }

          return new Date(NaN);
        }

        // Optimized gauge updates
        function updateGaugesFromDataset() {
          const container = document.getElementById('latestSensorData');
          if (!container) return;

          const data = {
            smoke: container.getAttribute('data-smoke'),
            temp: container.getAttribute('data-temp'),
            heat: container.getAttribute('data-heat'),
            flame: container.getAttribute('data-flame'),
            timestamp: container.getAttribute('data-timestamp'),
            deviceName: container.getAttribute('data-device-name')
          };

          // Update numeric values with smooth counting animation
          const valueElements = {
            smoke: {
              el: document.getElementById('smokeValueNum'),
              max: 30000,
              critical: 15000
            },
            temp: {
              el: document.getElementById('tempValueNum'),
              max: 30000,
              critical: 80
            },
            heat: {
              el: document.getElementById('heatValueNum'),
              max: 30000,
              critical: 85
            }
          };

          Object.entries(valueElements).forEach(([key, config]) => {
            if (config.el) {
              const currentValue = config.el.textContent === '--' ? 0 : parseFloat(config.el.textContent) || 0;
              const newValue = (data[key] && data[key] !== 'null' && data[key] !== '') ? parseFloat(data[key]) : null;

              if (newValue !== null && !isNaN(newValue)) {
                animateNumber(config.el, currentValue, newValue, 800);
              } else {
                config.el.textContent = '--';
              }
            }
          });

          // Determine critical states
          const smokeCritical = Number(data.smoke) >= 15000;
          const tempCritical = Number(data.temp) >= 80;
          const heatCritical = Number(data.heat) >= 85;

          // Render gauges with critical state
          renderGauge(document.getElementById('smokeArc'), data.smoke, 30000, smokeCritical);
          renderGauge(document.getElementById('tempArc'), data.temp, 30000, tempCritical);
          renderGauge(document.getElementById('heatArc'), data.heat, 30000, heatCritical);

          // Update icons with critical state
          const icons = {
            smoke: document.querySelector('#latestSensorData .col-md-3:nth-child(1) .gauge-icon'),
            temp: document.querySelector('#latestSensorData .col-md-3:nth-child(2) .gauge-icon'),
            heat: document.querySelector('#latestSensorData .col-md-3:nth-child(3) .gauge-icon')
          };

          if (icons.smoke) icons.smoke.classList.toggle('critical', smokeCritical);
          if (icons.temp) icons.temp.classList.toggle('critical', tempCritical);
          if (icons.heat) icons.heat.classList.toggle('critical', heatCritical);

          // Update legends with animation
          const smokeLegend = document.getElementById('smokeLegend');
          const tempLegend = document.getElementById('tempLegend');
          const heatLegend = document.getElementById('heatLegend');

          if (smokeLegend) {
            smokeLegend.style.opacity = '0';
            setTimeout(() => {
              smokeLegend.textContent = smokeCritical ? 'Dangerous' : 'Normal';
              smokeLegend.style.opacity = '1';
            }, 150);
          }

          if (tempLegend) {
            tempLegend.style.opacity = '0';
            setTimeout(() => {
              tempLegend.textContent = tempCritical ? 'Critical' : 'Normal';
              tempLegend.style.opacity = '1';
            }, 200);
          }

          if (heatLegend) {
            heatLegend.style.opacity = '0';
            setTimeout(() => {
              heatLegend.textContent = heatCritical ? 'Dangerous' : 'Normal';
              heatLegend.style.opacity = '1';
            }, 250);
          }

          // Flame detection with animation
          const flameDetected = Number(data.flame) === 1;
          const flameValueText = document.getElementById('flameValueText');
          const flameArc = document.getElementById('flameArc');
          const flameDot = document.getElementById('flameLegendDot');
          const flameLegend = document.getElementById('flameLegend');

          if (flameValueText) {
            flameValueText.style.opacity = '0';
            setTimeout(() => {
              flameValueText.textContent = flameDetected ? 'Detected' : 'Not Detected';
              flameValueText.style.opacity = '1';
            }, 100);
          }

          if (flameArc) {
            renderGauge(flameArc, flameDetected ? 1 : 0, 1, flameDetected);
          }

          if (flameDot) {
            flameDot.className = 'legend-dot ' + (flameDetected ? 'legend-danger' : 'legend-ok');
            if (flameDetected) {
              flameDot.classList.add('critical');
            } else {
              flameDot.classList.remove('critical');
            }
          }

          if (flameLegend) {
            flameLegend.style.opacity = '0';
            setTimeout(() => {
              flameLegend.textContent = flameDetected ? 'Flame' : 'No Flame';
              flameLegend.style.opacity = '1';
            }, 150);
          }

          // Update metadata for all gauges
          updateGaugeMetadata(data.timestamp, data.deviceName);
        }

        // Optimized metadata update
        function updateGaugeMetadata(timestamp, deviceName) {
          let formattedDate = '--';
          let formattedTime = '--';
          const displayDeviceName = deviceName && deviceName !== 'null' ? deviceName : '--';

          if (timestamp && timestamp !== 'null' && timestamp !== '') {
            try {
              const date = new Date(timestamp);
              if (!isNaN(date.getTime())) {
                formattedDate = date.toLocaleDateString();
                formattedTime = date.toLocaleTimeString();
              }
            } catch (e) {
              const mysqlMatch = timestamp.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2}):(\d{2})$/);
              if (mysqlMatch) {
                const [, year, month, day, hour, minute, second] = mysqlMatch;
                formattedDate = `${month}/${day}/${year}`;
                formattedTime = `${hour}:${minute}:${second}`;
              }
            }
          }

          // Update all gauge metadata efficiently
          const gaugeTypes = ['smoke', 'temp', 'heat', 'flame'];
          gaugeTypes.forEach(type => {
            document.getElementById(`${type}DeviceName`).textContent = displayDeviceName;
            document.getElementById(`${type}Date`).textContent = formattedDate;
            document.getElementById(`${type}Time`).textContent = formattedTime;
          });
        }

        // Initial render with entrance animation
        setTimeout(() => {
          updateGaugesFromDataset();

          // Trigger initial gauge fill animation
          const gaugeArcs = document.querySelectorAll('.gauge-arc-value');
          gaugeArcs.forEach((arc, index) => {
            setTimeout(() => {
              arc.classList.add('animating');
              setTimeout(() => arc.classList.remove('animating'), 1500);
            }, index * 100);
          });
        }, 100);

        // Optimized polling with filter awareness
        let POLL_INTERVAL_MS = 5000;
        let pollTimeout;

        function pollLatest() {
          const params = {
            status: $('#filterStatus').val() || '',
            building_type: $('#filterBuildingType').val() || '',
            barangay: $('#filterBarangay').val() || '',
            user: $('#filterUser').val() || '',
            device: $('#filterDevice').val() || '',
            date_from: $('#dateFrom').val() || '',
            date_to: $('#dateTo').val() || ''
          };

          $.get('get_latest_fire_data.php', params)
            .done(function(resp) {
              if (!resp || !resp.data) return;
              const r = resp.data;
              const cont = document.getElementById('latestSensorData');
              if (cont) {
                cont.setAttribute('data-smoke', r.smoke ?? '');
                cont.setAttribute('data-temp', r.temp ?? '');
                cont.setAttribute('data-heat', r.heat ?? '');
                cont.setAttribute('data-flame', (Number(r.flame_detected) === 1) ? '1' : '0');
                cont.setAttribute('data-timestamp', r.timestamp ?? '');
                cont.setAttribute('data-device-name', r.device_name ?? '');
              }
              updateGaugesFromDataset();
            })
            .always(() => {
              pollTimeout = setTimeout(pollLatest, POLL_INTERVAL_MS);
            });
        }

        pollLatest();

        // Helper function to get table reference safely
        function getTable() {
          // Try to get table from closure variable first
          if (table && typeof table.draw === 'function') {
            return table;
          }
          // Fallback: get table from DataTables API
          if ($.fn.DataTable.isDataTable('#fireDataTable')) {
            return $('#fireDataTable').DataTable();
          }
          return null;
        }

        // Initialize filters - fetch unique values from server
        function initializeFilters() {
          var currentTable = getTable();
          if (!currentTable) {
            console.log('Table not ready, retrying filter initialization...');
            setTimeout(initializeFilters, 300);
            return;
          }

          console.log('Initializing filters...');

          // Fetch all data once to populate filter options (with a reasonable limit)
          $.get('get_fire_data.php', {
              start: 0,
              length: 10000, // Get more records for filter options
              draw: 1,
              order: [{
                column: 0,
                dir: 'desc'
              }]
            })
            .done(function(response) {
              console.log('Filter initialization response:', response);
              if (response && response.data && Array.isArray(response.data) && response.data.length > 0) {
                // Extract unique values from response data
                const statuses = new Set();
                const buildingTypes = new Set();
                const barangays = new Set();
                const users = new Set();
                const devices = new Set();

                response.data.forEach(function(row) {
                  // Parse HTML to get text values
                  const tempDiv = document.createElement('div');

                  // Status (column index 1)
                  if (row[1]) {
                    tempDiv.innerHTML = row[1];
                    const status = (tempDiv.textContent || tempDiv.innerText || '').trim();
                    if (status && status !== 'All Statuses') statuses.add(status);
                  }

                  // Building Type (column index 2)
                  if (row[2]) {
                    tempDiv.innerHTML = row[2];
                    const bt = (tempDiv.textContent || tempDiv.innerText || '').trim();
                    if (bt && bt !== 'All Building Types') buildingTypes.add(bt);
                  }

                  // Barangay (column index 9)
                  if (row[9]) {
                    tempDiv.innerHTML = row[9];
                    const brgy = (tempDiv.textContent || tempDiv.innerText || '').trim();
                    if (brgy && brgy !== 'All Barangays') barangays.add(brgy);
                  }

                  // User (column index 8)
                  if (row[8]) {
                    tempDiv.innerHTML = row[8];
                    const user = (tempDiv.textContent || tempDiv.innerText || '').trim();
                    if (user && !user.startsWith('User #') && user !== 'All Users') users.add(user);
                  }

                  // Device (column index 10)
                  if (row[10]) {
                    tempDiv.innerHTML = row[10];
                    const dev = (tempDiv.textContent || tempDiv.innerText || '').trim();
                    // Remove HTML tags and extract text
                    const devText = dev.replace(/<[^>]*>/g, '').trim();
                    if (devText && !devText.startsWith('Device #') && devText !== 'N/A' && devText !== 'All Devices') {
                      devices.add(devText);
                    }
                  }
                });

                // Populate filter dropdowns
                const $statusSelect = $('#filterStatus');
                if ($statusSelect.length) {
                  $statusSelect.find('option:not(:first)').remove();
                  Array.from(statuses).sort().forEach(v => {
                    if (v) $statusSelect.append($('<option>').val(v).text(v));
                  });
                  console.log('Statuses populated:', statuses.size);
                }

                const $btSelect = $('#filterBuildingType');
                if ($btSelect.length) {
                  $btSelect.find('option:not(:first)').remove();
                  Array.from(buildingTypes).sort().forEach(v => {
                    if (v) $btSelect.append($('<option>').val(v).text(v));
                  });
                  console.log('Building Types populated:', buildingTypes.size);
                }

                const $brgySelect = $('#filterBarangay');
                if ($brgySelect.length) {
                  $brgySelect.find('option:not(:first)').remove();
                  Array.from(barangays).sort().forEach(v => {
                    if (v) $brgySelect.append($('<option>').val(v).text(v));
                  });
                  console.log('Barangays populated:', barangays.size);
                }

                const $userSelect = $('#filterUser');
                if ($userSelect.length) {
                  $userSelect.find('option:not(:first)').remove();
                  Array.from(users).sort().forEach(v => {
                    if (v) $userSelect.append($('<option>').val(v).text(v));
                  });
                  console.log('Users populated:', users.size);
                }

                const $devSelect = $('#filterDevice');
                if ($devSelect.length) {
                  $devSelect.find('option:not(:first)').remove();
                  Array.from(devices).sort().forEach(v => {
                    if (v) $devSelect.append($('<option>').val(v).text(v));
                  });
                  console.log('Devices populated:', devices.size);
                }

                console.log('Filter options populated successfully:', {
                  statuses: statuses.size,
                  buildingTypes: buildingTypes.size,
                  barangays: barangays.size,
                  users: users.size,
                  devices: devices.size
                });
              } else {
                console.warn('No data received for filter initialization. Response:', response);
                // Retry once more after a delay
                setTimeout(function() {
                  var retryTable = getTable();
                  if (retryTable) {
                    console.log('Retrying filter initialization...');
                    initializeFilters();
                  }
                }, 1000);
              }
            })
            .fail(function(xhr, status, error) {
              console.warn('Failed to load filter options:', error, xhr);
            });
        }

        // Start filter initialization after table loads (fallback if initComplete doesn't trigger it)
        // The main initialization happens in initComplete callback, this is just a safety net
        setTimeout(function() {
          if (typeof initializeFilters === 'function') {
            var currentTable = getTable();
            if (currentTable) {
              initializeFilters();
            }
          }
        }, 2000);

        // Optimized core filter application - reloads table with new filters
        function applyCoreFilters() {
          console.log('applyCoreFilters called');
          var currentTable = getTable();
          if (!currentTable) {
            console.warn('Table not initialized, cannot apply filters. Retrying...');
            // Retry after a short delay if table isn't ready
            setTimeout(function() {
              var retryTable = getTable();
              if (retryTable) {
                applyCoreFilters();
              } else {
                console.warn('Table still not initialized after retry');
              }
            }, 100);
            return;
          }

          const filters = {
            status: $('#filterStatus').val() || '',
            building_type: $('#filterBuildingType').val() || '',
            barangay: $('#filterBarangay').val() || '',
            user: $('#filterUser').val() || '',
            device: $('#filterDevice').val() || '',
            date_from: $('#dateFrom').val() || '',
            date_to: $('#dateTo').val() || ''
          };

          console.log('Applying filters:', filters);

          // Validate date range
          if (filters.date_from && filters.date_to) {
            const fromDate = new Date(filters.date_from + 'T00:00:00');
            const toDate = new Date(filters.date_to + 'T23:59:59');
            if (fromDate > toDate) {
              if (typeof toastr !== 'undefined') {
                toastr.error('Start date cannot be after end date. Please correct the date range.');
              } else {
                alert('Start date cannot be after end date. Please correct the date range.');
              }
              $('#dateFrom').val('');
              $('#dateTo').val('');
              return;
            }
          }

          // Reload table with new filters (server-side processing handles filtering)
          // Use draw() instead of ajax.reload() to ensure ajax.data function is called
          console.log('Reloading table with filters...');
          currentTable.draw(false);

          if (typeof updateDateFilterVisuals === 'function') {
            updateDateFilterVisuals();
          }
        }

        // Set up filter event handlers
        function setupFilterHandlers() {
          console.log('Setting up filter handlers...');

          // Close filter panel when clicking overlay (toggleFilterPanel is already defined globally)
          // Note: The burger button uses onclick attribute, so no jQuery handler needed here

          // Remove any existing handlers to avoid duplicates
          $('#filterStatus,#filterBuildingType,#filterBarangay,#filterUser,#filterDevice,#dateFrom,#dateTo').off('change');

          // Set up dropdown filter handlers
          var filterElements = $('#filterStatus,#filterBuildingType,#filterBarangay,#filterUser,#filterDevice');
          if (filterElements.length > 0) {
            filterElements.on('change', function() {
              console.log('Filter changed:', this.id, $(this).val());
              applyCoreFilters();
            });
            console.log('Dropdown filter handlers attached');
          }

          // Date filter handlers with visual feedback
          var dateFromEl = $('#dateFrom');
          var dateToEl = $('#dateTo');
          if (dateFromEl.length > 0) {
            dateFromEl.on('change', function() {
              console.log('Date from changed:', $(this).val());
              updateDateFilterVisuals();
              applyCoreFilters();
            });
          }
          if (dateToEl.length > 0) {
            dateToEl.on('change', function() {
              console.log('Date to changed:', $(this).val());
              updateDateFilterVisuals();
              applyCoreFilters();
            });
          }

          // Initialize date filter visuals on load
          if (typeof updateDateFilterVisuals === 'function') {
            updateDateFilterVisuals();
          }

          // Reset filters button
          var resetFiltersEl = $('#resetFilters');
          if (resetFiltersEl.length > 0) {
            resetFiltersEl.off('click'); // Remove existing handlers
            resetFiltersEl.on('click', function() {
              var currentTable = getTable();
              if (!currentTable) {
                console.warn('Table not initialized, retrying...');
                setTimeout(function() {
                  var retryTable = getTable();
                  if (!retryTable) {
                    console.error('Table not initialized after retry');
                    return;
                  }
                  $('#filterStatus,#filterBuildingType,#filterBarangay,#filterUser,#filterDevice,#dateFrom,#dateTo').val('');
                  retryTable.search(''); // Clear global search
                  retryTable.draw(false); // Reload with empty filters
                  if (typeof updateDateFilterVisuals === 'function') {
                    updateDateFilterVisuals();
                  }
                }, 200);
                return;
              }

              $('#filterStatus,#filterBuildingType,#filterBarangay,#filterUser,#filterDevice,#dateFrom,#dateTo').val('');
              currentTable.search(''); // Clear global search
              currentTable.draw(false); // Reload with empty filters

              if (typeof updateDateFilterVisuals === 'function') {
                updateDateFilterVisuals();
              }

              if (typeof Swal !== 'undefined') {
                Swal.fire({
                  title: 'Success!',
                  text: 'All filters have been reset successfully.',
                  icon: 'success',
                  confirmButtonText: 'OK',
                  confirmButtonColor: '#28a745'
                });
              }
            });
            console.log('Reset button handler attached');
          }

          // Export CSV button
          var exportCSVEl = $('#exportCSV');
          if (exportCSVEl.length > 0) {
            exportCSVEl.off('click'); // Remove existing handlers
            exportCSVEl.on('click', function() {
              var currentTable = getTable();
              if (!currentTable) {
                console.warn('Table not initialized, retrying...');
                setTimeout(function() {
                  var retryTable = getTable();
                  if (!retryTable) {
                    console.error('Table not initialized after retry');
                    if (typeof Swal !== 'undefined') {
                      Swal.fire({
                        title: 'Error',
                        text: 'Table is not ready. Please wait a moment and try again.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                      });
                    }
                    return;
                  }
                  // Retry with the table
                  exportCSVData(retryTable);
                }, 200);
                return;
              }

              exportCSVData(currentTable);
            });
            console.log('Export CSV button handler attached');
          }

          // Helper function to export CSV data
          function exportCSVData(currentTable) {
            // Show loading indicator
            if (typeof Swal !== 'undefined') {
              Swal.fire({
                title: 'Exporting...',
                text: 'Please wait while we prepare your data.',
                allowOutsideClick: false,
                didOpen: () => {
                  Swal.showLoading();
                }
              });
            }

            // Fetch all filtered data (with large limit)
            const filters = {
              status: $('#filterStatus').val() || '',
              building_type: $('#filterBuildingType').val() || '',
              barangay: $('#filterBarangay').val() || '',
              user: $('#filterUser').val() || '',
              device: $('#filterDevice').val() || '',
              date_from: $('#dateFrom').val() || '',
              date_to: $('#dateTo').val() || '',
              search: (currentTable && typeof currentTable.search === 'function') ? currentTable.search() : ''
            };

            $.get('get_fire_data.php', $.extend({
                start: 0,
                length: 100000, // Large limit to get all filtered records
                draw: 1,
                order: [{
                  column: 0,
                  dir: 'desc'
                }]
              }, filters))
              .done(function(response) {
                if (!response || !response.data || response.data.length === 0) {
                  if (typeof Swal !== 'undefined') {
                    Swal.fire({
                      title: 'No Data',
                      text: 'There are no records to export. Please adjust your filters.',
                      icon: 'warning',
                      confirmButtonText: 'OK',
                      confirmButtonColor: '#ffc107'
                    });
                  } else {
                    alert('There are no records to export. Please adjust your filters.');
                  }
                  return;
                }

                const headers = [
                  'ID', 'Status', 'Building Type', 'Smoke (ppm)', 'Temperature (°C)',
                  'Heat Index (°C)', 'Flame Detected', 'Timestamp', 'User',
                  'Barangay', 'Device'
                ];

                let csvContent = headers.join(',') + '\n';

                response.data.forEach(row => {
                  const csvRow = row.map(cell => {
                    let cellContent = '';
                    if (cell) {
                      const tempDiv = document.createElement('div');
                      tempDiv.innerHTML = cell;
                      cellContent = tempDiv.textContent || tempDiv.innerText || '';
                    }

                    if (cellContent.includes(',') || cellContent.includes('"') || cellContent.includes('\n')) {
                      cellContent = '"' + cellContent.replace(/"/g, '""') + '"';
                    }

                    return cellContent;
                  });

                  csvContent += csvRow.join(',') + '\n';
                });

                const blob = new Blob([csvContent], {
                  type: 'text/csv;charset=utf-8;'
                });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);

                const now = new Date();
                const dateStr = now.getFullYear() + '-' +
                  String(now.getMonth() + 1).padStart(2, '0') + '-' +
                  String(now.getDate()).padStart(2, '0') + '_' +
                  String(now.getHours()).padStart(2, '0') + '-' +
                  String(now.getMinutes()).padStart(2, '0');

                link.setAttribute('download', 'fire_data_export_' + dateStr + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                if (typeof Swal !== 'undefined') {
                  Swal.fire({
                    title: 'Export Successful!',
                    text: 'Fire data has been exported to CSV successfully.',
                    icon: 'success',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#28a745'
                  });
                }
              })
              .fail(function() {
                if (typeof Swal !== 'undefined') {
                  Swal.fire({
                    title: 'Export Failed',
                    text: 'Unable to export data. Please try again.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                  });
                } else {
                  alert('Unable to export data. Please try again.');
                }
              });
          }

          console.log('Filter handlers setup complete');
        }

        // Optimized visual feedback
        function updateDateFilterVisuals() {
          const from = $('#dateFrom').val();
          const to = $('#dateTo').val();

          $('#dateFrom, #dateTo').removeClass('date-filter-active date-filter-error');

          if (from || to) {
            if (from && to) {
              const fromDate = new Date(from + 'T00:00:00');
              const toDate = new Date(to + 'T23:59:59');
              if (fromDate > toDate) {
                $('#dateFrom, #dateTo').addClass('date-filter-error');
              } else {
                $('#dateFrom, #dateTo').addClass('date-filter-active');
              }
            } else {
              $('#dateFrom, #dateTo').addClass('date-filter-active');
            }
          }
        }

        // Event handlers will be set up in initComplete callback via setupFilterHandlers()

        // CSV export handler is set up in setupFilterHandlers()

        // Cleanup on page unload
        $(window).on('beforeunload', function() {
          if (pollTimeout) clearTimeout(pollTimeout);
        });
      } // End of initializeApp function
    </script>
    <!-- Include header components -->
    <?php include '../../components/scripts.php'; ?>

    <!-- Global Filter Panel Toggle Function - Must be defined before page loads -->
    <script>
      // Filter Panel Toggle Function - Defined globally for onclick handlers
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

      // Make sure function is available on window object as well
      window.toggleFilterPanel = toggleFilterPanel;

      // Close filter panel when clicking outside (after DOM is ready)
      document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(event) {
          const filterPanel = document.getElementById('filterPanel');
          const filterOverlay = document.getElementById('filterOverlay');
          const filterToggleBtn = document.getElementById('filterToggleBtn');

          // If clicking outside the panel and overlay is active, close it
          if (filterOverlay && filterOverlay.classList.contains('active') &&
            filterPanel && !filterPanel.contains(event.target) &&
            filterToggleBtn && !filterToggleBtn.contains(event.target) &&
            !filterOverlay.contains(event.target)) {
            toggleFilterPanel();
          }
        });
      });
    </script>

    <!-- Initialize DataTables after scripts.php loads -->
    <script>
      (function() {
        'use strict';

        var maxRetries = 5; // Reduced retries
        var retryCount = 0;
        var initialized = false;
        var dataTablesLoading = false;

        function initializeDataTable() {
          if (initialized) return;

          // Check if jQuery is available
          if (typeof jQuery === 'undefined' || typeof $ === 'undefined') {
            retryCount++;
            if (retryCount < maxRetries) {
              setTimeout(initializeDataTable, 200);
            } else {
              console.error('jQuery not available after maximum retries');
            }
            return;
          }

          // Check if DataTables is available
          if (typeof $.fn === 'undefined' || typeof $.fn.DataTable === 'undefined') {
            // If we're already loading DataTables, don't retry
            if (dataTablesLoading) {
              return;
            }

            // Check if DataTables scripts are in the page
            var dataTablesScripts = document.querySelectorAll('script[src*="datatables"], script[src*="DataTables"]');

            if (dataTablesScripts.length > 0) {
              // Scripts exist, wait for them to load (but limit retries)
              retryCount++;
              if (retryCount < maxRetries) {
                setTimeout(initializeDataTable, 300);
              } else {
                // After limited retries, try loading dynamically
                loadDataTablesDynamically();
              }
            } else {
              // Scripts not found, load them immediately
              loadDataTablesDynamically();
            }
            return;
          }

          // All dependencies ready
          console.log('✓ DataTables ready. jQuery version:', $.fn.jquery);
          initialized = true;
          retryCount = 0; // Reset counter

          // Initialize the app
          jQuery(document).ready(function($) {
            if (typeof initializeApp === 'function') {
              initializeApp();
            } else {
              console.error('initializeApp function not found');
            }
          });
        }

        function loadDataTablesDynamically() {
          if (dataTablesLoading) return; // Prevent multiple loads
          dataTablesLoading = true;
          console.log('Loading DataTables dynamically...');

          // Check if CSS is loaded
          var cssLoaded = document.querySelector('link[href*="datatables"]');
          if (!cssLoaded) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css';
            document.head.appendChild(link);
          }

          // Load DataTables core
          var script1 = document.createElement('script');
          script1.src = 'https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js';
          script1.onload = function() {
            // Load Bootstrap integration
            var script2 = document.createElement('script');
            script2.src = 'https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js';
            script2.onload = function() {
              console.log('✓ DataTables loaded dynamically');
              dataTablesLoading = false;
              retryCount = 0; // Reset counter
              setTimeout(initializeDataTable, 100);
            };
            script2.onerror = function() {
              console.error('Failed to load DataTables Bootstrap integration');
              dataTablesLoading = false;
            };
            document.head.appendChild(script2);
          };
          script1.onerror = function() {
            console.error('Failed to load DataTables core library');
            dataTablesLoading = false;
          };
          document.head.appendChild(script1);
        }

        // Suppress run_customtabs console.log from custom.min.js
        (function() {
          var originalLog = console.log;
          console.log = function() {
            // Suppress the run_customtabs message
            if (arguments.length > 0 && typeof arguments[0] === 'string' && arguments[0].trim() === 'run_customtabs') {
              return;
            }
            originalLog.apply(console, arguments);
          };
        })();

        // Start initialization
        // Use window.load to ensure all scripts including scripts.php are loaded
        if (document.readyState === 'complete') {
          setTimeout(initializeDataTable, 200);
        } else {
          window.addEventListener('load', function() {
            setTimeout(initializeDataTable, 200);
          });
        }
      })();
    </script>
</body>

</html>
<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../../../index.php");
    exit();
} 

require_once __DIR__ . '/../../../db/db.php';

$pdo = getDatabaseConnection();

// Fetch fire_data with related device, user
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
     ORDER BY fd.id DESC"
);
$stmt->execute();
$rows = $stmt->fetchAll();
// Latest (most recent) record for gauges
$latest = $rows[0] ?? null;
?>
<?php include '../../components/header.php'; ?>
    <link rel="stylesheet" href="../css/style.css">
    <style>
   
    /* Gauges */
    .gauge-card-title { font-size: .85rem; letter-spacing: .03em; font-weight: 600; color: #6c757d; text-transform: uppercase; }
    .gauge-value { font-size: 1.5rem; font-weight: 700; color: #343a40; }
    .gauge-icon { width: 40px; height: 40px; border-radius: 10px; display: inline-block; }
    .gauge-legend { font-weight: 600; color: #495057; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: .5rem; }
    .legend-danger { background-color: #dc3545; }
    .legend-warn { background-color: #f0ad4e; }
    .legend-ok { background-color: #28a745; }
    .legend-info { background-color: #3b82f6; }
    .gauge-svg { width: 100%; height: auto; }
    .gauge-arc-track { stroke: #f1f3f5; stroke-width: 12; fill: none; }
    .gauge-arc-value { stroke-width: 12; fill: none; stroke-linecap: round; transition: stroke-dashoffset 0.8s cubic-bezier(0.4, 0, 0.2, 1), stroke 0.3s ease; }
    .card-modern { border: 1px solid #eef1f5; border-radius: 14px; box-shadow: 0 2px 8px rgba(16,24,40,.06); }
    .card-modern .card-body { padding: 1rem 1rem; }
    /* Date filter active state */
    .date-filter-active { border-color: #007bff !important; box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25) !important; }
    .date-filter-error { border-color: #dc3545 !important; box-shadow: 0 0 0 0.2rem rgba(220,53,69,.25) !important; }
    </style>
    <!-- DataTables per-column filter row -->
</head>
  <!-- Include header with all necessary libraries -->
  <?php include '../../components/header.php'; ?>
  <body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main"> 
 
        <?php
        // Optimized unified badge rendering function
        function renderBadgeClass($type, $value, $warn = null, $danger = null) {
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
                            <div class="gauge-icon" style="background-color:#dc3545"></div>
                            <div class="text-end">
                                <div class="gauge-card-title">Temperature</div>
                                <div class="gauge-value"><span id="tempValueNum">--</span> <span class="text-muted" style="font-weight:600;">°C</span></div>
                            </div>
                        </div>
                        <svg class="gauge-svg" viewBox="0 0 160 120" preserveAspectRatio="xMidYMid meet">
                            <path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
                            <path id="tempArc" class="gauge-arc-value" stroke="#dc3545" d="M20,80 A60,60 0 0 1 140,80" />
                        </svg>
                        <div class="mt-2"><span class="legend-dot legend-danger"></span><span id="tempLegend" class="gauge-legend">--</span></div>
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
                            <div class="gauge-icon" style="background-color:#28a745"></div>
                            <div class="text-end">
                                <div class="gauge-card-title">Flame Detection</div>
                                <div class="gauge-value"><span id="flameValueText">--</span></div>
                            </div>
                        </div>
                        <svg class="gauge-svg" viewBox="0 0 160 120" preserveAspectRatio="xMidYMid meet">
                            <path class="gauge-arc-track" d="M20,80 A60,60 0 0 1 140,80" />
                            <path id="flameArc" class="gauge-arc-value" stroke="#28a745" d="M20,80 A60,60 0 0 1 140,80" />
                        </svg>
                        <div class="mt-2"><span id="flameLegendDot" class="legend-dot legend-ok"></span><span id="flameLegend" class="gauge-legend">--</span></div>
                        <div class="mt-2 small text-muted">
                            <div><strong>Device:</strong> <span id="flameDeviceName">--</span></div>
                            <div><strong>Date:</strong> <span id="flameDate">--</span></div>
                            <div><strong>Time:</strong> <span id="flameTime">--</span></div>
                        </div>
                    </div>
                </div>
            </div>
                </div>
                <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <!-- Column 1: Basic Filters -->
                    <div class="col-md-6">
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="filterBuildingType" class="form-label small text-muted">Building Type</label>
                                <select id="filterBuildingType" class="form-select form-select-sm">
                                    <option value="">All Building Types</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="filterBarangay" class="form-label small text-muted">Barangay</label>
                                <select id="filterBarangay" class="form-select form-select-sm">
                                    <option value="">All Barangays</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="filterUser" class="form-label small text-muted">User</label>
                                <select id="filterUser" class="form-select form-select-sm">
                                    <option value="">All Users</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="filterDevice" class="form-label small text-muted">Device</label>
                                <select id="filterDevice" class="form-select form-select-sm">
                                    <option value="">All Devices</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Column 2: Date Range & Actions -->
                    <div class="col-md-6">
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="dateFrom" class="form-label small text-muted">Start Date</label>
                                <input id="dateFrom" type="date" class="form-control form-control-sm" placeholder="Start date" title="Filter records from this date">
                            </div>
                            <div class="col-6">
                                <label for="dateTo" class="form-label small text-muted">End Date</label>
                                <input id="dateTo" type="date" class="form-control form-control-sm" placeholder="End date" title="Filter records until this date">
                            </div>
                            <div class="col-12">
                                <div class="d-flex gap-2 mt-2">
                                    <button id="resetFilters" class="btn btn-sm btn-outline-secondary">Reset</button>
                                    <button id="exportCSV" class="btn btn-sm btn-success">Export CSV</button>
                                    <!-- <button id="debugDateFilter" class="btn btn-sm btn-outline-info">Debug Dates</button> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
              </div>
            </div>

                <div class="card shadow-sm">
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
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['id']); ?></td>
                                <td><span class="badge <?php echo renderBadgeClass('status', $r['status']); ?>"><?php echo htmlspecialchars($r['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($r['building_type']); ?></td>
                                <td><span class="badge <?php echo renderBadgeClass('level', $r['smoke'], 200, 400); ?>"><?php echo htmlspecialchars($r['smoke']); ?></span></td>
                                <td><span class="badge <?php echo renderBadgeClass('level', $r['temp'], 50, 80); ?>"><?php echo htmlspecialchars($r['temp']); ?></span></td>
                                <td><span class="badge <?php echo renderBadgeClass('level', $r['heat'], 60, 85); ?>"><?php echo htmlspecialchars($r['heat']); ?></span></td>
                                <td><span class="badge <?php echo renderBadgeClass('yesno', $r['flame_detected']); ?>"><?php echo (int)$r['flame_detected'] === 1 ? 'Yes' : 'No'; ?></span></td>
                                <td><?php echo htmlspecialchars($r['timestamp']); ?></td>
                                <td><?php echo htmlspecialchars($r['username'] ?? ('User #' . $r['user_id'])); ?></td>
                                <td><?php echo htmlspecialchars($r['barangay_name'] ?? ''); ?></td>
                                <td><span class="badge <?php echo renderBadgeClass('device', $r['device_name'] ?? null); ?>"><?php echo htmlspecialchars($r['device_name'] ?? ('Device #' . ($r['device_id'] ?? 'N/A'))); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
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
$(function() {
  var table = $('#fireDataTable').DataTable({
    order: [[0, 'desc']],
    pageLength: 25,
    lengthMenu: [10, 25, 50, 100],
    stateSave: false,
    columnDefs: [
      { targets: 0, visible: false, searchable: true }
    ]
  });

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
      smoke: { el: document.getElementById('smokeValueNum'), max: 30000, critical: 15000 },
      temp: { el: document.getElementById('tempValueNum'), max: 30000, critical: 80 },
      heat: { el: document.getElementById('heatValueNum'), max: 30000, critical: 85 }
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

  // Optimized filter management
  const filterManager = {
    populateUniqueOptions(columnIndex, selectId) {
      const opts = new Set(['']);
      table.column(columnIndex).data().each(function(v) {
        const t = cellText(v);
        if (t && t !== 'N/A' && t !== 'Building #N/A' && t !== 'Device #N/A') opts.add(t);
      });
      const select = $(selectId);
      select.find('option:not(:first)').remove();
      Array.from(opts).sort().forEach(v => {
        if (v) select.append($('<option>').val(v).text(v));
      });
    },

    rebuildDeviceOptionsForUser() {
      const selectedUser = $('#filterUser').val();
      const deviceSelect = $('#filterDevice');
      const previousDevice = deviceSelect.val();

      const devices = new Set(['']);
      if (!selectedUser) {
        table.column(10).data().each(function(v) {
          const t = cellText(v);
          if (t && t !== 'Device #N/A') devices.add(t);
        });
      } else {
        table.rows().every(function() {
          const d = this.data();
          const userText = cellText(d[8] || '');
          const deviceText = cellText(d[10] || '');
          if (userText === selectedUser && deviceText && deviceText !== 'Device #N/A') {
            devices.add(deviceText);
          }
        });
      }

      deviceSelect.find('option:not(:first)').remove();
      Array.from(devices).sort().forEach(v => {
        if (v) deviceSelect.append($('<option>').val(v).text(v));
      });

      deviceSelect.val(previousDevice && devices.has(previousDevice) ? previousDevice : '');
    },

    rebuildUserOptionsForBarangay() {
      const selectedBarangay = $('#filterBarangay').val();
      const userSelect = $('#filterUser');
      const previousUser = userSelect.val();

      const users = new Set(['']);
      if (!selectedBarangay) {
        table.column(8).data().each(function(v) {
          const t = cellText(v);
          if (t && t !== 'User #N/A') users.add(t);
        });
      } else {
        table.rows().every(function() {
          const d = this.data();
          const userText = cellText(d[8] || '');
          const brgyText = cellText(d[9] || '');
          if (brgyText === selectedBarangay && userText && userText !== 'User #N/A') {
            users.add(userText);
          }
        });
      }

      userSelect.find('option:not(:first)').remove();
      Array.from(users).sort().forEach(v => {
        if (v) userSelect.append($('<option>').val(v).text(v));
      });

      userSelect.val(previousUser && users.has(previousUser) ? previousUser : '');
    }
  };

  // Initialize filters
  filterManager.populateUniqueOptions(2, '#filterBuildingType');
  filterManager.populateUniqueOptions(9, '#filterBarangay');
  filterManager.populateUniqueOptions(8, '#filterUser');
  filterManager.populateUniqueOptions(10, '#filterDevice');
  filterManager.rebuildDeviceOptionsForUser();
  filterManager.rebuildUserOptionsForBarangay();

  // Optimized core filter application
  function applyCoreFilters() {
    const filters = {
      bt: $('#filterBuildingType').val(),
      brgy: $('#filterBarangay').val(),
      usr: $('#filterUser').val(),
      dev: $('#filterDevice').val(),
      from: $('#dateFrom').val(),
      to: $('#dateTo').val()
    };

    // Validate date range
    if (filters.from && filters.to) {
      const fromDate = new Date(filters.from + 'T00:00:00');
      const toDate = new Date(filters.to + 'T23:59:59');
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

    // Clear previous custom filter
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => !fn._isCore);

    // Create optimized filter function
    const filterFunction = function(settings, data) {
      const buildingType = cellText(data[2] || '');
      const barangay = cellText(data[9] || '');
      const user = cellText(data[8] || '');
      const device = cellText(data[10] || '');
      const ts = cellText(data[7] || '');

      // Apply filters
      if (filters.bt && buildingType !== filters.bt) return false;
      if (filters.brgy && barangay !== filters.brgy) return false;
      if (filters.usr && user !== filters.usr) return false;
      if (filters.dev && device !== filters.dev) return false;

      // Apply date filters
      if (filters.from || filters.to) {
        const recordDate = parseTimestamp(ts);
        
        if (isNaN(recordDate.getTime())) return true;
        
        if (filters.from) {
          const fromDate = new Date(filters.from + 'T00:00:00');
          if (recordDate < fromDate) return false;
        }
        
        if (filters.to) {
          const toDate = new Date(filters.to + 'T23:59:59');
          if (recordDate > toDate) return false;
        }
      }
      
      return true;
    };
    
    filterFunction._isCore = true;
    $.fn.dataTable.ext.search.push(filterFunction);
    table.draw();
    
    updateDateFilterVisuals();
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

  // Event handlers
  $('#filterBuildingType,#filterBarangay,#filterUser,#filterDevice,#dateFrom,#dateTo').on('change keyup', applyCoreFilters);
  
  $('#filterBarangay').on('change', function() {
    filterManager.rebuildUserOptionsForBarangay();
    filterManager.rebuildDeviceOptionsForUser();
    applyCoreFilters();
  });
  
  $('#filterUser').on('change', function() {
    filterManager.rebuildDeviceOptionsForUser();
    applyCoreFilters();
  });

  // Reset filters
  $('#resetFilters').on('click', function() {
    table.search('');
    $('#filterBuildingType,#filterBarangay,#filterUser,#filterDevice,#dateFrom,#dateTo').val('');
    $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(fn => !fn._isCore);
    table.draw();
    filterManager.rebuildDeviceOptionsForUser();
    filterManager.rebuildUserOptionsForBarangay();
    updateDateFilterVisuals();
    
    Swal.fire({
      title: 'Success!',
      text: 'All filters have been reset successfully.',
      icon: 'success',
      confirmButtonText: 'OK',
      confirmButtonColor: '#28a745'
    });
  });

  // Optimized CSV export
  $('#exportCSV').on('click', function() {
    const filteredData = table.rows({search: 'applied'}).data().toArray();
    
    if (filteredData.length === 0) {
      Swal.fire({
        title: 'No Data',
        text: 'There are no records to export. Please adjust your filters.',
        icon: 'warning',
        confirmButtonText: 'OK',
        confirmButtonColor: '#ffc107'
      });
      return;
    }
    
    const headers = [
      'ID', 'Status', 'Building Type', 'Smoke (ppm)', 'Temperature (°C)', 
      'Heat Index (°C)', 'Flame Detected', 'Timestamp', 'User', 
      'Barangay', 'Device'
    ];
    
    let csvContent = headers.join(',') + '\n';
    
    filteredData.forEach(row => {
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
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
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
    
    Swal.fire({
      title: 'Export Successful!',
      text: 'Fire data has been exported to CSV successfully.',
      icon: 'success',
      confirmButtonText: 'OK',
      confirmButtonColor: '#28a745'
    });
  });

  // Update gauges on table draw
  table.on('draw', function() {
    const first = table.row(0).data();
    if (!first) return;
    
    const smokeCell = $(table.row(0).node()).find('td').eq(3).text().trim();
    const tempCell = $(table.row(0).node()).find('td').eq(4).text().trim();
    const heatCell = $(table.row(0).node()).find('td').eq(5).text().trim();
    const flameCell = $(table.row(0).node()).find('td').eq(6).text().trim();
    const timestampCell = $(table.row(0).node()).find('td').eq(7).text().trim();
    const deviceCell = $(table.row(0).node()).find('td').eq(10).text().trim();

    const cont = document.getElementById('latestSensorData');
    if (cont) {
      cont.setAttribute('data-smoke', smokeCell || '');
      cont.setAttribute('data-temp', tempCell || '');
      cont.setAttribute('data-heat', heatCell || '');
      cont.setAttribute('data-flame', (flameCell && flameCell.toLowerCase() === 'yes') ? '1' : '0');
      cont.setAttribute('data-timestamp', timestampCell || '');
      cont.setAttribute('data-device-name', deviceCell || '');
    }
    updateGaugesFromDataset();
  });

  // Cleanup on page unload
  $(window).on('beforeunload', function() {
    if (pollTimeout) clearTimeout(pollTimeout);
  });
});
</script>
 <!-- Include header components -->
 <?php include '../../components/scripts.php'; ?>
</body>
</html>



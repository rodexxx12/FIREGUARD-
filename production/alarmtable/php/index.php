<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

// OPTIMIZED Query to get ONLY EMERGENCY and ACKNOWLEDGED fire_data records - FIXED LOOP ISSUE
$query = "
    SELECT 
        fd.id as fire_data_id,
        fd.status as alarm_status,
        fd.timestamp as alarm_timestamp,
        fd.smoke,
        fd.temp,
        fd.heat,
        fd.flame_detected,
        fd.ml_confidence,
        fd.ml_prediction,
        fd.ml_fire_probability,
        fd.ai_prediction,
        fd.acknowledged_at_time,
        fd.notified,
        fd.building_id,
        fd.user_id,
        fd.device_id,
        b.building_name,
        b.building_type,
        b.address,
        b.contact_person,
        b.contact_number,
        b.total_floors,
        b.has_sprinkler_system,
        b.has_fire_alarm,
        b.has_fire_extinguishers,
        b.has_emergency_exits,
        b.has_emergency_lighting,
        b.has_fire_escape,
        b.last_inspected,
        b.latitude,
        b.longitude,
        b.construction_year,
        b.building_area,
        b.created_at,
        u.fullname as owner_name,
        u.email_address as owner_email,
        u.contact_number as owner_contact,
        br.barangay_name,
        d.device_name,
        d.device_number,
        d.status as device_status,
        d.last_activity
    FROM fire_data fd
    LEFT JOIN buildings b ON fd.building_id = b.id
    LEFT JOIN users u ON fd.user_id = u.user_id
    LEFT JOIN barangay br ON b.barangay_id = br.id
    LEFT JOIN devices d ON fd.device_id = d.device_id
    WHERE fd.status IN ('EMERGENCY', 'ACKNOWLEDGED')
    AND fd.timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY fd.timestamp DESC, fd.id DESC
    LIMIT 1000
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $fire_data_records = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching fire data: " . $e->getMessage());
    $fire_data_records = [];
}
?>

<?php include('../../components/header.php'); ?>
    <link rel="stylesheet" href="../css/custom.css">
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- DataTables JS - Using CDNJS for better reliability -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Verify DataTables loaded -->
    <script>
        console.log('DataTables loaded check:', typeof $.fn.DataTable !== 'undefined');
    </script>
    <style>
        /* Additional styling for alarm table specific elements */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-normal { background-color: #dcfce7; color: #166534; }
        .status-warning { background-color: #fef3c7; color: #92400e; }
        .status-danger { background-color: #fee2e2; color: #991b1b; }
        .status-info { background-color: #dbeafe; color: #1e40af; }
        .status-missed { background-color: #fee2e2; color: #991b1b; border: 2px solid #ef4444; }
        
        .device-status {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .device-online { background-color: #10b981; }
        .device-offline { background-color: #ef4444; }
        
        .safety-features {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        
        .safety-badge {
            background-color: #f3f4f6;
            color: #6b7280;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
            border: 1px solid #e5e7eb;
        }
        
        .safety-badge.has {
            background-color: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        
        .ml-confidence {
            font-weight: 600;
        }
        
        .ml-high { color: #ef4444; }
        .ml-medium { color: #f59e0b; }
        .ml-low { color: #10b981; }
        
        /* Filter grid layout */
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-label {
            font-size: 0.875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 6px;
        }
        
        .filter-input {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 0.875rem;
            background: #ffffff;
            color: #111827;
            transition: all 0.2s ease;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .filter-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }
        
        .filter-stats {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .filter-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn-filter {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-reset {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        
        .btn-reset:hover {
            background: #e5e7eb;
        }
        
        .btn-success {
            background: #10b981;
            color: #ffffff;
            border: 1px solid #10b981;
        }
        
        .btn-success:hover {
            background: #059669;
            border-color: #047857;
        }
        
        /* Burger Menu Toggle Button Styles - Green with Animations */
        .filter-toggle-btn {
            display: flex !important;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            width: auto;
            height: auto;
            background: rgba(38, 185, 154, 0.08);
            border: 1px solid rgba(38, 185, 154, 0.2);
            cursor: pointer;
            padding: 6px 8px;
            margin: 0;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            visibility: visible !important;
            opacity: 1 !important;
            border-radius: 6px;
            position: relative;
            line-height: 1;
            box-shadow: 0 2px 4px rgba(38, 185, 154, 0.15);
        }
        
        .filter-toggle-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(38, 185, 154, 0.15);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: all 0.4s ease;
        }
        
        .filter-toggle-btn:hover {
            transform: scale(1.1);
            background: rgba(38, 185, 154, 0.15);
            border-color: rgba(38, 185, 154, 0.4);
            box-shadow: 0 3px 8px rgba(38, 185, 154, 0.25);
        }
        
        .filter-toggle-btn:hover::before {
            width: 100%;
            height: 100%;
        }
        
        .filter-toggle-btn.active {
            transform: scale(1.1) rotate(90deg);
            background: rgba(38, 185, 154, 0.2);
            border-color: rgba(38, 185, 154, 0.5);
            box-shadow: 0 3px 10px rgba(38, 185, 154, 0.3);
        }
        
        .filter-toggle-btn.active::before {
            width: 100%;
            height: 100%;
        }
        
        .burger-line {
            width: 22px;
            height: 3px;
            background-color: #26B99A;
            border-radius: 3px;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: block;
            margin: 2.5px 0;
            position: relative;
            box-shadow: 0 2px 4px rgba(38, 185, 154, 0.4);
        }
        
        .filter-toggle-btn:hover .burger-line {
            background-color: #1e9d82;
            box-shadow: 0 3px 6px rgba(38, 185, 154, 0.5);
            transform: scaleX(1.1);
        }
        
        .filter-toggle-btn.active .burger-line {
            background-color: #26B99A;
            box-shadow: 0 3px 6px rgba(38, 185, 154, 0.6);
        }
        
        .filter-toggle-btn.active .burger-line:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
            background-color: #26B99A;
            width: 22px;
        }
        
        .filter-toggle-btn.active .burger-line:nth-child(2) {
            opacity: 0;
            transform: scaleX(0);
        }
        
        .filter-toggle-btn.active .burger-line:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
            background-color: #26B99A;
            width: 22px;
        }
        
        /* Pulse animation when active */
        @keyframes pulse-green {
            0% {
                box-shadow: 0 0 0 0 rgba(38, 185, 154, 0.4);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(38, 185, 154, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(38, 185, 154, 0);
            }
        }
        
        .filter-toggle-btn.active {
            animation: pulse-green 2s infinite;
        }
        
        /* Filter Overlay with Animation */
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
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .filter-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Filter Panel - Side Panel Style with Enhanced Animations */
        .filter-panel {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100%;
            background-color: #fff;
            box-shadow: -2px 0 20px rgba(0,0,0,0.3);
            z-index: 1000;
            transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            padding: 2rem;
            transform: translateX(0);
        }
        
        .filter-panel.active {
            right: 0;
            animation: slideInRight 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideInRight {
            0% {
                transform: translateX(100%);
                opacity: 0;
            }
            60% {
                transform: translateX(-5%);
            }
            100% {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Filter Panel Header Animation */
        .filter-panel-header {
            animation: fadeInDown 0.5s ease 0.2s both;
        }
        
        @keyframes fadeInDown {
            0% {
                opacity: 0;
                transform: translateY(-20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Filter Groups Staggered Animation */
        .filter-panel.active .filter-group {
            animation: fadeInUp 0.5s ease both;
        }
        
        .filter-panel.active .filter-group:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        .filter-panel.active .filter-group:nth-child(2) {
            animation-delay: 0.15s;
        }
        
        .filter-panel.active .filter-group:nth-child(3) {
            animation-delay: 0.2s;
        }
        
        .filter-panel.active .filter-group:nth-child(4) {
            animation-delay: 0.25s;
        }
        
        .filter-panel.active .filter-group:nth-child(5) {
            animation-delay: 0.3s;
        }
        
        .filter-panel.active .filter-group:nth-child(6) {
            animation-delay: 0.35s;
        }
        
        .filter-panel.active .filter-group:nth-child(7) {
            animation-delay: 0.4s;
        }
        
        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
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
            display: flex;
            align-items: center;
        }
        
        .panel_toolbox li {
            float: left;
            cursor: pointer;
            margin-left: 5px;
            display: flex;
            align-items: center;
            min-height: 32px;
        }
        
        .panel_toolbox li a {
            padding: 5px;
            color: #C5C7CB;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            height: 100%;
        }
        
        .panel_toolbox li a:hover {
            color: #26B99A;
        }
        
        /* Ensure burger toggle is always visible and aligned */
        #filterToggleBtn {
            display: flex !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative;
            z-index: 10;
            align-items: center;
            justify-content: center;
            min-height: 32px;
        }
        
        .panel_toolbox #filterToggleBtn {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
        
        /* Make sure the burger lines are visible and green */
        #filterToggleBtn .burger-line {
            background-color: #26B99A !important;
            display: block !important;
            visibility: visible !important;
            width: 22px !important;
            height: 3px !important;
        }
        
        #filterToggleBtn:hover .burger-line {
            background-color: #1e9d82 !important;
            transform: scaleX(1.1);
        }
        
        /* Add smooth transitions to filter inputs */
        .filter-group .form-control,
        .filter-group .form-select {
            transition: all 0.3s ease;
            border: 1px solid #ddd;
        }
        
        .filter-group .form-control:focus,
        .filter-group .form-select:focus {
            border-color: #26B99A;
            box-shadow: 0 0 0 3px rgba(38, 185, 154, 0.1);
            transform: translateY(-1px);
        }
        
        /* Animate buttons on hover */
        .filter-group .btn {
            transition: all 0.3s ease;
        }
        
        .filter-group .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .filter-group .btn:active {
            transform: translateY(0);
        }
        
        /* Add subtle animation to panel header icon */
        .filter-panel-header h3 i {
            animation: rotateIn 0.6s ease 0.3s both;
        }
        
        @keyframes rotateIn {
            0% {
                opacity: 0;
                transform: rotate(-180deg) scale(0);
            }
            100% {
                opacity: 1;
                transform: rotate(0deg) scale(1);
            }
        }
        
        /* DataTables Filter Input Styling */
        .dataTables_wrapper .dataTables_filter input {
            margin: 0 5px;
            padding: 5px 10px;
            padding-right: 25px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: inline-block;
            width: auto;
        }

        /* DataTables length (Show N records) styling */
        .dataTables_wrapper .dataTables_length {
            margin-bottom: 12px;
        }

        .dataTables_wrapper .dataTables_length label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            color: #555;
            margin: 0;
        }

        .dataTables_wrapper .dataTables_length select {
            margin: 0 5px;
            padding: 6px 30px 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            width: auto;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn-filter {
                width: 100%;
            }
        }
    </style>
</head>
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
            <!-- Building Type Filter -->
            <div class="filter-group">
                <label for="buildingTypeFilter" class="form-label">Building Type:</label>
                <select id="buildingTypeFilter" class="form-select form-select-sm" onchange="filterColumn(0, this.value)">
                    <option value="">All Building Types</option>
                    <?php
                    // Get unique building types from the data
                    $building_types = array_unique(array_column($fire_data_records, 'building_type'));
                    $building_types = array_filter($building_types);
                    sort($building_types);
                    foreach ($building_types as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Device Status Filter -->
            <div class="filter-group">
                <label for="deviceStatusFilter" class="form-label">Device Status:</label>
                <select id="deviceStatusFilter" class="form-select form-select-sm" onchange="filterColumn(3, this.value)">
                    <option value="">All Device Status</option>
                    <option value="Online">Online</option>
                    <option value="Offline">Offline</option>
                    <option value="Faulty">Faulty</option>
                    <option value="No Device">No Device</option>
                </select>
            </div>
            
            <!-- Alarm Status Filter -->
            <div class="filter-group">
                <label for="alarmStatusFilter" class="form-label">Alarm Status:</label>
                <select id="alarmStatusFilter" class="form-select form-select-sm" onchange="filterColumn(4, this.value)">
                    <option value="">All Alarm Status</option>
                    <option value="MISSED">Emergency (Missed)</option>
                    <option value="ACKNOWLEDGED">Acknowledged</option>
                </select>
            </div>
            
            <!-- User Filter -->
            <div class="filter-group">
                <label for="userFilter" class="form-label">User:</label>
                <select id="userFilter" class="form-select form-select-sm" onchange="filterColumn(1, this.value)">
                    <option value="">All Users</option>
                    <?php
                    // Get unique users from the data
                    $users = array_unique(array_column($fire_data_records, 'owner_name'));
                    $users = array_filter($users);
                    sort($users);
                    foreach ($users as $user): ?>
                        <option value="<?php echo htmlspecialchars($user); ?>"><?php echo htmlspecialchars($user); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Date Range Filter -->
            <div class="filter-group">
                <label for="startDateFilter" class="form-label">Start Date:</label>
                <input type="date" id="startDateFilter" class="form-control form-control-sm" onchange="applyDateFilter()">
                <small class="text-muted" style="font-size: 0.75rem; margin-top: 4px; display: block;">mm/dd/yyyy</small>
            </div>

            <div class="filter-group">
                <label for="endDateFilter" class="form-label">End Date:</label>
                <input type="date" id="endDateFilter" class="form-control form-control-sm" onchange="applyDateFilter()">
                <small class="text-muted" style="font-size: 0.75rem; margin-top: 4px; display: block;">mm/dd/yyyy</small>
            </div>

            <!-- Action Buttons -->
            <div class="filter-group">
                <button class="btn btn-outline-secondary btn-sm" onclick="resetAllFilters()" style="width: 100%; margin-top: 10px;">
                    <i class="fas fa-times"></i> Reset All Filters
                </button>
                <button class="btn btn-success btn-sm" onclick="exportToCSV()" style="width: 100%; margin-top: 10px;">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
            <div class="filter-group">
                <small class="text-muted" style="display: block; margin-top: 10px;">
                    <span id="filterStats">Showing all records</span>
                </small>
            </div>
        </div>
    </div>
    
    <div class="main-card">
                <!-- Main Content -->
                <div class="row">
            <div class="col-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fas fa-list-alt"></i> Alarm Records</h2>
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
        
     <!-- Data Table -->
     <div class="x_panel">
                    <div class="x_content">
            <table id="buildingsTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Residence</th>
                        <th>Owner</th>
                        <th>Location</th>
                        <th>Device</th>
                        <th>Acknowledgment</th>
                        <th>Time</th>
                        <th>Sensor Readings</th>
                        <th>Safety Features</th>
                        <th>ML Analysis</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fire_data_records as $record): ?>
                    <tr>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($record['building_name'] ?? 'Unknown Building'); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($record['building_type'] ?? 'Unknown Type'); ?></small><br>
                                <small class="text-muted"><?php echo htmlspecialchars($record['address'] ?? 'No Address'); ?></small>
                            </div>
                        </td>
                        <td>
                            <div>
                                <?php if ($record['owner_name'] && $record['owner_name'] !== 'Unknown Owner'): ?>
                                    <strong><?php echo htmlspecialchars($record['owner_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['owner_email'] ?? 'No Email'); ?></small><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['owner_contact'] ?? 'No Contact'); ?></small>
                                <?php else: ?>
                                    <strong>Unknown Owner</strong><br>
                                    <small class="text-muted">No Email</small><br>
                                    <small class="text-muted">No Contact</small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div>
                                <strong><?php echo htmlspecialchars($record['barangay_name'] ?? 'Unknown Location'); ?></strong><br>
                                <?php if ($record['latitude'] && $record['longitude']): ?>
                                    <small class="text-muted"><?php echo $record['latitude']; ?>, <?php echo $record['longitude']; ?></small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($record['device_name']): ?>
                                <div>
                                    <span class="device-status device-<?php echo strtolower($record['device_status']); ?>"></span>
                                    <strong><?php echo htmlspecialchars($record['device_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['device_number']); ?></small><br>
                                    <?php if ($record['last_activity']): ?>
                                        <small class="text-muted">Last: <?php echo date('M j, Y H:i', strtotime($record['last_activity'])); ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center">
                                    <span class="status-badge status-warning">NO DEVICE</span><br>
                                    <small class="text-muted">DEVICE</small>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            // Display the actual fire_data status
                            $display_status = $record['alarm_status'];
                            $status_class = 'normal';
                            
                            // Set status class based on fire_data status
                            if ($record['alarm_status'] === 'EMERGENCY') {
                                $display_status = 'MISSED';
                                $status_class = 'missed';
                            } elseif ($record['alarm_status'] === 'ACKNOWLEDGED') {
                                $display_status = 'ACKNOWLEDGED';
                                $status_class = 'info';
                            }
                            ?>
                            <div class="text-center">
                                <span class="status-badge status-<?php echo $status_class; ?>">
                                    <?php echo htmlspecialchars($display_status); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php if ($record['alarm_timestamp']): ?>
                                <div>
                                    <strong><?php echo date('M j, Y', strtotime($record['alarm_timestamp'])); ?></strong><br>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($record['alarm_timestamp'])); ?></small>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">No Time</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($record['fire_data_id']): ?>
                                <div>
                                    <small><strong>Smoke:</strong> <?php echo $record['smoke']; ?> ppm</small><br>
                                    <small><strong>Temp:</strong> <?php echo $record['temp']; ?>°C</small><br>
                                    <small><strong>Heat:</strong> <?php echo $record['heat']; ?>°C</small><br>
                                    <?php if ($record['flame_detected']): ?>
                                        <small class="text-danger"><strong>Flame:</strong> Detected</small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div>
                                    <small><strong>Smoke:</strong> <?php echo $record['smoke'] ?? '0'; ?> ppm</small><br>
                                    <small><strong>Temp:</strong> <?php echo $record['temp'] ?? '0'; ?>°C</small><br>
                                    <small><strong>Heat:</strong> <?php echo $record['heat'] ?? '0'; ?>°C</small><br>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="safety-features">
                                <?php 
                                $features = [
                                    'Sprinkler' => $record['has_sprinkler_system'],
                                    'Alarm' => $record['has_fire_alarm'],
                                    'Extinguisher' => $record['has_fire_extinguishers'],
                                    'Exits' => $record['has_emergency_exits'],
                                    'Lighting' => $record['has_emergency_lighting'],
                                    'Escape' => $record['has_fire_escape']
                                ];
                                foreach ($features as $name => $has): ?>
                                    <span class="safety-badge <?php echo $has ? 'has' : ''; ?>">
                                        <?php echo $name; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($record['ml_confidence']): ?>
                                <div>
                                    <small class="ml-confidence ml-<?php 
                                        echo $record['ml_confidence'] >= 80 ? 'high' : 
                                            ($record['ml_confidence'] >= 50 ? 'medium' : 'low'); 
                                    ?>">
                                        <?php echo number_format($record['ml_confidence'], 1); ?>%
                                    </small><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($record['ai_prediction']); ?></small>
                                </div>
                            <?php else: ?>
                                <div>
                                    <small class="text-muted">0.0%</small><br>
                                    <small class="text-muted">N/A</small>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
    <script>
        let dataTable;
        
        // Success modal function for filter operations
        function showFilterSuccessModal(message = 'Filter applied successfully!') {
            Swal.fire({
                title: 'Success!',
                text: message,
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        }
        
        // Function to initialize DataTable - FIXED LOOP ISSUE
        function initializeDataTable() {
            console.log('Initializing DataTable...');
            
            // Prevent multiple initializations
            if (window.dataTableInitialized) {
                console.log('DataTable already initialized, skipping...');
                return true;
            }
            
            // Check if DataTable is already initialized and destroy safely
            if ($.fn.DataTable.isDataTable('#buildingsTable')) {
                console.log('DataTable already initialized, destroying first...');
                try {
                    $('#buildingsTable').DataTable().destroy();
                } catch (e) {
                    console.log('Error destroying existing DataTable:', e);
                }
            }
            
            try {
                // Initialize DataTable with enhanced pagination
                dataTable = $('#buildingsTable').DataTable({
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "All"]],
                    order: [[5, 'desc']], // Sort by time column (most recent first)
                    columnDefs: [
                        { className: "text-center", targets: [3, 4, 5, 8] },
                        { orderable: false, targets: [] } // All columns are orderable
                    ],
                    language: {
                        search: "Quick Search:",
                        lengthMenu: "Show _MENU_ records per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ records",
                        infoEmpty: "No records found",
                        infoFiltered: "(filtered from _MAX_ total records)",
                        paginate: {
                            first: "<< First",
                            last: "Last >>",
                            next: "Next >",
                            previous: "< Previous"
                        },
                        emptyTable: "No alarm data available",
                        zeroRecords: "No matching records found"
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    pagingType: "full_numbers",
                    stateSave: true,
                    processing: true,
                    initComplete: function() {
                        console.log('DataTable initialized successfully');
                        $('.dataTables_filter input').addClass('form-control');
                        $('.dataTables_length select').addClass('form-control');
                        updateFilterStats();
                        window.dataTableInitialized = true; // Mark as initialized
                    },
                    drawCallback: function() {
                        updateFilterStats();
                    }
                });
                
                console.log('DataTable initialized successfully');
                return true;
                
            } catch (error) {
                console.error('Error initializing DataTable:', error);
                dataTable = null;
                window.dataTableInitialized = false;
                return false;
            }
        }
        
        // Document ready handler
        $(document).ready(function() {
            console.log('Document ready, initializing DataTable...');
            
            // Simple initialization without complex retry logic
            if (typeof $.fn.DataTable !== 'undefined') {
                initializeDataTable();
            } else {
                console.error('DataTables not available');
            }
        });
        
        // Essential utility functions
        function refreshTableData() {
            if (dataTable) {
                dataTable.ajax.reload();
            } else {
                location.reload();
            }
        }
        
        function exportData() {
            if (dataTable) {
                const data = dataTable.rows({search: 'applied'}).data().toArray();
                const csvContent = convertToCSV(data);
                downloadCSV(csvContent, 'alarm_data_export.csv');
            } else {
                console.error('DataTable not available for export');
            }
        }
        
        function convertToCSV(data) {
            const headers = ['Building Info', 'Owner', 'Location', 'Device Status', 'Alarm Status', 'Time', 'Sensor Readings', 'Safety Features', 'ML Analysis'];
            let csv = headers.join(',') + '\n';
            
            data.forEach(row => {
                const csvRow = row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',');
                csv += csvRow + '\n';
            });
            
            return csv;
        }
        
        function downloadCSV(content, filename) {
            const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Enhanced export function for the new export button
        function exportToCSV() {
            try {
                console.log('Starting CSV export...');
                
                let csvContent = '';
                let headers = [];
                let rows = [];
                
                if (dataTable) {
                    // Use DataTables API to get filtered data
                    console.log('Using DataTables for export...');
                    
                    // Get headers from the table
                    $('#buildingsTable thead th').each(function() {
                        headers.push($(this).text().trim());
                    });
                    
                    // Get visible/filtered rows
                    dataTable.rows({search: 'applied'}).every(function() {
                        const rowData = [];
                        const rowNode = this.node();
                        
                        $(rowNode).find('td').each(function() {
                            // Clean the cell content - remove HTML tags and extra whitespace
                            let cellText = $(this).text().trim();
                            cellText = cellText.replace(/\s+/g, ' '); // Replace multiple spaces with single space
                            rowData.push(cellText);
                        });
                        
                        rows.push(rowData);
                    });
                    
                } else {
                    // Fallback: get data directly from table HTML
                    console.log('Using direct HTML parsing for export...');
                    
                    // Get headers
                    $('#buildingsTable thead th').each(function() {
                        headers.push($(this).text().trim());
                    });
                    
                    // Get visible rows only
                    $('#buildingsTable tbody tr:visible').each(function() {
                        const rowData = [];
                        $(this).find('td').each(function() {
                            let cellText = $(this).text().trim();
                            cellText = cellText.replace(/\s+/g, ' ');
                            rowData.push(cellText);
                        });
                        rows.push(rowData);
                    });
                }
                
                // Build CSV content
                csvContent = headers.map(header => `"${header.replace(/"/g, '""')}"`).join(',') + '\n';
                
                rows.forEach(row => {
                    const csvRow = row.map(cell => `"${cell.replace(/"/g, '""')}"`).join(',');
                    csvContent += csvRow + '\n';
                });
                
                // Generate filename with timestamp
                const now = new Date();
                const timestamp = now.getFullYear() + '-' + 
                                String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                                String(now.getDate()).padStart(2, '0') + '_' +
                                String(now.getHours()).padStart(2, '0') + '-' +
                                String(now.getMinutes()).padStart(2, '0');
                
                const filename = `alarm_table_export_${timestamp}.csv`;
                
                // Download the file
                downloadCSV(csvContent, filename);
                
                console.log(`CSV export completed: ${rows.length} rows exported to ${filename}`);
                
                // Show success message
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Export Successful!',
                        text: `${rows.length} records exported to ${filename}`,
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false
                    });
                } else {
                    alert(`Export successful! ${rows.length} records exported to ${filename}`);
                }
                
            } catch (error) {
                console.error('Error during CSV export:', error);
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'Export Failed',
                        text: 'There was an error exporting the data. Please try again.',
                        icon: 'error'
                    });
                } else {
                    alert('Export failed. Please try again.');
                }
            }
        }
        
        // Filter individual columns using DataTables column().search() - FIXED LOOP ISSUE
        function filterColumn(columnIndex, value) {
            // Prevent multiple simultaneous calls
            if (window.columnFilterInProgress) {
                console.log('Column filter already in progress, skipping...');
                return;
            }
            
            window.columnFilterInProgress = true;
            
            try {
                if (dataTable) {
                    // Use regex search for better matching with loop prevention
                    if (value === '') {
                        dataTable.column(columnIndex).search('').draw();
                    } else {
                        // Escape special regex characters and create case-insensitive search
                        const escapedValue = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                        dataTable.column(columnIndex).search(escapedValue, true, false).draw();
                    }
                    console.log(`Filtering column ${columnIndex} with value: "${value}"`);
                    
                    // Show success modal for filter application
                    if (value !== '') {
                        showFilterSuccessModal(`Filter applied to column ${columnIndex + 1}`);
                    }
                } else {
                    console.log('DataTable not available, using basic filtering...');
                    // Basic filtering for fallback mode with loop prevention
                    var searchValue = value.toLowerCase();
                    $('#buildingsTable tbody tr').each(function() {
                        var row = $(this);
                        var cellText = row.find('td').eq(columnIndex).text().toLowerCase();
                        if (value === '' || cellText.indexOf(searchValue) > -1) {
                            row.show();
                        } else {
                            row.hide();
                        }
                    });
                    
                    // Show success modal for basic filter application
                    if (value !== '') {
                        showFilterSuccessModal(`Filter applied to column ${columnIndex + 1}`);
                    }
                }
            } catch (error) {
                console.error('Error in column filtering:', error);
            } finally {
                // Always reset the flag
                window.columnFilterInProgress = false;
            }
        }
        
        // Helper function to parse date from table cell
        function parseDateFromCell(timeText) {
            console.log('Parsing date from:', timeText);
            
            // Clean the text - remove HTML tags and extra whitespace
            const cleanText = timeText.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
            console.log('Cleaned text:', cleanText);
            
            // Extract date from timestamp string (format: "Jan 15, 2025")
            const dateMatch = cleanText.match(/(\w{3})\s+(\d{1,2}),\s+(\d{4})/);
            if (!dateMatch) {
                console.log('No date match found in cleaned text:', cleanText);
                return null;
            }
            
            const [, month, day, year] = dateMatch;
            const monthMap = {
                'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
                'May': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
                'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
            };
            
            const recordDate = `${year}-${monthMap[month]}-${day.padStart(2, '0')}`;
            console.log(`Successfully parsed date: ${cleanText} -> ${recordDate}`);
            return recordDate;
        }
        
        // Date filter function with start and end dates - FIXED LOOP ISSUE
        function applyDateFilter() {
            // Prevent multiple simultaneous calls
            if (window.dateFilterInProgress) {
                console.log('Date filter already in progress, skipping...');
                return;
            }
            
            window.dateFilterInProgress = true;
            
            const startDate = document.getElementById('startDateFilter').value;
            const endDate = document.getElementById('endDateFilter').value;
            
            console.log(`Applying date filter: ${startDate || 'no start'} to ${endDate || 'no end'}`);
            
            try {
                if (dataTable) {
                    // DataTables mode - FIXED LOOP ISSUE
                    console.log('Using DataTables date filtering...');
                    
                    // Clear existing custom search functions safely
                    if ($.fn.dataTable.ext.search) {
                        $.fn.dataTable.ext.search = [];
                    }
                    
                    // If no dates selected, don't apply any filter
                    if (!startDate && !endDate) {
                        dataTable.draw();
                        updateFilterStats();
                        return;
                    }
                    
                    // Apply custom date filtering with loop prevention
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        // Prevent infinite loops by checking if this is the same table
                        if (settings.nTable.id !== 'buildingsTable') {
                            return true;
                        }
                        
                        const alarmTimestamp = data[5]; // Column 5 contains the time
                        const recordDate = parseDateFromCell(alarmTimestamp);
                        
                        if (!recordDate) return true; // If no date found, include the row
                        
                        // Check if record date is within range
                        if (startDate && recordDate < startDate) return false;
                        if (endDate && recordDate > endDate) return false;
                        
                        return true;
                    });
                    
                    dataTable.draw();
                    updateFilterStats();
                    console.log('DataTables date filter applied successfully');
                    
                    // Show success modal for date filter
                    if (startDate || endDate) {
                        let dateMessage = 'Date filter applied';
                        if (startDate && endDate) {
                            dateMessage += ` (${startDate} to ${endDate})`;
                        } else if (startDate) {
                            dateMessage += ` (from ${startDate})`;
                        } else if (endDate) {
                            dateMessage += ` (until ${endDate})`;
                        }
                        showFilterSuccessModal(dateMessage);
                    }
                    
                } else {
                    // Basic mode - FIXED LOOP ISSUE
                    console.log('Using basic date filtering...');
                    
                    // If no dates selected, show all rows
                    if (!startDate && !endDate) {
                        $('#buildingsTable tbody tr').show();
                        console.log('Date filter cleared - showing all rows');
                        return;
                    }
                    
                    // Filter rows based on date with loop prevention
                    let visibleCount = 0;
                    let totalCount = 0;
                    
                    $('#buildingsTable tbody tr').each(function() {
                        totalCount++;
                        const row = $(this);
                        const timeCell = row.find('td').eq(5); // Column 5 contains the time
                        const timeText = timeCell.html(); // Use html() to get the full HTML content
                        
                        console.log('Processing row', totalCount, 'with time content:', timeText);
                        
                        const recordDate = parseDateFromCell(timeText);
                        if (!recordDate) {
                            row.show(); // Show if no date found
                            visibleCount++;
                            return;
                        }
                        
                        // Check if record date is within range
                        let shouldShow = true;
                        if (startDate && recordDate < startDate) {
                            console.log(`Row ${totalCount}: Date ${recordDate} is before start date ${startDate}`);
                            shouldShow = false;
                        }
                        if (endDate && recordDate > endDate) {
                            console.log(`Row ${totalCount}: Date ${recordDate} is after end date ${endDate}`);
                            shouldShow = false;
                        }
                        
                        if (shouldShow) {
                            row.show();
                            visibleCount++;
                            console.log(`Row ${totalCount}: Showing (date ${recordDate} is within range)`);
                        } else {
                            row.hide();
                            console.log(`Row ${totalCount}: Hiding (date ${recordDate} is outside range)`);
                        }
                    });
                    
                    console.log(`Basic date filter applied: ${visibleCount} of ${totalCount} rows visible`);
                    console.log('Basic date filter applied successfully');
                    
                    // Show success modal for basic date filter
                    if (startDate || endDate) {
                        let dateMessage = 'Date filter applied';
                        if (startDate && endDate) {
                            dateMessage += ` (${startDate} to ${endDate})`;
                        } else if (startDate) {
                            dateMessage += ` (from ${startDate})`;
                        } else if (endDate) {
                            dateMessage += ` (until ${endDate})`;
                        }
                        showFilterSuccessModal(dateMessage);
                    }
                }
            } catch (error) {
                console.error('Error in date filtering:', error);
            } finally {
                // Always reset the flag
                window.dateFilterInProgress = false;
            }
        }
        
        
        
        // Reset all filters - FIXED LOOP ISSUE
        function resetAllFilters() {
            // Prevent multiple simultaneous calls
            if (window.resetFilterInProgress) {
                console.log('Reset filter already in progress, skipping...');
                return;
            }
            
            window.resetFilterInProgress = true;
            
            console.log('Resetting all filters...');
            
            try {
                // Clear all filter inputs
                document.getElementById('buildingTypeFilter').value = '';
                document.getElementById('deviceStatusFilter').value = '';
                document.getElementById('alarmStatusFilter').value = '';
                document.getElementById('userFilter').value = '';
                document.getElementById('startDateFilter').value = '';
                document.getElementById('endDateFilter').value = '';
                
                // Clear DataTable search and column filters with loop prevention
                if (dataTable) {
                    // Clear all custom search functions safely
                    if ($.fn.dataTable.ext.search) {
                        $.fn.dataTable.ext.search = [];
                    }
                    dataTable.search('').columns().search('').draw();
                    updateFilterStats();
                    console.log('DataTable filters cleared');
                } else {
                    console.log('DataTable not available, clearing basic filters...');
                    // Show all rows for basic mode
                    $('#buildingsTable tbody tr').show();
                    console.log('Basic filters cleared');
                }
                
                // Clear any date filter state
                console.log('All filters reset including date filters');
                
                // Show success modal for filter reset
                showFilterSuccessModal('All filters have been reset successfully!');
            } catch (error) {
                console.error('Error resetting filters:', error);
            } finally {
                // Always reset the flag
                window.resetFilterInProgress = false;
            }
        }
        
        
        function updateFilterStats() {
            if (dataTable && typeof dataTable.page === 'function') {
                try {
                    const info = dataTable.page.info();
                    const statsElement = document.getElementById('filterStats');
                    if (statsElement) {
                        statsElement.textContent = `Showing ${info.recordsDisplay} of ${info.recordsTotal} records`;
                    }
                } catch (error) {
                    console.log('Stats update skipped:', error.message);
                }
            }
        }
        
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
        
        // Initialize burger toggle on page load
        $(document).ready(function() {
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
        });
    </script>
    <!-- Include header components -->
    <?php include '../../components/scripts.php'; ?>
</body>
</html>

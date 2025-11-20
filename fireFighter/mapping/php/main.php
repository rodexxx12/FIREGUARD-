<?php
    include('../functions/function_index.php');
    // Ensure $counts has all required keys to prevent undefined array key warnings
    $counts = array_merge([
        'SAFE' => 0,
        'MONITORING' => 0,
        'ACKNOWLEDGED' => 0,
        'EMERGENCY' => 0
    ], $counts ?? []);
?>
<?php include('../../components/header.php'); ?>

    <!-- Link to the modern side tab CSS -->
    <link rel="stylesheet" href="../css/side-tab.css">

    <style>
        /* Mobile-friendly SweetAlert styles */
        .swal-wide {
            max-width: 90vw !important;
            width: 90vw !important;
        }
        
        .swal-wide .swal2-popup {
            max-width: 90vw !important;
            width: 90vw !important;
            font-size: 14px !important;
        }
        
        .swal-wide .swal2-title {
            font-size: 18px !important;
            margin-bottom: 15px !important;
        }
        
        .swal-wide .swal2-html-container {
            font-size: 14px !important;
            line-height: 1.4 !important;
        }
        
        .swal-wide .swal2-actions {
            margin-top: 20px !important;
        }
        
        .swal-wide .swal2-confirm,
        .swal-wide .swal2-cancel {
            font-size: 14px !important;
            padding: 10px 20px !important;
            border-radius: 25px !important;
        }
        
        /* Mobile-responsive map controls */
        @media (max-width: 768px) {
            .control-toolbar {
                padding: 10px !important;
            }
            
            .control-toolbar .btn {
                font-size: 12px !important;
                padding: 8px 12px !important;
                margin: 2px !important;
            }
            
            .control-toolbar .btn-text {
                display: none;
            }
            
            .control-toolbar .btn i {
                font-size: 14px !important;
            }
            
            /* Show button text on larger mobile screens */
            @media (min-width: 480px) {
                .control-toolbar .btn-text {
                    display: inline;
                }
            }
        }
        
        /* Better mobile alerts */
        .swal2-popup {
            border-radius: 15px !important;
        }
        
        .swal2-popup .swal2-title {
            color: #333 !important;
        }
        
        .swal2-popup .swal2-html-container {
            color: #666 !important;
        }
        
        /* Mobile-friendly list styles */
        .swal2-popup ul, .swal2-popup ol {
            padding-left: 20px !important;
            margin: 10px 0 !important;
        }
        
        .swal2-popup li {
            margin-bottom: 5px !important;
        }
        
        /* Better button styling for mobile (flat colors, no gradients) */
        .swal2-confirm {
            background-color: #dc3545 !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(220,53,69,0.25) !important;
        }
        
        .swal2-cancel {
            background-color: #007bff !important;
            color: #ffffff !important;
            border: none !important;
            box-shadow: 0 2px 4px rgba(0,123,255,0.25) !important;
        }
        
        /* Simple Text Display for Acknowledged Alarms */
        .simple-alarm-row {
            display: flex;
            align-items: flex-start;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            background: white;
        }
        
        .simple-alarm-row:last-child {
            border-bottom: none;
        }
        
        .alarm-number {
            font-weight: bold;
            color: #495057;
            margin-right: 12px;
            min-width: 20px;
            font-size: 14px;
        }
        
        .alarm-info {
            flex: 1;
            font-size: 13px;
            line-height: 1.4;
        }
        
        .alarm-id-time {
            font-weight: 500;
            color: #495057;
            margin-bottom: 2px;
        }
        
        .alarm-location {
            color: #28a745;
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .alarm-sensors {
            color: #6c757d;
            margin-bottom: 2px;
        }
        
        .alarm-contact {
            color: #6c757d;
            font-size: 12px;
        }
        
        /* Pagination Styles */
        .pagination-container {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            background: white;
        }
        
        .pagination-info {
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            margin-bottom: 10px;
        }
        
        .pagination-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .pagination-btn {
            padding: 4px 8px;
            border: 1px solid #dee2e6;
            background: white;
            color: #495057;
            font-size: 11px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: #f8f9fa;
            border-color: #adb5bd;
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-current {
            color: #495057;
            font-size: 12px;
            font-weight: 500;
            margin: 0 8px;
        }
        
        .no-alarms {
            text-align: center;
            color: #6c757d;
            font-style: italic;
            padding: 20px;
        }
        
        .no-alarms.text-danger {
            color: #dc3545 !important;
        }
        
        .no-alarms.text-success {
            color: #28a745 !important;
        }
        
        .no-alarms i {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        /* Refresh Indicator Styles */
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            max-width: 300px;
        }
        
        .refresh-indicator.loading {
            background-color: #6c757d;
            color: #ffffff;
        }
        
        .refresh-indicator.success {
            background-color: #28a745;
            color: #ffffff;
        }
        
        .refresh-indicator.error {
            background-color: #dc3545;
            color: #ffffff;
        }
        
        .refresh-indicator i {
            margin-right: 6px;
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        /* Auto-refresh status indicator */
        .auto-refresh-status {
            position: absolute;
            top: 5px;
            right: 5px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #28a745;
            animation: pulse 2s infinite;
        }
        
        .auto-refresh-status.error {
            background: #dc3545;
        }
        
        .auto-refresh-status.loading {
            background: #6c757d;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        /* Simple action buttons below the map */
        .action-buttons {
            display: flex;
            flex-direction: row;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .action-buttons .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid transparent;
            transition: all 0.3s ease;
        }
        .action-buttons .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @media (max-width: 768px) {
            .action-buttons {
                gap: 10px;
                justify-content: center;
            }
            .action-buttons .btn {
                padding: 8px 16px;
                font-size: 13px;
            }
        }
        
        /* White icons in all buttons */
        .btn i { color: #ffffff !important; }
        .btn:disabled i, .btn.disabled i { color: #ffffff !important; opacity: 0.85; }
        .swal2-popup .btn i { color: #ffffff !important; }
        /* Button colors using Bootstrap classes */
        .btn-emergency { 
            background-color: #fd7e14; 
            border-color: #fd7e14; 
            color: white; 
        }
        .btn-emergency:hover { 
            background-color: #e8650a; 
            border-color: #e8650a; 
        }
        /* Focus styles for action buttons */
        .action-buttons .btn:focus { 
            outline: none; 
            box-shadow: 0 0 0 0.2rem rgba(13,110,253,0.15); 
        }
        
        /* Ensure text-based buttons render properly */
        #routeToStation,
        #routeToEmergency,
        #clearRoute,
        #locate-emergency,
        #toggle-buildings { font-size: 14px; }
        
        /* Map size */
        #map {
            width: 100%;
            height: 85vh; /* Full viewport height */
            background: #f8f9fa; /* fallback while tiles load */
            position: relative; /* For legend positioning */
        }
        
        /* Fire Data Marquee */
        .fire-data-marquee {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 12px 20px;
            z-index: 1001;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            overflow: hidden;
        }
        
        .fire-data-marquee-content {
            display: inline-flex;
            align-items: center;
            gap: 20px;
            animation: scroll 60s linear infinite;
            white-space: nowrap;
            will-change: transform;
        }
        
        .fire-data-marquee-content:hover {
            animation-play-state: paused;
        }
        
        @keyframes scroll {
            0% {
                transform: translateX(0);
            }
            100% {
                transform: translateX(-50%);
            }
        }
        
        .marquee-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            font-size: 15px;
            color: #2c3e50;
            margin-right: 30px;
            background: white;
            padding: 4px 8px;
            border-radius: 4px;
        }
        
        .marquee-item i {
            font-size: 18px;
        }
        
        .marquee-item.status-SAFE i {
            color: #28a745;
        }
        
        .marquee-item.status-MONITORING i {
            color: #ffc107;
        }
        
        .marquee-item.status-ACKNOWLEDGED {
            color: #ffc107;
        }
        
        .marquee-item.status-ACKNOWLEDGED i {
            color: #ffc107;
        }
        
        .marquee-item.status-EMERGENCY {
            color: #dc3545;
        }
        
        .marquee-item.status-EMERGENCY i {
            color: #dc3545;
        }
        
        .marquee-separator {
            color: #dee2e6;
            font-weight: normal;
            margin: 0 10px;
        }
        
        /* Responsive marquee */
        @media (max-width: 768px) {
            .fire-data-marquee {
                padding: 10px 15px;
            }
            
            .marquee-item {
                font-size: 13px;
                gap: 6px;
            }
            
            .marquee-item i {
                font-size: 16px;
            }
        }
        
        /* Leaflet Layer Control (Base Maps) */
        .leaflet-control-layers {
            margin-top: 70px !important;
        }
        
        /* Burger Icon Button for Legend */
        .legend-toggle-btn {
            position: absolute;
            bottom: 20px;
            left: 20px;
            z-index: 1001;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }
        
        .legend-toggle-btn:hover {
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
            transform: scale(1.05);
        }
        
        .legend-toggle-btn i {
            font-size: 24px;
            color: #2c3e50;
        }
        
        /* Modern Map Legend */
        .map-legend {
            position: absolute;
            bottom: 80px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1000;
            min-width: 200px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: none; /* Hidden by default */
            animation: slideInLeft 0.3s ease;
        }
        
        .map-legend.show {
            display: block;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .map-legend h6 {
            margin: 0 0 12px 0;
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 8px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
            color: #495057;
        }
        
        .legend-item:last-child {
            margin-bottom: 0;
        }
        
        .legend-icon {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
        }
        
        .legend-icon i {
            font-size: 14px;
        }
        
        .legend-text {
            flex: 1;
            font-weight: 500;
        }
        
        /* Responsive legend */
        @media (max-width: 768px) {
            .legend-toggle-btn {
                bottom: 15px;
                left: 15px;
                width: 45px;
                height: 45px;
            }
            
            .legend-toggle-btn i {
                font-size: 20px;
            }
            
            .map-legend {
                bottom: 70px;
                left: 15px;
                padding: 12px;
                min-width: 180px;
            }
            
            .map-legend h6 {
                font-size: 13px;
                margin-bottom: 10px;
            }
            
            .legend-item {
                font-size: 12px;
                margin-bottom: 6px;
            }
            
            .legend-icon {
                width: 18px;
                height: 18px;
                margin-right: 8px;
            }
            
            .legend-icon i {
                font-size: 12px;
            }
        }
        @media (max-width: 768px) {
            #map {
                height: 100vh; /* Keep full height on mobile */
            }
        }
        
        /* Building Modal Styles */
        #buildingModal .modal-content {
            background-color: #ffffff !important;
            font-family: Arial, sans-serif !important;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        #buildingModal .modal-header {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e9ecef;
            font-family: Arial, sans-serif !important;
        }
        
        #buildingModal .modal-title {
            font-family: Arial, sans-serif !important;
            font-weight: bold;
            color: #333;
        }
        
        #buildingModal .modal-body {
            background-color: #ffffff !important;
            font-family: Arial, sans-serif !important;
            padding: 20px;
        }
        
        #buildingModal .modal-footer {
            background-color: #ffffff !important;
            border-top: 1px solid #e9ecef;
            font-family: Arial, sans-serif !important;
        }
        
        #buildingModal .building-info {
            font-family: Arial, sans-serif !important;
            font-size: 14px;
            line-height: 1.4;
        }
        
        #buildingModal .building-info strong {
            font-family: Arial, sans-serif !important;
            font-weight: bold;
            color: #333;
        }
        
        #buildingModal .safety-features {
            font-family: Arial, sans-serif !important;
        }
        
        #buildingModal .safety-features h6 {
            font-family: Arial, sans-serif !important;
            font-weight: bold;
            color: #333;
        }
        
        #buildingModal .btn {
            font-family: Arial, sans-serif !important;
        }
        
        #buildingModal hr {
            border-color: #e9ecef;
        }
        
        /* Compact modal size */
        #buildingModal .modal-dialog {
            max-width: 500px;
        }
        
        /* Safety feature indicators */
        .safety-yes {
            color: #28a745;
            font-weight: bold;
        }
        
        .safety-no {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Modern Building Card Styles */
        .modern-building-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border: 1px solid #e9ecef;
            overflow: hidden;
            min-width: 280px;
            max-width: 320px;
        }
        
        .modern-building-card .card-header {
            background: white;
            color: #333;
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .modern-building-card .card-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .modern-building-card .text-muted {
            color: #6c757d !important;
            font-size: 12px;
        }
        
        .modern-building-card .card-body {
            padding: 16px;
        }
        
        .modern-building-card .info-row {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        
        .modern-building-card .info-row i {
            width: 16px;
            margin-right: 8px;
            color: #6c757d;
            font-size: 14px;
        }
        
        .modern-building-card .info-row span {
            color: #495057;
            flex: 1;
        }
        
        .modern-building-card .safety-features {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e9ecef;
        }
        
        .modern-building-card .safety-title {
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .modern-building-card .safety-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
        }
        
        .modern-building-card .safety-badge {
            background: #e8f5e8;
            color: #28a745;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            border: 1px solid #c3e6c3;
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
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main">         <!-- Main Map and Side Tab Row -->
                <div class="row flex-row">
                 
                <!-- Main Map Column Full-Width -->
                <div class="col-12">
                    <div class="card shadow-sm mb-3 p-3">
                        <h5 style="font-weight: bold; font-family: Arial, sans-serif; margin-top: 10px; margin-bottom: 20px;">Locate Fire Incidents in Bago City - <?php echo date('F Y'); ?></h5>
                        <div class="card-body p-0">
                            <div class="control-panel">
                                <div id="map">
                                    <!-- Fire Data Marquee -->
                                    <div class="fire-data-marquee" id="fireDataMarquee">
                                        <div class="fire-data-marquee-content" id="fireDataMarqueeContent">
                                            <div class="marquee-item">
                                                <i class="fas fa-spinner fa-spin"></i>
                                                <span>Loading latest fire data...</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Burger Icon Toggle Button for Legend -->
                                    <button class="legend-toggle-btn" id="legendToggleBtn" onclick="toggleLegend()" title="Toggle Map Legend">
                                        <i class="fas fa-bars" id="legendToggleIcon"></i>
                                    </button>
                                    
                                    <!-- Modern Map Legend -->
                                    <div class="map-legend" id="mapLegend">
                                        <h6>Map Legend</h6>
                                        <div class="legend-item">
                                            <div class="legend-icon" style="background-color:rgb(1, 123, 231);">
                                                <i class="fas fa-home" style="color: white;"></i>
                                            </div>
                                            <div class="legend-text">Registered Buildings</div>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-icon" style="background-color:rgb(249, 162, 62);">
                                                <i class="fas fa-check" style="color: white;"></i>
                                            </div>
                                            <div class="legend-text">Acknowledged Alerts</div>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-icon" style="background-color: #28a745;">
                                                <i class="fas fa-user" style="color: white;"></i>
                                            </div>
                                            <div class="legend-text">Your Current Location</div>
                                        </div>
                                        <div class="legend-item">
                                            <div class="legend-icon" style="background-color: #dc3545;">
                                                <i class="fas fa-fire" style="color: white;"></i>
                                            </div>
                                            <div class="legend-text">Fire Station</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Control Buttons Inside Card -->
                        <div class="card-footer bg-white">
                            <div class="action-buttons">
                                <button id="routeToStation" class="btn btn-emergency">
                                    Route to Station
                                </button>
                                <button id="locate-emergency" onclick="locateEmergency()" class="btn btn-primary">
                                    Locate Emergency
                                </button>
                                <button id="clearRoute" class="btn btn-danger" onclick="clearRoute()">
                                    Clear Route
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </main>
        </div>
    </div>
       
<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.4.1/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// SweetAlert modal for Recent Status Alerts
document.addEventListener('DOMContentLoaded', function() {
    var list = document.querySelectorAll('.recent-alert-item');
    list.forEach(function(item) {
        item.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var status = this.getAttribute('data-status');
            var type = this.getAttribute('data-type');
            var flame = this.getAttribute('data-flame');
            var bname = this.getAttribute('data-building-name');
            var addr = this.getAttribute('data-address');
            var lat = this.getAttribute('data-lat');
            var lng = this.getAttribute('data-lng');
            var smoke = this.getAttribute('data-smoke');
            var temp = this.getAttribute('data-temp');
            var heat = this.getAttribute('data-heat');
            var mlc = this.getAttribute('data-ml-confidence');
            var mlp = this.getAttribute('data-ml-prob');
            var ai = this.getAttribute('data-ai');
            var mlt = this.getAttribute('data-ml-time');
            var ts = this.getAttribute('data-ts');
            var deviceId = this.getAttribute('data-device-id');
            var buildingId = this.getAttribute('data-building-id');
            var userId = this.getAttribute('data-user-id');

            var meta = [
                `#${id}`,
                `Status: ${status}`,
                `Type: ${type}`,
                `Flame: ${flame}`
            ].filter(Boolean).join(' • ');
            var addrLines = [];
            if (bname) addrLines.push(`<div><strong>📍 ${bname}</strong></div>`);
            if (addr) addrLines.push(`<div>${addr}</div>`);
            if (lat && lng) addrLines.push(`<div>Coords: (${lat}, ${lng})</div>`);

            var mlParts = [];
            if (mlc) mlParts.push(`Confidence: <strong>${mlc}%</strong>`);
            if (ai) mlParts.push(`Prediction: ${ai}`);
            if (mlp) mlParts.push(`Fire Prob: ${mlp}%`);
            mlParts.push(`Time: ${mlt || ts}`);

            var html = `
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div style="font-size:14px;color:#2b2d42;background:#f1f5ff;border:1px solid #e3ecff;padding:8px 10px;border-radius:8px;">${meta}</div>
                    <div style="font-size:14px;color:#2b2d42;background:#fff8ee;border:1px solid #ffe2bf;padding:10px;border-radius:8px;">${addrLines.join('')}</div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:120px;background:#f8fafc;border:1px solid #eef2f7;border-radius:8px;padding:10px;">🌫️ Smoke<br><span style="font-size:18px;font-weight:700;">${smoke}</span></div>
                        <div style="flex:1;min-width:120px;background:#f8fafc;border:1px solid #eef2f7;border-radius:8px;padding:10px;">🌡️ Temp<br><span style="font-size:18px;font-weight:700;">${temp}</span></div>
                        <div style="flex:1;min-width:120px;background:#f8fafc;border:1px solid #eef2f7;border-radius:8px;padding:10px;">♨️ Heat<br><span style="font-size:18px;font-weight:700;">${heat}</span></div>
                    </div>
                    <div style="background:#f6fffb;border:1px solid #d7f5ea;border-radius:8px;padding:10px;">🤖 ${mlParts.join(' • ')}</div>
                </div>
            `;

            Swal.fire({
                title: 'Recent Alert Details',
                html: html,
                icon: 'info',
                width: '48rem',
                background: '#ffffff',
                color: '#1f2937',
                showCloseButton: true,
                confirmButtonText: 'Close',
                confirmButtonColor: '#dc3545',
                backdrop: `rgba(0,0,0,0.35)`,
                customClass: {
                    popup: 'swal2-rounded',
                    title: 'swal2-title-compact',
                    htmlContainer: 'swal2-html-compact',
                    confirmButton: 'swal2-confirm-compact'
                }
            });
        });
    });
});

// Configuration
const config = {
fireStation: { 
    lat: 10.525468, 
    lng: 122.841238, 
    name: "Bago City Fire Station",
    contact: "(034) 461-1234",
    address: "" // Will be populated by reverse geocoding
},

buildings: <?php echo json_encode($buildings); ?>,
apiEndpoint: "server.php",
updateInterval: 30000, // 30 seconds
heatmapRadius: 25,
heatmapBlur: 15,
statusThresholds: {
    Safe: { smoke: 20, temp: 30, heat: 30 },
    Monitoring: { smoke: 50, temp: 50, heat: 50 },
    'Pre-Dispatch': { smoke: 100, temp: 100, heat: 100 },
    Emergency: { smoke: 200, temp: 200, heat: 200 }
},

userId: <?= $_SESSION['user_id'] ?? 0 ?>,
mapboxAccessToken: 'pk.eyJ1IjoibWFwYm94IiwiYSI6ImNpejY4NXVycTA2emYycXBndHRqcmZ3N3gifQ.rJcFIG214AriISLbB6B5aw',

geolocation: {
        enableHighAccuracy: true,
        timeout: 10000, // 10 seconds
        maximumAge: 30000 // 30 seconds
    }
};

// Global variables
let map, fireMarkers, heatLayer, routingControl;
let allFireData = [];
let heatmapEnabled = false;
let clusteringEnabled = true;
let userLocation = null;
let userMarker = null;
let userLocationWatchId = null;
let userLocationMarker = null;
let userLocationCircle = null;

// moved earlier with globals

let emergencyMarker = null;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    initEventListeners();
    fetchFireData();
    initBuildingsLayer();
    // Auto-refresh is now handled by startAutoRefresh() function
    
    // Request location permission on page load for mobile devices
    requestLocationPermission();
    
    // Add location help button for mobile users
    addLocationPermissionButton();
    
    // Initialize side tab functionality
    initSideTab();
    
    // Initialize fire data marquee
    updateFireDataMarquee();
    // Update marquee every 30 seconds
    setInterval(updateFireDataMarquee, 30000);
});

// Function to update fire data marquee
async function updateFireDataMarquee() {
    try {
        const response = await fetch('get_latest_fire_data_marquee.php');
        const result = await response.json();
        
        const marqueeContent = document.getElementById('fireDataMarqueeContent');
        if (!marqueeContent) return;
        
        if (result.success && result.data) {
            const data = result.data;
            const status = data.status || 'UNKNOWN';
            const statusIcon = getStatusIcon(status);
            const statusColor = getStatusColor(status);
            
            // Build marquee content with icons
            let html = '';
            
            // Status
            html += `<div class="marquee-item status-${status}">
                <i class="${statusIcon}"></i>
                <span>Status: ${status}</span>
            </div>`;
            
            html += `<span class="marquee-separator">|</span>`;
            
            // Building
            if (data.building_name && data.building_name !== 'Unknown Building') {
                html += `<div class="marquee-item">
                    <i class="fas fa-building"></i>
                    <span>Building: ${escapeHtml(data.building_name)}</span>
                </div>`;
                html += `<span class="marquee-separator">|</span>`;
            }
            
            // Device
            if (data.device_name) {
                html += `<div class="marquee-item">
                    <i class="fas fa-microchip"></i>
                    <span>Device: ${escapeHtml(data.device_name)}</span>
                </div>`;
                html += `<span class="marquee-separator">|</span>`;
            }
            
            // Barangay
            if (data.barangay_name && data.barangay_name !== 'Unknown Barangay') {
                html += `<div class="marquee-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Barangay: ${escapeHtml(data.barangay_name)}</span>
                </div>`;
                html += `<span class="marquee-separator">|</span>`;
            }
            
            // Sensors
            html += `<div class="marquee-item">
                <i class="fas fa-smog"></i>
                <span>Smoke: ${data.smoke || 0}</span>
            </div>`;
            html += `<span class="marquee-separator">|</span>`;
            
            html += `<div class="marquee-item">
                <i class="fas fa-thermometer-half"></i>
                <span>Temp: ${data.temp || 0}°C</span>
            </div>`;
            html += `<span class="marquee-separator">|</span>`;
            
            html += `<div class="marquee-item">
                <i class="fas fa-fire"></i>
                <span>Heat: ${data.heat || 0}</span>
            </div>`;
            html += `<span class="marquee-separator">|</span>`;
            
            // Flame detected
            if (data.flame_detected == 1) {
                html += `<div class="marquee-item" style="color: #dc3545;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Flame: DETECTED</span>
                </div>`;
                html += `<span class="marquee-separator">|</span>`;
            }
            
            // ML Prediction
            if (data.ml_confidence && parseFloat(data.ml_confidence) > 0) {
                html += `<div class="marquee-item">
                    <i class="fas fa-brain"></i>
                    <span>ML Confidence: ${parseFloat(data.ml_confidence).toFixed(1)}%</span>
                </div>`;
                html += `<span class="marquee-separator">|</span>`;
            }
            
            // Timestamp
            html += `<div class="marquee-item">
                <i class="fas fa-clock"></i>
                <span>Time: ${data.formatted_timestamp || data.timestamp || 'N/A'}</span>
            </div>`;
            
            // Duplicate content for seamless scrolling
            marqueeContent.innerHTML = html + html;
        } else {
            marqueeContent.innerHTML = `
                <div class="marquee-item">
                    <i class="fas fa-info-circle"></i>
                    <span>No fire data available</span>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error updating fire data marquee:', error);
        const marqueeContent = document.getElementById('fireDataMarqueeContent');
        if (marqueeContent) {
            marqueeContent.innerHTML = `
                <div class="marquee-item">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Error loading fire data</span>
                </div>
            `;
        }
    }
}

// Helper function to get status icon
function getStatusIcon(status) {
    const icons = {
        'SAFE': 'fas fa-check-circle',
        'MONITORING': 'fas fa-eye',
        'ACKNOWLEDGED': 'fas fa-check-double',
        'EMERGENCY': 'fas fa-exclamation-triangle',
        'PRE-DISPATCH': 'fas fa-bell'
    };
    return icons[status] || 'fas fa-info-circle';
}

// Helper function to get status color
function getStatusColor(status) {
    const colors = {
        'SAFE': '#28a745',
        'MONITORING': '#ffc107',
        'ACKNOWLEDGED': '#17a2b8',
        'EMERGENCY': '#dc3545',
        'PRE-DISPATCH': '#fd7e14'
    };
    return colors[status] || '#6c757d';
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Request location permission explicitly for mobile devices
function requestLocationPermission() {
    // Check if geolocation is supported
    if (!navigator.geolocation) {
        console.log("Geolocation is not supported by this browser");
        return;
    }

    // Enhanced mobile detection
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS|FxiOS/i.test(navigator.userAgent) || 
                     (window.innerWidth <= 768) || 
                     ('ontouchstart' in window);
    
    if (isMobile) {
        // Use the new force location access method for mobile
        console.log("Mobile device detected, using force location access");
        forceLocationAccess();
    } else {
        // For desktop, just try to get location without explicit permission dialog
        navigator.geolocation.getCurrentPosition(
            position => updateUserLocation(position.coords.latitude, position.coords.longitude),
            error => console.log("Geolocation error:", error)
        );
    }
}

// Handle location errors with mobile-specific messages
function handleLocationError(error) {
    let errorMessage = 'Unable to retrieve your location';
    let errorTitle = 'Location Error';
    let errorIcon = 'warning';
    
    switch(error.code) {
        case error.PERMISSION_DENIED:
            errorTitle = '📍 Location Access Denied';
            errorIcon = 'error';
            errorMessage = `
                <div class="text-start">
                    <p><strong>Location access was denied.</strong></p>
                    <p>To use routing features, please follow these steps:</p>
                    <ol class="text-start">
                        <li>Tap the <strong>lock/info icon</strong> in your browser's address bar</li>
                        <li>Find <strong>"Location"</strong> or <strong>"Site Settings"</strong></li>
                        <li>Change it to <strong>"Allow"</strong></li>
                        <li>Refresh this page</li>
                    </ol>
                    <p class="text-muted small mt-2">
                        <strong>Alternative:</strong> You can also go to your browser settings → Site Settings → Location → Allow for this site.
                    </p>
                </div>
            `;
            break;
        case error.POSITION_UNAVAILABLE:
            errorTitle = '📍 Location Unavailable';
            errorMessage = `
                <div class="text-start">
                    <p><strong>Location information is currently unavailable.</strong></p>
                    <p>Please check:</p>
                    <ul class="text-start">
                        <li>🔧 Your device's GPS is turned on</li>
                        <li>📶 You have a stable internet connection</li>
                        <li>🌍 You're not in a location with poor GPS signal</li>
                    </ul>
                </div>
            `;
            break;
        case error.TIMEOUT:
            errorTitle = '⏱️ Location Timeout';
            errorMessage = `
                <div class="text-start">
                    <p><strong>The request to get your location timed out.</strong></p>
                    <p>This usually happens when:</p>
                    <ul class="text-start">
                        <li>🌐 Your internet connection is slow</li>
                        <li>📱 Your device is taking time to get GPS signal</li>
                        <li>🏢 You're indoors with poor GPS reception</li>
                    </ul>
                    <p class="text-muted small mt-2">
                        <strong>Tip:</strong> Try going outside or near a window for better GPS signal.
                    </p>
                </div>
            `;
            break;
    }
    
    // Show mobile-friendly error dialog with retry option
    Swal.fire({
        title: errorTitle,
        html: errorMessage,
        icon: errorIcon,
        confirmButtonText: 'OK',
        showCancelButton: true,
        cancelButtonText: '🔄 Try Again',
        allowOutsideClick: false,
        customClass: {
            popup: 'swal-wide'
        }
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            // Retry location request after a short delay
            setTimeout(() => {
                requestLocationPermission();
            }, 1000);
        }
    });
}

// Initialize the map
async function initMap() {
    map = L.map('map').setView([config.fireStation.lat, config.fireStation.lng], 15);
    
    // Base layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });
    
    // Layer control
    const baseMaps = {
        "Street Map": osmLayer,
        "Satellite": satelliteLayer
    };
    
    L.control.layers(baseMaps, null, {position: 'topright'}).addTo(map);
    
    // Initialize marker cluster group
    fireMarkers = L.markerClusterGroup({
        spiderfyOnMaxZoom: true,
        showCoverageOnHover: false,
        zoomToBoundsOnClick: true,
        maxClusterRadius: 80
    });
    
    // Add fire station marker (async - will fetch address)
    await addFireStationMarker();

    // Initialize and permanently display buildings layer
    initBuildingsLayer();
    const toggleBtn = document.getElementById('toggle-buildings');
    if (toggleBtn && !toggleBtn.classList.contains('active')) {
toggleBtn.classList.add('active');
    }
}

// Reverse geocoding function to get readable address
async function reverseGeocode(lat, lng) {
    try {
        // Use the PHP proxy to avoid CORS issues with Nominatim API
        const response = await fetch(`reverse_geocode.php?lat=${lat}&lon=${lng}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });
        
        if (!response || !response.ok) {
            // If response is not ok, return coordinates as fallback
            console.warn('Reverse geocoding failed:', response.status, response.statusText);
            return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        
        const data = await response.json();
        
        // Check if the response contains an error
        if (data.error) {
            console.warn('Reverse geocoding error:', data.error);
            return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        
        if (data && data.address) {
            const addr = data.address;
            // Build a readable address from the response
            let addressParts = [];
            if (addr.road) addressParts.push(addr.road);
            if (addr.house_number) addressParts.push(addr.house_number);
            if (addr.suburb || addr.neighbourhood) addressParts.push(addr.suburb || addr.neighbourhood);
            if (addr.city || addr.town || addr.municipality) addressParts.push(addr.city || addr.town || addr.municipality);
            if (addr.state) addressParts.push(addr.state);
            if (addr.postcode) addressParts.push(addr.postcode);
            if (addr.country) addressParts.push(addr.country);
            
            return addressParts.length > 0 ? addressParts.join(', ') : data.display_name || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        return data.display_name || `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    } catch (error) {
        // Silently handle CORS and other errors, return coordinates as fallback
        console.warn('Reverse geocoding error (handled gracefully):', error.message);
        return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    }
}

// Add fire station to map
async function addFireStationMarker() {
    const fireStationIcon = L.icon({
        iconUrl: 'https://cdn-icons-png.flaticon.com/512/2972/2972035.png',
        iconSize: [40, 40],
        iconAnchor: [20, 40],
        popupAnchor: [0, -40]
    });
    
    // Get readable address
    const address = await reverseGeocode(config.fireStation.lat, config.fireStation.lng);
    config.fireStation.address = address;
    
    const marker = L.marker([config.fireStation.lat, config.fireStation.lng], { 
        icon: fireStationIcon,
        zIndexOffset: 1000 
    })
    .addTo(map)
    .bindPopup(`
        <div class="fire-station-popup" style="min-width: 280px; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <div class="text-center mb-3">
                <h4 class="fw-bold mb-3" style="font-size: 1.2rem; color: black; margin: 0;">${config.fireStation.name}</h4>
            </div>
            <div class="fire-station-info">
                <div class="info-item" style="display: flex; align-items: center; margin-bottom: 12px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #e9ecef;">
                    <i class="bi bi-telephone-fill" style="color: #28a745; font-size: 1.1rem; margin-right: 10px;"></i>
                    <span style="font-weight: 500; color: black;">${config.fireStation.contact}</span>
                </div>
                <div class="info-item" style="display: flex; align-items: flex-start; margin-bottom: 12px; padding: 10px; background: white; border-radius: 6px; border: 1px solid #e9ecef;">
                    <i class="bi bi-geo-alt-fill" style="color: #6c757d; font-size: 1.1rem; margin-right: 10px; margin-top: 2px;"></i>
                    <span style="font-weight: 500; color: black; line-height: 1.4;">${address}</span>
                </div>
                <div class="info-item" style="display: flex; align-items: center; padding: 10px; background: white; border-radius: 6px; border: 1px solid #e9ecef;">
                    <i class="bi bi-shield-check" style="color: #ffc107; font-size: 1.1rem; margin-right: 10px;"></i>
                    <span style="font-weight: 500; color: black;">Emergency Response Ready</span>
                </div>
            </div>
        </div>
    `, {
        maxWidth: 320,
        className: 'fire-station-popup-container'
    })
    .bindTooltip(config.fireStation.name, { 
        permanent: false, 
        direction: 'top',
        className: 'fw-bold' 
    })
    .openPopup();
}

// Global variable for buildings layer
let buildingsLayer = L.featureGroup();

// Initialize buildings layer only once
function initBuildingsLayer() {
    // Only add markers if not yet initialized
    if (buildingsLayer.getLayers().length === 0) {
        config.buildings.forEach(building => {
            const marker = createBuildingMarker(building);
            marker.options.buildingLayer = true; // tag for easy removal (optional)
            buildingsLayer.addLayer(marker);
        });
    }

    // Add layer to map if not already added
    if (!map.hasLayer(buildingsLayer)) {
        map.addLayer(buildingsLayer);
    }
}

// Toggle Button Logic
const toggleBuildingsBtnDuplicate = document.getElementById('toggle-buildings');
if (toggleBuildingsBtnDuplicate) {
    toggleBuildingsBtnDuplicate.addEventListener('click', function () {
    const btn = this;
    const isActive = btn.classList.contains('active');

    if (isActive) {
        btn.classList.remove('active');
        if (map.hasLayer(buildingsLayer)) {
            map.removeLayer(buildingsLayer);
        }
    } else {
        btn.classList.add('active');
        initBuildingsLayer();
    }
});
}

// Create a marker for a building
function createBuildingMarker(building) {
    const buildingIcon = L.icon({
        iconUrl: getBuildingIcon(building.building_type),
        iconSize: [48, 48],
        iconAnchor: [24, 48],
        popupAnchor: [0, -48]
        
    });
    
    const marker = L.marker([building.latitude, building.longitude], {
        icon: buildingIcon,
        riseOnHover: true
    });
    
    // Add popup with building details
    marker.bindPopup(`
        <div class="modern-building-card">
            <div class="card-header">
                <h6 class="card-title mb-0">${building.building_name}</h6>
                <small class="text-muted">${building.building_type}</small>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <i class="bi bi-geo-alt"></i>
                    <span>${building.address}</span>
                </div>
                <div class="info-row">
                    <i class="bi bi-person"></i>
                    <span>${building.contact_person || 'N/A'}</span>
                </div>
                <div class="info-row">
                    <i class="bi bi-telephone"></i>
                    <span>${building.contact_number || 'N/A'}</span>
                </div>
                <div class="info-row">
                    <i class="bi bi-layers"></i>
                    <span>${building.total_floors} floors</span>
                </div>
                <div class="safety-features">
                    <div class="safety-title">Safety Features</div>
                    <div class="safety-badges">
                        ${building.has_sprinkler_system ? '<span class="safety-badge">Sprinklers</span>' : ''}
                        ${building.has_fire_alarm ? '<span class="safety-badge">Alarm</span>' : ''}
                        ${building.has_fire_extinguishers ? '<span class="safety-badge">Extinguishers</span>' : ''}
                        ${building.has_emergency_exits ? '<span class="safety-badge">Exits</span>' : ''}
                        ${building.has_emergency_lighting ? '<span class="safety-badge">Lighting</span>' : ''}
                        ${building.has_fire_escape ? '<span class="safety-badge">Fire Escape</span>' : ''}
                    </div>
                </div>
            </div>
        </div>
    `);
    
	// Add tooltip
	marker.bindTooltip(`
		<div class="building-tooltip">
			<div class="fw-bold">${building.building_name}</div>
			<div>Type: ${building.building_type}</div>
			<div>${building.address}</div>
		</div>
	`, {
		permanent: false,
		direction: 'top',
		className: 'building-tooltip'
	});
    
    return marker;
}




// Get appropriate icon for building type
function getBuildingIcon(buildingType) {
    const icons = {
        'Residential': '../images/residential.png',
        'Commercial': '../images/commercial.png',
        'Industrial': '../images/industrial.png',
        'Institutional': '../images/institutional.png',
    };

    return icons[buildingType] || '../images/residential.png';
}



// Show building details modal
function showBuildingModal(building) {
    // Update modal title
    const modalLabel = document.getElementById('buildingModalLabel');
    if (modalLabel) modalLabel.textContent = building.building_name;
    
    // Update building information with null checks
    const buildingNameEl = document.getElementById('modal-building-name');
    if (buildingNameEl) buildingNameEl.textContent = building.building_name || 'N/A';
    
    const buildingTypeEl = document.getElementById('modal-building-type');
    if (buildingTypeEl) buildingTypeEl.textContent = building.building_type || 'N/A';
    
    const buildingAddressEl = document.getElementById('modal-building-address');
    if (buildingAddressEl) buildingAddressEl.textContent = building.address || 'N/A';
    
    const buildingContactEl = document.getElementById('modal-building-contact');
    if (buildingContactEl) {
        buildingContactEl.textContent = 
        (building.contact_person || 'N/A') + (building.contact_number ? ` (${building.contact_number})` : '');
    }
    
    const buildingFloorsEl = document.getElementById('modal-building-floors');
    if (buildingFloorsEl) buildingFloorsEl.textContent = building.total_floors || 'N/A';
    
    const buildingAreaEl = document.getElementById('modal-building-area');
    if (buildingAreaEl) {
        buildingAreaEl.textContent = 
        building.building_area ? `${building.building_area} sqm` : 'N/A';
    }
    
    const buildingYearEl = document.getElementById('modal-building-year');
    if (buildingYearEl) buildingYearEl.textContent = building.construction_year || 'N/A';
    
    const lastInspectedEl = document.getElementById('modal-last-inspected');
    if (lastInspectedEl) lastInspectedEl.textContent = building.last_inspected || 'Never';
    
    // Update safety features with visual indicators
    const sprinklerEl = document.getElementById('modal-sprinkler');
    if (sprinklerEl) {
        sprinklerEl.innerHTML = 
        building.has_sprinkler_system ? '<span class="safety-yes">✓ Yes</span>' : '<span class="safety-no">✗ No</span>';
    }
    
    const fireAlarmEl = document.getElementById('modal-fire-alarm');
    if (fireAlarmEl) {
        fireAlarmEl.innerHTML = 
        building.has_fire_alarm ? '<span class="safety-yes">✓ Yes</span>' : '<span class="safety-no">✗ No</span>';
    }
    
    const extinguishersEl = document.getElementById('modal-extinguishers');
    if (extinguishersEl) {
        extinguishersEl.innerHTML = 
        building.has_fire_extinguishers ? '<span class="safety-yes">✓ Yes</span>' : '<span class="safety-no">✗ No</span>';
    }
    
    const emergencyExitsEl = document.getElementById('modal-emergency-exits');
    if (emergencyExitsEl) {
        emergencyExitsEl.innerHTML = 
        building.has_emergency_exits ? '<span class="safety-yes">✓ Yes</span>' : '<span class="safety-no">✗ No</span>';
    }
    
    const emergencyLightingEl = document.getElementById('modal-emergency-lighting');
    if (emergencyLightingEl) {
        emergencyLightingEl.innerHTML = 
        building.has_emergency_lighting ? '<span class="safety-yes">✓ Yes</span>' : '<span class="safety-no">✗ No</span>';
    }
    
    const fireEscapeEl = document.getElementById('modal-fire-escape');
    if (fireEscapeEl) {
        fireEscapeEl.innerHTML = 
        building.has_fire_escape ? '<span class="safety-yes">✓ Yes</span>' : '<span class="safety-no">✗ No</span>';
    }
    
    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('buildingModal'));
    modal.show();
}

// Initialize event listeners
function initEventListeners() {
    console.log('Initializing event listeners');
    
    // Route buttons
    const routeToStationBtn = document.getElementById('routeToStation');
    const routeToEmergencyBtn = document.getElementById('routeToEmergency');
    const clearRouteBtn = document.getElementById('clearRoute');
    const locateEmergencyBtn = document.getElementById('locate-emergency');
    
    console.log('Button elements found:', {
        routeToStation: !!routeToStationBtn,
        routeToEmergency: !!routeToEmergencyBtn,
        clearRoute: !!clearRouteBtn,
        locateEmergency: !!locateEmergencyBtn
    });
    
    if (routeToStationBtn) {
        routeToStationBtn.addEventListener('click', function() {
            speakText('Routing to fire station.');
            showRouteToStation();
        });
    }
    
    if (routeToEmergencyBtn) {
        routeToEmergencyBtn.addEventListener('click', function() {
            speakText('Routing to emergency.');
            routeToEmergencyFromLocation();
        });
    }
    
    if (clearRouteBtn) {
        clearRouteBtn.addEventListener('click', function() {
            console.log('Clear Route button clicked');
            speakText('Route cleared.');
            clearRoute();
        });
    }
    
    if (locateEmergencyBtn) {
        locateEmergencyBtn.addEventListener('click', function() {
            speakText('Locating emergency.');
            locateEmergency();
        });
    }
    // Removed 'find-me' button and its handler
    // Toggle buildings button
    const toggleBuildingsBtn = document.getElementById('toggle-buildings');
    if (toggleBuildingsBtn) {
        toggleBuildingsBtn.addEventListener('click', function() {
        speakText('Toggling building visibility.');
        // Toggle buildings layer visibility
        if (buildingsLayer) {
            if (map.hasLayer(buildingsLayer)) {
                map.removeLayer(buildingsLayer);
                speakText('Buildings hidden.');
            } else {
                map.addLayer(buildingsLayer);
                speakText('Buildings shown.');
            }
        }
    });
    }
    
    // Filter legend
    document.querySelectorAll('.filter-legend').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            const status = this.getAttribute('data-status');
            speakText(`Filtering fires by status: ${status}.`);
            filterFiresByStatus(status);
            
            // Update active state
            document.querySelectorAll('.filter-legend').forEach(el => el.classList.remove('active'));
            this.classList.add('active');
        });
    });
    
    // Refresh button
    const refreshBtn = document.getElementById('refresh-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
        speakText('Refreshing fire data.');
        fetchFireData();
    });
    }
    // View on map buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-on-map')) {
            const lat = parseFloat(e.target.getAttribute('data-lat'));
            const lng = parseFloat(e.target.getAttribute('data-lng'));
            speakText('Flying to location on map.');
            map.flyTo([lat, lng], 18);
        }
        if (e.target.classList.contains('calculate-distance')) {
            const lat = parseFloat(e.target.getAttribute('data-lat'));
            const lng = parseFloat(e.target.getAttribute('data-lng'));
            if (userLocation) {
                const distance = calculateDistance(userLocation.lat, userLocation.lng, lat, lng);
                speakText(`Distance to this location is ${distance.toFixed(2)} kilometers.`);
            } else {
                speakText('Please get your location first to calculate distance.');
            }
        }
    });
}

// Find my location function with improved error handling and routing
function findMyLocation() {
    speakText('Finding your location.');
    const btn = document.getElementById('find-me');
    if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Locating...';
    }
    
    if (!navigator.geolocation) {
        speakText('Geolocation is not supported by your browser.');
        showAlert('error', 'Geolocation is not supported by your browser');
        if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> My Location';
        }
        return;
    }
    
    // Stop any previous watch
    if (userLocationWatchId) {
        navigator.geolocation.clearWatch(userLocationWatchId);
    }
    
    // Enhanced mobile detection
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS|FxiOS/i.test(navigator.userAgent) || 
                     (window.innerWidth <= 768) || 
                     ('ontouchstart' in window);
    
    if (isMobile) {
        // Use the new force location access method for mobile
        console.log("Mobile device detected, using force location access");
        forceLocationAccess();
        
        // Reset button after a delay
        if (btn) {
        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> My Location';
        }, 2000);
        }
    } else {
        // For desktop, proceed directly
        getCurrentLocationWithRetry(btn);
    }
}

// Get current location with retry mechanism
function getCurrentLocationWithRetry(btn) {
    let retryCount = 0;
    const maxRetries = 3;
    
    function attemptGetLocation() {
        navigator.geolocation.getCurrentPosition(
            position => {
                const { latitude, longitude, accuracy } = position.coords;
                
                // Update user location on map
                updateUserLocation(latitude, longitude, accuracy);
                
                // Update button state
                if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-geo-alt-fill me-1"></i> My Location';
                }
                
                // Center map on user's location
                map.flyTo([latitude, longitude], 16);
                
                // Show success message
                speakText('Location found. Looking for nearby emergency buildings.');
                showAlert('success', 'Location found! Looking for nearby emergency buildings...');
                
                // Find and route to nearest emergency building
                routeToLatestEmergencyBuilding(latitude, longitude);
            },
            error => {
                console.error('Geolocation error:', error);
                retryCount++;
                
                if (retryCount < maxRetries) {
                    // Retry with exponential backoff
                    setTimeout(() => {
                        attemptGetLocation();
                    }, 1000 * retryCount);
                } else {
                    // Max retries reached, show error
                    handleLocationError(error);
                    if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-geo-alt me-1"></i> My Location';
                    }
                }
            },
            {
                enableHighAccuracy: true,
                maximumAge: 30000,
                timeout: 15000 // Increased timeout for mobile
            }
        );
    }
    
    attemptGetLocation();
}

// Route to latest emergency building from current location
function routeToLatestEmergencyBuilding(userLat, userLng) {
    speakText('Searching for emergency buildings.');
    fetchEmergencyBuildings()
        .then(buildings => {
            if (!buildings || buildings.length === 0) {
                speakText('No emergency buildings found. All buildings are currently safe.');
                showAlert('info', 'No emergency buildings found in the system. All buildings are currently safe.');
                return;
            }
            
            const now = new Date();
            const emergencyBuildings = buildings
                .filter(b => {
                    const statusUpper = b.status.toUpperCase();
                    const timestamp = new Date(b.timestamp);
                    const minutesAgo = (now - timestamp) / 1000 / 60;

                    // Include all emergency-related statuses
                    return (statusUpper === 'EMERGENCY' || statusUpper === 'ACKNOWLEDGED' || statusUpper === 'PRE-DISPATCH') &&
                        minutesAgo <= 60; // Include those within the last 60 minutes
                })
                .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

            if (emergencyBuildings.length === 0) {
                showAlert('success', 'Great news! No recent emergency buildings found. All buildings are currently safe.');
                return;
            }
            
            // Get the latest emergency building
            const latestEmergency = emergencyBuildings[0];
            const { latitude, longitude, building_name, address, status } = latestEmergency;
            
            // Clear any existing route
            clearRoute();
            
            // Remove any existing emergency marker
            if (emergencyMarker) {
                map.removeLayer(emergencyMarker);
            }
            
            // Determine styling based on status
            const statusUpper = status.toUpperCase();
            let routeColor, iconUrl;
            
            switch(statusUpper) {
                case 'EMERGENCY':
                    routeColor = '#dc3545'; // Red
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599508.png';
                    break;
                case 'ACKNOWLEDGED':
                    routeColor = '#ffc107'; // Yellow
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png';
                    break;
                case 'PRE-DISPATCH':
                    routeColor = '#fd7e14'; // Orange
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599502.png';
                    break;
                default:
                    routeColor = '#6c757d'; // Gray
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
            }
            
            // Create routing control
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(userLat, userLng),
                    L.latLng(latitude, longitude)
                ],
                routeWhileDragging: false,
                showAlternatives: false,
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: 'smart',
                lineOptions: {
                    styles: [{ color: routeColor, weight: 5, opacity: 0.8 }]
                },
                createMarker: () => null, // Don't create default markers
                show: false, // Hide the default routing panel
                collapsible: false,
                containerClassName: 'custom-routing-container'
            }).addTo(map);
            
            // Add custom emergency marker
            emergencyMarker = L.marker([latitude, longitude], {
                icon: L.icon({
                    iconUrl: iconUrl,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32]
                })
            }).addTo(map);
            
            // Handle route found event
            routingControl.on('routesfound', function(e) {
                const route = e.routes[0];
                const distanceMeters = route.summary.totalDistance;
                const durationSeconds = route.summary.totalTime;
                
                const distanceKm = (distanceMeters / 1000).toFixed(2);
                const durationFormatted = formatDuration(durationSeconds);
                
                // Speak route directions
                speakText(`Route found to ${building_name}. Distance is ${distanceKm} kilometers. Estimated travel time is ${durationFormatted}. Status is ${statusUpper}.`);
                
                // Extract and speak detailed turn-by-turn directions
                if (route.instructions) {
                    speakDetailedDirections(route.instructions, building_name);
                }
                
                // Update emergency marker popup
                emergencyMarker.bindPopup(`
                    <div class="text-center">
                        <h5 class="fw-bold">${building_name}</h5>
                        <p class="mb-1">${address}</p>
                        <p class="mb-1"><strong>Status:</strong> <span style="color:${routeColor}">${statusUpper}</span></p>
                        <p class="mb-1"><strong>Distance:</strong> ${distanceKm} km</p>
                        <p class="mb-1"><strong>ETA:</strong> ${durationFormatted}</p>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary" onclick="clearRoute()">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </div>
                    </div>
                `).openPopup();
                
                // Show alert with route info
                let alertType = 'info';
                if (statusUpper === 'EMERGENCY') alertType = 'danger';
                else if (statusUpper === 'ACKNOWLEDGED') alertType = 'warning';
                
                showAlert(alertType, `🚨 Routing to ${building_name}<br>
                    <strong>Status:</strong> ${statusUpper}<br>
                    <strong>Distance:</strong> ${distanceKm} km<br>
                    <strong>ETA:</strong> ${durationFormatted}`);
            });
            
            // Handle routing error
            routingControl.on('routingerror', function(e) {
                console.error('Routing error:', e.error);
                speakText('Routing error. Failed to calculate route to emergency building. Please try again.');
                showAlert('error', 'Failed to calculate route to emergency building. Please try again.');
            });
            
            // Fit map to show both locations
            map.fitBounds([
                [userLat, userLng],
                [latitude, longitude]
            ], { padding: [50, 50] });
        })
        .catch(error => {
            console.error('Error fetching emergency buildings:', error);
            showAlert('error', 'Failed to locate emergency buildings. Please check your connection and try again.');
        });
}

// Helper function to format duration (seconds to HH:MM:SS)
function formatDuration(seconds) {
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    
    return [
        hours.toString().padStart(2, '0'),
        minutes.toString().padStart(2, '0'),
        secs.toString().padStart(2, '0')
    ].join(':');
}

// Function to clear existing route
function clearRoute() {
    console.log('clearRoute function called');
    console.log('map object exists:', !!map);
    
    if (!map) {
        console.error('Map object is not initialized');
        return;
    }
    
    // Add visual feedback
    const clearRouteBtn = document.getElementById('clearRoute');
    if (clearRouteBtn) {
        clearRouteBtn.textContent = 'Clearing...';
        clearRouteBtn.disabled = true;
    }
    
    // Stop text-to-speech when clearing route
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        console.log('Text-to-speech stopped');
    }
    
    console.log('routingControl exists:', !!routingControl);
    if (routingControl) {
        console.log('Removing routing control');
        try {
            map.removeControl(routingControl);
            routingControl = null;
            console.log('Routing control removed successfully');
        } catch (error) {
            console.error('Error removing routing control:', error);
            routingControl = null;
        }
    }
    
    console.log('emergencyMarker exists:', !!emergencyMarker);
    if (emergencyMarker) {
        console.log('Removing emergency marker');
        try {
            map.removeLayer(emergencyMarker);
            emergencyMarker = null;
            console.log('Emergency marker removed successfully');
        } catch (error) {
            console.error('Error removing emergency marker:', error);
            emergencyMarker = null;
        }
    }
    
    // Close directions panel when route is cleared
    console.log('Closing directions panel');
    closeDirectionsPanel();
    
    // Restore button state
    if (clearRouteBtn) {
        clearRouteBtn.textContent = 'Clear Route';
        clearRouteBtn.disabled = false;
    }
    
    console.log('clearRoute function completed');
}

function locateEmergency() {
    const locateEmergencyBtn = document.getElementById('locate-emergency');
    const originalButtonHtml = locateEmergencyBtn ? locateEmergencyBtn.innerHTML : null;
    if (locateEmergencyBtn) {
        locateEmergencyBtn.disabled = true;
        locateEmergencyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Locating...';
    }

    speakText('Locating the latest ACKNOWLEDGED fire incident based on device data.');

    // Use the new endpoint that only fetches ACKNOWLEDGED status
    fetchMostRecentAcknowledged()
        .then(response => {
            if (!response || !response.success || !response.data) {
                const infoMessage = (response && response.message) ? response.message : 'No ACKNOWLEDGED fire incidents were found.';
                showAlert('info', infoMessage);
                speakText('No ACKNOWLEDGED incidents available.');
                return;
            }

            const acknowledged = response.data;
            // Prioritize GPS coordinates (gps_latitude, gps_longitude) from fire_data
            // Then fall back to geo_lat/geo_long, but NOT building coordinates
            const latitude = parseFloat(acknowledged.gps_latitude ?? acknowledged.latitude ?? acknowledged.geo_lat ?? acknowledged.fire_data_latitude);
            const longitude = parseFloat(acknowledged.gps_longitude ?? acknowledged.longitude ?? acknowledged.geo_long ?? acknowledged.fire_data_longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                showAlert('warning', 'Latest ACKNOWLEDGED fire_data entry does not include valid coordinates.');
                console.warn('Missing coordinates in fire_data payload:', acknowledged);
                return;
            }

            const statusUpper = (acknowledged.status || 'UNKNOWN').toUpperCase();
            // Since we're only fetching ACKNOWLEDGED, this will always be false
            const isEmergency = false;
            const displayName = acknowledged.building_name || `Device ${acknowledged.device_name || acknowledged.device_id || 'Unknown'}`;
            const address = acknowledged.address || 'Location derived from fire_data GPS coordinates.';
            const lastUpdated = acknowledged.timestamp ? new Date(acknowledged.timestamp).toLocaleString() : 'Unknown';
            
            // Show acknowledged time if available
            const acknowledgedTime = acknowledged.acknowledged_at_time ? acknowledged.acknowledged_at_time : null;

            // Clear any existing marker/route for clarity
            if (emergencyMarker) {
                map.removeLayer(emergencyMarker);
                emergencyMarker = null;
            }

            map.flyTo([latitude, longitude], 16);

            const markerColor = isEmergency ? '#dc3545' : (statusUpper === 'ACKNOWLEDGED' ? '#ffc107' : '#0d6efd');
            const alertType = isEmergency ? 'danger' : (statusUpper === 'ACKNOWLEDGED' ? 'warning' : 'info');
            const statusIcon = isEmergency ? '🚨' : '⚠️';

            const escapeQuotes = (value) => String(value ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
            const safeName = escapeQuotes(displayName);
            const safeAddress = escapeQuotes(address);

            const customIcon = L.divIcon({
                className: 'custom-marker',
                html: `<div style="
                    background-color: ${markerColor};
                    width: 22px;
                    height: 22px;
                    border-radius: 50%;
                    border: 3px solid white;
                    box-shadow: 0 0 10px rgba(0,0,0,0.5);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: bold;
                    font-size: 12px;
                ">${isEmergency ? 'E' : 'A'}</div>`,
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            });

            const buildingId = acknowledged.building_id ? parseInt(acknowledged.building_id, 10) : null;
            const hasBuildingId = Number.isFinite(buildingId) && buildingId > 0;
            const respondButtonHtml = hasBuildingId ? `
                <button class="btn btn-outline-secondary btn-sm" onclick="confirmRespond(${buildingId})" title="Respond">
                    <i class="bi bi-check2-circle"></i>
                </button>` : '';

            emergencyMarker = L.marker([latitude, longitude], { icon: customIcon })
                .addTo(map)
                .bindPopup(`
                    <div class="text-center">
                        <h5 class="fw-bold">${displayName}</h5>
                        <p class="mb-1">${address}</p>
                        <p class="mb-1"><strong>Status:</strong> <span style="color:${markerColor}">${statusUpper}</span></p>
                        <p class="mb-1"><strong>Last Update:</strong> ${lastUpdated}</p>
                        <div class="mt-2 d-flex justify-content-center gap-2">
                            <button class="btn btn-sm btn-primary" onclick="routeToThisBuildingWithSpeech(${latitude}, ${longitude}, '${safeName}', '${safeAddress}', '${statusUpper}')">
                                <i class="bi bi-route me-1"></i> Route
                            </button>
                            ${respondButtonHtml}
                        </div>
                    </div>
                `)
                .openPopup();

            let alertMessage = `${statusIcon} Latest ACKNOWLEDGED fire incident located at <strong>${displayName}</strong>.<br>
                <strong>Status:</strong> ${statusUpper}<br>
                <strong>Updated:</strong> ${lastUpdated}`;
            
            if (acknowledgedTime) {
                alertMessage += `<br><strong>Acknowledged At:</strong> ${acknowledgedTime}`;
            }

            showAlert(alertType, alertMessage);
            speakText(`Latest ${statusUpper.toLowerCase()} incident located at ${displayName}.`);
        })
        .catch(error => {
            console.error("Error locating fire_data incident:", error);
            showAlert('danger', 'Unable to locate the latest fire incident. Please try again.');
        })
        .finally(() => {
            if (locateEmergencyBtn) {
                locateEmergencyBtn.disabled = false;
                locateEmergencyBtn.innerHTML = originalButtonHtml || '<i class="bi bi-crosshair2 me-1"></i> Locate Emergency';
            }
        });
}

// Function to route to a specific building
function routeToThisBuilding(latitude, longitude, buildingName, address, status) {
	if (!userLocation) {
		// Try to acquire location automatically, then proceed
		if (!navigator.geolocation) {
			showAlert('error', 'Geolocation is not supported by your browser');
			return;
		}

		// Provide immediate feedback
		showAlert('info', '📍 Getting your current location for routing...');

		navigator.geolocation.getCurrentPosition(
			position => {
				updateUserLocation(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
				// Retry routing now that we have location
				routeToThisBuilding(latitude, longitude, buildingName, address, status);
			},
			error => {
				handleLocationError(error);
			}
		);
		return;
	}

    // Clear any existing route
    clearRoute();

    // Determine styling based on status
    const statusUpper = status.toUpperCase();
    let routeColor, iconUrl;
    
    switch(statusUpper) {
        case 'EMERGENCY':
            routeColor = '#dc3545'; // Red
            iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599508.png';
            break;
        case 'ACKNOWLEDGED':
            routeColor = '#ffc107'; // Yellow
            iconUrl = 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png';
            break;
        case 'PRE-DISPATCH':
            routeColor = '#fd7e14'; // Orange
            iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599502.png';
            break;
        default:
            routeColor = '#6c757d'; // Gray
            iconUrl = 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
    }

    // Create routing control from user location to building
    routingControl = L.Routing.control({
        waypoints: [
            L.latLng(userLocation.lat, userLocation.lng),
            L.latLng(latitude, longitude)
        ],
        routeWhileDragging: false,
        showAlternatives: false,
        addWaypoints: false,
        draggableWaypoints: false,
        fitSelectedRoutes: 'smart',
        lineOptions: {
            styles: [{ color: routeColor, weight: 5, opacity: 0.8 }]
        },
        createMarker: () => null, // Don't create default markers
        show: false, // Hide the default routing panel
        collapsible: false,
        containerClassName: 'custom-routing-container'
    }).addTo(map);

    // Add custom emergency marker
    emergencyMarker = L.marker([latitude, longitude], {
        icon: L.icon({
            iconUrl: iconUrl,
            iconSize: [32, 32],
            iconAnchor: [16, 32]
        })
    }).addTo(map);

    // Handle route found event
    routingControl.on('routesfound', function(e) {
        const route = e.routes[0];
        const distanceMeters = route.summary.totalDistance;
        const durationSeconds = route.summary.totalTime;
        
        const distanceKm = (distanceMeters / 1000).toFixed(2);
        const durationFormatted = formatDuration(durationSeconds);
        
        // Speak route directions
        speakText(`Route found to ${buildingName}. Distance is ${distanceKm} kilometers. Estimated travel time is ${durationFormatted}. Status is ${statusUpper}.`);
        
        // Extract and speak detailed turn-by-turn directions
        if (route.instructions) {
            speakDetailedDirections(route.instructions, buildingName);
        }
        
        // Update emergency marker popup
        emergencyMarker.bindPopup(`
            <div class="text-center">
                <h5 class="fw-bold">${buildingName}</h5>
                <p class="mb-1">${address}</p>
                <p class="mb-1"><strong>Status:</strong> <span style="color:${routeColor}">${statusUpper}</span></p>
                <p class="mb-1"><strong>Distance:</strong> ${distanceKm} km</p>
                <p class="mb-1"><strong>ETA:</strong> ${durationFormatted}</p>
                <div class="mt-2">
                    <button class="btn btn-sm btn-primary" onclick="clearRoute()">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
            </div>
        `).openPopup();
        
        // Show alert with route info
        let alertType = 'info';
        if (statusUpper === 'EMERGENCY') alertType = 'danger';
        else if (statusUpper === 'ACKNOWLEDGED') alertType = 'warning';
        
        showAlert(alertType, `🚨 Routing to ${buildingName}<br>
            <strong>Status:</strong> ${statusUpper}<br>
            <strong>Distance:</strong> ${distanceKm} km<br>
            <strong>ETA:</strong> ${durationFormatted}`);
    });

    // Handle routing error
    routingControl.on('routingerror', function(e) {
        console.error('Routing error:', e.error);
        speakText('Routing error. Failed to calculate route to emergency building. Please try again.');
        showAlert('error', 'Failed to calculate route to emergency building. Please try again.');
    });

    // Fit map to show both locations
    map.fitBounds([
        [userLocation.lat, userLocation.lng],
        [latitude, longitude]
    ], { padding: [50, 50] });
}

// Respond to the latest EMERGENCY or ACKNOWLEDGED record for this building
async function respondToBuilding(buildingId) {
    try {
        const payload = { building_id: buildingId, response_type: 'Respond' };
        const res = await fetch('create_response.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data && data.success) {
            showAlert('success', 'Response recorded.');
        } else {
            const msg = (data && data.message) ? data.message : 'Failed to create response';
            showAlert('warning', msg);
        }
    } catch (e) {
        console.error('Respond error:', e);
        showAlert('danger', 'Error while creating response.');
    }
}

// Confirm before responding
function confirmRespond(buildingId) {
	Swal.fire({
		title: 'Confirm Response',
		text: 'Are you sure you want to send a response to this building?',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonColor: '#dc3545',
		cancelButtonColor: '#007bff',
		confirmButtonText: 'Yes, respond',
		cancelButtonText: 'Cancel'
	}).then((result) => {
		if (result.isConfirmed) {
			respondToBuilding(buildingId);
		}
	});
}

function fetchEmergencyBuildings() {
    return new Promise((resolve, reject) => {
        fetch('get_emergency_buildings.php?t=' + new Date().getTime())
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    reject(new Error(data.error));
                } else if (data.message) {
                    resolve([]); // Return empty array if no buildings found
                } else {
                    resolve(data);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                reject(error);
            });
    });
}

function fetchMostRecentCritical() {
    return fetch('get_most_recent_critical.php?t=' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .catch(error => {
            console.error('Fetch error (critical):', error);
            throw error;
        });
}

// Fetch most recent ACKNOWLEDGED status only
function fetchMostRecentAcknowledged() {
    return fetch('get_most_recent_acknowledged.php?t=' + new Date().getTime())
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .catch(error => {
            console.error('Fetch error (acknowledged):', error);
            throw error;
        });
}

// Show alert notification
function showAlert(type, message, autoClose = true) {
    const icon = {
        success: 'success',
        danger: 'error',
        warning: 'warning',
        info: 'info'
    }[type] || 'info';
    
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: autoClose ? 3000 : undefined,
        timerProgressBar: autoClose,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
    
    Toast.fire({
        icon: icon,
        title: message
    });
}

// Update last updated time
function updateTime() {
    const now = new Date();
    const updateTimeEl = document.getElementById('update-time');
    if (updateTimeEl) {
        updateTimeEl.textContent = now.toLocaleTimeString();
    }
}

// Toggle map legend visibility
function toggleLegend() {
    const legend = document.getElementById('mapLegend');
    const toggleIcon = document.getElementById('legendToggleIcon');
    
    if (legend && toggleIcon) {
        const isVisible = legend.classList.contains('show');
        
        if (isVisible) {
            // Hide legend
            legend.classList.remove('show');
            toggleIcon.classList.remove('fa-times');
            toggleIcon.classList.add('fa-bars');
        } else {
            // Show legend
            legend.classList.add('show');
            toggleIcon.classList.remove('fa-bars');
            toggleIcon.classList.add('fa-times');
        }
    }
}

// Calculate distance between two points (Haversine formula)
function calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth radius in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLon = (lon2 - lon1) * Math.PI / 180;
    const a = 
        Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLon/2) * Math.sin(dLon/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// WebSocket message handler 
// Only set up if websocket is defined
if (typeof websocket !== 'undefined' && websocket) {
websocket.onmessage = function (event) {
    let message = JSON.parse(event.data);
    
    // Log the whole message to see if it's correct
    console.log('Received WebSocket message:', message);

    // Ensure the user_id is properly received
    if (message.user_id && !isNaN(message.user_id) && message.user_id !== 0) {
        console.log('Received valid user ID from WebSocket:', message.user_id);
        // Assuming latitude and longitude are available, send the user location
        sendUserLocationToServer(message.user_id, message.latitude, message.longitude);
    } else {
        console.error('Invalid or missing user_id in WebSocket message');
    }
};
} else {
    console.warn('WebSocket not initialized, skipping message handler setup');
}

// Update user location on map
function updateUserLocation(latitude, longitude, accuracy) {
    userLocation = { lat: latitude, lng: longitude };
    
    // Speak location update
    speakText(`Location updated. Latitude ${latitude.toFixed(4)}, Longitude ${longitude.toFixed(4)}.`);
    
    // Remove previous marker if exists
    if (userLocationMarker) {
        map.removeLayer(userLocationMarker);
    }
    if (userLocationCircle) {
        map.removeLayer(userLocationCircle);
    }
    
    // Create new marker
    const userIcon = L.icon({
        iconUrl: '../images/person1.png',
        iconSize: [60, 60],
        iconAnchor: [60, 60],
        popupAnchor: [0, -32]
    });
    
    userLocationMarker = L.marker([latitude, longitude], {
        icon: userIcon,
        zIndexOffset: 1000
    }).addTo(map);
    
    // Add accuracy circle if available
    if (accuracy) {
        userLocationCircle = L.circle([latitude, longitude], {
            radius: accuracy,
            color: '#6c757d',
            fillColor: '#6c757d',
            fillOpacity: 0.15,
            weight: 1
        }).addTo(map);
    }
    
    // Fetch address from reverse geocoding
    fetch(`reverse_geocode.php?lat=${latitude}&lon=${longitude}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            let addressText = 'Address not available';
            
            // Check if the response contains an error
            if (data.error) {
                console.warn('Reverse geocoding error:', data.error);
                addressText = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
            } else if (data && data.display_name) {
                addressText = data.display_name;
            } else if (data && data.address) {
                // Build address from address object if display_name is not available
                const addr = data.address;
                let addressParts = [];
                if (addr.road) addressParts.push(addr.road);
                if (addr.house_number) addressParts.push(addr.house_number);
                if (addr.suburb || addr.neighbourhood) addressParts.push(addr.suburb || addr.neighbourhood);
                if (addr.city || addr.town || addr.municipality) addressParts.push(addr.city || addr.town || addr.municipality);
                if (addr.state) addressParts.push(addr.state);
                if (addr.postcode) addressParts.push(addr.postcode);
                if (addr.country) addressParts.push(addr.country);
                addressText = addressParts.length > 0 ? addressParts.join(', ') : `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
            } else {
                addressText = `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
            }
            
            // Bind popup with location info
            userLocationMarker.bindPopup(`
                <div class="text-center">
                    <h6 class="fw-bold mb-2">Your Location</h6>
                    <p class="mb-1" style="background-color: red; color: white; padding: 5px; border-radius: 3px;">${addressText}</p>
                    ${accuracy ? `<p class="mb-0">Accuracy: ${Math.round(accuracy)} meters</p>` : ''}
                </div>
            `).openPopup();
        })
        .catch(error => {
            console.error('Reverse geocoding error:', error);
            // Fallback to coordinates if geocoding fails
            userLocationMarker.bindPopup(`
                <div class="text-center">
                    <h6 class="fw-bold mb-2">Your Location</h6>
                    <p class="mb-1" style="background-color: red; color: white; padding: 5px; border-radius: 3px;">${latitude.toFixed(6)}, ${longitude.toFixed(6)}</p>
                    ${accuracy ? `<p class="mb-0">Accuracy: ${Math.round(accuracy)} meters</p>` : ''}
                </div>
            `).openPopup();
        });
}

// Fetch fire data from API
function fetchFireData() {
    // Show loading indicator
    showRefreshIndicator('loading', 'Updating fire data...');
    
    fetch(`${config.apiEndpoint}?user_id=${config.userId}`)
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                allFireData = data.data;
                updateDashboard(data.data);
                updateTime();
                showRefreshIndicator('success', 'Fire data updated successfully');
            } else {
                throw new Error(data.message);
            }
        })
        .catch(error => {
            console.error("Error fetching fire data:", error);
            showRefreshIndicator('error', 'Failed to fetch fire data');
        });
}

// Update dashboard with new data
function updateDashboard(fireData) {
    // Update counts
    const counts = {
        "SAFE": 0,
        "MONITORING": 0,
        "PRE-DISPATCH": 0,
        "EMERGENCY": 0
    };
    
    fireData.forEach(fire => {
        const status = fire.status.toUpperCase();
        if (counts.hasOwnProperty(status)) {
            counts[status]++;
        }
    });
    
    // Safely update elements if they exist
    const safeCountEl = document.getElementById('safe-count');
    if (safeCountEl) safeCountEl.textContent = counts.SAFE;
    
    const monitoringCountEl = document.getElementById('monitoring-count');
    if (monitoringCountEl) monitoringCountEl.textContent = counts.MONITORING;
    
    const predispatchCountEl = document.getElementById('predispatch-count');
    if (predispatchCountEl) predispatchCountEl.textContent = counts['PRE-DISPATCH'];
    
    const emergencyCountEl = document.getElementById('emergency-count');
    if (emergencyCountEl) emergencyCountEl.textContent = counts.EMERGENCY;
    
    const allCountEl = document.getElementById('all-count');
    if (allCountEl) allCountEl.textContent = fireData.length;
    
    // Update map with new data
    const activeFilter = document.querySelector('.filter-legend.active');
    if (activeFilter) {
        const status = activeFilter.getAttribute('data-status');
        if (status) {
            filterFiresByStatus(status);
        }
    }
    
    // Update alerts count
    const emergencyCount = counts.EMERGENCY;
    const alertCountEl = document.getElementById('alert-count');
    if (alertCountEl) alertCountEl.textContent = emergencyCount;
}

// Filter fires by status
function filterFiresByStatus(status) {
    // Clear existing layers
    map.removeLayer(fireMarkers);
    if (heatLayer) map.removeLayer(heatLayer);
    
    fireMarkers.clearLayers();
    const heatData = [];
    let emergencyCount = 0;
    
    // Process each fire incident
    allFireData.forEach(fire => {
        if (status === 'all' || fire.status === status) {
            // Get GPS coordinates (prioritize GPS, then fall back to geo_lat/geo_long)
            const lat = parseFloat(fire.gps_latitude ?? fire.latitude ?? fire.geo_lat);
            const lng = parseFloat(fire.gps_longitude ?? fire.longitude ?? fire.geo_long);
            
            // Skip if no valid coordinates
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
                return;
            }
            
            // Create marker
            const marker = createFireMarker(fire);
            if (marker) {
            fireMarkers.addLayer(marker);
            }
            
            // Add to heatmap data using GPS coordinates
            if (fire.heat > 0) {
                const intensity = Math.min(fire.heat / 100, 1);
                heatData.push([lat, lng, intensity]);
            }
            
            // Count emergencies for alert
            if (fire.status === 'Emergency') emergencyCount++;
        }
    });
}

// Create a fire marker
function createFireMarker(fire) {
    const statusClass = fire.status.toLowerCase().replace(/\s+/g, '-');
    const iconUrl = getIconForStatus(fire.status);
    
    // Prioritize GPS coordinates (gps_latitude, gps_longitude) from fire_data
    // Then fall back to geo_lat/geo_long, but NOT building coordinates
    const lat = parseFloat(fire.gps_latitude ?? fire.latitude ?? fire.geo_lat);
    const lng = parseFloat(fire.gps_longitude ?? fire.longitude ?? fire.geo_long);
    
    // Skip if no valid coordinates
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
        console.warn('Fire marker skipped - no valid GPS coordinates:', fire);
        return null;
    }
    
    const fireIcon = L.icon({
        iconUrl: iconUrl,
        iconSize: [32, 32],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32],
        className: 'fire-icon'
    });
    
    const marker = L.marker([lat, lng], { 
        icon: fireIcon,
        riseOnHover: true
    });
    
    // Add popup with fire details
    marker.bindPopup(`
        <div class="fire-popup">
            <h5 class="fw-bold text-${statusClass}">${fire.status}</h5>
            <p class="mb-1"><i class="bi bi-thermometer-sun me-2"></i>Heat: ${fire.heat}°C</p>
            <p class="mb-1"><i class="bi bi-thermometer-high me-2"></i>Temp: ${fire.temp}°C</p>
            <p class="mb-1"><i class="bi bi-cloud-fog me-2"></i>Smoke: ${fire.smoke}</p>
            <p class="mb-1"><i class="bi bi-fire me-2"></i>Flame: ${fire.flame_detected ? 'Detected' : 'Not detected'}</p>
            <p class="mb-2"><i class="bi bi-clock me-2"></i>${new Date(fire.timestamp).toLocaleString()}</p>
            <div class="d-grid gap-2">
                <button class="btn btn-sm btn-outline-${statusClass} calculate-distance" 
                        data-lat="${lat}" data-lng="${lng}">
                    <i class="bi bi-rulers"></i>
                </button>
            </div>
        </div>
    `);

    marker.bindTooltip(`${fire.status} - ${fire.temp}°C`, {
        permanent: false,
        direction: 'top',
        className: `fire-tooltip-${statusClass}`
    });
    
    return marker;
}

// Get appropriate icon for status
function getIconForStatus(status) {
    const icons = {
        'Safe': 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png',
        'Monitoring': 'https://cdn-icons-png.flaticon.com/512/3523/3523096.png',
        'Pre-Dispatch': 'https://cdn-icons-png.flaticon.com/512/599/599502.png',
        'Emergency': 'https://cdn-icons-png.flaticon.com/512/599/599508.png'
    };
    return icons[status] || 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
}

// Show route to station function
function showRouteToStation() {
    speakText('Finding route to fire station from latest fire incident.');
    // Use the most recent ACKNOWLEDGED fire_data with GPS coordinates
    fetchMostRecentAcknowledged()
        .then(response => {
            if (!response || !response.success || !response.data) {
                const infoMessage = (response && response.message) ? response.message : 'No fire incidents found.';
                speakText('No fire incidents available.');
                showAlert('info', infoMessage);
                clearRoute();
                return;
            }

            const fireData = response.data;
            
            // Prioritize GPS coordinates (gps_latitude, gps_longitude) from fire_data
            // Then fall back to geo_lat/geo_long, but NOT building coordinates
            const latitude = parseFloat(fireData.gps_latitude ?? fireData.latitude ?? fireData.geo_lat ?? fireData.fire_data_latitude);
            const longitude = parseFloat(fireData.gps_longitude ?? fireData.longitude ?? fireData.geo_long ?? fireData.fire_data_longitude);

            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
                showAlert('warning', 'Latest fire_data entry does not include valid GPS coordinates.');
                console.warn('Missing GPS coordinates in fire_data payload:', fireData);
                clearRoute();
                return;
            }

            const status = fireData.status || 'UNKNOWN';
            const building_name = fireData.building_name || `Device ${fireData.device_name || fireData.device_id || 'Unknown'}`;
            const address = fireData.address || 'Location derived from fire_data GPS coordinates.';

            clearRoute();

            // Determine styling based on status
            const statusUpper = status.toUpperCase();
            let routeColor, iconUrl;
            
            switch(statusUpper) {
                case 'EMERGENCY':
                    routeColor = '#dc3545'; // Red
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599508.png';
                    break;
                case 'ACKNOWLEDGED':
                    routeColor = '#ffc107'; // Yellow
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png';
                    break;
                case 'PRE-DISPATCH':
                    routeColor = '#fd7e14'; // Orange
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/599/599502.png';
                    break;
                default:
                    routeColor = '#6c757d'; // Gray
                    iconUrl = 'https://cdn-icons-png.flaticon.com/512/3212/3212567.png';
            }

            // Add marker with appropriate styling
            emergencyMarker = L.marker([latitude, longitude], {
                icon: L.icon({
                    iconUrl: iconUrl,
                    iconSize: [32, 32],
                    iconAnchor: [16, 32]
                })
            }).addTo(map);

            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(latitude, longitude),
                    L.latLng(config.fireStation.lat, config.fireStation.lng)
                ],
                routeWhileDragging: false,
                showAlternatives: false,
                addWaypoints: false,
                draggableWaypoints: false,
                fitSelectedRoutes: 'smart',
                lineOptions: {
                    styles: [{ color: routeColor, weight: 5, opacity: 0.8 }]
                },
                createMarker: () => null, // Don't create default markers
                show: false, // Hide the default routing panel
                collapsible: false,
                containerClassName: 'custom-routing-container'
            }).addTo(map);

            routingControl.on('routesfound', function (e) {
                const route = e.routes[0];
                const distanceMeters = route.summary.totalDistance;
                const durationSeconds = route.summary.totalTime;

                const distanceKm = (distanceMeters / 1000).toFixed(2);
                const durationFormatted = formatDuration(durationSeconds);

                // Speak route directions to fire station
                speakText(`Route to fire station from ${building_name}. Distance is ${distanceKm} kilometers. Estimated travel time is ${durationFormatted}. Status is ${statusUpper}.`);

                // Extract and speak detailed turn-by-turn directions
                if (route.instructions) {
                    speakDetailedDirections(route.instructions, 'Fire Station');
                }

                if (emergencyMarker) {
                    emergencyMarker.bindPopup(`
                        <div class="text-center">
                            <h5 class="fw-bold">${building_name}</h5>
                            <p class="mb-1">${address}</p>
                            <p class="mb-1"><strong>Status:</strong> <span style="color:${routeColor}">${statusUpper}</span></p>
                            <p class="mb-1"><strong>Distance to Station:</strong> ${distanceKm} km</p>
                            <p class="mb-1"><strong>ETA:</strong> ${durationFormatted}</p>
                        </div>
                    `).openPopup();
                }

                let alertType = 'info';
                if (statusUpper === 'EMERGENCY') alertType = 'danger';
                else if (statusUpper === 'ACKNOWLEDGED') alertType = 'warning';

                showAlert(alertType, `🚨 Route to Fire Station from ${building_name}<br>
                    <strong>Status:</strong> ${statusUpper}<br>
                    <strong>Distance:</strong> ${distanceKm} km<br>
                    <strong>ETA:</strong> ${durationFormatted}`);
            });

            map.fitBounds([
                [latitude, longitude],
                [config.fireStation.lat, config.fireStation.lng]
            ]);
        })
        .catch(error => {
            console.error("Error fetching fire_data:", error);
            showAlert('danger', 'Error locating fire incident. Please try again.');
            clearRoute();
        });
}

// Function to route to emergency from current location
function routeToEmergencyFromLocation() {
    speakText('Getting location for emergency routing.');
    if (!navigator.geolocation) {
        speakText('Geolocation is not supported by your browser.');
        showAlert('error', 'Geolocation is not supported by your browser');
        return;
    }

    const btn = document.getElementById('routeToEmergency');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Getting Location...';

    // Enhanced mobile detection
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS|FxiOS/i.test(navigator.userAgent) || 
                     (window.innerWidth <= 768) || 
                     ('ontouchstart' in window);
    
    if (isMobile) {
        // For mobile, show permission request first
        Swal.fire({
            title: '📍 Location Access Required',
            html: `
                <div class="text-start">
                    <p>To route to emergency buildings, we need your current location.</p>
                    <p>This will help us:</p>
                    <ul class="text-start">
                        <li>🗺️ Calculate the best route to emergencies</li>
                        <li>⏱️ Provide accurate travel time estimates</li>
                        <li>🚨 Show nearby emergency situations</li>
                    </ul>
                    <p class="text-muted small mt-3">
                        <strong>Note:</strong> Your location is only used for emergency routing and is not stored permanently.
                    </p>
                </div>
            `,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '📍 Allow Location',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            allowOutsideClick: false,
            customClass: {
                popup: 'swal-wide'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                getLocationForRouting(btn);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-route me-1"></i> Route to Emergency';
                showAlert('info', 'ℹ️ Location access is required for emergency routing. You can try again anytime.');
            }
        });
    } else {
        // For desktop, proceed directly
        getLocationForRouting(btn);
    }
}

// Get location specifically for routing
function getLocationForRouting(btn) {
    speakText('Getting your current location for routing.');
    navigator.geolocation.getCurrentPosition(
        position => {
            const { latitude, longitude, accuracy } = position.coords;
            
            // Update user location on map
            updateUserLocation(latitude, longitude, accuracy);
            
            // Update button state
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-route me-1"></i> Route to Emergency';
            
            // Route to emergency building
            routeToLatestEmergencyBuilding(latitude, longitude);
        },
        error => {
            console.error('Geolocation error:', error);
            handleLocationError(error);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-route me-1"></i> Route to Emergency';
        },
        {
            enableHighAccuracy: true,
            maximumAge: 30000,
            timeout: 15000 // Increased timeout for mobile
        }
    );
}

// Check location permission status
function checkLocationPermission() {
    if (!navigator.geolocation) {
        return 'not-supported';
    }
    
    // For browsers that support permissions API
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(result => {
            return result.state;
        });
    }
    
    // Fallback: try to get current position to check permission
    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            () => resolve('granted'),
            (error) => {
                if (error.code === error.PERMISSION_DENIED) {
                    resolve('denied');
                } else {
                    resolve('prompt');
                }
            },
            { timeout: 1000 }
        );
    });
}

// Add manual location permission check button
function addLocationPermissionButton() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS|FxiOS/i.test(navigator.userAgent) || 
                     (window.innerWidth <= 768) || 
                     ('ontouchstart' in window);
    
    if (isMobile) {
        // Disabled floating mobile help buttons to avoid floating UI
        return;
    }
}

// Show location help for mobile users
function showLocationHelp() {
    Swal.fire({
        title: '📍 Location Access Help',
        html: `
            <div class="text-start">
                <p><strong>Having trouble with location access?</strong></p>
                
                <h6>📱 For Mobile Browsers:</h6>
                <ol class="text-start">
                    <li>Tap the <strong>lock/info icon</strong> in your browser's address bar</li>
                    <li>Find <strong>"Location"</strong> or <strong>"Site Settings"</strong></li>
                    <li>Change it to <strong>"Allow"</strong></li>
                    <li>Refresh this page</li>
                </ol>
                
                <h6>🔧 For iOS Safari:</h6>
                <ol class="text-start">
                    <li>Go to <strong>Settings</strong> → <strong>Safari</strong></li>
                    <li>Tap <strong>Location</strong></li>
                    <li>Select <strong>"Ask"</strong> or <strong>"Allow"</strong></li>
                </ol>
                
                <h6>🤖 For Android Chrome:</h6>
                <ol class="text-start">
                    <li>Go to <strong>Settings</strong> → <strong>Site Settings</strong></li>
                    <li>Tap <strong>Location</strong></li>
                    <li>Find this website and set to <strong>"Allow"</strong></li>
                </ol>
                
                <div class="mt-3 p-3 bg-light rounded">
                    <p class="mb-0 text-muted small">
                        <strong>💡 Tip:</strong> Make sure your device's GPS is turned on for the most accurate location.
                    </p>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Got it!',
        showCancelButton: true,
        cancelButtonText: 'Try Again',
        allowOutsideClick: false,
        customClass: {
            popup: 'swal-wide'
        }
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            requestLocationPermission();
        }
    });
}

// Force location access with multiple fallback methods
function forceLocationAccess() {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|mobile|CriOS|FxiOS/i.test(navigator.userAgent) || 
                     (window.innerWidth <= 768) || 
                     ('ontouchstart' in window);
    
    if (!isMobile) {
        console.log("Not a mobile device, using standard location request");
        return;
    }
    
    console.log("Attempting to force location access on mobile device");
    
    // Method 1: Try Permissions API first
    if (navigator.permissions && navigator.permissions.query) {
        navigator.permissions.query({ name: 'geolocation' }).then(result => {
            console.log("Permission status:", result.state);
            
            if (result.state === 'granted') {
                console.log("Location already granted, getting position");
                getCurrentPositionWithHighAccuracy();
            } else if (result.state === 'prompt') {
                console.log("Permission prompt needed");
                showForceLocationDialog();
            } else if (result.state === 'denied') {
                console.log("Permission denied, showing manual instructions");
                showManualLocationInstructions();
            }
        }).catch(error => {
            console.log("Permissions API failed, trying direct approach");
            showForceLocationDialog();
        });
    } else {
        // Method 2: Direct approach without Permissions API
        console.log("Permissions API not available, trying direct approach");
        showForceLocationDialog();
    }
}

// Show a more aggressive location request dialog
function showForceLocationDialog() {
    Swal.fire({
        title: '📍 CRITICAL: Location Access Required',
        html: `
            <div class="text-start">
                <div class="alert alert-danger mb-3">
                    <strong>🚨 Emergency System Alert:</strong><br>
                    This Fire Detection System requires your location to provide emergency routing and safety alerts.
                </div>
                
                <p><strong>Why we need your location:</strong></p>
                <ul class="text-start">
                    <li>🚨 <strong>Emergency Response:</strong> Route firefighters to your location</li>
                    <li>🏢 <strong>Building Safety:</strong> Alert you about nearby fire incidents</li>
                    <li>🗺️ <strong>Navigation:</strong> Provide accurate directions to safety</li>
                    <li>⏱️ <strong>Real-time Updates:</strong> Get instant emergency notifications</li>
                </ul>
                
                <div class="alert alert-warning mt-3">
                    <strong>⚠️ Important:</strong> Your location is only used for emergency purposes and is not stored permanently.
                </div>
                
                <p class="text-muted small mt-3">
                    <strong>Device:</strong> ${navigator.userAgent.includes('Android') ? 'Android' : navigator.userAgent.includes('iPhone') ? 'iPhone' : 'Mobile'}<br>
                    <strong>Browser:</strong> ${navigator.userAgent.includes('Chrome') ? 'Chrome' : navigator.userAgent.includes('Safari') ? 'Safari' : 'Other'}
                </p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '📍 ALLOW LOCATION ACCESS',
        cancelButtonText: 'Show Manual Instructions',
        reverseButtons: true,
        allowOutsideClick: false,
        customClass: {
            popup: 'swal-wide'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            attemptMultipleLocationMethods();
        } else {
            showManualLocationInstructions();
        }
    });
}

// Try multiple location methods
function attemptMultipleLocationMethods() {
    console.log("Attempting multiple location methods");
    
    // Method 1: High accuracy with long timeout
    navigator.geolocation.getCurrentPosition(
        position => {
            console.log("Method 1 successful:", position);
            handleLocationSuccess(position);
        },
        error1 => {
            console.log("Method 1 failed:", error1);
            
            // Method 2: Lower accuracy, shorter timeout
            navigator.geolocation.getCurrentPosition(
                position => {
                    console.log("Method 2 successful:", position);
                    handleLocationSuccess(position);
                },
                error2 => {
                    console.log("Method 2 failed:", error2);
                    
                    // Method 3: Watch position (continuous)
                    const watchId = navigator.geolocation.watchPosition(
                        position => {
                            console.log("Method 3 successful:", position);
                            navigator.geolocation.clearWatch(watchId);
                            handleLocationSuccess(position);
                        },
                        error3 => {
                            console.log("Method 3 failed:", error3);
                            navigator.geolocation.clearWatch(watchId);
                            showManualLocationInstructions();
                        },
                        {
                            enableHighAccuracy: false,
                            timeout: 10000,
                            maximumAge: 300000 // 5 minutes
                        }
                    );
                    
                    // Clear watch after 15 seconds if no success
                    setTimeout(() => {
                        navigator.geolocation.clearWatch(watchId);
                    }, 15000);
                },
                {
                    enableHighAccuracy: false,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes
                }
            );
        },
        {
            enableHighAccuracy: true,
            timeout: 30000, // 30 seconds
            maximumAge: 60000 // 1 minute
        }
    );
}

// Handle successful location
function handleLocationSuccess(position) {
    const { latitude, longitude, accuracy } = position.coords;
    
    console.log("Location obtained successfully:", { latitude, longitude, accuracy });
    
    // Update user location on map
    updateUserLocation(latitude, longitude, accuracy);
    
    // Show success message
    Swal.fire({
        title: '✅ Location Access Granted!',
        html: `
            <div class="text-center">
                <p><strong>Your location has been successfully obtained!</strong></p>
                <p>Latitude: ${latitude.toFixed(6)}</p>
                <p>Longitude: ${longitude.toFixed(6)}</p>
                <p>Accuracy: ${Math.round(accuracy)} meters</p>
                <div class="alert alert-success mt-3">
                    <strong>🎉 You can now use all emergency routing features!</strong>
                </div>
            </div>
        `,
        icon: 'success',
        confirmButtonText: 'Great!',
        allowOutsideClick: false
    });
    
    // Center map on user's location
    if (map) {
        map.flyTo([latitude, longitude], 16);
    }
}

// Show detailed manual instructions
function showManualLocationInstructions() {
    const isAndroid = /Android/i.test(navigator.userAgent);
    const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
    const isChrome = /Chrome/i.test(navigator.userAgent);
    const isSafari = /Safari/i.test(navigator.userAgent) && !/Chrome/i.test(navigator.userAgent);
    
    let instructions = '';
    
    if (isAndroid && isChrome) {
        instructions = `
            <h6>🤖 Android Chrome Instructions:</h6>
            <ol class="text-start">
                <li>Tap the <strong>lock icon</strong> (🔒) in the address bar</li>
                <li>Tap <strong>"Site settings"</strong></li>
                <li>Find <strong>"Location"</strong> and tap it</li>
                <li>Select <strong>"Allow"</strong></li>
                <li>Go back and <strong>refresh this page</strong></li>
            </ol>
            
            <h6>🔧 Alternative Method:</h6>
            <ol class="text-start">
                <li>Open <strong>Chrome Settings</strong> (three dots menu)</li>
                <li>Go to <strong>Site Settings</strong> → <strong>Location</strong></li>
                <li>Find this website and set to <strong>"Allow"</strong></li>
                <li>Refresh this page</li>
            </ol>
        `;
    } else if (isIOS && isSafari) {
        instructions = `
            <h6>🍎 iOS Safari Instructions:</h6>
            <ol class="text-start">
                <li>Tap the <strong>AA icon</strong> in the address bar</li>
                <li>Tap <strong>"Website Settings"</strong></li>
                <li>Find <strong>"Location"</strong> and set to <strong>"Allow"</strong></li>
                <li>Refresh this page</li>
            </ol>
            
            <h6>🔧 Alternative Method:</h6>
            <ol class="text-start">
                <li>Go to <strong>Settings</strong> → <strong>Safari</strong></li>
                <li>Tap <strong>"Location"</strong></li>
                <li>Select <strong>"Ask"</strong> or <strong>"Allow"</strong></li>
                <li>Refresh this page</li>
            </ol>
        `;
    } else {
        instructions = `
            <h6>📱 General Mobile Instructions:</h6>
            <ol class="text-start">
                <li>Look for a <strong>lock/info icon</strong> in the address bar</li>
                <li>Tap it to see <strong>site permissions</strong></li>
                <li>Find <strong>"Location"</strong> and enable it</li>
                <li>Refresh this page</li>
            </ol>
            
            <h6>🔧 Device Settings:</h6>
            <ol class="text-start">
                <li>Make sure <strong>GPS/Location Services</strong> is turned ON</li>
                <li>Check that your <strong>browser has location permission</strong></li>
                <li>Try <strong>clearing browser cache</strong> and cookies</li>
                <li>Restart your browser and try again</li>
            </ol>
        `;
    }
    
    Swal.fire({
        title: '📍 Manual Location Setup Required',
        html: `
            <div class="text-start">
                <p><strong>Automatic location access failed. Please follow these manual steps:</strong></p>
                ${instructions}
                
                <div class="alert alert-info mt-3">
                    <strong>💡 After following these steps:</strong>
                    <ul class="mb-0">
                        <li>Refresh this page</li>
                        <li>Try the "My Location" button again</li>
                        <li>Or click "Try Again" below</li>
                    </ul>
                </div>
            </div>
        `,
        icon: 'info',
        confirmButtonText: 'Got it!',
        showCancelButton: true,
        cancelButtonText: 'Try Again',
        allowOutsideClick: false,
        customClass: {
            popup: 'swal-wide'
        }
    }).then((result) => {
        if (result.dismiss === Swal.DismissReason.cancel) {
            forceLocationAccess();
        }
    });
}

// Add this helper at the top of the main <script> section (after config and before other functions)
function speakText(text, rate = null) {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel(); // Stop any current speech
        const utterance = new SpeechSynthesisUtterance(text);
        
        // Use provided rate, or current slider rate, or default slower rate
        const speechRate = rate !== null ? rate : (window.currentSpeechRate || 0.7);
        utterance.rate = speechRate; // Set speech rate (0.1 to 10, default is 1)
        utterance.pitch = 1; // Set pitch (0 to 2, default is 1)
        utterance.volume = 1; // Set volume (0 to 1, default is 1)
        window.speechSynthesis.speak(utterance);
    }
}

// Function to stop all text-to-speech
function stopAllSpeech() {
    if ('speechSynthesis' in window) {
        window.speechSynthesis.cancel();
        console.log('All text-to-speech stopped');
    }
}

// For dynamically generated buttons (e.g., Route to This Building),
// replace the onclick in the HTML with a wrapper function:
// <button class="btn btn-sm btn-primary" onclick="routeToThisBuildingWithSpeech(latitude, longitude, buildingName, address, status)">
//     <i class="bi bi-route me-1"></i> Route to This Building
// </button>
// Add this wrapper function:
function routeToThisBuildingWithSpeech(latitude, longitude, buildingName, address, status) {
    speakText('Routing to ' + buildingName + ', status: ' + status);
    routeToThisBuilding(latitude, longitude, buildingName, address, status);
}

// Function to speak detailed turn-by-turn directions
function speakDetailedDirections(instructions, destination) {
    if (!instructions || !Array.isArray(instructions)) {
        console.log('No detailed instructions available');
        return;
    }
    
    // Create or update directions panel
    createDirectionsPanel(instructions, destination);
    
    // Wait a moment before starting detailed directions
    setTimeout(() => {
        speakText(`Starting turn-by-turn directions to ${destination}.`, 0.6);
        
        // Process each instruction with a delay
        instructions.forEach((instruction, index) => {
            setTimeout(() => {
                if (instruction.text) {
                    // Clean up the instruction text for better speech
                    let cleanText = instruction.text
                        .replace(/<[^>]*>/g, '') // Remove HTML tags
                        .replace(/&nbsp;/g, ' ') // Replace HTML entities
                        .replace(/\s+/g, ' ') // Normalize whitespace
                        .trim();
                    
                    // Convert distance to more natural speech
                    cleanText = cleanText.replace(/(\d+)\s*m/, (match, meters) => {
                        const distance = parseInt(meters);
                        if (distance >= 1000) {
                            return `${(distance / 1000).toFixed(1)} kilometers`;
                        } else {
                            return `${distance} meters`;
                        }
                    });
                    
                    // Convert time to more natural speech
                    cleanText = cleanText.replace(/(\d+)\s*min/, (match, minutes) => {
                        const mins = parseInt(minutes);
                        if (mins === 1) {
                            return '1 minute';
                        } else {
                            return `${mins} minutes`;
                        }
                    });
                    
                    // Highlight current instruction in panel
                    highlightCurrentInstruction(index);
                    
                    // Speak the instruction with slower rate
                    speakText(cleanText, 0.6);
                }
            }, (index + 1) * 4000); // 4 second delay between each instruction (increased from 3)
        });
        
        // Final arrival message
        setTimeout(() => {
            speakText(`You have arrived at your destination: ${destination}.`, 0.6);
            highlightCurrentInstruction(instructions.length);
        }, (instructions.length + 1) * 4000);
    }, 2000);
}

// Function to create directions panel
function createDirectionsPanel(instructions, destination) {
    // Remove existing panel if any
    const existingPanel = document.getElementById('directions-panel');
    if (existingPanel) {
        existingPanel.remove();
    }
    
    // Create new panel
    const panel = document.createElement('div');
    panel.id = 'directions-panel';
    panel.className = 'directions-panel';
    panel.innerHTML = `
        <div class="directions-header">
            <h5><i class="bi bi-route me-2"></i>Directions to ${destination}</h5>
            <div class="speech-controls">
                <span class="speed-label">Speed:</span>
                <input type="range" id="speech-rate" class="speed-slider" min="0.3" max="1.5" step="0.1" value="0.6">
                <span id="rate-display" class="speed-display">0.6x</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleDirectionsTTS()" title="Toggle Speech">
                    <i class="bi bi-volume-up" id="tts-icon"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="closeDirectionsPanel()" title="Close">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        </div>
        <div class="directions-content">
            ${instructions.map((instruction, index) => {
                const colorClass = index % 3 === 0 ? 'step-blue' : index % 3 === 1 ? 'step-green' : 'step-red';
                return `
                <div class="direction-step" data-step="${index}">
                    <div class="step-number ${colorClass}">${index + 1}</div>
                    <div class="step-text">${instruction.text}</div>
                </div>
            `;
            }).join('')}
            <div class="direction-step arrival-step" data-step="${instructions.length}">
                <div class="step-number">✓</div>
                <div class="step-text">You have arrived at your destination</div>
            </div>
        </div>
    `;
    
    // Add panel to map container
    const mapContainer = document.getElementById('map');
    if (mapContainer) {
        mapContainer.appendChild(panel);
    } else {
        document.body.appendChild(panel);
    }
    
    // Add event listener for speech rate slider
    setTimeout(() => {
        const rateSlider = document.getElementById('speech-rate');
        const rateDisplay = document.getElementById('rate-display');
        
        if (rateSlider && rateDisplay) {
            rateSlider.addEventListener('input', function() {
                const rate = parseFloat(this.value);
                rateDisplay.textContent = rate.toFixed(1) + 'x';
                // Store the current rate for use in speakText function
                window.currentSpeechRate = rate;
            });
        }
    }, 100);
    
    // Add CSS for the panel
    if (!document.getElementById('directions-panel-css')) {
        const style = document.createElement('style');
        style.id = 'directions-panel-css';
        style.textContent = `
            .directions-panel {
                position: absolute;
                bottom: 20px;
                right: 20px;
                width: 320px;
                max-height: 65vh;
                background: #ffffff;
                border: none;
                border-radius: 12px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08);
                z-index: 1000;
                overflow: hidden;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                backdrop-filter: blur(10px);
            }
            .directions-header {
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                color: #1a1a1a;
                padding: 12px 16px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #e9ecef;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                position: relative;
                z-index: 10;
            }
            .speech-controls {
                display: flex;
                align-items: center;
                gap: 4px;
                flex-wrap: nowrap;
            }
            .speed-label {
                font-size: 10px;
                color: #6c757d;
                font-weight: 500;
                white-space: nowrap;
            }
            .speed-slider {
                width: 50px;
                height: 3px;
                background: #e9ecef;
                border-radius: 2px;
                outline: none;
                margin: 0 2px;
            }
            .speed-slider::-webkit-slider-thumb {
                background: #007bff;
                border: none;
                border-radius: 50%;
                width: 12px;
                height: 12px;
                cursor: pointer;
                -webkit-appearance: none;
            }
            .speed-slider::-moz-range-thumb {
                background: #007bff;
                border: none;
                border-radius: 50%;
                width: 12px;
                height: 12px;
                cursor: pointer;
            }
            .speed-display {
                font-size: 9px;
                color: #495057;
                background: #f8f9fa;
                padding: 1px 4px;
                border-radius: 3px;
                min-width: 25px;
                text-align: center;
                font-weight: 600;
                white-space: nowrap;
            }
            .speech-controls .btn {
                padding: 2px 6px;
                font-size: 10px;
                line-height: 1.2;
                min-width: 24px;
                height: 24px;
            }
            .directions-header {
                background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
                color: #1a1a1a;
                padding: 8px 12px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid #e9ecef;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
                position: relative;
                z-index: 10;
            }
            .directions-header h5 {
                margin: 0;
                font-size: 13px;
                font-weight: 600;
                color: #1a1a1a;
                flex: 1;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .directions-header h5 i {
                color: #6c757d;
                font-size: 20px;
                font-weight: bold;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
                margin-right: 8px;
            }
            .directions-header .btn {
                width: 36px;
                height: 36px;
                padding: 0;
                border-radius: 10px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-left: 8px;
                transition: all 0.2s ease;
                border: 2px solid #dee2e6;
                background: #ffffff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .directions-header .btn-outline-secondary {
                color: white;
                border-color: #fd7e14;
                background: #fd7e14;
            }
            .directions-header .btn-outline-danger {
                color: white;
                border-color: #dc3545;
                background: #dc3545;
            }
            .directions-header .btn i {
                font-size: 18px;
                font-weight: bold;
                text-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            .directions-content {
                max-height: 50vh;
                overflow-y: auto;
                padding: 8px;
                background: #ffffff;
            }
            .directions-content::-webkit-scrollbar {
                width: 6px;
            }
            .directions-content::-webkit-scrollbar-track {
                background: #f1f3f4;
                border-radius: 3px;
            }
            .directions-content::-webkit-scrollbar-thumb {
                background: #c1c8cd;
                border-radius: 3px;
            }
            .directions-content::-webkit-scrollbar-thumb:hover {
                background: #a8b2ba;
            }
            .direction-step {
                display: flex;
                align-items: flex-start;
                padding: 10px 12px;
                border-bottom: 1px solid #f1f3f4;
                transition: all 0.2s ease;
                border-radius: 8px;
                margin-bottom: 2px;
            }
            .direction-step:last-child {
                border-bottom: none;
                margin-bottom: 0;
            }
            .direction-step:hover {
                background-color: #f8f9fa;
            }
            .direction-step.active {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-left: 4px solid #6c757d;
                box-shadow: 0 2px 8px rgba(108, 117, 125, 0.15);
                transform: translateX(2px);
            }
            .step-number {
                background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
                color: white;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 13px;
                font-weight: 800;
                margin-right: 14px;
                flex-shrink: 0;
                box-shadow: 0 3px 8px rgba(0, 123, 255, 0.4);
                border: 3px solid #ffffff;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            }
            
            .step-number.step-blue {
                background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
                box-shadow: 0 3px 8px rgba(0, 123, 255, 0.4);
            }
            
            .step-number.step-green {
                background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
                box-shadow: 0 3px 8px rgba(40, 167, 69, 0.4);
            }
            
            .step-number.step-red {
                background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
                box-shadow: 0 3px 8px rgba(220, 53, 69, 0.4);
            }
            .arrival-step .step-number {
                background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
                box-shadow: 0 3px 8px rgba(40, 167, 69, 0.4);
                border: 3px solid #ffffff;
                text-shadow: 0 1px 2px rgba(0,0,0,0.3);
            }
            .step-text {
                flex: 1;
                font-size: 13px;
                line-height: 1.4;
                color: #2c3e50;
                font-weight: 500;
            }
            @media (max-width: 768px) {
                .directions-panel {
                    width: 280px;
                    right: 15px;
                    bottom: 15px;
                    max-height: 60vh;
                }
                .directions-header {
                    padding: 6px 10px;
                }
                .directions-header h5 {
                    font-size: 12px;
                }
                .speech-controls {
                    gap: 2px;
                }
                .speed-label {
                    font-size: 9px;
                }
                .speed-slider {
                    width: 40px;
                }
                .speed-display {
                    font-size: 8px;
                    min-width: 20px;
                    padding: 1px 3px;
                }
                .speech-controls .btn {
                    padding: 1px 4px;
                    font-size: 9px;
                    min-width: 20px;
                    height: 20px;
                }
                .directions-content {
                    padding: 6px;
                }
                .direction-step {
                    padding: 8px 10px;
                }
                .step-number {
                    width: 26px;
                    height: 26px;
                    font-size: 12px;
                    margin-right: 12px;
                    border: 2px solid #ffffff;
                }
                .step-text {
                    font-size: 12px;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Function to highlight current instruction
function highlightCurrentInstruction(stepIndex) {
    const steps = document.querySelectorAll('.direction-step');
    steps.forEach((step, index) => {
        step.classList.remove('active');
        if (index === stepIndex) {
            step.classList.add('active');
            step.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
}

// Function to toggle TTS
function toggleDirectionsTTS() {
    const icon = document.getElementById('tts-icon');
    if (icon.classList.contains('bi-volume-up')) {
        icon.classList.remove('bi-volume-up');
        icon.classList.add('bi-volume-mute');
        window.speechSynthesis.cancel();
    } else {
        icon.classList.remove('bi-volume-mute');
        icon.classList.add('bi-volume-up');
    }
}

// Function to close directions panel
function closeDirectionsPanel() {
    console.log('closeDirectionsPanel function called');
    const panel = document.getElementById('directions-panel');
    console.log('directions-panel element found:', !!panel);
    if (panel) {
        console.log('Removing directions panel');
        panel.remove();
    }
    console.log('Cancelling speech synthesis');
    window.speechSynthesis.cancel();
    console.log('closeDirectionsPanel function completed');
}

// Side Tab Functions
function initSideTab() {
    updateAcknowledgedAlarmsWithPagination();
    
    // Auto-refresh is now handled by the startAutoRefresh() function
    // which is called when the page loads
}

function updateStatusCounts() {
    const emergencyCount = allFireData.filter(data => data.status === 'EMERGENCY').length;
    const monitoringCount = allFireData.filter(data => data.status === 'MONITORING').length;
    const safeCount = allFireData.filter(data => data.status === 'SAFE').length;
    
    const emergencyCountEl = document.getElementById('emergency-count');
    if (emergencyCountEl) emergencyCountEl.textContent = emergencyCount;
    
    const monitoringCountEl = document.getElementById('monitoring-count');
    if (monitoringCountEl) monitoringCountEl.textContent = monitoringCount;
    
    const safeCountEl = document.getElementById('safe-count');
    if (safeCountEl) safeCountEl.textContent = safeCount;
}

function updateSystemInfo() {
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    const totalBuildings = config.buildings.length;
    
    // Update elements if they exist
    const lastUpdateEl = document.getElementById('last-update');
    const totalBuildingsEl = document.getElementById('total-buildings');
    const mapStatusEl = document.getElementById('map-status');
    
    if (lastUpdateEl) lastUpdateEl.textContent = timeString;
    if (totalBuildingsEl) totalBuildingsEl.textContent = totalBuildings;
    if (mapStatusEl) mapStatusEl.textContent = map ? 'Active' : 'Inactive';
    
    // Update refresh button with last update time
    const refreshBtn = document.querySelector('button[onclick="updateAcknowledgedAlarms()"]');
    if (refreshBtn) {
        refreshBtn.title = `Last updated: ${timeString}`;
    }
}

// Removed Recent Alerts updater to display only acknowledged alarms in the sidebar

function getTimeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    return `${Math.floor(diffInSeconds / 86400)}d ago`;
}

function updateAcknowledgedAlarms() {
    const container = document.getElementById('acknowledged-alarms');
    
    // Show loading indicator with timestamp
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    container.innerHTML = `
        <div class="no-alarms">
            <i class="bi bi-arrow-clockwise spin me-2"></i>
            Loading acknowledged alarms...
            <br><small class="text-muted">Last update: ${timeString}</small>
        </div>
    `;
    
    console.log('Starting to fetch acknowledged alarms...'); // Debug log
    
    fetch('get_acknowledged_alarms.php')
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            console.log('Response headers:', response.headers); // Debug log
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get response as text first
        })
        .then(text => {
            console.log('Raw response text:', text); // Debug log
            
            try {
                const data = JSON.parse(text);
                console.log('Parsed JSON data:', data); // Debug log
                
                if (data.success) {
                    if (data.data && data.data.length > 0) {
                        console.log('Found', data.data.length, 'acknowledged alarms'); // Debug log
                        
                        // Limit to 10 most recent alarms
                        const recentAlarms = data.data.slice(0, 10);
                        
                        const alarmsHTML = recentAlarms.map((alarm, index) => {
                            const timeAgo = getTimeAgo(new Date(alarm.timestamp));
                            const timestamp = new Date(alarm.timestamp).toLocaleString();
                            
                            return `
                                <div class="simple-alarm-row">
                                    <div class="alarm-number">${index + 1}.</div>
                                    <div class="alarm-info">
                                        <div class="alarm-id-time">ID: ${alarm.id} • ${timeAgo}</div>
                                        <div class="alarm-location">${alarm.building_name || alarm.building_type || 'Unknown Location'}</div>
                                        <div class="alarm-sensors">Temp: ${alarm.temp}°C • Smoke: ${alarm.smoke} ppm • Flame: ${alarm.flame_detected ? 'Yes' : 'No'}</div>
                                        <div class="alarm-contact">Contact: ${alarm.contact_person || alarm.user_fullname || 'N/A'}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        // Add pagination if there are more than 10 alarms
                        let paginationHTML = '';
                        if (data.data.length > 10) {
                            const totalPages = Math.ceil(data.data.length / 10);
                            paginationHTML = `
                                <div class="pagination-container">
                                    <div class="pagination-info">Showing 1-10 of ${data.data.length} alarms</div>
                                    <div class="pagination-controls">
                                        <button class="pagination-btn" onclick="changePage(1)" disabled>First</button>
                                        <button class="pagination-btn" onclick="changePage(1)" disabled>Previous</button>
                                        <span class="pagination-current">Page 1 of ${totalPages}</span>
                                        <button class="pagination-btn" onclick="changePage(2)" ${totalPages <= 1 ? 'disabled' : ''}>Next</button>
                                        <button class="pagination-btn" onclick="changePage(${totalPages})" ${totalPages <= 1 ? 'disabled' : ''}>Last</button>
                                    </div>
                                </div>
                            `;
                        }
                        
                        container.innerHTML = alarmsHTML + paginationHTML;
                        
                        // Add success indicator
                        showRefreshIndicator('success', 'Acknowledged alarms updated');
                    } else {
                        // No acknowledged alarms found
                        container.innerHTML = `
                            <div class="no-alarms">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                No acknowledged alarms found
                                <br><small class="text-muted">All alarms are currently active or resolved</small>
                                <br><small class="text-muted">Last update: ${new Date().toLocaleTimeString()}</small>
                            </div>
                        `;
                        
                        // Add success indicator
                        showRefreshIndicator('success', 'No acknowledged alarms found');
                    }
                } else {
                    // API returned an error
                    container.innerHTML = `
                        <div class="no-alarms text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Error loading acknowledged alarms'}
                        </div>
                    `;
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError); // Debug log
                console.error('Raw text that failed to parse:', text); // Debug log
                throw new Error('Invalid JSON response from server');
            }
        })
        .catch(error => {
            console.error('Error fetching acknowledged alarms:', error);
            container.innerHTML = `
                <div class="no-alarms text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load acknowledged alarms
                    <br><small class="text-muted">Error: ${error.message}</small>
                    <br><small class="text-muted">Check console for details</small>
                    <br><small class="text-muted">Last attempt: ${new Date().toLocaleTimeString()}</small>
                </div>
            `;
            
            // Add error indicator
            showRefreshIndicator('error', 'Failed to load acknowledged alarms');
        });
}

// Quick Action Functions
function toggleHeatmap() {
    if (heatmapEnabled) {
        if (heatLayer && map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
        }
        heatmapEnabled = false;
        showNotification('Heatmap disabled', 'info');
    } else {
        if (heatLayer) {
            map.addLayer(heatLayer);
        }
        heatmapEnabled = true;
        showNotification('Heatmap enabled', 'success');
    }
}

function toggleClustering() {
    if (clusteringEnabled) {
        if (fireMarkers && fireMarkers.getLayers().length > 0) {
            fireMarkers.clearLayers();
            addFireMarkersToMap();
        }
        clusteringEnabled = false;
        showNotification('Clustering disabled', 'info');
    } else {
        if (fireMarkers && fireMarkers.getLayers().length > 0) {
            fireMarkers.clearLayers();
            addFireMarkersToMap();
        }
        clusteringEnabled = true;
        showNotification('Clustering enabled', 'success');
    }
}

function centerOnFireStation() {
    if (map) {
        map.setView([config.fireStation.lat, config.fireStation.lng], 15);
        showNotification('Centered on Fire Station', 'info');
    }
}

function showNotification(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast-notification toast-${type}`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Modern Card Functions
function initializeModernCards() {
    // Emergency Response Card
    const emergencyBtn = document.querySelector('.modern-card:nth-child(1) .card-action-btn');
    if (emergencyBtn) {
        emergencyBtn.addEventListener('click', function() {
            showNotification('Opening Emergency Response Protocols...', 'info');
            // Add your emergency response logic here
            setTimeout(() => {
                showNotification('Emergency Response Dashboard Loaded', 'success');
            }, 1000);
        });
    }
    
    // Analytics Card
    const analyticsBtn = document.querySelector('.modern-card:nth-child(2) .card-action-btn');
    if (analyticsBtn) {
        analyticsBtn.addEventListener('click', function() {
            showNotification('Loading Analytics Dashboard...', 'info');
            // Add your analytics logic here
            setTimeout(() => {
                showNotification('Analytics Dashboard Ready', 'success');
            }, 1000);
        });
    }
    
    // Settings Card
    const settingsBtn = document.querySelector('.modern-card:nth-child(3) .card-action-btn');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', function() {
            showNotification('Opening Settings Panel...', 'info');
            // Add your settings logic here
            setTimeout(() => {
                showNotification('Settings Panel Loaded', 'success');
            }, 1000);
        });
    }
}

// Initialize modern cards when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeModernCards();
});

// Refresh Indicator Functions
function showRefreshIndicator(type, message) {
    // Remove existing indicator
    const existingIndicator = document.querySelector('.refresh-indicator');
    if (existingIndicator) {
        existingIndicator.remove();
    }
    
    // Create new indicator
    const indicator = document.createElement('div');
    indicator.className = `refresh-indicator ${type}`;
    
    let icon = '';
    switch(type) {
        case 'loading':
            icon = '<i class="bi bi-arrow-clockwise spin"></i>';
            break;
        case 'success':
            icon = '<i class="bi bi-check-circle"></i>';
            break;
        case 'error':
            icon = '<i class="bi bi-exclamation-triangle"></i>';
            break;
    }
    
    indicator.innerHTML = `${icon}${message}`;
    document.body.appendChild(indicator);
    
    // Update refresh status indicator
    updateRefreshStatus(type);
    
    // Auto-remove after 3 seconds (except for loading)
    if (type !== 'loading') {
        setTimeout(() => {
            if (indicator.parentNode) {
                indicator.parentNode.removeChild(indicator);
            }
        }, 3000);
    }
}

function updateRefreshStatus(type) {
    const statusEl = document.getElementById('refresh-status');
    if (statusEl) {
        statusEl.className = `auto-refresh-status ${type}`;
    }
    
    // Update refresh button icon
    const refreshIcon = document.getElementById('refresh-icon');
    if (refreshIcon) {
        if (type === 'loading') {
            refreshIcon.className = 'bi bi-arrow-clockwise spin';
        } else {
            refreshIcon.className = 'bi bi-arrow-clockwise';
        }
    }
}

// Enhanced auto-refresh with error handling and retry logic
let refreshIntervals = {
    acknowledgedAlarms: null,
    fireData: null,
    systemInfo: null
};

function startAutoRefresh() {
    // Clear existing intervals
    stopAutoRefresh();
    
    // Acknowledged alarms - every 15 seconds
    refreshIntervals.acknowledgedAlarms = setInterval(() => {
        updateAcknowledgedAlarmsWithPagination();
    }, 15000);
    
    // Fire data - every 30 seconds
    refreshIntervals.fireData = setInterval(() => {
        fetchFireData();
    }, 30000);
    
    // System info - every 10 seconds
    refreshIntervals.systemInfo = setInterval(() => {
        updateSystemInfo();
    }, 10000);
    
    console.log('Auto-refresh started');
    updateRefreshStatus('success');
}

function stopAutoRefresh() {
    Object.values(refreshIntervals).forEach(interval => {
        if (interval) {
            clearInterval(interval);
        }
    });
    refreshIntervals = {
        acknowledgedAlarms: null,
        fireData: null,
        systemInfo: null
    };
    console.log('Auto-refresh stopped');
    updateRefreshStatus('error');
}

// Start auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    startAutoRefresh();
});

// Pause auto-refresh when page is hidden (browser tab not active)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAutoRefresh();
    } else {
        startAutoRefresh();
    }
});

// Pagination functionality
let currentPage = 1;
let totalAlarms = 0;
let alarmsPerPage = 10;

function changePage(page) {
    if (page < 1 || page > Math.ceil(totalAlarms / alarmsPerPage)) return;
    
    currentPage = page;
    updateAcknowledgedAlarms();
}

function updateAcknowledgedAlarmsWithPagination() {
    const container = document.getElementById('acknowledged-alarms');
    if (!container) {
        console.warn('Acknowledged alarms container not found. Skipping update.');
        return;
    }
    
    // Show loading indicator with timestamp
    const now = new Date();
    const timeString = now.toLocaleTimeString();
    container.innerHTML = `
        <div class="no-alarms">
            <i class="bi bi-arrow-clockwise spin me-2"></i>
            Loading acknowledged alarms...
            <br><small class="text-muted">Last update: ${timeString}</small>
        </div>
    `;
    
    console.log('Starting to fetch acknowledged alarms...'); // Debug log
    
    fetch('get_acknowledged_alarms.php')
        .then(response => {
            console.log('Response status:', response.status); // Debug log
            console.log('Response headers:', response.headers); // Debug log
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text(); // Get response as text first
        })
        .then(text => {
            console.log('Raw response text:', text); // Debug log
            
            try {
                const data = JSON.parse(text);
                console.log('Parsed JSON data:', data); // Debug log
                
                if (data.success) {
                    if (data.data && data.data.length > 0) {
                        console.log('Found', data.data.length, 'acknowledged alarms'); // Debug log
                        
                        totalAlarms = data.data.length;
                        const totalPages = Math.ceil(totalAlarms / alarmsPerPage);
                        const startIndex = (currentPage - 1) * alarmsPerPage;
                        const endIndex = Math.min(startIndex + alarmsPerPage, totalAlarms);
                        const pageAlarms = data.data.slice(startIndex, endIndex);
                        
                        const alarmsHTML = pageAlarms.map((alarm, index) => {
                            const timeAgo = getTimeAgo(new Date(alarm.timestamp));
                            const globalIndex = startIndex + index + 1;
                            
                            return `
                                <div class="simple-alarm-row">
                                    <div class="alarm-number">${globalIndex}.</div>
                                    <div class="alarm-info">
                                        <div class="alarm-id-time">ID: ${alarm.id} • ${timeAgo}</div>
                                        <div class="alarm-location">${alarm.building_name || alarm.building_type || 'Unknown Location'}</div>
                                        <div class="alarm-sensors">Temp: ${alarm.temp}°C • Smoke: ${alarm.smoke} ppm • Flame: ${alarm.flame_detected ? 'Yes' : 'No'}</div>
                                        <div class="alarm-contact">Contact: ${alarm.contact_person || alarm.user_fullname || 'N/A'}</div>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        // Add pagination
                        let paginationHTML = '';
                        if (totalAlarms > alarmsPerPage) {
                            paginationHTML = `
                                <div class="pagination-container">
                                    <div class="pagination-info">Showing ${startIndex + 1}-${endIndex} of ${totalAlarms} alarms</div>
                                    <div class="pagination-controls">
                                        <button class="pagination-btn" onclick="changePage(1)" ${currentPage === 1 ? 'disabled' : ''}>First</button>
                                        <button class="pagination-btn" onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                                        <span class="pagination-current">Page ${currentPage} of ${totalPages}</span>
                                        <button class="pagination-btn" onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next</button>
                                        <button class="pagination-btn" onclick="changePage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}>Last</button>
                                    </div>
                                </div>
                            `;
                        }
                        
                        container.innerHTML = alarmsHTML + paginationHTML;
                        
                        // Add success indicator
                        showRefreshIndicator('success', 'Acknowledged alarms updated');
                    } else {
                        // No acknowledged alarms found
                        container.innerHTML = `
                            <div class="no-alarms">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                No acknowledged alarms found
                                <br><small class="text-muted">All alarms are currently active or resolved</small>
                                <br><small class="text-muted">Last update: ${new Date().toLocaleTimeString()}</small>
                            </div>
                        `;
                        
                        // Add success indicator
                        showRefreshIndicator('success', 'No acknowledged alarms found');
                    }
                } else {
                    // API returned an error
                    container.innerHTML = `
                        <div class="no-alarms text-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${data.message || 'Error loading acknowledged alarms'}
                        </div>
                    `;
                }
            } catch (parseError) {
                console.error('JSON parse error:', parseError); // Debug log
                console.error('Raw text that failed to parse:', text); // Debug log
                throw new Error('Invalid JSON response from server');
            }
        })
        .catch(error => {
            console.error('Error fetching acknowledged alarms:', error);
            container.innerHTML = `
                <div class="no-alarms text-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load acknowledged alarms
                    <br><small class="text-muted">Error: ${error.message}</small>
                    <br><small class="text-muted">Check console for details</small>
                    <br><small class="text-muted">Last attempt: ${new Date().toLocaleTimeString()}</small>
                </div>
            `;
            
            // Add error indicator
            showRefreshIndicator('error', 'Failed to load acknowledged alarms');
        });
}

// Stop text-to-speech when page is being unloaded (logout, navigation, etc.)
window.addEventListener('beforeunload', function() {
    stopAllSpeech();
});

// Also stop TTS when page becomes hidden (tab switching, minimizing, etc.)
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        stopAllSpeech();
    }
});
</script>

<!-- Bootstrap Modal for Building Details -->
<div class="modal fade" id="buildingModal" tabindex="-1" aria-labelledby="buildingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="buildingModalLabel">Building Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="building-info">
                    <div class="row mb-2">
                        <div class="col-4"><strong>Name:</strong></div>
                        <div class="col-8" id="modal-building-name">-</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Type:</strong></div>
                        <div class="col-8" id="modal-building-type">-</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Address:</strong></div>
                        <div class="col-8" id="modal-building-address">-</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Contact:</strong></div>
                        <div class="col-8" id="modal-building-contact">-</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Floors:</strong></div>
                        <div class="col-8" id="modal-building-floors">-</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Area:</strong></div>
                        <div class="col-8" id="modal-building-area">-</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Constructed:</strong></div>
                        <div class="col-8" id="modal-building-year">-</div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <h6 class="mb-2"><strong>Safety Features:</strong></h6>
                    <div class="safety-features">
                        <div class="row mb-1">
                            <div class="col-8">Sprinkler System</div>
                            <div class="col-4 text-end" id="modal-sprinkler">-</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Fire Alarm</div>
                            <div class="col-4 text-end" id="modal-fire-alarm">-</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Fire Extinguishers</div>
                            <div class="col-4 text-end" id="modal-extinguishers">-</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Emergency Exits</div>
                            <div class="col-4 text-end" id="modal-emergency-exits">-</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Emergency Lighting</div>
                            <div class="col-4 text-end" id="modal-emergency-lighting">-</div>
                        </div>
                        <div class="row mb-1">
                            <div class="col-8">Fire Escape</div>
                            <div class="col-4 text-end" id="modal-fire-escape">-</div>
                        </div>
                    </div>
                    
                    <hr class="my-3">
                    
                    <div class="row mb-2">
                        <div class="col-4"><strong>Last Inspected:</strong></div>
                        <div class="col-8" id="modal-last-inspected">-</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include('../../components/scripts.php')?>
</body>
</html>
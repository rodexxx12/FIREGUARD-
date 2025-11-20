<?php
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../../index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIREGUARD</title>
    <link rel="icon" type="image/png" sizes="32x32" href="fireguard.png?v=1">
    <link rel="icon" type="image/png" sizes="16x16" href="fireguard.png?v=1">
    <link rel="shortcut icon" type="image/png" href="fireguard.png?v=1">
    <link rel="apple-touch-icon" sizes="180x180" href="fireguard.png?v=1">
    <link rel="apple-touch-icon" href="fireguard.png?v=1">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --success-color: #059669;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --secondary-color: #6b7280;
            --light-bg: #f8fafc;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #6b7280;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            background-color: var(--light-bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        #map {
            height: 120vh;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px 0;
            margin-bottom: 24px;
            border-radius: 0 0 12px 12px;
        }

        .sidebar {
            background-color: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow-md);
            height: 70vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }

        .fence-item {
            border-left: 4px solid var(--primary-color);
            padding: 16px 20px;
            margin-bottom: 12px;
            background-color: #fff;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
        }

        .fence-item:hover {
            background-color: var(--light-bg);
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }

        .btn-action {
            margin-right: 8px;
            margin-bottom: 8px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: none;
            padding: 10px 16px;
        }

        .btn-action:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
        }

        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }

        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }

        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }

        .drawing-controls {
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .drawing-controls h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.1rem;
        }

        .stats-box {
            background-color: #fff;
            padding: 24px;
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-bottom: 24px;
            border: 1px solid var(--border-color);
        }

        .stats-number {
            font-size: 28px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 4px;
        }

        .stats-box small {
            color: #000;
            font-weight: 500;
            font-size: 0.875rem;
        }

        .mode-indicator {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            background-color: var(--warning-color);
            color: #fff;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 0.875rem;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-label {
            font-weight: 500;
            color: var(--text-primary);
        }

        .card {
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            background-color: #ffffff;
        }

        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            padding: 32px 32px 24px 32px;
            border-radius: 12px 12px 0 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .card-body {
            background-color: #ffffff;
            padding: 0;
        }

        .card-title {
            color: #1f2937;
            font-weight: 700;
            margin: 0;
            font-size: 1.25rem;
            letter-spacing: -0.025em;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            background-color: #ffffff;
            border-bottom: 2px solid var(--border-color);
            font-weight: 600;
            color: var(--text-primary);
            padding: 20px 16px;
            font-size: 0.875rem;
            border-top: none;
            border-left: none;
            border-right: none;
        }

        .table td {
            padding: 20px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            background-color: #ffffff;
            border-left: none;
            border-right: none;
        }

        .table-hover tbody tr:hover {
            background-color: #f8fafc;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table tbody tr {
            transition: all 0.2s ease;
        }

        .badge {
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 24px;
            font-size: 0.8rem;
            letter-spacing: 0.025em;
        }

        .badge.bg-primary {
            background-color: #3b82f6 !important;
            color: #ffffff;
        }

        .btn-sm {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .footer {
            text-align: center;
            padding: 20px 0;
            margin-top: 32px;
            color: var(--text-secondary);
            font-size: 0.875rem;
            background-color: #fff;
            border-top: 1px solid var(--border-color);
        }

        .d-grid .btn-action {
            margin-right: 0;
            margin-bottom: 12px;
        }

        .d-grid .btn-action:last-child {
            margin-bottom: 0;
        }

        /* Filter Controls Styling */
        .input-group-text {
            background-color: #f8fafc;
            border-color: #e5e7eb;
            color: #6b7280;
            border-right: none;
            font-weight: 500;
        }

        .form-select {
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 12px 16px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background-color: #ffffff;
            font-weight: 500;
        }

        .form-select:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }

        #searchInput {
            border-left: none;
            border-color: #e5e7eb;
            font-weight: 500;
        }

        #searchInput:focus {
            border-left: none;
            box-shadow: none;
            border-color: #3b82f6;
            outline: none;
        }

        .input-group:focus-within .input-group-text {
            border-color: #3b82f6;
            background-color: #ffffff;
        }

        .input-group:focus-within #searchInput {
            border-color: #3b82f6;
        }

        .input-group .form-control {
            border-left: none;
        }

        .input-group:focus-within {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            border-radius: 8px;
        }

        #clearFilters {
            border-color: #e5e7eb;
            color: #6b7280;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        #clearFilters:hover {
            background-color: #f3f4f6;
            border-color: #d1d5db;
            color: #374151;
        }

        /* Edit mode styling */
        .edit-mode .fence-item {
            border-left-color: var(--warning-color);
            background-color: #fef3c7;
        }
        
        .edit-mode .btn-action {
            box-shadow: 0 0 0 2px rgba(217, 119, 6, 0.2);
        }
        
        .fence-row {
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .fence-row:hover {
            background-color: #f8fafc;
        }
        
        .fence-row.selected {
            background-color: #dbeafe;
            border-left: 4px solid var(--primary-color);
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                height: auto;
                margin-bottom: 20px;
            }
            
            #map {
                height: 50vh;
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
        <div class="row mb-4">
            <div class="col-lg-8">
                <div id="map"></div>
            </div>
            
            <div class="col-lg-4"> 
                <div class="stats-box">
                    <h6 class="text-muted mb-3 d-flex justify-content-between align-items-center">
                        <span>Overview</span>
                        <span class="mode-indicator" id="modeIndicator">View Mode</span>
                    </h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="stats-number" id="totalFences">0</div>
                            <small>Total Fences</small>
                        </div>
                        <div class="col-4">
                            <div class="stats-number" id="drawnFences">0</div>
                            <small>Drawn</small>
                        </div>
                    </div>
                </div>
                
                <div class="drawing-controls">
                    <h5><i class="fas fa-pencil-alt me-2"></i>Drawing Tools</h5>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-action" id="btnDraw">
                            <i class="fas fa-draw-polygon me-2"></i> Draw New Fence
                        </button>
                        <button class="btn btn-success btn-action" id="btnSave" disabled>
                            <i class="fas fa-save me-2"></i> Save Fence
                        </button>
                        <button class="btn btn-warning btn-action" id="btnEdit" disabled>
                            <i class="fas fa-edit me-2"></i> Edit Selected Fence
                        </button>
                        <button class="btn btn-info btn-action" id="btnRedraw" disabled>
                            <i class="fas fa-redo me-2"></i> Redraw Polygon
                        </button>
                        <!-- <button class="btn btn-danger btn-action" id="btnDelete" disabled>
                            <i class="fas fa-trash me-2"></i> Delete Selected Fence
                        </button> -->
                        <button class="btn btn-secondary btn-action" id="btnCancel" disabled>
                            <i class="fas fa-times me-2"></i> Cancel
                        </button>
                    </div>
                    
                    <div class="mt-4" id="fenceForm">
                        <h6 class="text-muted mb-3">Fence Details</h6>
                        <div class="mb-3">
                            <label for="cityName" class="form-label">City Name</label>
                            <input type="text" class="form-control" id="cityName" placeholder="Enter city name">
                        </div>
                        <div class="mb-3">
                            <label for="countryCode" class="form-label">Country Code</label>
                            <input type="text" class="form-control" id="countryCode" maxlength="2" placeholder="US, GB, etc.">
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isActive" checked>
                            <label class="form-check-label" for="isActive">
                                Active Fence
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Saved Geo-Fences</h5>
                            <span class="badge bg-primary" id="fenceCount">0 Fences</span>
                        </div>
                        
                        <!-- Filter Controls -->
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" id="searchInput" placeholder="Search by city name...">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="countryFilter">
                                    <option value="">All Countries</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="dayFilter">
                                    <option value="">All Days</option>
                                    <option value="today">Today</option>
                                    <option value="yesterday">Yesterday</option>
                                    <option value="this_week">This Week</option>
                                    <option value="last_week">Last Week</option>
                                    <option value="this_month">This Month</option>
                                    <option value="last_month">Last Month</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-secondary w-100" id="clearFilters">
                                    <i class="fas fa-times me-1"></i>Clear
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="fencesTable">
                                <thead>
                                    <tr>
                                        <th class="border-0">ID</th>
                                        <th class="border-0">City Name</th>
                                        <th class="border-0">Country Code</th>
                                        <th class="border-0">Status</th>
                                        <th class="border-0">Created By</th>
                                        <th class="border-0">Created At</th>
                                        <th class="border-0 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Fences will be loaded here via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Map initialization
        // Initialize map centered on Bago City, Negros Occidental, Philippines
        var map = L.map('map').setView([10.5377, 122.8384], 13);
        
        // Base layers
        var osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        });
        
        var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; <a href="https://www.esri.com/">Esri</a> &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });
        
        // Hybrid imagery (imagery with labels, if available)
        var hybridLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '&copy; <a href="https://www.esri.com/">Esri</a> &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
        });
        
        var topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://opentopomap.org/">OpenTopoMap</a> contributors'
        });
        
        // Add default base layer
        osmLayer.addTo(map);
        
        // Variables for drawing
        var drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);
        
        // Layer controls
        var baseLayers = {
            "OpenStreetMap": osmLayer,
            "Satellite": satelliteLayer,
            "Hybrid": hybridLayer,
            "Topo": topoLayer
        };
        var overlays = {
            "Drawn Fences": drawnItems
        };
        L.control.layers(baseLayers, overlays).addTo(map);
        
        var currentPolygon = null;
        var drawingMode = false;
        var currentEditingId = null;
        var selectedFenceId = null; // Track selected fence for editing
        var allFences = []; // Store all fences for filtering
        
        // Update stats
        function updateStats() {
            var totalFences = $('#fencesTable tbody tr').length;
            var activeFences = $('#fencesTable tbody tr:has(.badge-success)').length;
            var drawnFences = drawnItems.getLayers().length;
            
            $('#totalFences').text(totalFences);
            $('#activeFences').text(activeFences);
            $('#drawnFences').text(drawnFences);
            $('#fenceCount').text(totalFences + ' Fence' + (totalFences !== 1 ? 's' : ''));
        }

        // Filter functions
        function filterFences() {
            var searchTerm = $('#searchInput').val().toLowerCase();
            var countryFilter = $('#countryFilter').val();
            var dayFilter = $('#dayFilter').val();
            
            var filteredFences = allFences.filter(function(fence) {
                // Search filter
                var matchesSearch = !searchTerm || fence.city_name.toLowerCase().includes(searchTerm);
                
                // Country filter
                var matchesCountry = !countryFilter || fence.country_code === countryFilter;
                
                // Day filter
                var matchesDay = true;
                if (dayFilter) {
                    var fenceDate = new Date(fence.created_at);
                    var now = new Date();
                    var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    
                    switch(dayFilter) {
                        case 'today':
                            matchesDay = fenceDate >= today;
                            break;
                        case 'yesterday':
                            var yesterday = new Date(today);
                            yesterday.setDate(yesterday.getDate() - 1);
                            matchesDay = fenceDate >= yesterday && fenceDate < today;
                            break;
                        case 'this_week':
                            var weekStart = new Date(today);
                            weekStart.setDate(weekStart.getDate() - weekStart.getDay());
                            matchesDay = fenceDate >= weekStart;
                            break;
                        case 'last_week':
                            var lastWeekEnd = new Date(today);
                            lastWeekEnd.setDate(lastWeekEnd.getDate() - today.getDay());
                            var lastWeekStart = new Date(lastWeekEnd);
                            lastWeekStart.setDate(lastWeekStart.getDate() - 7);
                            matchesDay = fenceDate >= lastWeekStart && fenceDate < lastWeekEnd;
                            break;
                        case 'this_month':
                            var monthStart = new Date(now.getFullYear(), now.getMonth(), 1);
                            matchesDay = fenceDate >= monthStart;
                            break;
                        case 'last_month':
                            var lastMonthStart = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                            var lastMonthEnd = new Date(now.getFullYear(), now.getMonth(), 1);
                            matchesDay = fenceDate >= lastMonthStart && fenceDate < lastMonthEnd;
                            break;
                    }
                }
                
                return matchesSearch && matchesCountry && matchesDay;
            });
            
            displayFences(filteredFences);
        }

        function displayFences(fences) {
            $('#fencesTable tbody').empty();
            drawnItems.clearLayers();
            
            $.each(fences, function(index, fence) {
                var row = `<tr class="fence-row" data-id="${fence.id}">
                    <td>${fence.id}</td>
                    <td>${fence.city_name}</td>
                    <td>${fence.country_code}</td>
                    <td><span class="badge ${fence.is_active ? 'bg-success' : 'bg-secondary'}">${fence.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>User ${fence.created_by}</td>
                    <td>${fence.created_at}</td>
                    <td class="d-flex gap-1">
                        <button class="btn btn-sm btn-info view-fence" data-id="${fence.id}" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn btn-sm btn-warning edit-fence" data-id="${fence.id}" title="Edit"><i class="fas fa-edit"></i></button>
                        <!-- <button class="btn btn-sm ${fence.is_active ? 'btn-secondary' : 'btn-success'} toggle-active-fence" data-id="${fence.id}" data-active="${fence.is_active ? 1 : 0}" title="${fence.is_active ? 'Set Inactive' : 'Set Active'}">
                            <i class="fas ${fence.is_active ? 'fa-ban' : 'fa-check'}"></i>
                        </button> -->
                        <button class="btn btn-sm btn-danger delete-fence" data-id="${fence.id}" title="Delete"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>`;
                $('#fencesTable tbody').append(row);
                
                // Add to map
                if (fence.polygon) {
                    var polygon = L.polygon(fence.polygon, {
                        color: fence.is_active ? '#2563eb' : '#6b7280',
                        weight: 2,
                        fillOpacity: 0.3
                    }).addTo(drawnItems);
                    polygon.bindPopup(`<b>${fence.city_name}</b> (${fence.country_code})<br>Status: ${fence.is_active ? 'Active' : 'Inactive'}`);
                }
            });
            
            updateStats();
        }

        function populateCountryFilter() {
            var countries = [...new Set(allFences.map(fence => fence.country_code))].sort();
            var countrySelect = $('#countryFilter');
            countrySelect.find('option:not(:first)').remove();
            
            countries.forEach(function(country) {
                countrySelect.append(`<option value="${country}">${country}</option>`);
            });
        }
        
        // Debounce helper
        function debounce(fn, delay) {
            var timer = null;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timer);
                timer = setTimeout(function() { fn.apply(context, args); }, delay);
            };
        }
        
        // Auto-fill country code from city name using Nominatim
        var lookupCountryByCity = debounce(function() {
            var city = ($('#cityName').val() || '').trim();
            if (!city) return;
            
            fetch('https://nominatim.openstreetmap.org/search?format=json&addressdetails=1&limit=1&city=' + encodeURIComponent(city), {
                headers: { 'Accept': 'application/json' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (Array.isArray(data) && data.length > 0 && data[0].address && data[0].address.country_code) {
                    var cc = (data[0].address.country_code || '').toUpperCase();
                    if (cc && cc.length === 2) {
                        $('#countryCode').val(cc);
                    }
                }
            })
            .catch(function() {
                // Silent fail; user can still enter manually
            });
        }, 600);
        
        // Load fences from database
        function loadFences() {
            $.ajax({
                url: 'geo_fences_api.php',
                type: 'GET',
                data: {action: 'getFences'},
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        allFences = response.data;
                        populateCountryFilter();
                        filterFences(); // Apply current filters
                    } else {
                        Swal.fire('Error', 'Failed to load fences: ' + response.message, 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Could not connect to server. Please try again.', 'error');
                }
            });
        }
        
        // Initialize the map and load fences
        $(document).ready(function() {
            loadFences();
            
            // Auto-select country code when city changes
            $('#cityName').on('input', lookupCountryByCity);
            
            // Real-time filtering event listeners
            $('#searchInput').on('input', debounce(filterFences, 300));
            $('#countryFilter').on('change', filterFences);
            $('#dayFilter').on('change', filterFences);
            
            // Clear filters button
            $('#clearFilters').on('click', function() {
                $('#searchInput').val('');
                $('#countryFilter').val('');
                $('#dayFilter').val('');
                filterFences();
            });
            
            // Row selection functionality
            $(document).on('click', '.fence-row', function(e) {
                // Don't select if clicking on action buttons
                if ($(e.target).closest('button').length > 0) {
                    return;
                }
                
                // Remove previous selection
                $('.fence-row').removeClass('selected');
                
                // Add selection to clicked row
                $(this).addClass('selected');
                
                // Get fence ID and enable edit and delete buttons
                selectedFenceId = $(this).data('id');
                $('#btnEdit').prop('disabled', false);
                $('#btnDelete').prop('disabled', false);
                
                // Update button text
                $('#btnEdit').html('<i class="fas fa-edit me-2"></i> Edit Selected Fence');
                $('#btnDelete').html('<i class="fas fa-trash me-2"></i> Delete Selected Fence');
            });
            
            // Helper to extract polygonData from currentPolygon
            function getPolygonDataFromLayer(layer) {
                var latlngs = (layer && layer.getLatLngs()[0]) ? layer.getLatLngs()[0] : [];
                return latlngs.map(function(latlng) { return [latlng.lat, latlng.lng]; });
            }

            // Draw button click
            $('#btnDraw').click(function() {
                if (drawingMode) return;
                
                // Clear any selection
                $('.fence-row').removeClass('selected');
                selectedFenceId = null;
                $('#btnEdit').prop('disabled', true);
                $('#btnDelete').prop('disabled', true);
                $('#btnEdit').html('<i class="fas fa-edit me-2"></i> Edit Selected Fence');
                $('#btnDelete').html('<i class="fas fa-trash me-2"></i> Delete Selected Fence');
                
                drawingMode = true;
                $('#modeIndicator').text('Drawing Mode').css('background-color', 'var(--primary-color)');
                $('#btnSave').prop('disabled', false);
                $('#btnCancel').prop('disabled', false);
                $('#btnDraw').prop('disabled', true);
                $('#btnRedraw').prop('disabled', true);
                
                // Initialize drawing tool
                map.on('click', function(e) {
                    if (!currentPolygon) {
                        currentPolygon = L.polygon([e.latlng], {
                            color: '#2563eb',
                            weight: 2,
                            fillOpacity: 0.3
                        }).addTo(map);
                    } else {
                        currentPolygon.addLatLng(e.latlng);
                    }
                });
            });
            
            // Edit button click (from Drawing Tools)
            $('#btnEdit').click(function() {
                if (!selectedFenceId) {
                    Swal.fire('Error', 'Please select a fence from the table first', 'error');
                    return;
                }
                
                // Trigger the edit functionality using the selected fence ID
                $.ajax({
                    url: 'geo_fences_api.php',
                    type: 'GET',
                    data: { action: 'getFence', id: selectedFenceId },
                    dataType: 'json',
                    success: function(resp) {
                        if (!resp.success) {
                            Swal.fire('Error', resp.message || 'Failed to load fence', 'error');
                            return;
                        }
                        var f = resp.data;
                        
                        // Reset current drawing
                        if (currentPolygon) { 
                            map.removeLayer(currentPolygon); 
                            currentPolygon = null; 
                        }
                        map.off('click');
                        
                        // Set edit mode
                        drawingMode = true;
                        currentEditingId = f.id;
                        $('body').addClass('edit-mode');
                        $('#modeIndicator').text('Edit Mode').css('background-color', 'var(--warning-color)');
                        $('#btnSave').prop('disabled', false);
                        $('#btnCancel').prop('disabled', false);
                        $('#btnDraw').prop('disabled', true);
                        $('#btnRedraw').prop('disabled', false);
                        
                        // Populate form
                        $('#cityName').val(f.city_name);
                        $('#countryCode').val((f.country_code || '').toUpperCase());
                        $('#isActive').prop('checked', !!parseInt(f.is_active));
                        
                        // Draw polygon with editing capabilities
                        if (Array.isArray(f.polygon) && f.polygon.length >= 3) {
                            currentPolygon = L.polygon(f.polygon, {
                                color: '#d97706',
                                weight: 2,
                                fillOpacity: 0.3
                            }).addTo(map);
                            
                            // Fit map to polygon bounds
                            map.fitBounds(currentPolygon.getBounds());
                            
                            // Enable click to add new points
                            map.on('click', function(e) {
                                if (currentPolygon && drawingMode) {
                                    currentPolygon.addLatLng(e.latlng);
                                }
                            });
                        }
                        
                        // Show success message with instructions
                        Swal.fire({
                            title: 'Edit Mode',
                            text: 'You can now modify the fence. Click on the map to add new points to the polygon, or click Save to update the fence.',
                            icon: 'info',
                            timer: 4000,
                            showConfirmButton: false
                        });
                    },
                    error: function() {
                        Swal.fire('Error', 'Could not connect to server. Please try again.', 'error');
                    }
                });
            });
            
            // Save button click
            $('#btnSave').click(function() {
                if (!currentPolygon) {
                    Swal.fire('Error', 'Please draw a polygon first', 'error');
                    return;
                }
                
                var cityName = $('#cityName').val();
                var countryCode = $('#countryCode').val();
                var isActive = $('#isActive').is(':checked') ? 1 : 0;
                
                // Normalize country code
                countryCode = (countryCode || '').trim().toUpperCase().slice(0, 2);
                
                if (!cityName || !countryCode || countryCode.length !== 2) {
                    Swal.fire('Error', 'Please enter city name and a 2-letter country code', 'error');
                    return;
                }
                
                var latlngs = currentPolygon.getLatLngs()[0] || [];
                if (latlngs.length < 3) {
                    Swal.fire('Error', 'Polygon requires at least 3 points', 'error');
                    return;
                }
                
                var polygonData = getPolygonDataFromLayer(currentPolygon);
                
                // Ensure polygon ring is closed (append first point if needed)
                var first = polygonData[0];
                var last = polygonData[polygonData.length - 1];
                if (first[0] !== last[0] || first[1] !== last[1]) {
                    polygonData.push(first);
                }
                
                var action = currentEditingId ? 'updateFence' : 'saveFence';
                var payload = {
                    action: action,
                    city_name: cityName,
                    country_code: countryCode,
                    is_active: isActive,
                    polygon: JSON.stringify(polygonData)
                };
                if (currentEditingId) { payload.id = currentEditingId; }
                
                // Send data to server
                $.ajax({
                    url: 'geo_fences_api.php',
                    type: 'POST',
                    data: payload,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire('Success', currentEditingId ? 'Geo-fence updated successfully' : 'Geo-fence saved successfully', 'success');
                            resetDrawing();
                            loadFences();
                            currentEditingId = null;
                            $('#btnDraw').prop('disabled', false);
                            $('#btnRedraw').prop('disabled', true);
                            $('#modeIndicator').text('View Mode').css('background-color', 'var(--warning-color)');
                        } else {
                            Swal.fire('Error', 'Failed to save fence: ' + response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Could not connect to server. Please try again.', 'error');
                    }
                });
            });
            
            // Delete button click (from Drawing Tools)
            $('#btnDelete').click(function() {
                if (!selectedFenceId) {
                    Swal.fire('Error', 'Please select a fence from the table first', 'error');
                    return;
                }
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'geo_fences_api.php',
                            type: 'POST',
                            data: {
                                action: 'deleteFence',
                                id: selectedFenceId
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Deleted!', 'The geo-fence has been deleted.', 'success');
                                    
                                    // Clear selection and reset buttons
                                    $('.fence-row').removeClass('selected');
                                    selectedFenceId = null;
                                    $('#btnEdit').prop('disabled', true);
                                    $('#btnDelete').prop('disabled', true);
                                    $('#btnEdit').html('<i class="fas fa-edit me-2"></i> Edit Selected Fence');
                                    $('#btnDelete').html('<i class="fas fa-trash me-2"></i> Delete Selected Fence');
                                    
                                    loadFences();
                                } else {
                                    Swal.fire('Error', 'Failed to delete fence: ' + response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Could not connect to server. Please try again.', 'error');
                            }
                        });
                    }
                });
            });
            
            // Cancel button click
            $('#btnCancel').click(function() {
                resetDrawing();
            });
            
            // Redraw button click
            $('#btnRedraw').click(function() {
                if (!currentEditingId) {
                    Swal.fire('Error', 'No fence selected for editing', 'error');
                    return;
                }
                
                // Clear current polygon
                if (currentPolygon) {
                    map.removeLayer(currentPolygon);
                    currentPolygon = null;
                }
                
                // Start new drawing
                $('#modeIndicator').text('Redraw Mode').css('background-color', 'var(--primary-color)');
                
                // Enable click to draw new polygon
                map.on('click', function(e) {
                    if (!currentPolygon) {
                        currentPolygon = L.polygon([e.latlng], {
                            color: '#2563eb',
                            weight: 2,
                            fillOpacity: 0.3
                        }).addTo(map);
                    } else {
                        currentPolygon.addLatLng(e.latlng);
                    }
                });
                
                Swal.fire({
                    title: 'Redraw Mode',
                    text: 'Click on the map to draw a new polygon. Click Save when done.',
                    icon: 'info',
                    timer: 3000,
                    showConfirmButton: false
                });
            });
            
            // Reset drawing state
            function resetDrawing() {
                drawingMode = false;
                $('body').removeClass('edit-mode');
                $('#modeIndicator').text('View Mode').css('background-color', 'var(--warning-color)');
                $('#btnSave').prop('disabled', true);
                $('#btnCancel').prop('disabled', true);
                $('#btnDraw').prop('disabled', false);
                $('#btnRedraw').prop('disabled', true);
                
                // Clear selection
                $('.fence-row').removeClass('selected');
                selectedFenceId = null;
                $('#btnEdit').prop('disabled', true);
                $('#btnDelete').prop('disabled', true);
                $('#btnEdit').html('<i class="fas fa-edit me-2"></i> Edit Selected Fence');
                $('#btnDelete').html('<i class="fas fa-trash me-2"></i> Delete Selected Fence');
                
                if (currentPolygon) {
                    map.removeLayer(currentPolygon);
                    currentPolygon = null;
                }
                
                $('#cityName').val('');
                $('#countryCode').val('');
                $('#isActive').prop('checked', true);
                
                map.off('click');
                currentEditingId = null;
            }
            
            // Handle view fence click
            $(document).on('click', '.view-fence', function() {
                var fenceId = $(this).data('id');
                
                // Find the polygon and zoom to it
                drawnItems.eachLayer(function(layer) {
                    // In a real app, you would associate each layer with a fence ID
                    // For this example, we'll just zoom to the first polygon
                    map.fitBounds(layer.getBounds());
                });
            });
            
            // Handle delete fence click
            $(document).on('click', '.delete-fence', function() {
                var fenceId = $(this).data('id');
                
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'geo_fences_api.php',
                            type: 'POST',
                            data: {
                                action: 'deleteFence',
                                id: fenceId
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire('Deleted!', 'The geo-fence has been deleted.', 'success');
                                    loadFences();
                                } else {
                                    Swal.fire('Error', 'Failed to delete fence: ' + response.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'Could not connect to server. Please try again.', 'error');
                            }
                        });
                    }
                });
            });

            // Enter edit mode from table
            $(document).on('click', '.edit-fence', function() {
                var fenceId = $(this).data('id');
                $.ajax({
                    url: 'geo_fences_api.php',
                    type: 'GET',
                    data: { action: 'getFence', id: fenceId },
                    dataType: 'json',
                    success: function(resp) {
                        if (!resp.success) {
                            Swal.fire('Error', resp.message || 'Failed to load fence', 'error');
                            return;
                        }
                        var f = resp.data;
                        
                        // Reset current drawing
                        if (currentPolygon) { 
                            map.removeLayer(currentPolygon); 
                            currentPolygon = null; 
                        }
                        map.off('click');
                        
                        // Set edit mode
                        drawingMode = true;
                        currentEditingId = f.id;
                        $('body').addClass('edit-mode');
                        $('#modeIndicator').text('Edit Mode').css('background-color', 'var(--warning-color)');
                        $('#btnSave').prop('disabled', false);
                        $('#btnCancel').prop('disabled', false);
                        $('#btnDraw').prop('disabled', true);
                        $('#btnRedraw').prop('disabled', false);
                        
                        // Populate form
                        $('#cityName').val(f.city_name);
                        $('#countryCode').val((f.country_code || '').toUpperCase());
                        $('#isActive').prop('checked', !!parseInt(f.is_active));
                        
                        // Draw polygon with editing capabilities
                        if (Array.isArray(f.polygon) && f.polygon.length >= 3) {
                            currentPolygon = L.polygon(f.polygon, {
                                color: '#d97706',
                                weight: 2,
                                fillOpacity: 0.3
                            }).addTo(map);
                            
                            // Fit map to polygon bounds
                            map.fitBounds(currentPolygon.getBounds());
                            
                            // Enable click to add new points
                            map.on('click', function(e) {
                                if (currentPolygon && drawingMode) {
                                    currentPolygon.addLatLng(e.latlng);
                                }
                            });
                        }
                        
                        // Show success message with instructions
                        Swal.fire({
                            title: 'Edit Mode',
                            text: 'You can now modify the fence. Click on the map to add new points to the polygon, or click Save to update the fence.',
                            icon: 'info',
                            timer: 4000,
                            showConfirmButton: false
                        });
                    },
                    error: function() {
                        Swal.fire('Error', 'Could not connect to server. Please try again.', 'error');
                    }
                });
            });

            // Toggle active/inactive
            $(document).on('click', '.toggle-active-fence', function() {
                var fenceId = $(this).data('id');
                var isActive = $(this).data('active');
                var newVal = isActive ? 0 : 1;
                $.ajax({
                    url: 'geo_fences_api.php',
                    type: 'POST',
                    data: { action: 'setActive', id: fenceId, is_active: newVal },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            loadFences();
                        } else {
                            Swal.fire('Error', r.message || 'Failed to update status', 'error');
                        }
                    },
                    error: function() { Swal.fire('Error', 'Could not connect to server. Please try again.', 'error'); }
                });
            });
        });
    </script>
</body>
<?php include('../../components/scripts.php'); ?>
</html>
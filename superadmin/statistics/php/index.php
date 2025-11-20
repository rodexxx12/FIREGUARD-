<?php
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    header("Location: ../../../index.php");
    exit();
}
require_once '../../../db/db.php';

// Check if user is logged in (add your authentication logic here)
// if (!isset($_SESSION['user_id'])) {
//     header('Location: ../login/');
//     exit();
// }
?>

<?php include('../../components/header.php'); ?>
    <link rel="stylesheet" href="../css/custom.css">
    <style>        
        .fireguard-logo {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        .status-indicators {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #ffffff;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .status-item i {
            color: #ffffff;
            font-size: 1.1rem;
        }
        
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }
        
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 350px;
            font-size: 1rem;
            color: #6b7280;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 0;
            margin: 15px 0;
            border: 1px solid #fecaca;
        }
        
        .chart-filters {
            background: white;
            border-radius: 0;
            padding: 6px;
            margin-bottom: 6px;

            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
        }
        
        .chart-filters::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            pointer-events: none;
        }
        
        .chart-filters > * {
            position: relative;
            z-index: 1;
        }
        
        .filter-row {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        
        .filter-row:last-child {
            margin-bottom: 0;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        
        .filter-group label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #000000;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .filter-group select,
        .filter-group input {
            padding: 4px 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 0;
            font-size: 0.8rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            min-width: 100px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
            transform: translateY(-1px);
        }
        
        .filter-group select:hover,
        .filter-group input:hover {
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }
        
        .filter-actions {
            display: flex;
            gap: 4px;
            margin-left: auto;
        }
        
        .btn-filter {
            padding: 4px 8px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: #ffffff;
            border: none;
            border-radius: 0;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .btn-filter:hover {
            background: linear-gradient(135deg, #3d8bfe 0%, #00d4fe 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 172, 254, 0.4);
        }
        
        .btn-filter:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(79, 172, 254, 0.3);
        }
        
        .btn-reset {
            padding: 4px 8px;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            color: #ffffff;
            border: none;
            border-radius: 0;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }
        
        .btn-reset:hover {
            background: linear-gradient(135deg, #ff5252 0%, #e53935 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
        }
        
        .btn-reset:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(255, 107, 107, 0.3);
        }
        
        .chart-card {
            position: relative;
        }
        
        .chart-loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(255, 255, 255, 0.95);
            padding: 15px;
            border-radius: 0;
            display: none;
            z-index: 10;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(5px);
        }
        
        .real-time-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 3px 6px;
            border-radius: 0;
            font-size: 0.7rem;
            font-weight: 600;
            display: none;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        .chart-filters:hover .real-time-indicator {
            display: block;
        }
        
        #current-time {
            display: none;
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
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">

                <div class="status-indicators">
                    <div class="status-item">
                        <span id="current-time"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Summary Cards -->
        <div class="stats-summary">
            <div class="stat-card alarms">
                <div class="stat-content">
                    <div class="stat-number" id="total-alarms">-</div>
                    <div class="stat-subtitle">Total Fire Alarms</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            
            <div class="stat-card heat">
                <div class="stat-content">
                    <div class="stat-number" id="total-devices">-</div>
                    <div class="stat-subtitle">Active Devices</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-microchip"></i>
                </div>
            </div>
            
            <div class="stat-card fire">
                <div class="stat-content">
                    <div class="stat-number" id="total-buildings">-</div>
                    <div class="stat-subtitle">Registered Buildings</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            
            <div class="stat-card responses">
                <div class="stat-content">
                    <div class="stat-number" id="total-users">-</div>
                    <div class="stat-subtitle">System Users</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Chart 1: Device Status Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Device Status Distribution</h3>
                    <i class="fas fa-microchip chart-icon"></i>
                </div>
                
                <!-- Device Status Filters -->
                <div class="chart-filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="deviceStatusFilter">Device Type</label>
                            <select id="deviceStatusFilter" onchange="debouncedDeviceStatusFilter()">
                                <option value="all">All Devices</option>
                                <option value="user">User Devices</option>
                                <option value="admin">Admin Devices</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="deviceLocationFilter">Location</label>
                            <select id="deviceLocationFilter" onchange="debouncedDeviceStatusFilter()">
                                <option value="all">All Locations</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button class="btn-reset" onclick="resetDeviceStatusFilter()">Reset</button>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-loading" id="deviceStatusLoading">Loading...</div>
                    <canvas id="deviceStatusChart"></canvas>
                </div>
            </div>

            <!-- Chart 2: Fire Incidents Over Time -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Fire Incidents Over Time</h3>
                    <i class="fas fa-chart-line chart-icon"></i>
                </div>
                
                <!-- Fire Incidents Filters -->
                <div class="chart-filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="fireDateRange">Date Range</label>
                            <select id="fireDateRange" onchange="debouncedFireIncidentsFilter()">
                                <option value="7">Last 7 Days</option>
                                <option value="30">Last 30 Days</option>
                                <option value="90">Last 3 Months</option>
                                <option value="365">Last Year</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="fireSeverityFilter">Severity</label>
                            <select id="fireSeverityFilter" onchange="debouncedFireIncidentsFilter()">
                                <option value="all">All Severities</option>
                                <option value="detected">Fire Detected</option>
                                <option value="alert">Fire Alert</option>
                                <option value="critical">Critical Fire</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="fireLocationFilter">Location</label>
                            <select id="fireLocationFilter" onchange="debouncedFireIncidentsFilter()">
                                <option value="all">All Locations</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button class="btn-reset" onclick="resetFireIncidentsFilter()">Reset</button>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-loading" id="fireIncidentsLoading">Loading...</div>
                    <canvas id="fireIncidentsChart"></canvas>
                </div>
            </div>

            <!-- Chart 3: Building Types Distribution -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Building Types Distribution</h3>
                    <i class="fas fa-building chart-icon"></i>
                </div>
                
                <!-- Building Types Filters -->
                <div class="chart-filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="buildingBarangayFilter">Barangay</label>
                            <select id="buildingBarangayFilter" onchange="debouncedBuildingTypesFilter()">
                                <option value="all">All Barangays</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="buildingFloorsFilter">Floors</label>
                            <select id="buildingFloorsFilter" onchange="debouncedBuildingTypesFilter()">
                                <option value="all">All Floors</option>
                                <option value="1">1 Floor</option>
                                <option value="2-5">2-5 Floors</option>
                                <option value="6-10">6-10 Floors</option>
                                <option value="10+">10+ Floors</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="buildingLimitFilter">Show Top</label>
                            <select id="buildingLimitFilter" onchange="debouncedBuildingTypesFilter()">
                                <option value="8">Top 8</option>
                                <option value="5">Top 5</option>
                                <option value="10">Top 10</option>
                                <option value="15">Top 15</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button class="btn-reset" onclick="resetBuildingTypesFilter()">Reset</button>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-loading" id="buildingTypesLoading">Loading...</div>
                    <canvas id="buildingTypesChart"></canvas>
                </div>
            </div>

            <!-- Chart 4: User Registration Trends -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">User Registration Trends</h3>
                    <i class="fas fa-user-plus chart-icon"></i>
                </div>
                
                <!-- User Registration Filters -->
                <div class="chart-filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="userDateRange">Time Period</label>
                            <select id="userDateRange" onchange="debouncedUserRegistrationFilter()">
                                <option value="6">Last 6 Months</option>
                                <option value="12">Last 12 Months</option>
                                <option value="24">Last 24 Months</option>
                                <option value="36">Last 3 Years</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="userStatusFilter">User Status</label>
                            <select id="userStatusFilter" onchange="debouncedUserRegistrationFilter()">
                                <option value="all">All Users</option>
                                <option value="Active">Active Only</option>
                                <option value="Inactive">Inactive Only</option>
                            </select>
                        </div>
                        <div class="filter-actions">
                            <button class="btn-reset" onclick="resetUserRegistrationFilter()">Reset</button>
                        </div>
                    </div>
                </div>
                
                <div class="chart-container">
                    <div class="chart-loading" id="userRegistrationLoading">Loading...</div>
                    <canvas id="userRegistrationChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>

    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>
    <script>
        // Global variables for charts
        let deviceStatusChart = null;
        let fireIncidentsChart = null;
        let buildingTypesChart = null;
        let userRegistrationChart = null;
        
        // Debounce timers for each chart
        let deviceStatusTimer = null;
        let fireIncidentsTimer = null;
        let buildingTypesTimer = null;
        let userRegistrationTimer = null;
        
        // Debounce delay (in milliseconds)
        const DEBOUNCE_DELAY = 500;

        // Toast Notification System
        class ToastManager {
            constructor() {
                this.container = document.getElementById('toast-container');
                this.toasts = new Map();
            }

            show(options) {
                const {
                    type = 'info',
                    title = '',
                    message = '',
                    duration = 5000,
                    showProgress = true,
                    icon = null
                } = options;

                const toastId = Date.now().toString();
                const toast = this.createToast(toastId, type, title, message, icon, showProgress);
                
                this.container.appendChild(toast);
                this.toasts.set(toastId, toast);

                // Auto remove after duration
                if (duration > 0) {
                    setTimeout(() => {
                        this.remove(toastId);
                    }, duration);
                }

                return toastId;
            }

            createToast(id, type, title, message, icon, showProgress) {
                const toast = document.createElement('div');
                toast.className = `toast ${type} ${showProgress ? 'progress' : ''}`;
                toast.setAttribute('data-toast-id', id);

                const defaultIcons = {
                    success: 'fas fa-check-circle',
                    warning: 'fas fa-exclamation-triangle',
                    error: 'fas fa-times-circle',
                    info: 'fas fa-info-circle'
                };

                const iconClass = icon || defaultIcons[type] || defaultIcons.info;

                toast.innerHTML = `
                    <i class="toast-icon ${iconClass}"></i>
                    <div class="toast-content">
                        <div class="toast-title">${title}</div>
                        <div class="toast-message">${message}</div>
                    </div>
                    <button class="toast-close" onclick="toastManager.remove('${id}')">
                        <i class="fas fa-times"></i>
                    </button>
                `;

                return toast;
            }

            remove(toastId) {
                const toast = this.toasts.get(toastId);
                if (toast) {
                    toast.classList.add('slide-out');
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                        this.toasts.delete(toastId);
                    }, 300);
                }
            }

            clear() {
                this.toasts.forEach((toast, id) => {
                    this.remove(id);
                });
            }
        }

        // Initialize toast manager
        const toastManager = new ToastManager();

        // Chart animation configuration - REMOVED
        
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: '2-digit', 
                minute: '2-digit' 
            });
            document.getElementById('current-time').textContent = timeString;
        }
        
        // Update time every second
        updateTime();
        setInterval(updateTime, 1000);

        // Chart.js configuration (handle v2 and v3+)
        if (typeof Chart !== 'undefined' && Chart.defaults) {
            if (Chart.defaults.font) {
                // Chart.js v3+
                Chart.defaults.font.family = 'Inter, sans-serif';
                Chart.defaults.font.size = 12;
                Chart.defaults.color = '#6b7280';
            } else if (Chart.defaults.global) {
                // Chart.js v2.x
                Chart.defaults.global.defaultFontFamily = 'Inter, sans-serif';
                Chart.defaults.global.defaultFontSize = 12;
                Chart.defaults.global.defaultFontColor = '#6b7280';
            }
        } else {
            console.warn('Chart.js is not loaded – skipping default configuration.');
        }

        // Show loading indicator
        function showChartLoading(chartId) {
            document.getElementById(chartId + 'Loading').style.display = 'block';
        }

        // Hide loading indicator
        function hideChartLoading(chartId) {
            document.getElementById(chartId + 'Loading').style.display = 'none';
        }

        // Debounced filter function
        function debounceFilter(filterFunction, timerVariable, delay = DEBOUNCE_DELAY) {
            return function(...args) {
                clearTimeout(timerVariable);
                timerVariable = setTimeout(() => {
                    filterFunction.apply(this, args);
                }, delay);
            };
        }

        // Load dashboard data
        async function loadDashboardData() {
            try {
                // Show welcome message
                toastManager.show({
                    type: 'success',
                    title: 'Welcome to Statistics Dashboard',
                    message: 'Loading your fire detection system statistics...',
                    duration: 4000,
                    icon: 'fas fa-fire'
                });

                // Load summary statistics
                const summaryResponse = await fetch('get_summary_stats.php');
                const summaryData = await summaryResponse.json();
                
                if (summaryData.success) {
                    // Animate stat numbers
                    animateStatNumbers(summaryData.data);
                }

                // Load barangay options for building types filter
                await loadBarangayOptions();

                // Load chart data with progress notifications
                const chartPromises = [
                    loadDeviceStatusChart(),
                    loadFireIncidentsChart(),
                    loadBuildingTypesChart(),
                    loadUserRegistrationChart()
                ];

                await Promise.all(chartPromises);

                // Show completion message
                toastManager.show({
                    type: 'success',
                    title: 'Dashboard Loaded Successfully',
                    message: 'All charts and statistics have been updated with the latest data.',
                    duration: 3000,
                    icon: 'fas fa-chart-line'
                });

            } catch (error) {
                console.error('Error loading dashboard data:', error);
                toastManager.show({
                    type: 'error',
                    title: 'Loading Error',
                    message: 'Failed to load dashboard data. Please refresh the page.',
                    duration: 5000,
                    icon: 'fas fa-exclamation-triangle'
                });
            }
        }

        // Animate stat numbers
        function animateStatNumbers(data) {
            const elements = {
                'total-alarms': data.total_alarms,
                'total-devices': data.total_devices,
                'total-buildings': data.total_buildings,
                'total-users': data.total_users
            };

            Object.entries(elements).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    animateNumber(element, 0, value, 1500);
                }
            });
        }

        // Animate number counting
        function animateNumber(element, start, end, duration) {
            const startTime = performance.now();
            const difference = end - start;

            function updateNumber(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                // Easing function for smooth animation
                const easeOutQuart = 1 - Math.pow(1 - progress, 4);
                const current = Math.round(start + (difference * easeOutQuart));
                
                element.textContent = current.toLocaleString();
                
                if (progress < 1) {
                    requestAnimationFrame(updateNumber);
                }
            }
            
            requestAnimationFrame(updateNumber);
        }

        // Load barangay options for building types filter
        async function loadBarangayOptions() {
            try {
                const response = await fetch('get_barangay_options.php');
                const data = await response.json();
                
                if (data.success) {
                    const select = document.getElementById('buildingBarangayFilter');
                    // Clear existing options except "All Barangays"
                    select.innerHTML = '<option value="all">All Barangays</option>';
                    
                    // Add barangay options
                    data.data.forEach(barangay => {
                        const option = document.createElement('option');
                        option.value = barangay.value;
                        option.textContent = barangay.text;
                        select.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Error loading barangay options:', error);
            }
        }

        // Device Status Chart
        async function loadDeviceStatusChart(filters = {}) {
            try {
                showChartLoading('deviceStatus');
                
                const params = new URLSearchParams();
                if (filters.deviceType) params.append('device_type', filters.deviceType);
                if (filters.location) params.append('location', filters.location);
                
                const response = await fetch(`get_device_status_data.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    const ctx = document.getElementById('deviceStatusChart').getContext('2d');
                    
                    // Destroy existing chart if it exists
                    if (deviceStatusChart) {
                        deviceStatusChart.destroy();
                    }
                    
                    deviceStatusChart = new Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: data.data.labels,
                            datasets: [{
                                data: data.data.values,
                                backgroundColor: [
                                    '#10b981', // green for online
                                    '#f59e0b', // amber for offline
                                    '#ef4444'  // red for faulty
                                ],
                                borderWidth: 2,
                                borderColor: '#ffffff'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        padding: 20,
                                        usePointStyle: true
                                    }
                                }
                            }
                        }
                    });

                    // Show chart update notification
                    toastManager.show({
                        type: 'info',
                        title: 'Device Status Chart Updated',
                        message: `Showing ${data.data.labels.length} device status categories`,
                        duration: 3000,
                        icon: 'fas fa-microchip'
                    });
                }
                hideChartLoading('deviceStatus');
            } catch (error) {
                console.error('Error loading device status chart:', error);
                hideChartLoading('deviceStatus');
                toastManager.show({
                    type: 'error',
                    title: 'Chart Error',
                    message: 'Failed to load device status data',
                    duration: 4000
                });
            }
        }

        // Fire Incidents Chart
        async function loadFireIncidentsChart(filters = {}) {
            try {
                showChartLoading('fireIncidents');
                
                const params = new URLSearchParams();
                if (filters.dateRange) params.append('date_range', filters.dateRange);
                if (filters.severity) params.append('severity', filters.severity);
                if (filters.location) params.append('location', filters.location);
                
                const response = await fetch(`get_fire_incidents_data.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    const ctx = document.getElementById('fireIncidentsChart').getContext('2d');
                    
                    // Destroy existing chart if it exists
                    if (fireIncidentsChart) {
                        fireIncidentsChart.destroy();
                    }
                    
                    fireIncidentsChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: data.data.labels,
                            datasets: [{
                                label: 'Fire Incidents',
                                data: data.data.values,
                                borderColor: '#ef4444',
                                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#ef4444',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                pointRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f3f4f6'
                                    }
                                },
                                x: {
                                    grid: {
                                        color: '#f3f4f6'
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });

                    // Show chart update notification
                    toastManager.show({
                        type: 'warning',
                        title: 'Fire Incidents Chart Updated',
                        message: `Displaying ${data.data.labels.length} time periods of incident data`,
                        duration: 3000,
                        icon: 'fas fa-fire'
                    });
                }
                hideChartLoading('fireIncidents');
            } catch (error) {
                console.error('Error loading fire incidents chart:', error);
                hideChartLoading('fireIncidents');
                toastManager.show({
                    type: 'error',
                    title: 'Chart Error',
                    message: 'Failed to load fire incidents data',
                    duration: 4000
                });
            }
        }

        // Building Types Chart
        async function loadBuildingTypesChart(filters = {}) {
            try {
                showChartLoading('buildingTypes');
                
                const params = new URLSearchParams();
                if (filters.barangay) params.append('barangay', filters.barangay);
                if (filters.floors) params.append('floors', filters.floors);
                if (filters.limit) params.append('limit', filters.limit);
                
                const response = await fetch(`get_building_types_data.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    const ctx = document.getElementById('buildingTypesChart').getContext('2d');
                    
                    // Destroy existing chart if it exists
                    if (buildingTypesChart) {
                        buildingTypesChart.destroy();
                    }
                    
                    buildingTypesChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.data.labels,
                            datasets: [{
                                label: 'Number of Buildings',
                                data: data.data.values,
                                backgroundColor: [
                                    '#3b82f6', '#10b981', '#f59e0b', '#ef4444', 
                                    '#8b5cf6', '#06b6d4', '#84cc16', '#f97316'
                                ],
                                borderColor: '#ffffff',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f3f4f6'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });

                    // Show chart update notification
                    toastManager.show({
                        type: 'info',
                        title: 'Building Types Chart Updated',
                        message: `Showing ${data.data.labels.length} building categories`,
                        duration: 3000,
                        icon: 'fas fa-building'
                    });
                }
                hideChartLoading('buildingTypes');
            } catch (error) {
                console.error('Error loading building types chart:', error);
                hideChartLoading('buildingTypes');
                toastManager.show({
                    type: 'error',
                    title: 'Chart Error',
                    message: 'Failed to load building types data',
                    duration: 4000
                });
            }
        }

        // User Registration Chart
        async function loadUserRegistrationChart(filters = {}) {
            try {
                showChartLoading('userRegistration');
                
                const params = new URLSearchParams();
                if (filters.dateRange) params.append('date_range', filters.dateRange);
                if (filters.status) params.append('status', filters.status);
                
                const response = await fetch(`get_user_registration_data.php?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    const ctx = document.getElementById('userRegistrationChart').getContext('2d');
                    
                    // Destroy existing chart if it exists
                    if (userRegistrationChart) {
                        userRegistrationChart.destroy();
                    }
                    
                    userRegistrationChart = new Chart(ctx, {
                        type: 'bar',
                        data: {
                            labels: data.data.labels,
                            datasets: [{
                                label: 'New Registrations',
                                data: data.data.values,
                                backgroundColor: '#8b5cf6',
                                borderColor: '#7c3aed',
                                borderWidth: 1,
                                borderRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f3f4f6'
                                    }
                                },
                                x: {
                                    grid: {
                                        display: false
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });

                    // Show chart update notification
                    toastManager.show({
                        type: 'success',
                        title: 'User Registration Chart Updated',
                        message: `Displaying ${data.data.labels.length} registration periods`,
                        duration: 3000,
                        icon: 'fas fa-user-plus'
                    });
                }
                hideChartLoading('userRegistration');
            } catch (error) {
                console.error('Error loading user registration chart:', error);
                hideChartLoading('userRegistration');
                toastManager.show({
                    type: 'error',
                    title: 'Chart Error',
                    message: 'Failed to load user registration data',
                    duration: 4000
                });
            }
        }

        // Filter Functions
        function applyDeviceStatusFilter() {
            const filters = {
                deviceType: document.getElementById('deviceStatusFilter').value,
                location: document.getElementById('deviceLocationFilter').value
            };
            
            toastManager.show({
                type: 'info',
                title: 'Filter Applied',
                message: 'Updating device status chart with new filters...',
                duration: 2000,
                icon: 'fas fa-filter'
            });
            
            loadDeviceStatusChart(filters);
        }

        function resetDeviceStatusFilter() {
            document.getElementById('deviceStatusFilter').value = 'all';
            document.getElementById('deviceLocationFilter').value = 'all';
            
            toastManager.show({
                type: 'warning',
                title: 'Filters Reset',
                message: 'Device status chart filters have been cleared',
                duration: 2000,
                icon: 'fas fa-undo'
            });
            
            loadDeviceStatusChart();
        }

        function applyFireIncidentsFilter() {
            const filters = {
                dateRange: document.getElementById('fireDateRange').value,
                severity: document.getElementById('fireSeverityFilter').value,
                location: document.getElementById('fireLocationFilter').value
            };
            
            toastManager.show({
                type: 'info',
                title: 'Filter Applied',
                message: 'Updating fire incidents chart with new filters...',
                duration: 2000,
                icon: 'fas fa-filter'
            });
            
            loadFireIncidentsChart(filters);
        }

        function resetFireIncidentsFilter() {
            document.getElementById('fireDateRange').value = '7';
            document.getElementById('fireSeverityFilter').value = 'all';
            document.getElementById('fireLocationFilter').value = 'all';
            
            toastManager.show({
                type: 'warning',
                title: 'Filters Reset',
                message: 'Fire incidents chart filters have been cleared',
                duration: 2000,
                icon: 'fas fa-undo'
            });
            
            loadFireIncidentsChart();
        }

        function applyBuildingTypesFilter() {
            const filters = {
                barangay: document.getElementById('buildingBarangayFilter').value,
                floors: document.getElementById('buildingFloorsFilter').value,
                limit: document.getElementById('buildingLimitFilter').value
            };
            
            toastManager.show({
                type: 'info',
                title: 'Filter Applied',
                message: 'Updating building types chart with new filters...',
                duration: 2000,
                icon: 'fas fa-filter'
            });
            
            loadBuildingTypesChart(filters);
        }

        function resetBuildingTypesFilter() {
            document.getElementById('buildingBarangayFilter').value = 'all';
            document.getElementById('buildingFloorsFilter').value = 'all';
            document.getElementById('buildingLimitFilter').value = '8';
            
            toastManager.show({
                type: 'warning',
                title: 'Filters Reset',
                message: 'Building types chart filters have been cleared',
                duration: 2000,
                icon: 'fas fa-undo'
            });
            
            loadBuildingTypesChart();
        }

        function applyUserRegistrationFilter() {
            const filters = {
                dateRange: document.getElementById('userDateRange').value,
                status: document.getElementById('userStatusFilter').value
            };
            
            toastManager.show({
                type: 'info',
                title: 'Filter Applied',
                message: 'Updating user registration chart with new filters...',
                duration: 2000,
                icon: 'fas fa-filter'
            });
            
            loadUserRegistrationChart(filters);
        }

        function resetUserRegistrationFilter() {
            document.getElementById('userDateRange').value = '6';
            document.getElementById('userStatusFilter').value = 'all';
            
            toastManager.show({
                type: 'warning',
                title: 'Filters Reset',
                message: 'User registration chart filters have been cleared',
                duration: 2000,
                icon: 'fas fa-undo'
            });
            
            loadUserRegistrationChart();
        }

        // Create debounced versions of filter functions
        const debouncedDeviceStatusFilter = debounceFilter(applyDeviceStatusFilter, deviceStatusTimer);
        const debouncedFireIncidentsFilter = debounceFilter(applyFireIncidentsFilter, fireIncidentsTimer);
        const debouncedBuildingTypesFilter = debounceFilter(applyBuildingTypesFilter, buildingTypesTimer);
        const debouncedUserRegistrationFilter = debounceFilter(applyUserRegistrationFilter, userRegistrationTimer);

        // Error handling
        function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            document.querySelector('.main-card').insertBefore(errorDiv, document.querySelector('.stats-summary'));
        }

        // Initialize dashboard when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Load dashboard data immediately
            loadDashboardData();
        });

        // Add keyboard shortcuts for better UX
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + R to refresh all charts
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                toastManager.show({
                    type: 'info',
                    title: 'Refreshing Dashboard',
                    message: 'Reloading all charts and statistics...',
                    duration: 2000,
                    icon: 'fas fa-sync-alt'
                });
                loadDashboardData();
            }
            
            // Escape to clear all toasts
            if (e.key === 'Escape') {
                toastManager.clear();
            }
        });
    </script>
</body>
<?php include('../../components/scripts.php'); ?>
</html>

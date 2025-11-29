<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Manila');

// Database connection
function getDatabaseConnection() {
    static $conn = null;
    if ($conn === null) {
        $host = "localhost";
        $dbname = "u520834156_DBBagofire"; 
        $username = "u520834156_userBagofire";
        $password = "i[#[GQ!+=C9";
        
        try {
            $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die(json_encode(['success' => false, 'message' => 'System temporarily unavailable']));
        }
    }
    return $conn;
}

// Get dashboard data
function getDashboardData() {
    $conn = getDatabaseConnection();
    $data = [];
    
    // Get device status
    $stmt = $conn->prepare("SELECT device_id, device_name, status, last_activity FROM devices WHERE is_active = 1");
    $stmt->execute();
    $data['devices'] = $stmt->fetchAll();
    
    // Get latest readings
    $stmt = $conn->prepare("
        SELECT 
            fd.*,
            d.device_name,
            b.building_name,
            u.first_name,
            u.last_name,
            u.phone
        FROM fire_data fd
        JOIN devices d ON fd.device_id = d.device_id
        JOIN buildings b ON fd.building_id = b.building_id
        JOIN users u ON fd.user_id = u.user_id
        ORDER BY fd.timestamp DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $data['latest_readings'] = $stmt->fetchAll();
    
    // Get alert counts
    $stmt = $conn->prepare("
        SELECT status, COUNT(*) as count 
        FROM fire_data 
        WHERE status != 'NORMAL' 
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY status
    ");
    $stmt->execute();
    $data['alerts'] = $stmt->fetchAll();
    
    // Get sensor statistics
    $stmt = $conn->prepare("
        SELECT 
            AVG(smoke) as avg_smoke,
            MAX(smoke) as max_smoke,
            AVG(temp) as avg_temp,
            MAX(temp) as max_temp,
            AVG(heat) as avg_heat,
            MAX(heat) as max_heat,
            COUNT(*) as total_readings
        FROM fire_data 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->execute();
    $data['stats'] = $stmt->fetch();
    
    return $data;
}

// Get chart data
function getChartData($hours = 24) {
    $conn = getDatabaseConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(timestamp, '%Y-%m-%d %H:00:00') as time_group,
            AVG(smoke) as avg_smoke,
            AVG(temp) as avg_temp,
            AVG(heat) as avg_heat,
            AVG(humidity) as avg_humidity
        FROM fire_data 
        WHERE timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        GROUP BY time_group
        ORDER BY time_group
    ");
    $stmt->execute([$hours]);
    
    return $stmt->fetchAll();
}

// Get device details
function getDeviceDetails($device_id) {
    $conn = getDatabaseConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            b.building_name,
            b.address,
            u.first_name,
            u.last_name,
            u.phone,
            u.email
        FROM devices d
        JOIN buildings b ON d.building_id = b.building_id
        JOIN users u ON d.user_id = u.user_id
        WHERE d.device_id = ?
    ");
    $stmt->execute([$device_id]);
    
    return $stmt->fetch();
}

// Check if it's an AJAX request for data
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_dashboard_data':
            echo json_encode(getDashboardData());
            exit;
            
        case 'get_chart_data':
            $hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;
            echo json_encode(getChartData($hours));
            exit;
            
        case 'get_device_details':
            if (isset($_GET['device_id'])) {
                echo json_encode(getDeviceDetails($_GET['device_id']));
            } else {
                echo json_encode(['error' => 'Device ID required']);
            }
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fire Detection System Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #0d6efd;
            --danger: #dc3545;
            --warning: #ffc107;
            --success: #198754;
            --info: #0dcaf0;
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-online {
            background-color: rgba(25, 135, 84, 0.2);
            color: var(--success);
        }
        .status-offline {
            background-color: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }
        .status-warning {
            background-color: rgba(255, 193, 7, 0.2);
            color: var(--warning);
        }
        .status-danger {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--danger);
        }
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        .device-card {
            transition: transform 0.2s;
            cursor: pointer;
        }
        .device-card:hover {
            transform: translateY(-5px);
        }
        .reading-card {
            border-left: 4px solid var(--primary);
        }
        .reading-warning {
            border-left-color: var(--warning);
        }
        .reading-danger {
            border-left-color: var(--danger);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }
        #lastUpdate {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-fire me-2"></i>Fire Detection System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#"><i class="fas fa-home me-1"></i> Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-history me-1"></i> History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-cog me-1"></i> Settings</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Dashboard Overview</h2>
                    <span id="lastUpdate">Last updated: <span id="updateTime"><?php echo date('Y-m-d H:i:s'); ?></span></span>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Statistics Cards -->
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-white">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-fire"></i>
                    </div>
                    <div class="stat-value" id="avgSmoke">0</div>
                    <div class="stat-label">Average Smoke Level</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-white">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-temperature-high"></i>
                    </div>
                    <div class="stat-value" id="avgTemp">0°C</div>
                    <div class="stat-label">Average Temperature</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-white">
                    <div class="stat-icon text-warning">
                        <i class="fas fa-radiation"></i>
                    </div>
                    <div class="stat-value" id="avgHeat">0°C</div>
                    <div class="stat-label">Average Heat Index</div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card bg-white">
                    <div class="stat-icon text-info">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-value" id="alertCount">0</div>
                    <div class="stat-label">Alerts (24h)</div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Devices List -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Connected Devices</h5>
                        <span class="badge bg-primary" id="deviceCount">0</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="devicesList" class="list-group list-group-flush">
                            <!-- Devices will be loaded here -->
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Alerts</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="alertsList" class="list-group list-group-flush">
                            <!-- Alerts will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sensor Data Trends</h5>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-hours="24">24H</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-hours="48">48H</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-hours="72">72H</button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="sensorChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Latest Readings</h5>
                    </div>
                    <div class="card-body p-0">
                        <div id="readingsList" class="list-group list-group-flush">
                            <!-- Readings will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Device Modal -->
    <div class="modal fade" id="deviceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Device Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="deviceDetails">
                    <!-- Device details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let sensorChart = null;
        let updateInterval = null;
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            loadDashboardData();
            setupEventListeners();
            
            // Set up auto-refresh every 30 seconds
            updateInterval = setInterval(loadDashboardData, 30000);
        });
        
        // Set up event listeners
        function setupEventListeners() {
            // Chart time period buttons
            document.querySelectorAll('[data-hours]').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('[data-hours]').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    const hours = parseInt(this.getAttribute('data-hours'));
                    loadChartData(hours);
                });
            });
        }
        
        // Load all dashboard data
        function loadDashboardData() {
            fetch('?action=get_dashboard_data')
                .then(response => response.json())
                .then(data => {
                    updateStatistics(data);
                    updateDevicesList(data.devices);
                    updateAlertsList(data.alerts);
                    updateReadingsList(data.latest_readings);
                    updateLastUpdateTime();
                    
                    // Load chart data if chart doesn't exist yet
                    if (!sensorChart) {
                        loadChartData(24);
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard data:', error);
                });
        }
        
        // Load chart data
        function loadChartData(hours) {
            fetch(`?action=get_chart_data&hours=${hours}`)
                .then(response => response.json())
                .then(data => {
                    updateSensorChart(data, hours);
                })
                .catch(error => {
                    console.error('Error loading chart data:', error);
                });
        }
        
        // Update statistics cards
        function updateStatistics(data) {
            const stats = data.stats;
            document.getElementById('avgSmoke').textContent = stats.avg_smoke ? Math.round(stats.avg_smoke) : '0';
            document.getElementById('avgTemp').textContent = stats.avg_temp ? `${Math.round(stats.avg_temp)}°C` : '0°C';
            document.getElementById('avgHeat').textContent = stats.avg_heat ? `${Math.round(stats.avg_heat)}°C` : '0°C';
            
            // Calculate total alerts
            const alertCount = data.alerts.reduce((total, alert) => total + parseInt(alert.count), 0);
            document.getElementById('alertCount').textContent = alertCount;
        }
        
        // Update devices list
        function updateDevicesList(devices) {
            const devicesList = document.getElementById('devicesList');
            devicesList.innerHTML = '';
            
            document.getElementById('deviceCount').textContent = devices.length;
            
            devices.forEach(device => {
                const statusClass = device.status === 'online' ? 'status-online' : 
                                  device.status === 'offline' ? 'status-offline' :
                                  device.status === 'warning' ? 'status-warning' : 'status-danger';
                
                const lastActivity = new Date(device.last_activity);
                const timeAgo = timeSince(lastActivity);
                
                const deviceElement = document.createElement('div');
                deviceElement.className = 'list-group-item device-card';
                deviceElement.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${device.device_name}</h6>
                            <small class="text-muted">Last activity: ${timeAgo}</small>
                        </div>
                        <div>
                            <span class="status-badge ${statusClass}">${device.status}</span>
                        </div>
                    </div>
                `;
                
                deviceElement.addEventListener('click', () => {
                    showDeviceDetails(device.device_id);
                });
                
                devicesList.appendChild(deviceElement);
            });
        }
        
        // Update alerts list
        function updateAlertsList(alerts) {
            const alertsList = document.getElementById('alertsList');
            alertsList.innerHTML = '';
            
            if (alerts.length === 0) {
                alertsList.innerHTML = '<div class="list-group-item text-center text-muted">No alerts in the last 24 hours</div>';
                return;
            }
            
            alerts.forEach(alert => {
                const alertClass = alert.status === 'WARNING' ? 'list-group-item-warning' : 'list-group-item-danger';
                
                const alertElement = document.createElement('div');
                alertElement.className = `list-group-item ${alertClass}`;
                alertElement.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${alert.status} Alert</h6>
                            <small>${alert.count} occurrence(s)</small>
                        </div>
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                `;
                
                alertsList.appendChild(alertElement);
            });
        }
        
        // Update readings list
        function updateReadingsList(readings) {
            const readingsList = document.getElementById('readingsList');
            readingsList.innerHTML = '';
            
            readings.forEach(reading => {
                const readingTime = new Date(reading.timestamp);
                const timeString = readingTime.toLocaleTimeString();
                
                let statusClass = '';
                let statusIcon = '';
                
                if (reading.status === 'NORMAL') {
                    statusClass = '';
                    statusIcon = '<i class="fas fa-check-circle text-success"></i>';
                } else if (reading.status === 'WARNING') {
                    statusClass = 'reading-warning';
                    statusIcon = '<i class="fas fa-exclamation-triangle text-warning"></i>';
                } else {
                    statusClass = 'reading-danger';
                    statusIcon = '<i class="fas fa-exclamation-circle text-danger"></i>';
                }
                
                const readingElement = document.createElement('div');
                readingElement.className = `list-group-item reading-card ${statusClass}`;
                readingElement.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">${reading.device_name} - ${reading.building_name}</h6>
                            <small class="text-muted">${timeString}</small>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <small>Smoke: <strong>${reading.smoke}</strong></small>
                            </div>
                            <div class="me-3">
                                <small>Temp: <strong>${reading.temp}°C</strong></small>
                            </div>
                            <div class="me-3">
                                <small>Heat: <strong>${reading.heat}°C</strong></small>
                            </div>
                            <div>
                                ${statusIcon}
                            </div>
                        </div>
                    </div>
                `;
                
                readingsList.appendChild(readingElement);
            });
        }
        
        // Update sensor chart
        function updateSensorChart(data, hours) {
            const ctx = document.getElementById('sensorChart').getContext('2d');
            
            // Destroy existing chart if it exists
            if (sensorChart) {
                sensorChart.destroy();
            }
            
            // Prepare data
            const labels = data.map(item => {
                const date = new Date(item.time_group);
                return date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
            });
            
            const smokeData = data.map(item => item.avg_smoke);
            const tempData = data.map(item => item.avg_temp);
            const heatData = data.map(item => item.avg_heat);
            const humidityData = data.map(item => item.avg_humidity);
            
            // Create chart
            sensorChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Smoke Level',
                            data: smokeData,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Temperature (°C)',
                            data: tempData,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Heat Index (°C)',
                            data: heatData,
                            borderColor: '#fd7e14',
                            backgroundColor: 'rgba(253, 126, 20, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: `Sensor Data for the Last ${hours} Hours`
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        },
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Time'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Value'
                            }
                        }
                    }
                }
            });
        }
        
        // Show device details
        function showDeviceDetails(deviceId) {
            fetch(`?action=get_device_details&device_id=${deviceId}`)
                .then(response => response.json())
                .then(device => {
                    const modalBody = document.getElementById('deviceDetails');
                    
                    const lastActivity = new Date(device.last_activity);
                    const formattedDate = lastActivity.toLocaleString();
                    
                    modalBody.innerHTML = `
                        <div class="row">
                            <div class="col-md-6">
                                <h5>${device.device_name}</h5>
                                <p class="text-muted">${device.device_number} - ${device.serial_number}</p>
                                
                                <table class="table table-sm">
                                    <tr>
                                        <th>Status:</th>
                                        <td>
                                            <span class="status-badge ${device.status === 'online' ? 'status-online' : 'status-offline'}">
                                                ${device.status}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Last Activity:</th>
                                        <td>${formattedDate}</td>
                                    </tr>
                                    <tr>
                                        <th>Building:</th>
                                        <td>${device.building_name}</td>
                                    </tr>
                                    <tr>
                                        <th>Address:</th>
                                        <td>${device.address}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5>Contact Information</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>User:</th>
                                        <td>${device.first_name} ${device.last_name}</td>
                                    </tr>
                                    <tr>
                                        <th>Email:</th>
                                        <td>${device.email}</td>
                                    </tr>
                                    <tr>
                                        <th>Phone:</th>
                                        <td>${device.phone}</td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    `;
                    
                    // Show the modal
                    const modal = new bootstrap.Modal(document.getElementById('deviceModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error loading device details:', error);
                });
        }
        
        // Update last update time
        function updateLastUpdateTime() {
            document.getElementById('updateTime').textContent = new Date().toLocaleString();
        }
        
        // Helper function to calculate time since
        function timeSince(date) {
            const seconds = Math.floor((new Date() - date) / 1000);
            
            let interval = seconds / 31536000;
            if (interval > 1) {
                return Math.floor(interval) + " years ago";
            }
            interval = seconds / 2592000;
            if (interval > 1) {
                return Math.floor(interval) + " months ago";
            }
            interval = seconds / 86400;
            if (interval > 1) {
                return Math.floor(interval) + " days ago";
            }
            interval = seconds / 3600;
            if (interval > 1) {
                return Math.floor(interval) + " hours ago";
            }
            interval = seconds / 60;
            if (interval > 1) {
                return Math.floor(interval) + " minutes ago";
            }
            return Math.floor(seconds) + " seconds ago";
        }
    </script>
</body>
</html>
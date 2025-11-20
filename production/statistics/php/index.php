<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../../db/db.php';
?>

<?php include '../../components/header.php'; ?>
    
    <link rel="stylesheet" href="../css/custom.css?v=<?php echo time(); ?>">
    <style>      
     
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .chart-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.06);
            border: none;
            transition: none;
            position: relative;
            overflow: hidden;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f5f5f5;
            position: relative;
        }
        
        .chart-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a1a;
            margin: 0;
            letter-spacing: -0.01em;
        }
        
        .chart-icon {
            font-size: 2rem;
            color: #666666;
            transition: all 0.3s ease;
        }
        
        .filters-container {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-label {
            font-size: 0.95rem;
            font-weight: 600;
            color: #2a2a2a;
            margin-bottom: 6px;
        }
        
        .filter-select, .filter-input {
            padding: 12px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            min-width: 150px;
            background: #ffffff;
            color: #1a1a1a;
            font-weight: 500;
        }
        
        
        .reset-btn {
            padding: 8px 12px;
            background: blue;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 120px;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
        }
        
        
        .reset-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        .reset-btn i {
            font-size: 1rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        
        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            font-size: 1.1rem;
            color: #6c757d;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border: 1px solid #f5c6cb;
        }
        
        /* Alert System Styles */
        .alert-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        }
        
        .alert {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-left: 4px solid;
            display: flex;
            align-items: center;
            animation: slideInRight 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            position: relative;
            transform: translateX(100%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .alert.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .alert-success {
            border-left-color: #27ae60;
            background: linear-gradient(135deg, #d5f4e6 0%, #ffffff 100%);
        }
        
        .alert-warning {
            border-left-color: #f39c12;
            background: linear-gradient(135deg, #fef5e7 0%, #ffffff 100%);
        }
        
        .alert-danger {
            border-left-color: #e74c3c;
            background: linear-gradient(135deg, #fadbd8 0%, #ffffff 100%);
        }
        
        .alert-info {
            border-left-color: #3498db;
            background: linear-gradient(135deg, #d6eaf8 0%, #ffffff 100%);
        }
        
        .alert-icon {
            font-size: 1.5rem;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .alert-success .alert-icon {
            color: #27ae60;
        }
        
        .alert-warning .alert-icon {
            color: #f39c12;
        }
        
        .alert-danger .alert-icon {
            color: #e74c3c;
        }
        
        .alert-info .alert-icon {
            color: #3498db;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .alert-message {
            font-size: 0.9rem;
            margin: 0;
            color: #6c757d;
            line-height: 1.4;
        }
        
        .alert-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            margin-left: 10px;
            transition: color 0.3s ease;
        }
        
        .alert-close:hover {
            color: #2c3e50;
        }
        
        @keyframes slideInRight {
            0% {
                transform: translateX(100%) scale(0.8);
                opacity: 0;
            }
            50% {
                transform: translateX(-10px) scale(1.05);
                opacity: 0.8;
            }
            100% {
                transform: translateX(0) scale(1);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            0% {
                transform: translateX(0) scale(1);
                opacity: 1;
            }
            50% {
                transform: translateX(10px) scale(0.95);
                opacity: 0.5;
            }
            100% {
                transform: translateX(100%) scale(0.8);
                opacity: 0;
            }
        }
        
        @keyframes bounceIn {
            0% {
                transform: scale(0.3);
                opacity: 0;
            }
            50% {
                transform: scale(1.05);
                opacity: 0.8;
            }
            70% {
                transform: scale(0.9);
                opacity: 0.9;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .alert.fade-out {
            animation: slideOutRight 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        }
        
        .alert-icon {
            animation: bounceIn 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) 0.2s both;
        }
    </style>

    
    <!-- Building Table CSS -->
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
            <!-- Alert Container -->
            <div id="alertContainer" class="alert-container"></div>
            
    <div class="main-card">
   <!-- Main Content -->
 <div class="row">

           
  
        <!-- Summary Statistics -->
        <div class="stats-summary" id="summaryStats">
            <div class="stat-card alarms">
                <div class="stat-content">
                <div class="stat-label">Emergency Alarms</div>
                    <div class="stat-number" id="emergencyAlarms">-</div>
                    <div class="stat-subtitle" id="currentMonth">-</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="stat-card heat">
                <div class="stat-content">
                <div class="stat-label">Avg Heat Level</div>
                    <div class="stat-number" id="avgHeatLevel">-</div>
                    <div class="stat-subtitle" id="currentMonthHeat">-</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-thermometer-half"></i>
                </div>
            </div>
            <div class="stat-card fire">
                <div class="stat-content">
                <div class="stat-label">Fire Incidents</div>
                    <div class="stat-number" id="totalFireIncidents">-</div>
                    <div class="stat-subtitle" id="currentMonthFire">-</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-fire"></i>
                </div>
            </div>
            <div class="stat-card responses">
                <div class="stat-content">
                <div class="stat-label">Responses</div>
                    <div class="stat-number" id="totalResponses">-</div>
                    <div class="stat-subtitle" id="currentMonthResponses">-</div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-truck"></i>
                </div>
            </div>
        </div>
        
        <!-- Charts Grid -->
        <div class="charts-grid">
            <!-- Chart 1: Alarm Statistics -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Alarm Statistics</h3>
                    <i class="fas fa-fire chart-icon"></i>
                </div>
                <div class="filters-container">
                    <div class="filter-group">
                        <label class="filter-label">Status Filter</label>
                        <select class="filter-select" id="alarmStatusFilter">
                            <option value="">All Status</option>
                            <option value="EMERGENCY">Emergency</option>
                            <option value="NORMAL">Normal</option>
                            <option value="ACKNOWLEDGED">Acknowledged</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Start Date</label>
                        <input type="date" class="filter-input" id="alarmStartDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">End Date</label>
                        <input type="date" class="filter-input" id="alarmEndDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button class="reset-btn" onclick="resetAlarmFilters()">
                            <i class="fas fa-undo"></i>
                            Reset
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="alarmChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 2: Barangay Statistics -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Barangay Heat Analysis</h3>
                    <i class="fas fa-map-marker-alt chart-icon"></i>
                </div>
                <div class="filters-container">
                    <div class="filter-group">
                        <label class="filter-label">Barangay</label>
                        <select class="filter-select" id="barangayFilter">
                            <option value="">All Barangays</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Month</label>
                        <select class="filter-select" id="barangayMonthFilter">
                            <option value="">All Months</option>
                            <option value="01">January</option>
                            <option value="02">February</option>
                            <option value="03">March</option>
                            <option value="04">April</option>
                            <option value="05">May</option>
                            <option value="06">June</option>
                            <option value="07">July</option>
                            <option value="08">August</option>
                            <option value="09">September</option>
                            <option value="10">October</option>
                            <option value="11">November</option>
                            <option value="12">December</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Year</label>
                        <select class="filter-select" id="barangayYearFilter">
                            <option value="">All Years</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button class="reset-btn" onclick="resetBarangayFilters()">
                            <i class="fas fa-undo"></i>
                            Reset
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="barangayChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 3: Fire Incidents -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Fire Data Analysis</h3>
                    <i class="fas fa-exclamation-triangle chart-icon"></i>
                </div>
                <div class="filters-container">
                    <div class="filter-group">
                        <label class="filter-label">Start Date</label>
                        <input type="date" class="filter-input" id="incidentStartDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">End Date</label>
                        <input type="date" class="filter-input" id="incidentEndDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button class="reset-btn" onclick="resetIncidentFilters()">
                            <i class="fas fa-undo"></i>
                            Reset
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="incidentChart"></canvas>
                </div>
            </div>
            
            <!-- Chart 4: Response Statistics -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3 class="chart-title">Response Statistics</h3>
                    <i class="fas fa-ambulance chart-icon"></i>
                </div>
                <div class="filters-container">
                    <div class="filter-group">
                        <label class="filter-label">Firefighter</label>
                        <select class="filter-select" id="responseFirefighterFilter">
                            <option value="">All Firefighters</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Start Date</label>
                        <input type="date" class="filter-input" id="responseStartDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">End Date</label>
                        <input type="date" class="filter-input" id="responseEndDate">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <button class="reset-btn" onclick="resetResponseFilters()">
                            <i class="fas fa-undo"></i>
                            Reset
                        </button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="responseChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="common/chart_utils.js"></script>
    <script>
        
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            initializeDashboard();
        });
        
        function loadSummaryStats() {
            console.log('Loading fire system monthly summary stats...');
            
            // First, test if elements exist
            const emergencyAlarmsEl = document.getElementById('emergencyAlarms');
            const avgHeatLevelEl = document.getElementById('avgHeatLevel');
            const totalFireIncidentsEl = document.getElementById('totalFireIncidents');
            const totalResponsesEl = document.getElementById('totalResponses');
            
            // Month subtitle elements
            const currentMonthEl = document.getElementById('currentMonth');
            const currentMonthHeatEl = document.getElementById('currentMonthHeat');
            const currentMonthFireEl = document.getElementById('currentMonthFire');
            const currentMonthResponsesEl = document.getElementById('currentMonthResponses');
            
            console.log('Elements found:', {
                emergencyAlarms: !!emergencyAlarmsEl,
                avgHeatLevel: !!avgHeatLevelEl,
                totalFireIncidents: !!totalFireIncidentsEl,
                totalResponses: !!totalResponsesEl,
                currentMonth: !!currentMonthEl,
                currentMonthHeat: !!currentMonthHeatEl,
                currentMonthFire: !!currentMonthFireEl,
                currentMonthResponses: !!currentMonthResponsesEl
            });
            
            if (!emergencyAlarmsEl || !avgHeatLevelEl || !totalFireIncidentsEl || !totalResponsesEl ||
                !currentMonthEl || !currentMonthHeatEl || !currentMonthFireEl || !currentMonthResponsesEl) {
                console.error('Some elements not found!');
                return;
            }
            
            // Try different possible paths for the API
            const apiPaths = [
                'get_fire_system_summary_stats.php',
                './get_fire_system_summary_stats.php',
                'php/get_fire_system_summary_stats.php',
                '../php/get_fire_system_summary_stats.php'
            ];
            
            let currentPathIndex = 0;
            
            function tryApiPath() {
                if (currentPathIndex >= apiPaths.length) {
                    console.error('All API paths failed');
                    // Set fallback values
                    emergencyAlarmsEl.textContent = 'N/A';
                    avgHeatLevelEl.textContent = 'N/A';
                    totalFireIncidentsEl.textContent = 'N/A';
                    totalResponsesEl.textContent = 'N/A';
                    
                    // Set fallback month values
                    const currentMonth = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                    console.log('Setting fallback month to:', currentMonth);
                    if (currentMonthEl) currentMonthEl.textContent = currentMonth;
                    if (currentMonthHeatEl) currentMonthHeatEl.textContent = currentMonth;
                    if (currentMonthFireEl) currentMonthFireEl.textContent = currentMonth;
                    if (currentMonthResponsesEl) currentMonthResponsesEl.textContent = currentMonth;
                    return;
                }
                
                const apiPath = apiPaths[currentPathIndex];
                console.log(`Trying API path: ${apiPath}`);
                
                fetch(apiPath)
                    .then(response => {
                        console.log(`Response received for ${apiPath}:`, response);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Data received:', data);
                        console.log('Current month from API:', data.current_month);
                        if (data.success) {
                            // Update alarm statistics with smooth animation
                            animateNumber(emergencyAlarmsEl, data.data.alarm_stats.emergency_alarms || 0);
                            
                            // Update heat analysis with smooth animation
                            const heatValue = (data.data.heat_analysis.avg_heat_level || 0) + '°C';
                            animateNumber(avgHeatLevelEl, heatValue);
                            
                            // Update fire data analysis with smooth animation
                            animateNumber(totalFireIncidentsEl, data.data.fire_data_analysis.total_fire_incidents || 0);
                            
                            // Update response data with smooth animation
                            animateNumber(totalResponsesEl, data.data.response_data.total_responses || 0);
                            
                            // Update month subtitles
                            const currentMonth = data.current_month || new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
                            console.log('Setting month subtitles to:', currentMonth);
                            console.log('Month elements found:', {
                                currentMonth: !!currentMonthEl,
                                currentMonthHeat: !!currentMonthHeatEl,
                                currentMonthFire: !!currentMonthFireEl,
                                currentMonthResponses: !!currentMonthResponsesEl
                            });
                            
                            if (currentMonthEl) currentMonthEl.textContent = currentMonth;
                            if (currentMonthHeatEl) currentMonthHeatEl.textContent = currentMonth;
                            if (currentMonthFireEl) currentMonthFireEl.textContent = currentMonth;
                            if (currentMonthResponsesEl) currentMonthResponsesEl.textContent = currentMonth;
                            
                            console.log('Fire system monthly stats updated successfully');
                        } else {
                            console.error('API returned error:', data.message);
                            currentPathIndex++;
                            tryApiPath();
                        }
                    })
                    .catch(error => {
                        console.error(`Error with path ${apiPath}:`, error);
                        currentPathIndex++;
                        tryApiPath();
                    });
            }
            
            tryApiPath();
        }
        
        function setupEventListeners() {
            // Alarm chart filters
            document.getElementById('alarmStatusFilter').addEventListener('change', loadAlarmChart);
            document.getElementById('alarmStartDate').addEventListener('change', loadAlarmChart);
            document.getElementById('alarmEndDate').addEventListener('change', loadAlarmChart);
            
            // Barangay chart filters
            document.getElementById('barangayFilter').addEventListener('change', loadBarangayChart);
            document.getElementById('barangayMonthFilter').addEventListener('change', loadBarangayChart);
            document.getElementById('barangayYearFilter').addEventListener('change', loadBarangayChart);
            
            // Incident chart filters
            document.getElementById('incidentStartDate').addEventListener('change', loadIncidentChart);
            document.getElementById('incidentEndDate').addEventListener('change', loadIncidentChart);
            
            // Response chart filters
            document.getElementById('responseStartDate').addEventListener('change', loadResponseChart);
            document.getElementById('responseEndDate').addEventListener('change', loadResponseChart);
            document.getElementById('responseFirefighterFilter').addEventListener('change', loadResponseChart);
        }
        
        function loadAllCharts() {
            loadAlarmChart();
            loadBarangayChart();
            loadIncidentChart();
            loadResponseChart();
        }
        
        function loadAlarmChart() {
            const status = document.getElementById('alarmStatusFilter').value;
            const startDate = document.getElementById('alarmStartDate').value;
            const endDate = document.getElementById('alarmEndDate').value;
            
            fetch(`get_alarm_stats.php?status=${status}&start_date=${startDate}&end_date=${endDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        createAlarmChart(data.data);
                    } else {
                        showError('alarmChart', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading alarm stats:', error);
                    showError('alarmChart', 'Failed to load alarm statistics');
                });
        }
        
        function loadBarangayChart() {
            const barangay = document.getElementById('barangayFilter').value;
            const month = document.getElementById('barangayMonthFilter').value;
            const year = document.getElementById('barangayYearFilter').value;
            
            const params = new URLSearchParams();
            if (barangay) params.append('barangay', barangay);
            if (month) params.append('month', month);
            if (year) params.append('year', year);
            
            fetch(`get_barangay_stats.php?${params.toString()}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Barangay stats data received:', data);
                    if (data.success) {
                        if (data.data && data.data.labels && data.data.labels.length > 0) {
                            createBarangayChart(data.data);
                        } else {
                            console.warn('No barangay data available');
                            showError('barangayChart', 'No data available for the selected filters');
                        }
                    } else {
                        console.error('Barangay stats error:', data.message);
                        showError('barangayChart', data.message || 'Failed to load barangay statistics');
                    }
                })
                .catch(error => {
                    console.error('Error loading barangay stats:', error);
                    showError('barangayChart', 'Failed to load barangay statistics: ' + error.message);
                });
        }
        
        function loadIncidentChart() {
            const startDate = document.getElementById('incidentStartDate').value;
            const endDate = document.getElementById('incidentEndDate').value;
            
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            
            fetch(`get_incident_stats.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        createIncidentChart(data.data);
                    } else {
                        showError('incidentChart', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading incident stats:', error);
                    showError('incidentChart', 'Failed to load incident statistics');
                });
        }
        
        function loadResponseChart() {
            const startDate = document.getElementById('responseStartDate').value;
            const endDate = document.getElementById('responseEndDate').value;
            const firefighterId = document.getElementById('responseFirefighterFilter').value;
            
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            if (firefighterId) params.append('firefighter_id', firefighterId);
            
            fetch(`get_response_stats.php?${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        createResponseChart(data.data);
                    } else {
                        showError('responseChart', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error loading response stats:', error);
                    showError('responseChart', 'Failed to load response statistics');
                });
        }
        
        function createAlarmChart(data) {
            const canvas = document.getElementById('alarmChart');
            if (!canvas) {
                console.error('Canvas element with ID "alarmChart" not found. Retrying in 100ms...');
                setTimeout(() => createAlarmChart(data), 100);
                return;
            }
            const ctx = canvas.getContext('2d');
            
            // Add loading animation
            const container = canvas.parentElement;
            container.classList.remove('loaded');
            
            if (alarmChart) {
                alarmChart.destroy();
            }
            
            // Ensure no negative values in data
            const fireData = data.fire_data.map(val => Math.max(0, val || 0));
            const normalData = data.normal_data.map(val => Math.max(0, val || 0));
            const warningData = data.warning_data.map(val => Math.max(0, val || 0));
            
            alarmChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Emergency Alarms',
                        data: fireData,
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Normal Status',
                        data: normalData,
                        borderColor: '#27ae60',
                        backgroundColor: 'rgba(39, 174, 96, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }, {
                        label: 'Acknowledged Status',
                        data: warningData,
                        borderColor: '#f39c12',
                        backgroundColor: 'rgba(243, 156, 18, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Alarm Trends Over Time'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            min: 0,
                            ticks: {
                                stepSize: 1,
                                min: 0,
                                callback: function(value) {
                                    return Math.max(0, value);
                                }
                            }
                        }
                    }
                }
            });
            
            // Mark as loaded after animation
            setTimeout(() => {
                container.classList.add('loaded');
            }, 2000);
        }
        
        function createBarangayChart(data) {
            const canvas = document.getElementById('barangayChart');
            if (!canvas) {
                console.error('Canvas element with ID "barangayChart" not found. Retrying in 100ms...');
                setTimeout(() => createBarangayChart(data), 100);
                return;
            }
            const ctx = canvas.getContext('2d');
            
            // Add loading animation
            const container = canvas.parentElement;
            container.classList.remove('loaded');
            
            if (barangayChart) {
                barangayChart.destroy();
            }
            
            // Validate data structure
            if (!data || !data.labels || !data.heat_data) {
                console.error('Invalid data structure:', data);
                showError('barangayChart', 'Invalid data structure received');
                return;
            }
            
            // Ensure arrays are the same length
            const minLength = Math.min(data.labels.length, data.heat_data.length);
            const labels = data.labels.slice(0, minLength);
            const heatData = data.heat_data.slice(0, minLength).map(val => Math.max(0, val || 0));
            
            console.log('Creating barangay chart with', minLength, 'data points');
            
            barangayChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Heat Index (°C)',
                        data: heatData,
                        backgroundColor: 'rgba(255, 99, 132, 0.8)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Barangay Heat Index Analysis'
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const index = context.dataIndex;
                                    
                                    // Safe array access with null checks
                                    const totalReadings = (data.total_readings && Array.isArray(data.total_readings) && data.total_readings[index]) || 0;
                                    const maxHeat = (data.max_heat && Array.isArray(data.max_heat) && data.max_heat[index]) || 0;
                                    const minHeat = (data.min_heat && Array.isArray(data.min_heat) && data.min_heat[index]) || 0;
                                    const avgTemp = (data.avg_temp && Array.isArray(data.avg_temp) && data.avg_temp[index]) || 0;
                                    const avgSmoke = (data.avg_smoke && Array.isArray(data.avg_smoke) && data.avg_smoke[index]) || 0;
                                    
                                    return [
                                        `Total Readings: ${totalReadings}`,
                                        `Max Heat: ${maxHeat}°C`,
                                        `Min Heat: ${minHeat}°C`,
                                        `Avg Temperature: ${avgTemp}°C`,
                                        `Avg Smoke: ${avgSmoke} PPM`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Barangay'
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Heat Index (°C)'
                            },
                            beginAtZero: true,
                            min: 0,
                            ticks: {
                                min: 0,
                                callback: function(value) {
                                    return Math.max(0, value) + '°C';
                                }
                            }
                        }
                    }
                }
            });
            
            // Mark as loaded after animation
            setTimeout(() => {
                container.classList.add('loaded');
            }, 2000);
        }
        
        function createIncidentChart(data) {
            const canvas = document.getElementById('incidentChart');
            if (!canvas) {
                console.error('Canvas element with ID "incidentChart" not found. Retrying in 100ms...');
                setTimeout(() => createIncidentChart(data), 100);
                return;
            }
            const ctx = canvas.getContext('2d');
            
            // Add loading animation
            const container = canvas.parentElement;
            container.classList.remove('loaded');
            
            if (incidentChart) {
                incidentChart.destroy();
            }
            
            // Ensure no negative values in incident data
            const incidentData = data.data.map(val => Math.max(0, val || 0));
            
            incidentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [
                        {
                            label: 'Fire Incidents',
                            data: incidentData,
                            backgroundColor: [
                                '#e74c3c',
                                '#f39c12',
                                '#27ae60',
                                '#3498db',
                                '#9b59b6',
                                '#1abc9c',
                                '#34495e',
                                '#e67e22'
                            ],
                            borderWidth: 2,
                            borderColor: '#fff'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: 'Fire Incidents Distribution',
                            font: {
                                size: 16,
                                weight: 'bold'
                            }
                        },
                        legend: {
                            display: true,
                            position: 'right'
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const index = context.dataIndex;
                                    
                                    // Safe array access with null checks
                                    const avgConfidence = (data.avg_ml_confidence && data.avg_ml_confidence[index]) || 0;
                                    const avgTemp = (data.avg_temp && data.avg_temp[index]) || 0;
                                    const avgHeat = (data.avg_heat && data.avg_heat[index]) || 0;
                                    const avgSmoke = (data.avg_smoke && data.avg_smoke[index]) || 0;
                                    const fireIncidents = (data.fire_incidents && data.fire_incidents[index]) || 0;
                                    const noFireIncidents = (data.no_fire_incidents && data.no_fire_incidents[index]) || 0;
                                    
                                    return [
                                        `Fire Incidents: ${fireIncidents}`,
                                        `No Fire Count: ${noFireIncidents}`,
                                        `Avg ML Confidence: ${avgConfidence}%`,
                                        `Avg Temperature: ${avgTemp}°C`,
                                        `Avg Heat: ${avgHeat}°C`,
                                        `Avg Smoke: ${avgSmoke} PPM`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Barangay - Building Type'
                            },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0
                            }
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Number of Incidents'
                            },
                            beginAtZero: true,
                            min: 0,
                            ticks: {
                                min: 0,
                                callback: function(value) {
                                    return Math.max(0, value);
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
            
            // Mark as loaded after animation
            setTimeout(() => {
                container.classList.add('loaded');
            }, 2000);
        }
        
        function createResponseChart(data) {
            const canvas = document.getElementById('responseChart');
            if (!canvas) {
                console.error('Canvas element with ID "responseChart" not found. Retrying in 100ms...');
                setTimeout(() => createResponseChart(data), 100);
                return;
            }
            const ctx = canvas.getContext('2d');
            
            // Add loading animation
            const container = canvas.parentElement;
            container.classList.remove('loaded');
            
            if (responseChart) {
                responseChart.destroy();
            }
            
            // Normalize data to same scale for better visualization
            const maxResponseTime = Math.max(...data.response_times);
            const maxResponseCount = Math.max(...data.response_counts);
            const maxValue = Math.max(maxResponseTime, maxResponseCount);
            
            // Normalize response times to 0-100 scale (ensure no negative values)
            const normalizedResponseTimes = data.response_times.map(time => {
                const normalized = maxValue > 0 ? (Math.max(0, time) / maxValue) * 100 : 0;
                return Math.max(0, normalized);
            });
            
            // Normalize response counts to 0-100 scale (ensure no negative values)
            const normalizedResponseCounts = data.response_counts.map(count => {
                const normalized = maxValue > 0 ? (Math.max(0, count) / maxValue) * 100 : 0;
                return Math.max(0, normalized);
            });
            
            responseChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Response Time (normalized)',
                        data: normalizedResponseTimes,
                        backgroundColor: 'rgba(75, 192, 192, 0.8)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    }, {
                        label: 'Number of Responses (normalized)',
                        data: normalizedResponseCounts,
                        backgroundColor: 'rgba(153, 102, 255, 0.8)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 2,
                        borderRadius: 4,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 2000,
                        easing: 'easeInOutQuart'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                usePointStyle: true,
                                padding: 20,
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            }
                        },
                        title: {
                            display: true,
                            text: 'Response Performance Analysis',
                            font: {
                                size: 16,
                                weight: 'bold'
                            },
                            padding: 20
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const datasetLabel = context.dataset.label;
                                    const normalizedValue = context.parsed.y;
                                    
                                    if (datasetLabel.includes('Response Time')) {
                                        const actualTime = data.response_times[context.dataIndex];
                                        return `${datasetLabel}: ${actualTime.toFixed(1)} minutes (${normalizedValue.toFixed(1)}%)`;
                                    } else {
                                        const actualCount = data.response_counts[context.dataIndex];
                                        return `${datasetLabel}: ${actualCount} responses (${normalizedValue.toFixed(1)}%)`;
                                    }
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Firefighter / Time Period',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0,0,0,0.1)'
                            }
                        },
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            min: 0,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Normalized Performance (%)',
                                font: {
                                    size: 12,
                                    weight: 'bold'
                                }
                            },
                            ticks: {
                                stepSize: 10,
                                min: 0,
                                callback: function(value) {
                                    return Math.max(0, value) + '%';
                                }
                            },
                            grid: {
                                display: true,
                                color: 'rgba(0,0,0,0.1)'
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    }
                }
            });
            
            // Mark as loaded after animation
            setTimeout(() => {
                container.classList.add('loaded');
            }, 2000);
        }
        
        function showError(chartId, message) {
            const element = document.getElementById(chartId);
            if (!element) {
                console.error(`Element with ID "${chartId}" not found`);
                return;
            }
            const container = element.parentElement;
            if (container) {
                container.innerHTML = `<div class="error-message">${message}</div>`;
            }
        }
        
        // Reset functions are now handled by FilterManager in chart_utils.js
    </script>
</body>
 <!-- Include header components -->
 <?php include '../../components/scripts.php'; ?>
</html>

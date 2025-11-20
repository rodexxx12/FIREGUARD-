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
    <div class="main-card">
                <!-- Main Content -->
                <div class="row">
            <div class="col-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fas fa-list-alt"></i> Alarm Records</h2>
                        <div class="clearfix"></div>
                    </div>
        <!-- Filter Section -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filters</h5>
                </div>
            <div class="card-body">
                <div class="filter-grid">
                    <div class="row">
                        <!-- Left Column -->
                        <div class="col-md-6">
                            <!-- Building Type Filter -->
                            <div class="filter-group">
                                <label class="form-label">Building Type</label>
                                <select class="form-control" id="buildingTypeFilter" onchange="filterColumn(0, this.value)">
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
                                <label class="form-label">Device Status</label>
                                <select class="form-control" id="deviceStatusFilter" onchange="filterColumn(3, this.value)">
                                    <option value="">All Device Status</option>
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                                <option value="Faulty">Faulty</option>
                                    <option value="No Device">No Device</option>
                            </select>
                            </div>
                            
                            <!-- Alarm Status Filter -->
                            <div class="filter-group">
                                <label class="form-label">Alarm Status</label>
                                <select class="form-control" id="alarmStatusFilter" onchange="filterColumn(4, this.value)">
                                    <option value="">All Alarm Status</option>
                                    <option value="MISSED">Emergency (Missed)</option>
                                    <option value="ACKNOWLEDGED">Acknowledged</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-md-6">
                            <!-- User Filter -->
                            <div class="filter-group">
                                <label class="form-label">User</label>
                                <select class="form-control" id="userFilter" onchange="filterColumn(1, this.value)">
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
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDateFilter" onchange="applyDateFilter()">
                            </div>

                            <div class="filter-group">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDateFilter" onchange="applyDateFilter()">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Actions -->
                <div class="filter-actions">
                    <div class="filter-stats">
                        <span id="filterStats">Showing all records</span>
                    </div>
                    <div class="filter-buttons">
                        <button class="btn btn-outline-secondary" onclick="resetAllFilters()">🔄 Reset All</button>
                        <button class="btn btn-success" onclick="exportToCSV()">📊 Export CSV</button>
                    </div>
                </div>
            </div>
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
    </script>
    <!-- Include header components -->
    <?php include '../../components/scripts.php'; ?>
</body>
</html>

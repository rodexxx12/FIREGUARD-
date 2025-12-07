<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

// Query to get all alarms
$query = "
    SELECT 
        id,
        status,
        timestamp,
        acknowledgment_id,
        acknowledged_at,
        building_type,
        temp,
        heat,
        smoke,
        flame_detected
    FROM alarms
    ORDER BY timestamp DESC
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $alarms = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching alarms: " . $e->getMessage());
    $alarms = [];
}

// Get unique values for filters
$statuses = array_unique(array_column($alarms, 'status'));
$statuses = array_filter($statuses);
sort($statuses);

$building_types = array_unique(array_column($alarms, 'building_type'));
$building_types = array_filter($building_types);
sort($building_types);
?>

<?php include('../../components/header.php'); ?>

    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/css/dataTables.bootstrap5.min.css">
    
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
        .status-acknowledged { background-color: #dbeafe; color: #1e40af; }
        .status-emergency { background-color: #fee2e2; color: #991b1b; }
        
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
        
        .flame-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 6px;
        }
        
        .flame-yes { background-color: #ef4444; }
        .flame-no { background-color: #10b981; }
        
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
                        <h2><i class="fas fa-bell"></i> Alarms</h2>
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
                            <!-- Status Filter -->
                            <div class="filter-group">
                                <label class="form-label">Status</label>
                                <select class="form-control" id="statusFilter" onchange="filterColumn(1, this.value)">
                                    <option value="">All Status</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Building Type Filter -->
                            <div class="filter-group">
                                <label class="form-label">Building Type</label>
                                <select class="form-control" id="buildingTypeFilter" onchange="filterColumn(5, this.value)">
                                    <option value="">All Building Types</option>
                                    <?php foreach ($building_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Flame Detected Filter -->
                            <div class="filter-group">
                                <label class="form-label">Flame Detected</label>
                                <select class="form-control" id="flameFilter" onchange="filterColumn(9, this.value)">
                                    <option value="">All</option>
                                    <option value="1">Yes</option>
                                    <option value="0">No</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Right Column -->
                        <div class="col-md-6">
                            <!-- Date Range Filter -->
                            <div class="filter-group">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="startDateFilter" onchange="applyDateFilter()">
                            </div>

                            <div class="filter-group">
                                <label class="form-label">End Date</label>
                                <input type="date" class="form-control" id="endDateFilter" onchange="applyDateFilter()">
                            </div>
                            
                            <!-- Acknowledgment Filter -->
                            <div class="filter-group">
                                <label class="form-label">Acknowledgment</label>
                                <select class="form-control" id="acknowledgmentFilter" onchange="filterColumn(3, this.value)">
                                    <option value="">All</option>
                                    <option value="has">Has Acknowledgment</option>
                                    <option value="none">No Acknowledgment</option>
                                </select>
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
            <table id="alarmsTable" class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th style="display: none;">ID</th>
                        <th>Status</th>
                        <th>Timestamp</th>
                        <th style="display: none;">Acknowledgment ID</th>
                        <th style="display: none;">Acknowledged At</th>
                        <th>Building Type</th>
                        <th>Temperature (°C)</th>
                        <th>Heat (°C)</th>
                        <th>Smoke (ppm)</th>
                        <th>Flame Detected</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alarms as $alarm): ?>
                    <tr>
                        <td style="display: none;"><?php echo htmlspecialchars($alarm['id']); ?></td>
                        <td>
                            <?php 
                            $status = $alarm['status'] ?? 'Unknown';
                            $status_class = strtolower($status);
                            if ($status_class === 'acknowledged') {
                                $status_class = 'acknowledged';
                            } elseif ($status_class === 'emergency') {
                                $status_class = 'emergency';
                            } else {
                                $status_class = 'normal';
                            }
                            ?>
                            <span class="status-badge status-<?php echo $status_class; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($alarm['timestamp']): ?>
                                <div>
                                    <strong><?php echo date('M j, Y', strtotime($alarm['timestamp'])); ?></strong><br>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($alarm['timestamp'])); ?></small>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="display: none;">
                            <?php echo $alarm['acknowledgment_id'] ? htmlspecialchars($alarm['acknowledgment_id']) : '<span class="text-muted">-</span>'; ?>
                        </td>
                        <td style="display: none;">
                            <?php if ($alarm['acknowledged_at']): ?>
                                <div>
                                    <strong><?php echo date('M j, Y', strtotime($alarm['acknowledged_at'])); ?></strong><br>
                                    <small class="text-muted"><?php echo date('H:i:s', strtotime($alarm['acknowledged_at'])); ?></small>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($alarm['building_type'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($alarm['temp'] ?? '0'); ?></td>
                        <td><?php echo htmlspecialchars($alarm['heat'] ?? '0'); ?></td>
                        <td><?php echo htmlspecialchars($alarm['smoke'] ?? '0'); ?></td>
                        <td>
                            <?php 
                            $flame = $alarm['flame_detected'] ?? 0;
                            if ($flame == 1 || $flame === '1' || $flame === true): ?>
                                <span class="flame-indicator flame-yes"></span>
                                <span class="text-danger">Yes</span>
                            <?php else: ?>
                                <span class="flame-indicator flame-no"></span>
                                <span class="text-success">No</span>
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
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Success!',
                    text: message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        }
        
        // Function to initialize DataTable
        function initializeDataTable() {
            console.log('Initializing DataTable...');
            
            // Prevent multiple initializations
            if (window.dataTableInitialized) {
                console.log('DataTable already initialized, skipping...');
                return true;
            }
            
            // Check if DataTable is already initialized and destroy safely
            if ($.fn.DataTable.isDataTable('#alarmsTable')) {
                console.log('DataTable already initialized, destroying first...');
                try {
                    $('#alarmsTable').DataTable().destroy();
                } catch (e) {
                    console.log('Error destroying existing DataTable:', e);
                }
            }
            
            try {
                // Initialize DataTable with enhanced pagination
                dataTable = $('#alarmsTable').DataTable({
                    responsive: true,
                    pageLength: 25,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                    order: [[2, 'desc']], // Sort by timestamp column (most recent first)
                    columnDefs: [
                        { visible: false, targets: [0, 3, 4] }, // Hide ID, Acknowledgment ID, and Acknowledged At columns
                        { className: "text-center", targets: [1, 9] }, // Status and Flame Detected
                        { orderable: true, targets: '_all' }
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
                        window.dataTableInitialized = true;
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
            
            // Wait for DataTables to be available
            if (typeof $.fn.DataTable !== 'undefined') {
                initializeDataTable();
            } else {
                console.warn('DataTables not yet loaded, retrying...');
                setTimeout(function() {
                    if (typeof $.fn.DataTable !== 'undefined') {
                        initializeDataTable();
                    } else {
                        console.error('DataTables not available');
                    }
                }, 500);
            }
        });
        
        // Filter individual columns using DataTables column().search()
        function filterColumn(columnIndex, value) {
            // Prevent multiple simultaneous calls
            if (window.columnFilterInProgress) {
                console.log('Column filter already in progress, skipping...');
                return;
            }
            
            window.columnFilterInProgress = true;
            
            try {
                if (dataTable) {
                    // Special handling for acknowledgment filter
                    if (columnIndex === 3 && (value === 'has' || value === 'none')) {
                        // Clear existing acknowledgment filter
                        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                            return !fn._isAcknowledgmentFilter;
                        });
                        
                        if (value !== '') {
                            // Apply custom acknowledgment filtering
                            const ackFilterFn = function(settings, data, dataIndex) {
                                if (settings.nTable.id !== 'alarmsTable') {
                                    return true;
                                }
                                
                                const ackId = data[3]; // Column 3 contains acknowledgment_id
                                const ackAt = data[4]; // Column 4 contains acknowledged_at
                                
                                const hasAcknowledgment = (ackId && ackId.trim() !== '-' && ackId.trim() !== '') || 
                                                          (ackAt && ackAt.trim() !== '-' && ackAt.trim() !== '');
                                
                                if (value === 'has') {
                                    return hasAcknowledgment;
                                } else if (value === 'none') {
                                    return !hasAcknowledgment;
                                }
                                
                                return true;
                            };
                            
                            ackFilterFn._isAcknowledgmentFilter = true;
                            $.fn.dataTable.ext.search.push(ackFilterFn);
                        }
                        
                        dataTable.draw();
                        updateFilterStats();
                    } else {
                        // Use regex search for better matching
                        if (value === '' || value === null) {
                            dataTable.column(columnIndex).search('').draw();
                        } else {
                            // Escape special regex characters and create case-insensitive search
                            const escapedValue = value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                            dataTable.column(columnIndex).search(escapedValue, true, false).draw();
                        }
                        console.log(`Filtering column ${columnIndex} with value: "${value}"`);
                    }
                } else {
                    console.log('DataTable not available, using basic filtering...');
                    // Basic filtering for fallback mode
                    if (columnIndex === 3 && (value === 'has' || value === 'none')) {
                        // Special handling for acknowledgment filter in basic mode
                        $('#alarmsTable tbody tr').each(function() {
                            var row = $(this);
                            var ackIdCell = row.find('td').eq(3).text().trim();
                            var ackAtCell = row.find('td').eq(4).text().trim();
                            
                            const hasAcknowledgment = (ackIdCell !== '-' && ackIdCell !== '') || 
                                                      (ackAtCell !== '-' && ackAtCell !== '');
                            
                            if (value === 'has' && hasAcknowledgment) {
                                row.show();
                            } else if (value === 'none' && !hasAcknowledgment) {
                                row.show();
                            } else if (value === '') {
                                row.show();
                            } else {
                                row.hide();
                            }
                        });
                    } else {
                        var searchValue = value.toLowerCase();
                        $('#alarmsTable tbody tr').each(function() {
                            var row = $(this);
                            var cellText = row.find('td').eq(columnIndex).text().toLowerCase();
                            if (value === '' || cellText.indexOf(searchValue) > -1) {
                                row.show();
                            } else {
                                row.hide();
                            }
                        });
                    }
                }
            } catch (error) {
                console.error('Error in column filtering:', error);
            } finally {
                window.columnFilterInProgress = false;
            }
        }
        
        // Helper function to parse date from table cell
        function parseDateFromCell(timeText) {
            // Clean the text - remove HTML tags and extra whitespace
            const cleanText = timeText.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
            
            // Extract date from timestamp string (format: "Jan 15, 2025")
            const dateMatch = cleanText.match(/(\w{3})\s+(\d{1,2}),\s+(\d{4})/);
            if (!dateMatch) {
                return null;
            }
            
            const [, month, day, year] = dateMatch;
            const monthMap = {
                'Jan': '01', 'Feb': '02', 'Mar': '03', 'Apr': '04',
                'May': '05', 'Jun': '06', 'Jul': '07', 'Aug': '08',
                'Sep': '09', 'Oct': '10', 'Nov': '11', 'Dec': '12'
            };
            
            const recordDate = `${year}-${monthMap[month]}-${day.padStart(2, '0')}`;
            return recordDate;
        }
        
        // Date filter function with start and end dates
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
                    // DataTables mode
                    console.log('Using DataTables date filtering...');
                    
                    // Clear existing custom search functions safely
                    if ($.fn.dataTable.ext.search) {
                        // Remove only our date filter function
                        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                            return !fn._isDateFilter;
                        });
                    }
                    
                    // If no dates selected, don't apply any filter
                    if (!startDate && !endDate) {
                        dataTable.draw();
                        updateFilterStats();
                        window.dateFilterInProgress = false;
                        return;
                    }
                    
                    // Apply custom date filtering
                    const dateFilterFn = function(settings, data, dataIndex) {
                        // Prevent infinite loops by checking if this is the same table
                        if (settings.nTable.id !== 'alarmsTable') {
                            return true;
                        }
                        
                        const alarmTimestamp = data[2]; // Column 2 contains the timestamp
                        const recordDate = parseDateFromCell(alarmTimestamp);
                        
                        if (!recordDate) return true; // If no date found, include the row
                        
                        // Check if record date is within range
                        if (startDate && recordDate < startDate) return false;
                        if (endDate && recordDate > endDate) return false;
                        
                        return true;
                    };
                    
                    // Mark this as a date filter function
                    dateFilterFn._isDateFilter = true;
                    
                    $.fn.dataTable.ext.search.push(dateFilterFn);
                    
                    dataTable.draw();
                    updateFilterStats();
                    console.log('DataTables date filter applied successfully');
                    
                } else {
                    // Basic mode
                    console.log('Using basic date filtering...');
                    
                    // If no dates selected, show all rows
                    if (!startDate && !endDate) {
                        $('#alarmsTable tbody tr').show();
                        console.log('Date filter cleared - showing all rows');
                        window.dateFilterInProgress = false;
                        return;
                    }
                    
                    // Filter rows based on date
                    let visibleCount = 0;
                    let totalCount = 0;
                    
                    $('#alarmsTable tbody tr').each(function() {
                        totalCount++;
                        const row = $(this);
                        const timeCell = row.find('td').eq(2); // Column 2 contains the timestamp
                        const timeText = timeCell.html();
                        
                        const recordDate = parseDateFromCell(timeText);
                        if (!recordDate) {
                            row.show();
                            visibleCount++;
                            return;
                        }
                        
                        // Check if record date is within range
                        let shouldShow = true;
                        if (startDate && recordDate < startDate) {
                            shouldShow = false;
                        }
                        if (endDate && recordDate > endDate) {
                            shouldShow = false;
                        }
                        
                        if (shouldShow) {
                            row.show();
                            visibleCount++;
                        } else {
                            row.hide();
                        }
                    });
                    
                    console.log(`Basic date filter applied: ${visibleCount} of ${totalCount} rows visible`);
                }
            } catch (error) {
                console.error('Error in date filtering:', error);
            } finally {
                window.dateFilterInProgress = false;
            }
        }
        
        // Reset all filters
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
                document.getElementById('statusFilter').value = '';
                document.getElementById('buildingTypeFilter').value = '';
                document.getElementById('flameFilter').value = '';
                document.getElementById('acknowledgmentFilter').value = '';
                document.getElementById('startDateFilter').value = '';
                document.getElementById('endDateFilter').value = '';
                
                // Clear DataTable search and column filters
                if (dataTable) {
                    // Clear all custom search functions safely
                    if ($.fn.dataTable.ext.search) {
                        $.fn.dataTable.ext.search = $.fn.dataTable.ext.search.filter(function(fn) {
                            return !fn._isDateFilter && !fn._isAcknowledgmentFilter;
                        });
                    }
                    dataTable.search('').columns().search('').draw();
                    updateFilterStats();
                    console.log('DataTable filters cleared');
                } else {
                    // Show all rows for basic mode
                    $('#alarmsTable tbody tr').show();
                    console.log('Basic filters cleared');
                }
                
                showFilterSuccessModal('All filters have been reset successfully!');
            } catch (error) {
                console.error('Error resetting filters:', error);
            } finally {
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
        
        // Enhanced export function
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
                    $('#alarmsTable thead th').each(function() {
                        headers.push($(this).text().trim());
                    });
                    
                    // Get visible/filtered rows
                    dataTable.rows({search: 'applied'}).every(function() {
                        const rowData = [];
                        const rowNode = this.node();
                        
                        $(rowNode).find('td').each(function() {
                            // Clean the cell content - remove HTML tags and extra whitespace
                            let cellText = $(this).text().trim();
                            cellText = cellText.replace(/\s+/g, ' ');
                            rowData.push(cellText);
                        });
                        
                        rows.push(rowData);
                    });
                    
                } else {
                    // Fallback: get data directly from table HTML
                    console.log('Using direct HTML parsing for export...');
                    
                    // Get headers
                    $('#alarmsTable thead th').each(function() {
                        headers.push($(this).text().trim());
                    });
                    
                    // Get visible rows only
                    $('#alarmsTable tbody tr:visible').each(function() {
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
                
                const filename = `alarms_export_${timestamp}.csv`;
                
                // Download the file
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
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
    </script>
    <!-- Include header components -->
    <?php include '../../components/scripts.php'; ?>
</body>
</html>


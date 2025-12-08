<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

// Fetch response data with names from related tables
$query = "
    SELECT 
        r.id,
        r.fire_data_id,
        r.response_type,
        r.notes,
        r.responded_by,
        r.timestamp,
        r.firefighter_id,
        f.name as firefighter_name,
        br.barangay_name
    FROM responses r
    LEFT JOIN firefighters f ON r.firefighter_id = f.id
    LEFT JOIN fire_data fd ON r.fire_data_id = fd.id
    LEFT JOIN barangay br ON fd.barangay_id = br.id
    ORDER BY r.timestamp DESC
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $responses = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching responses: " . $e->getMessage());
    $responses = [];
}
?>

    
    <!-- Custom CSS for response table -->
    <link rel="stylesheet" href="../css/response_table.css">
    
  
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
            <!-- Filter Overlay + Side Panel -->
            <div class="filter-overlay" id="filterOverlay" onclick="toggleFilterPanel()"></div>
            <div class="filter-panel" id="filterPanel">
                <div class="filter-panel-header">
                    <h3><i class="fa fa-filter" style="margin-right: 8px;"></i> Filters</h3>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleFilterPanel()" title="Close Filters">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
                <div class="filter-panel-body">
                    <div class="filter-group">
                        <label for="filterFirefighter" class="form-label">Firefighter:</label>
                        <select id="filterFirefighter" class="form-select form-select-sm">
                            <option value="">All Firefighters</option>
                            <option value="N/A">No Firefighter Assigned</option>
                            <?php
                            $firefighterQuery = "
                                SELECT DISTINCT f.name 
                                FROM responses r 
                                LEFT JOIN firefighters f ON r.firefighter_id = f.id 
                                WHERE f.name IS NOT NULL 
                                ORDER BY f.name
                            ";
                            $firefighterStmt = $conn->prepare($firefighterQuery);
                            $firefighterStmt->execute();
                            $firefighters = $firefighterStmt->fetchAll();
                            foreach ($firefighters as $firefighter) {
                                echo "<option value='{$firefighter['name']}'>{$firefighter['name']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filterStartDate" class="form-label">Start Date:</label>
                        <input type="date" id="filterStartDate" class="form-control form-control-sm" placeholder="mm/dd/yyyy">
                    </div>
                    <div class="filter-group">
                        <label for="filterEndDate" class="form-label">End Date:</label>
                        <input type="date" id="filterEndDate" class="form-control form-control-sm" placeholder="mm/dd/yyyy">
                    </div>
                    <div class="filter-group">
                        <div id="filterStatus" class="text-muted">
                            <span class="badge badge-secondary">No filters active</span>
                        </div>
                    </div>
                    <div class="filter-group">
                        <button type="button" id="clearAllFilters" class="btn btn-outline-secondary btn-sm" style="width: 100%; margin-bottom: 10px;">
                            <i class="fas fa-times"></i> Clear All
                        </button>
                        <button type="button" id="exportCSV" class="btn btn-success btn-sm" style="width: 100%;">
                            <i class="fas fa-file-csv"></i> Export to CSV
                        </button>
                    </div>
                </div>
            </div>

        <!-- Main Content -->
        <div class="row">
            <div class="col-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fas fa-list-alt"></i> Response Records</h2>
                        <ul class="nav navbar-right panel_toolbox">
                            <li>
                                <a class="filter-toggle-btn" id="filterToggleBtn" title="Toggle Filters">
                                    <span class="burger-line"></span>
                                    <span class="burger-line"></span>
                                    <span class="burger-line"></span>
                                </a>
                            </li>
                        </ul>
                        <div class="clearfix"></div>
                    </div>
                            <div class="x_content">
                            
     <div class="x_panel">

                                <div class="row mb-3">
                                    <div class="col-12">
                                        <div class="d-flex flex-wrap align-items-center gap-3 justify-content-between" id="tableToolbar">
                                            <div class="d-flex align-items-center gap-2 simple-length-control">
                                                <label for="pageLength" class="mb-0">Show</label>
                                                <select id="pageLength" class="form-select form-select-sm" style="width: 100px;">
                                                    <option value="10">10</option>
                                                    <option value="25">25</option>
                                                    <option value="50">50</option>
                                                    <option value="100">100</option>
                                                    <option value="-1">All</option>
                                                </select>
                                                <span>records per page</span>
                                            </div>
                                            <div id="tableSearchHolder" class="flex-grow-1 d-flex justify-content-end"></div>
                                        </div>
                                    </div>
                                </div>

                    <div class="x_content">
                                <div class="table-responsive">
                                    <table id="responseTable" class="table table-striped table-bordered" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th style="display: none;">ID</th>
                                                <th style="display: none;">Fire Data ID</th>
                                                <th>Response Type</th>
                                                <th>Responded By</th>
                                                <th>Timestamp</th>
                                                <th>Firefighter Name</th>
                                                <th>Barangay</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($responses as $response): ?>
                                            <tr>
                                                <td style="display: none;"><?php echo htmlspecialchars($response['id']); ?></td>
                                                <td style="display: none;"><?php echo htmlspecialchars($response['fire_data_id']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $response['response_type'] === 'Emergency' ? 'danger' : ($response['response_type'] === 'Routine' ? 'primary' : 'warning'); ?>">
                                                        <?php echo htmlspecialchars($response['response_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($response['responded_by']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($response['timestamp'])); ?></td>
                                                <td><?php echo $response['firefighter_name'] ? htmlspecialchars($response['firefighter_name']) : 'N/A'; ?></td>
                                                <td><?php echo $response['barangay_name'] ? htmlspecialchars($response['barangay_name']) : 'N/A'; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                 
 
    
    <!-- Custom JavaScript -->
    <script>
        // Toggle filter side panel and overlay
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
        
        $(document).ready(function() {
            // Cache selectors once for better performance
            const $filterPanel = $('#filterPanel');
            const $filterOverlay = $('#filterOverlay');
            const $filterToggleBtn = $('#filterToggleBtn');
            const $filterFirefighter = $('#filterFirefighter');
            const $filterStartDate = $('#filterStartDate');
            const $filterEndDate = $('#filterEndDate');
            const $filterStatus = $('#filterStatus');

            // Burger toggle open/close for side panel
            $filterToggleBtn.on('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleFilterPanel();
            });
            
            // Close when clicking overlay or outside the panel
            document.addEventListener('click', function(event) {
                if ($filterOverlay.hasClass('active') &&
                    !$filterPanel[0].contains(event.target) &&
                    !$filterToggleBtn[0].contains(event.target)) {
                    toggleFilterPanel();
                }
            });
            
            // Initialize DataTable with enhanced filtering capabilities
            var table = $('#responseTable').DataTable({
                "pageLength": 25,
                "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                "order": [[0, "desc"]],
                "responsive": true,
                "scrollX": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "paging": true,
                "processing": true,
                "language": {
                    "search": "Search all columns:",
                    "lengthMenu": "Show _MENU_ entries per page",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "No entries found",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "processing": "Loading data...",
                    "emptyTable": "No response records found",
                    "zeroRecords": "No matching records found"
                },
                "columnDefs": [
                    {
                        "targets": [0, 1], // Hide ID and Fire Data ID columns
                        "visible": false
                    },
                    {
                        "targets": [2], // Response Type column
                        "render": function(data, type, row) {
                            if (type === 'display') {
                                var badgeClass = 'badge-secondary';
                                if (data === 'Emergency') badgeClass = 'badge-danger';
                                else if (data === 'Routine') badgeClass = 'badge-primary';
                                else if (data === 'False Alarm') badgeClass = 'badge-warning';
                                else if (data === 'Training') badgeClass = 'badge-info';
                                
                                return '<span class="badge ' + badgeClass + '">' + data + '</span>';
                            }
                            return data;
                        }
                    },
                    {
                        "targets": [5, 6], // Firefighter Name and Barangay columns
                        "render": function(data, type, row) {
                            if (type === 'display') {
                                return data || 'N/A';
                            }
                            return data;
                        }
                    },
                    {
                        "targets": [4], // Timestamp column
                        "type": "date",
                        "render": function(data, type, row) {
                            if (type === 'display') {
                                return data;
                            }
                            return data;
                        }
                    }
                ],
                "dom": 'Bfrtip',
                "buttons": [
                    {
                        "extend": 'excel',
                        "text": '<i class="fas fa-file-excel"></i> Export Excel',
                        "className": 'btn btn-success btn-sm d-none'
                    },
                    {
                        "extend": 'pdf',
                        "text": '<i class="fas fa-file-pdf"></i> Export PDF',
                        "className": 'btn btn-danger btn-sm d-none'
                    },
                    {
                        "extend": 'print',
                        "text": '<i class="fas fa-print"></i> Print',
                        "className": 'btn btn-info btn-sm d-none'
                    }
                ]
            });
            
            // Move DataTables search into the toolbar row so it sits beside the length selector
            var $dtFilter = $('#responseTable_filter');
            $dtFilter.appendTo('#tableSearchHolder');
            // Tidy default markup for inline fit
            $dtFilter.addClass('mb-0');
            $dtFilter.find('label').addClass('mb-0 d-flex align-items-center gap-2 justify-content-end w-100');
            $dtFilter.find('input').addClass('form-control form-control-sm ms-2').css('width', '220px');

            // Page length control
            $('#pageLength').val('25');
            table.page.len(25).draw();
            $('#pageLength').on('change', function() {
                var len = parseInt($(this).val(), 10);
                table.page.len(len).draw();
            });

            // DataTables-only functionality - no custom search boxes

            // Advanced Filter Functions
            let dateRangeFilterRef = null; // keep single filter function instance
            function applyAdvancedFilters() {
                // Reset global search quickly
                table.search('').columns().search('');

                // Apply Firefighter filter
                var firefighter = $filterFirefighter.val();
                if (firefighter) {
                    table.column(5).search(firefighter, false, false);
                }
                
                // Apply Date Range filter (ensure only one filter is registered)
                var startDate = $filterStartDate.val();
                var endDate = $filterEndDate.val();

                if (dateRangeFilterRef) {
                    const idx = $.fn.dataTable.ext.search.indexOf(dateRangeFilterRef);
                    if (idx !== -1) $.fn.dataTable.ext.search.splice(idx, 1);
                }

                if (startDate || endDate) {
                    dateRangeFilterRef = function(settings, data) {
                        var timestamp = new Date(data[4]); // Column 4 is timestamp
                        var timestampDate = new Date(timestamp.getFullYear(), timestamp.getMonth(), timestamp.getDate());
                        
                        var start = startDate ? new Date(startDate) : null;
                        var end = endDate ? new Date(endDate) : null;
                        
                        if (start && end) return timestampDate >= start && timestampDate <= end;
                        if (start) return timestampDate >= start;
                        if (end) return timestampDate <= end;
                        return true;
                    };
                    $.fn.dataTable.ext.search.push(dateRangeFilterRef);
                } else {
                    dateRangeFilterRef = null;
                }
                
                // Redraw the table with all filters applied
                table.draw(false);
            }
            
            function clearAllAdvancedFilters() {
                // Clear dropdown filters
                $filterFirefighter.val('');
                $filterStartDate.val('');
                $filterEndDate.val('');
                
                // Clear DataTables filters
                table.search('').columns().search('');
                
                // Remove custom date filter
                if (dateRangeFilterRef) {
                    const idx = $.fn.dataTable.ext.search.indexOf(dateRangeFilterRef);
                    if (idx !== -1) $.fn.dataTable.ext.search.splice(idx, 1);
                    dateRangeFilterRef = null;
                }
                table.draw(false);
            }
            
            // Event handlers for advanced filters
            $('#applyFilters').on('click', function() {
                applyAdvancedFilters();
            });
            
            $('#clearAllFilters').on('click', function() {
                clearAllAdvancedFilters();
                
                // Show SweetAlert success modal
                Swal.fire({
                    icon: 'success',
                    title: 'Filters Cleared!',
                    text: 'All filters have been successfully cleared.',
                    showConfirmButton: false,
                    timer: 1500
                });
            });
            
            // Auto-apply filters when dropdowns change
            $filterFirefighter.add($filterStartDate).add($filterEndDate).on('change', function() {
                applyAdvancedFilters();
            });
            
            // Add filter status indicator
            function updateFilterStatus() {
                var activeFilters = 0;
                if ($filterFirefighter.val()) activeFilters++;
                if ($filterStartDate.val()) activeFilters++;
                if ($filterEndDate.val()) activeFilters++;
                
                var statusText = activeFilters > 0 ? 
                    '<span class="badge badge-info">' + activeFilters + ' filter(s) active</span>' : 
                    '<span class="badge badge-secondary">No filters active</span>';
                
                $filterStatus.html(statusText);
            }
            
            // Update filter status on table draw
            table.on('draw', function() {
                updateFilterStatus();
            });
            
            // Initial filter status
            updateFilterStatus();
            
            // CSV Export functionality
            $('#exportCSV').on('click', function() {
                // Get current filtered data
                var filteredData = table.rows({search: 'applied'}).data().toArray();
                
                if (filteredData.length === 0) {
                    alert('No data to export');
                    return;
                }
                
                // Create CSV content (excluding ID and Fire Data ID columns)
                var csvContent = "Response Type,Responded By,Timestamp,Firefighter Name,Barangay\n";
                
                filteredData.forEach(function(row) {
                    // Skip the first two columns (ID and Fire Data ID) and clean the data for CSV (remove HTML tags and escape commas)
                    var cleanRow = row.slice(2).map(function(cell) {
                        if (typeof cell === 'string') {
                            // Remove HTML tags and escape quotes
                            var cleaned = cell.replace(/<[^>]*>/g, '').replace(/"/g, '""');
                            // Wrap in quotes if contains comma or quote
                            if (cleaned.includes(',') || cleaned.includes('"') || cleaned.includes('\n')) {
                                return '"' + cleaned + '"';
                            }
                            return cleaned;
                        }
                        return cell;
                    });
                    csvContent += cleanRow.join(',') + '\n';
                });
                
                // Create and download the file
                var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'response_records_' + new Date().toISOString().slice(0, 10) + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // Show SweetAlert success notification
                Swal.fire({
                    icon: 'success',
                    title: 'Export Successful!',
                    text: 'Response records have been exported to CSV successfully.',
                    showConfirmButton: false,
                    timer: 2000
                });
            });
        });
    </script>
     <!-- Include header components -->
 <?php include '../../components/scripts.php'; ?>
</body>

</html>

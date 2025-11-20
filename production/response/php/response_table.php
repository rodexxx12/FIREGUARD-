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
        r.building_id,
        f.name as firefighter_name,
        bd.building_name
    FROM responses r
    LEFT JOIN firefighters f ON r.firefighter_id = f.id
    LEFT JOIN buildings bd ON r.building_id = bd.id
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

<?php include('../../components/header.php'); ?>
    <!-- jQuery (must be loaded first) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS for response table -->
    <link rel="stylesheet" href="../css/response_table.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
   
  

        <!-- Main Content -->
        <div class="row">
            <div class="col-12">
                <div class="x_panel">
                    <div class="x_title">
                        <h2><i class="fas fa-list-alt"></i> Response Records</h2>
                        <div class="clearfix"></div>
                    </div>
                            <div class="x_content">
                                <!-- Advanced Filters Panel -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card">
                                           
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-4">
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
                                                    <div class="col-md-4">
                                                        <label for="filterStartDate" class="form-label">Start Date:</label>
                                                        <input type="date" id="filterStartDate" class="form-control form-control-sm" placeholder="Start Date">
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label for="filterEndDate" class="form-label">End Date:</label>
                                                        <input type="date" id="filterEndDate" class="form-control form-control-sm" placeholder="End Date">
                                                    </div>
                                                </div>
                                                <div class="row mt-3">
                                                    <div class="col-md-8">
                                                        <div id="filterStatus" class="text-muted">
                                                            <span class="badge badge-secondary">No filters active</span>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4 text-end">
                                                        <button type="button" id="clearAllFilters" class="btn btn-outline-secondary btn-sm me-2">
                                                            <i class="fas fa-times"></i> Clear All
                                                        </button>
                                                        <!-- <button type="button" id="applyFilters" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-search"></i> Apply
                                                        </button> -->
                                                        <button type="button" id="exportCSV" class="btn btn-success btn-sm ms-2">
                                                            <i class="fas fa-file-csv"></i> Export to CSV
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                    <!-- Data Table -->
     <div class="x_panel">
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
                                                <th>Building Name</th>
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
                                                <td><?php echo $response['building_name'] ? htmlspecialchars($response['building_name']) : 'N/A'; ?></td>
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
        </div>
    </div>
    </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JavaScript -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
    
    <!-- SweetAlert2 JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
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
                        "targets": [5, 6], // Firefighter Name and Building Name columns
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

            // DataTables-only functionality - no custom search boxes

            // Advanced Filter Functions
            function applyAdvancedFilters() {
                // Clear all existing filters first
                table.search('').columns().search('').draw();
                
                // Apply Firefighter filter
                var firefighter = $('#filterFirefighter').val();
                if (firefighter) {
                    table.column(5).search(firefighter, false, false);
                }
                
                // Apply Date Range filter
                var startDate = $('#filterStartDate').val();
                var endDate = $('#filterEndDate').val();
                
                if (startDate || endDate) {
                    // Custom date filter function
                    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                        var timestamp = new Date(data[4]); // Column 4 is timestamp
                        var timestampDate = new Date(timestamp.getFullYear(), timestamp.getMonth(), timestamp.getDate());
                        
                        var start = startDate ? new Date(startDate) : null;
                        var end = endDate ? new Date(endDate) : null;
                        
                        if (start && end) {
                            return timestampDate >= start && timestampDate <= end;
                        } else if (start) {
                            return timestampDate >= start;
                        } else if (end) {
                            return timestampDate <= end;
                        }
                        return true;
                    });
                }
                
                // Redraw the table with all filters applied
                table.draw();
            }
            
            function clearAllAdvancedFilters() {
                // Clear dropdown filters
                $('#filterFirefighter, #filterStartDate, #filterEndDate').val('');
                
                // Clear DataTables filters
                table.search('').columns().search('').draw();
                
                // Remove custom date filter
                $.fn.dataTable.ext.search.pop();
                table.draw();
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
            $('#filterFirefighter, #filterStartDate, #filterEndDate').on('change', function() {
                applyAdvancedFilters();
            });
            
            // Add filter status indicator
            function updateFilterStatus() {
                var activeFilters = 0;
                if ($('#filterFirefighter').val()) activeFilters++;
                if ($('#filterStartDate').val()) activeFilters++;
                if ($('#filterEndDate').val()) activeFilters++;
                
                var statusText = activeFilters > 0 ? 
                    '<span class="badge badge-info">' + activeFilters + ' filter(s) active</span>' : 
                    '<span class="badge badge-secondary">No filters active</span>';
                
                $('#filterStatus').html(statusText);
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
                var csvContent = "Response Type,Responded By,Timestamp,Firefighter Name,Building Name\n";
                
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

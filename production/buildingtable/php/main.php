<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once '../../../db/db.php';

// Get database connection
$pdo = getDatabaseConnection();

// Optimized query with better performance and indexing
$sql = "
    SELECT 
        b.id,
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
        u.fullname as user_name,
        u.email_address,
        u.contact_number as user_contact,
        br.barangay_name,
        COALESCE(device_stats.device_count, 0) as device_count,
        COALESCE(device_stats.device_names, '') as device_names,
        COALESCE(device_stats.device_statuses, '') as device_statuses
    FROM buildings b
    LEFT JOIN users u ON b.user_id = u.user_id
    LEFT JOIN barangay br ON b.barangay_id = br.id
    LEFT JOIN (
        SELECT 
            building_id,
            COUNT(*) as device_count,
            GROUP_CONCAT(device_name SEPARATOR ', ') as device_names,
            GROUP_CONCAT(status SEPARATOR ', ') as device_statuses
        FROM devices 
        WHERE is_active = 1
        GROUP BY building_id
    ) device_stats ON b.id = device_stats.building_id
    ORDER BY b.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$buildings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Include header components -->
<?php include '../../components/header.php'; ?>

<!-- Building Table CSS -->
<link rel="stylesheet" href="../css/building_table.css">
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
                        <h2><i class="fas fa-list-alt"></i> Building Records</h2>
                        <div class="clearfix"></div>
                    </div>

                    <!-- Advanced Search & Filters -->
                    <div class="card">
                        <div class="card-header">
                            <h5>
                                <i class="fas fa-filter"></i> Advanced Search & Filters
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- 3-Column Layout -->
                            <div class="row">
                                <!-- Column 1: Search Fields -->
                                <div class="col-md-4">
                                    <h6 class="mb-3"><i class="fas fa-search"></i> Search Fields</h6>
                                    <div class="mb-3">
                                        <label for="buildingNameSearch" class="form-label">Building Name: <span class="shortcut-hint">(Ctrl+F)</span></label>
                                        <input type="text" id="buildingNameSearch" class="form-control form-control-sm" placeholder="Search by building name..." data-toggle="tooltip" title="Press Ctrl+F to focus quickly">
                                    </div>
                                    <div class="mb-3">
                                        <label for="ownerSearch" class="form-label">Owner:</label>
                                        <input type="text" id="ownerSearch" class="form-control form-control-sm" placeholder="Search by owner name...">
                                    </div>
                                    <div class="mb-3">
                                        <label for="locationSearch" class="form-label">Location:</label>
                                        <input type="text" id="locationSearch" class="form-control form-control-sm" placeholder="Search by address or barangay...">
                                    </div>
                                </div>

                                <!-- Column 2: Filter Options -->
                                <div class="col-md-4">
                                    <h6 class="mb-3"><i class="fas fa-filter"></i> Filter Options</h6>
                                    <div class="mb-3">
                                        <label for="buildingTypeFilter" class="form-label">Building Type:</label>
                                        <select id="buildingTypeFilter" class="form-select form-select-sm">
                                            <option value="">All Types</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="floorFilter" class="form-label">Floors:</label>
                                        <select id="floorFilter" class="form-select form-select-sm">
                                            <option value="">All Floors</option>
                                            <option value="1">1 Floor</option>
                                            <option value="2">2 Floors</option>
                                            <option value="3">3 Floors</option>
                                            <option value="4">4 Floors</option>
                                            <option value="5+">5+ Floors</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="deviceFilter" class="form-label">Device Status:</label>
                                        <select id="deviceFilter" class="form-select form-select-sm">
                                            <option value="">All Status</option>
                                            <option value="online">All Online</option>
                                            <option value="offline">Some Offline</option>
                                            <option value="faulty">Issues Detected</option>
                                            <option value="none">No Devices</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Column 3: Inspection & Actions -->
                                <div class="col-md-4">
                                    <h6 class="mb-3"><i class="fas fa-calendar-check"></i> Inspection & Actions</h6>
                                    <div class="mb-3">
                                        <label for="inspectionFilter" class="form-label">Inspection Status:</label>
                                        <select id="inspectionFilter" class="form-select form-select-sm">
                                            <option value="">All Status</option>
                                            <option value="recent">Recent (≤30 days)</option>
                                            <option value="due">Due Soon (31-90 days)</option>
                                            <option value="overdue">Overdue (>90 days)</option>
                                            <option value="never">Never Inspected</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="inspectionDateFrom" class="form-label">Inspection Date From:</label>
                                        <input type="date" id="inspectionDateFrom" class="form-control form-control-sm">
                                    </div>
                                    <div class="mb-3">
                                        <label for="inspectionDateTo" class="form-label">Inspection Date To:</label>
                                        <input type="date" id="inspectionDateTo" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="row">
                                <div class="col-md-6">
                                    <button id="clearFilters" class="btn btn-outline-secondary btn-sm me-2" data-toggle="tooltip" title="Press Ctrl+R to clear all filters">
                                        <i class="fas fa-times"></i> Clear All Filters <span class="shortcut-hint">(Ctrl+R)</span>
                                    </button>
                                    <button id="exportResults" class="btn btn-outline-success btn-sm" data-toggle="tooltip" title="Press Ctrl+E to export results">
                                        <i class="fas fa-download"></i> Export Results <span class="shortcut-hint">(Ctrl+E)</span>
                                    </button>
                                </div>
                                <div class="col-md-6 text-end">
                                    <small class="text-muted">
                                        <span id="filterCount">0</span> filters applied |
                                        <span id="resultCount">0</span> results shown
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="x_panel">
                        <div class="x_content">
                            <table id="buildingsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Building Name</th>
                                        <th>Type</th>
                                        <th>Owner</th>
                                        <th>Location</th>
                                        <th>Contact</th>
                                        <th>Floors</th>
                                        <th>Devices</th>
                                        <th>Last Inspected</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($buildings as $building): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($building['building_name']); ?></strong>
                                                <?php if ($building['building_area']): ?>
                                                    <br><small class="text-muted"><?php echo number_format($building['building_area'], 2); ?> sqm</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="building-type"><?php echo htmlspecialchars($building['building_type']); ?></span>
                                                <?php if ($building['construction_year']): ?>
                                                    <br><small class="text-muted">Built: <?php echo $building['construction_year']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($building['user_name'] ?? ''); ?></strong>
                                                <br><small class="contact-info"><?php echo htmlspecialchars($building['email_address'] ?? ''); ?></small>
                                                <?php if ($building['user_contact']): ?>
                                                    <br><small class="contact-info"><?php echo htmlspecialchars($building['user_contact']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($building['address']); ?></strong>
                                                <?php if ($building['barangay_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($building['barangay_name']); ?></small>
                                                <?php endif; ?>
                                                <?php if ($building['latitude'] && $building['longitude']): ?>
                                                    <br><small class="coordinates"><?php echo $building['latitude']; ?>, <?php echo $building['longitude']; ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($building['contact_person']): ?>
                                                    <strong><?php echo htmlspecialchars($building['contact_person']); ?></strong>
                                                <?php endif; ?>
                                                <?php if ($building['contact_number']): ?>
                                                    <br><small class="contact-info"><?php echo htmlspecialchars($building['contact_number']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $building['total_floors']; ?> floors</span>
                                            </td>
                                            <td>
                                                <span class="device-count"><?php echo $building['device_count']; ?> devices</span>
                                                <?php if ($building['device_names']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($building['device_names']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($building['last_inspected']): ?>
                                                    <?php 
                                                    $inspectionDate = new DateTime($building['last_inspected']);
                                                    $now = new DateTime();
                                                    $diff = $now->diff($inspectionDate);
                                                    $daysSince = $diff->days;
                                                    ?>
                                                    <div class="inspection-info">
                                                        <div class="inspection-date"><?php echo $inspectionDate->format('M d, Y'); ?></div>
                                                        <?php if ($daysSince <= 30): ?>
                                                            <span class="inspection-status status-recent">
                                                                <i class="fas fa-check-circle"></i> Recent (<?php echo $daysSince; ?> days ago)
                                                            </span>
                                                        <?php elseif ($daysSince <= 90): ?>
                                                            <span class="inspection-status status-due">
                                                                <i class="fas fa-clock"></i> Due Soon (<?php echo $daysSince; ?> days ago)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inspection-status status-overdue">
                                                                <i class="fas fa-exclamation-triangle"></i> Overdue (<?php echo $daysSince; ?> days ago)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="inspection-info">
                                                        <div class="inspection-date text-muted">No inspection</div>
                                                        <span class="inspection-status status-never">
                                                            <i class="fas fa-times-circle"></i> Never Inspected
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($building['device_count'] > 0): ?>
                                                    <?php 
                                                    $statuses = explode(', ', $building['device_statuses']);
                                                    $onlineCount = 0;
                                                    $offlineCount = 0;
                                                    $faultyCount = 0;
                                                    
                                                    foreach ($statuses as $status) {
                                                        if ($status === 'online') {
                                                            $onlineCount++;
                                                        } elseif ($status === 'offline') {
                                                            $offlineCount++;
                                                        } elseif ($status === 'faulty') {
                                                            $faultyCount++;
                                                        }
                                                    }
                                                    ?>
                                                    <?php if ($onlineCount > 0 && $offlineCount == 0 && $faultyCount == 0): ?>
                                                        <span class="badge badge-success">
                                                            <span class="status-indicator status-online"></span>
                                                            All Online
                                                        </span>
                                                    <?php elseif ($faultyCount > 0): ?>
                                                        <span class="badge badge-danger">
                                                            <span class="status-indicator status-faulty"></span>
                                                            Issues Detected
                                                        </span>
                                                    <?php elseif ($offlineCount > 0): ?>
                                                        <span class="badge badge-warning">
                                                            <span class="status-indicator status-offline"></span>
                                                            Some Offline
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">
                                                            <span class="status-indicator status-offline"></span>
                                                            No Devices
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">No Devices</span>
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

    <!-- jQuery and DataTables JavaScript -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Enhanced DataTables JavaScript with Real-time Filters -->
    <script>
        $(document).ready(function() {
            var table = $('#buildingsTable').DataTable({
                "responsive": true,
                "pageLength": 10,
                "lengthMenu": [[5, 10, 25, 50, 100], [5, 10, 25, 50, 100]],
                "order": [[0, "asc"]],
                "columnDefs": [
                    { "orderable": false, "targets": [6, 8] }
                ],
                "language": {
                    "search": "Search:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "infoEmpty": "No entries found",
                    "infoFiltered": "(filtered from _MAX_ total entries)",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "processing": true,
                "deferRender": true,
                "stateSave": true,
                "stateDuration": 60 * 60 * 24 // 24 hours
            });

            // Populate building type filter
            var buildingTypes = [...new Set($('#buildingsTable tbody tr').map(function() {
                return $(this).find('td:eq(1) .building-type').text().trim();
            }).get())].filter(type => type !== '');
            
            buildingTypes.forEach(function(type) {
                $('#buildingTypeFilter').append('<option value="' + type + '">' + type + '</option>');
            });

            // Real-time filter functions with instant updates
            var customFilters = [];
            
            function applyFilters() {
                var filters = [];
                
                // Clear previous custom filters
                $.fn.dataTable.ext.search = customFilters.slice();
                
                // Building name search (real-time)
                var buildingName = $('#buildingNameSearch').val().trim();
                if (buildingName) {
                    table.column(0).search(buildingName, false, false);
                    filters.push('Name: ' + buildingName);
                } else {
                    table.column(0).search('');
                }

                // Owner search (real-time)
                var ownerName = $('#ownerSearch').val().trim();
                if (ownerName) {
                    table.column(2).search(ownerName, false, false);
                    filters.push('Owner: ' + ownerName);
                } else {
                    table.column(2).search('');
                }

                // Location search (real-time)
                var location = $('#locationSearch').val().trim();
                if (location) {
                    table.column(3).search(location, false, false);
                    filters.push('Location: ' + location);
                } else {
                    table.column(3).search('');
                }
                
                // Building type filter
                var buildingType = $('#buildingTypeFilter').val();
                if (buildingType) {
                    table.column(1).search('^' + buildingType + '$', true, false);
                    filters.push('Type: ' + buildingType);
                } else {
                    table.column(1).search('');
                }

                // Floor filter with enhanced logic
                var floorFilter = $('#floorFilter').val();
                if (floorFilter) {
                    if (floorFilter === '5+') {
                        table.column(5).search('[5-9]', true, false);
                    } else {
                        table.column(5).search('^' + floorFilter + ' floors$', true, false);
                    }
                    filters.push('Floors: ' + floorFilter);
                } else {
                    table.column(5).search('');
                }

                // Device status filter
                var deviceStatus = $('#deviceFilter').val();
                if (deviceStatus) {
                    if (deviceStatus === 'online') {
                        table.column(8).search('All Online', true, false);
                    } else if (deviceStatus === 'offline') {
                        table.column(8).search('Some Offline', true, false);
                    } else if (deviceStatus === 'faulty') {
                        table.column(8).search('Issues Detected', true, false);
                    } else if (deviceStatus === 'none') {
                        table.column(8).search('No Devices', true, false);
                    }
                    filters.push('Devices: ' + deviceStatus);
                } else {
                    table.column(8).search('');
                }

                // Inspection status filter
                var inspectionStatus = $('#inspectionFilter').val();
                if (inspectionStatus) {
                    if (inspectionStatus === 'recent') {
                        table.column(7).search('Recent', true, false);
                    } else if (inspectionStatus === 'due') {
                        table.column(7).search('Due Soon', true, false);
                    } else if (inspectionStatus === 'overdue') {
                        table.column(7).search('Overdue', true, false);
                    } else if (inspectionStatus === 'never') {
                        table.column(7).search('Never', true, false);
                    }
                    filters.push('Inspection: ' + inspectionStatus);
                } else {
                    table.column(7).search('');
                }

                // Enhanced date range filter for inspection dates
                var dateFrom = $('#inspectionDateFrom').val();
                var dateTo = $('#inspectionDateTo').val();
                
                if (dateFrom || dateTo) {
                    var dateFilter = function(settings, data, dataIndex) {
                        var inspectionDate = data[7]; // Column index for inspection date
                        
                        if (!inspectionDate || inspectionDate.includes('Never')) {
                            return false; // Skip rows with no inspection date
                        }
                        
                        // Extract date from the inspection date string
                        var dateMatch = inspectionDate.match(/(\w{3} \d{1,2}, \d{4})/);
                        if (!dateMatch) {
                            return false;
                        }
                        
                        var rowDate = new Date(dateMatch[1]);
                        
                        if (dateFrom && rowDate < new Date(dateFrom)) {
                            return false;
                        }
                        if (dateTo && rowDate > new Date(dateTo)) {
                            return false;
                        }
                        
                        return true;
                    };
                    
                    customFilters.push(dateFilter);
                    
                    if (dateFrom && dateTo) {
                        filters.push('Date: ' + dateFrom + ' to ' + dateTo);
                    } else if (dateFrom) {
                        filters.push('Date: from ' + dateFrom);
                    } else if (dateTo) {
                        filters.push('Date: until ' + dateTo);
                    }
                }

                // Update filter count and result count
                $('#filterCount').text(filters.length);
                
                // Draw the table with real-time updates
                table.draw();
                
                // Update result count after draw
                setTimeout(function() {
                    $('#resultCount').text(table.rows({search: 'applied'}).count());
                }, 100);
            }

            // Real-time event listeners for instant filtering
            $('#buildingNameSearch, #ownerSearch, #locationSearch').on('input', function() {
                clearTimeout(this.searchTimeout);
                this.searchTimeout = setTimeout(applyFilters, 300);
            });
            
            // Instant filtering for dropdowns and date inputs
            $('#buildingTypeFilter, #floorFilter, #deviceFilter, #inspectionFilter').on('change', function() {
                applyFilters();
            });
            
            // Real-time date filtering
            $('#inspectionDateFrom, #inspectionDateTo').on('change', function() {
                applyFilters();
            });

            // Clear all filters with SweetAlert success message
            $('#clearFilters').on('click', function() {
                $('#buildingNameSearch, #ownerSearch, #locationSearch, #buildingTypeFilter, #floorFilter, #deviceFilter, #inspectionFilter, #inspectionDateFrom, #inspectionDateTo').val('');
                
                customFilters = [];
                $.fn.dataTable.ext.search = [];
                
                table.search('').columns().search('').draw();
                
                $('#filterCount').text('0');
                $('#resultCount').text(table.rows().count());
                
                Swal.fire({
                    icon: 'success',
                    title: 'Filters Reset!',
                    text: 'All filters have been cleared successfully.',
                    timer: 2000,
                    showConfirmButton: false
                });
            });

            // Export functionality
            $('#exportResults').on('click', function() {
                var filteredData = table.rows({search: 'applied'}).data();
                var csvContent = "data:text/csv;charset=utf-8,";
                
                // Add headers
                csvContent += "Building Name,Type,Owner,Location,Contact,Floors,Devices,Last Inspected,Status\n";
                
                // Add data rows
                filteredData.each(function(row) {
                    var csvRow = [];
                    $(row).each(function(index, cell) {
                        var text = $(cell).text().replace(/,/g, ';').replace(/\n/g, ' ').trim();
                        csvRow.push('"' + text + '"');
                    });
                    csvContent += csvRow.join(',') + "\n";
                });
                
                var encodedUri = encodeURI(csvContent);
                var link = document.createElement("a");
                var fileName = "building_data_" + new Date().toISOString().split('T')[0] + ".csv";
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", fileName);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                Swal.fire({
                    icon: 'success',
                    title: 'Export Successful!',
                    text: `Building data has been exported successfully as ${fileName}`,
                    timer: 3000,
                    showConfirmButton: true,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#28a745'
                });
            });

            table.on('draw', function() {
                $('#resultCount').text(table.rows({search: 'applied'}).count());
            });

            $('#filterCount').text('0');
            $('#resultCount').text(table.rows().count());
            
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'r') {
                    e.preventDefault();
                    $('#clearFilters').click();
                }
            });
        });
    </script>
    <!-- Include header components -->
    <?php include '../../components/scripts.php'; ?>
</body>
</html>


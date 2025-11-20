<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../../db/db.php';

// Get database connection
$conn = getDatabaseConnection();

// Fetch users data with related information
$query = "
    SELECT 
        u.user_id,
        u.fullname,
        u.birthdate,
        u.age,
        u.address,
        u.email_address,
        u.device_number,
        u.username,
        u.status,
        u.registration_date,
        u.email_verified,
        u.profile_image,
        u.contact_number,
        COUNT(d.device_id) as device_count,
        COUNT(b.id) as building_count,
        GROUP_CONCAT(DISTINCT d.status) as device_statuses
    FROM users u
    LEFT JOIN devices d ON u.user_id = d.user_id
    LEFT JOIN buildings b ON u.user_id = b.user_id
    GROUP BY u.user_id
    ORDER BY u.registration_date DESC
";

try {
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching users: " . $e->getMessage());
    $users = [];
}

// Fetch barangay data for filter
$barangayQuery = "SELECT id, barangay_name FROM barangay ORDER BY barangay_name";
try {
    $stmt = $conn->prepare($barangayQuery);
    $stmt->execute();
    $barangays = $stmt->fetchAll();
} catch (PDOException $e) {
    $barangays = [];
}
?>


    
    <!-- Include header components -->
<?php include '../../components/header.php'; ?>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/user_table.css">
    
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
                        <h2><i class="fas fa-list-alt"></i> User Records</h2>
                        <div class="clearfix"></div>
                    </div>
        

            <!-- Main Content -->
            <div class="container-fluid">
                <!-- Filter Panel -->
                <div class="card mb-2">
                    <div class="card-header">
                        <h5><i class="fas fa-filter"></i> Advanced Filters</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select id="statusFilter" class="form-select form-select-sm">
                                    <option value="">All Status</option>
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Device Count</label>
                                <select id="deviceCountFilter" class="form-select form-select-sm">
                                    <option value="">All</option>
                                    <option value="0">No Devices</option>
                                    <option value="1">1 Device</option>
                                    <option value="2+">2+ Devices</option>
                                </select>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Registration Date Range</label>
                                <div class="row">
                                    <div class="col-6">
                                        <input type="date" id="startDate" class="form-control form-control-sm" placeholder="Start Date">
                                    </div>
                                    <div class="col-6">
                                        <input type="date" id="endDate" class="form-control form-control-sm" placeholder="End Date">
                                    </div>
                                </div>
                            </div
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm" id="resetAllFilters">
                                        <i class="fas fa-undo"></i> Reset All Filters
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" id="exportData">
                                        <i class="fas fa-download"></i> Export Data
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3" style="display: none;">
                            <div class="col-md-8">
                                <label class="form-label">Search users:</label>
                                <input type="text" id="globalSearch" class="form-control form-control-sm" placeholder="Search users...">
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-end h-100 gap-2">
                                    <button id="clearFilters" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div id="filterStatus" class="mt-2"></div>
                    </div>
                </div>
            </div>

                <!-- Data Table -->
                <div class="x_panel">
                    <div class="x_content">
                        <div class="table-responsive">
                            <table id="usersTable" class="table table-striped table-bordered" style="width:100%">
                                 <thead>
                                     <tr>
                                         <th>Full Name</th>
                                         <th>Email</th>
                                         <th>Address</th>
                                         <th>Contact</th>
                                         <th>Username</th>
                                         <th>Status</th>
                                         <th>Devices</th>
                                         <th>Buildings</th>
                                         <th>Registration Date</th>
                                         <th>Actions</th>
                                     </tr>
                                 </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                        </td>
                                        <td>
                                            <div><?php echo htmlspecialchars($user['email_address']); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($user['address']): ?>
                                                <?php 
                                                    // Get first 3 words of address for short display
                                                    $addressWords = explode(' ', trim($user['address']));
                                                    $shortAddress = implode(' ', array_slice($addressWords, 0, 3));
                                                    if (count($addressWords) > 3) {
                                                        $shortAddress .= '...';
                                                    }
                                                ?>
                                                <span class="address-info" title="<?php echo htmlspecialchars($user['address']); ?>"><?php echo htmlspecialchars($shortAddress); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['contact_number']): ?>
                                                <span class="contact-info"><?php echo htmlspecialchars($user['contact_number']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td data-search="<?php echo htmlspecialchars($user['status']); ?>">
                                            <?php if ($user['status'] === 'Active'): ?>
                                                <span class="badge badge-success">
                                                    <span class="status-indicator status-online"></span>
                                                    Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">
                                                    <span class="status-indicator status-offline"></span>
                                                    Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-info"><?php echo $user['device_count']; ?></span>
                                            <?php if ($user['device_statuses']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($user['device_statuses']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo $user['building_count']; ?></span>
                                        </td>
                                        <td data-search="<?php echo date('Y-m-d', strtotime($user['registration_date'])); ?>">
                                            <div><?php echo date('M d, Y', strtotime($user['registration_date'])); ?></div>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($user['registration_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewUser(<?php echo $user['user_id']; ?>)" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($user['status'] === 'Active'): ?>
                                                    <button class="btn btn-outline-warning btn-sm" onclick="deactivateUser(<?php echo $user['user_id']; ?>)" title="Deactivate">
                                                        <i class="fas fa-user-times"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success btn-sm" onclick="activateUser(<?php echo $user['user_id']; ?>)" title="Activate">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
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

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content bg-white">
                <div class="modal-header bg-warning border-0 py-2">
                    <h6 class="modal-title text-dark mb-0" id="viewUserModalLabel">
                        User Details
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-white py-2">
                    <div id="userDetailsContent">
                        <!-- User details will be loaded here -->
                    </div>
                </div>
                <div class="modal-footer bg-white border-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../../components/scripts.php'; ?>
    
    <!-- Ensure DataTables is loaded after all scripts -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    // Ensure jQuery and DataTables are loaded
    if (typeof jQuery === 'undefined') {
        console.error('jQuery is not loaded. Please check script includes.');
    }
    
    // Define functions globally to ensure they're available for onclick handlers
    window.viewUser = function(userId) {
        if (typeof jQuery === 'undefined' || typeof Swal === 'undefined') {
            console.error('Required libraries not loaded');
            return;
        }
        // Fetch user details via AJAX
        jQuery.ajax({
            url: 'get_user_details.php',
            method: 'GET',
            data: { user_id: userId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var user = response.data;
                    var html = `
                        <div class="card bg-white border-0">
                            <div class="card-body p-2">
                                <h6 class="mb-2 text-dark">${user.fullname}</h6>
                                <div class="row g-1">
                                    <div class="col-6">
                                        <small class="text-muted">Username</small>
                                        <div class="small">${user.username}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Status</small>
                                        <div>
                                            <span class="badge ${user.status === 'Active' ? 'bg-success' : 'bg-secondary'} badge-sm">${user.status}</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Email</small>
                                        <div class="small">${user.email_address}</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">Contact</small>
                                        <div class="small">${user.contact_number || 'N/A'}</div>
                                    </div>
                                    <div class="col-12">
                                        <small class="text-muted">Address</small>
                                        <div class="small">${user.address || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    jQuery('#userDetailsContent').html(html);
                    jQuery('#editFromView').data('user-id', userId);
                    jQuery('#viewUserModal').modal('show');
                } else {
                    Swal.fire('Error', response.message || 'Failed to load user details', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to load user details', 'error');
            }
        });
    };

    window.activateUser = function(userId) {
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 is not loaded');
            return;
        }
        Swal.fire({
            title: 'Activate User',
            text: 'Are you sure you want to activate this user?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, activate!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.updateUserStatus(userId, 'Active');
            }
        });
    };

    window.deactivateUser = function(userId) {
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 is not loaded');
            return;
        }
        Swal.fire({
            title: 'Deactivate User',
            text: 'Are you sure you want to deactivate this user?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Yes, deactivate!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.updateUserStatus(userId, 'Inactive');
            }
        });
    };

    window.updateUserStatus = function(userId, status) {
        if (typeof jQuery === 'undefined' || typeof Swal === 'undefined') {
            console.error('Required libraries not loaded');
            return;
        }
        jQuery.ajax({
            url: 'update_user_status.php',
            method: 'POST',
            data: { 
                user_id: userId,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire('Success!', 'User status updated successfully.', 'success');
                    // Reload the page to show updated status
                    location.reload();
                } else {
                    Swal.fire('Error', response.message || 'Failed to update user status', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update user status', 'error');
            }
        });
    };

    // Wait for all scripts to load before initializing
    // Use window.onload to ensure all scripts including scripts.php are loaded
    window.addEventListener('load', function() {
        // Function to check and initialize DataTables
        function checkAndInitializeDataTable() {
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn.DataTable !== 'undefined') {
                    initializeDataTable();
                } else {
                console.warn('DataTables not yet loaded, retrying...');
                setTimeout(checkAndInitializeDataTable, 100);
                }
        }
        
        // Start checking after a short delay to ensure all scripts are loaded
        setTimeout(checkAndInitializeDataTable, 200);

        function initializeDataTable() {
            // Initialize DataTable using jQuery
            var table = jQuery('#usersTable').DataTable({
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            order: [[8, 'desc']], // Registration Date column (now index 8)
             columnDefs: [
                 { orderable: false, targets: [9] }, // Actions column (now index 9)
                 { className: "text-center", targets: [5, 6, 7, 8, 9] }, // Status, Devices, Buildings, Registration Date, Actions
                 { 
                     targets: [5], // Status column (now index 5)
                     searchable: true,
                     type: 'string'
                 }
             ],
            language: {
                search: "Search users:",
                lengthMenu: "Show _MENU_ users per page",
                info: "Showing _START_ to _END_ of _TOTAL_ users",
                infoEmpty: "No users found",
                infoFiltered: "(filtered from _MAX_ total users)"
            }
        });

        // Make table variable accessible to all functions
        window.usersTable = table;

         // Advanced filtering
         jQuery('#statusFilter').on('change', function() {
             var status = jQuery(this).val();
             console.log('Status filter changed to:', status);
             
             if (status === '') {
                 table.column(5).search('').draw();
             } else {
                 // Use simple text search for status filtering
                 table.column(5).search(status, true, false).draw();
             }
             
             // Debug: Check filtered results
             setTimeout(function() {
                 var info = table.page.info();
                 console.log('Filtered results:', info.recordsDisplay, 'of', info.recordsTotal);
             }, 100);
             
             updateFilterStatus();
         });

         jQuery('#deviceCountFilter').on('change', function() {
             var count = jQuery(this).val();
             if (count === '') {
                 table.column(6).search('').draw();
             } else if (count === '0') {
                 table.column(6).search('^0$', true, false).draw();
             } else if (count === '1') {
                 table.column(6).search('^1$', true, false).draw();
             } else if (count === '2+') {
                 table.column(6).search('[2-9]', true, false).draw();
             }
             updateFilterStatus();
         });

         // Date range filtering
         function applyDateFilter() {
             var startDate = $('#startDate').val();
             var endDate = $('#endDate').val();
             
             // Clear previous date filters
             jQuery.fn.dataTable.ext.search = [];
             
             if (startDate || endDate) {
                 jQuery.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                     if (settings.nTable.id !== 'usersTable') return true;
                     
                     // Get the date from data-search attribute
                     var $row = jQuery(table.row(dataIndex).node());
                     var regDateStr = $row.find('td:eq(8)').attr('data-search');
                     
                     if (!regDateStr) return true;
                     
                     var regDate = new Date(regDateStr);
                     var start = startDate ? new Date(startDate) : null;
                     var end = endDate ? new Date(endDate) : null;
                     
                     if (start && regDate < start) return false;
                     if (end && regDate > end) return false;
                     
                     return true;
                 });
             }
             
             table.draw();
             updateFilterStatus();
         }

         jQuery('#startDate, #endDate').on('change', function() {
             applyDateFilter();
         });

         // Quick date filter buttons
         jQuery('#todayBtn').on('click', function() {
             var today = new Date().toISOString().split('T')[0];
             jQuery('#startDate').val(today);
             jQuery('#endDate').val(today);
             applyDateFilter();
         });

         jQuery('#weekBtn').on('click', function() {
             var today = new Date();
             var weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
             jQuery('#startDate').val(weekAgo.toISOString().split('T')[0]);
             jQuery('#endDate').val(today.toISOString().split('T')[0]);
             applyDateFilter();
         });

         jQuery('#monthBtn').on('click', function() {
             var today = new Date();
             var monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
             jQuery('#startDate').val(monthAgo.toISOString().split('T')[0]);
             jQuery('#endDate').val(today.toISOString().split('T')[0]);
             applyDateFilter();
         });

         jQuery('#yearBtn').on('click', function() {
             var today = new Date();
             var yearAgo = new Date(today.getTime() - 365 * 24 * 60 * 60 * 1000);
             jQuery('#startDate').val(yearAgo.toISOString().split('T')[0]);
             jQuery('#endDate').val(today.toISOString().split('T')[0]);
             applyDateFilter();
         });

        jQuery('#globalSearch').on('keyup', function() {
            table.search(this.value).draw();
            updateFilterStatus();
        });

        // Reset all filters button
        jQuery('#resetAllFilters').on('click', function() {
            // Clear all filter inputs
            jQuery('#statusFilter, #deviceCountFilter').val('');
            jQuery('#startDate, #endDate').val('');
            jQuery('#globalSearch').val('');
            
            // Clear all DataTable filters
            jQuery.fn.dataTable.ext.search = []; // Clear custom date filters
            table.search('').columns().search('').draw();
            
            // Update filter status
            updateFilterStatus();
            
            // Show success message
            Swal.fire({
                title: 'Filters Reset',
                text: 'All filters have been cleared successfully!',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        });

        // Legacy clear filters button (for backward compatibility)
        jQuery('#clearFilters').on('click', function() {
            jQuery('#resetAllFilters').click();
        });

        function updateFilterStatus() {
            var activeFilters = [];
            
            if (jQuery('#statusFilter').val()) {
                activeFilters.push('<span class="badge badge-primary">Status: ' + jQuery('#statusFilter').val() + '</span>');
            }
            if (jQuery('#deviceCountFilter').val()) {
                activeFilters.push('<span class="badge badge-warning">Devices: ' + jQuery('#deviceCountFilter').val() + '</span>');
            }
            
            // Check for date range filters
            var startDate = jQuery('#startDate').val();
            var endDate = jQuery('#endDate').val();
            if (startDate || endDate) {
                var dateRange = '';
                if (startDate && endDate) {
                    dateRange = startDate + ' to ' + endDate;
                } else if (startDate) {
                    dateRange = 'from ' + startDate;
                } else if (endDate) {
                    dateRange = 'until ' + endDate;
                }
                activeFilters.push('<span class="badge badge-secondary">Date: ' + dateRange + '</span>');
            }
            
            if (jQuery('#globalSearch').val()) {
                activeFilters.push('<span class="badge badge-success">Search: "' + jQuery('#globalSearch').val() + '"</span>');
            }

            var filterHtml = '';
            if (activeFilters.length > 0) {
                filterHtml = '<strong>Active Filters:</strong> ' + activeFilters.join(' ');
                filterHtml += ' <span class="filter-count-badge">' + table.page.info().recordsDisplay + '</span>';
            } else {
                filterHtml = '<span class="text-muted">No filters applied</span>';
            }
            
            jQuery('#filterStatus').html(filterHtml);
        }

        // Export functionality
        jQuery('#exportData').on('click', function() {
            // Custom CSV export function
            var csvContent = "data:text/csv;charset=utf-8,";
            
            // Add headers
            var headers = ['Full Name', 'Email', 'Address', 'Contact', 'Username', 'Status', 'Devices', 'Buildings', 'Registration Date'];
            csvContent += headers.join(',') + '\n';
            
            // Helper function to clean HTML and convert to string
            function cleanData(field) {
                if (field === null || field === undefined) return '';
                var str = field.toString();
                return str.replace(/<[^>]*>/g, '').trim();
            }
            
            // Add data rows
            table.rows({search: 'applied'}).every(function(rowIdx, tableLoop, rowLoop) {
                var data = this.data();
                var row = [
                    cleanData(data[0]), // Full Name
                    cleanData(data[1]), // Email
                    cleanData(data[2]), // Address
                    cleanData(data[3]), // Contact
                    cleanData(data[4]), // Username
                    cleanData(data[5]), // Status (remove HTML)
                    cleanData(data[6]), // Devices (remove HTML)
                    cleanData(data[7]), // Buildings (remove HTML)
                    cleanData(data[8])  // Registration Date
                ];
                csvContent += row.map(function(field) {
                    return '"' + (field || '').toString().replace(/"/g, '""') + '"';
                }).join(',') + '\n';
            });
            
            // Download CSV
            var encodedUri = encodeURI(csvContent);
            var link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "users_export.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Show SweetAlert success modal
            Swal.fire({
                title: 'Export Successful!',
                text: 'User data has been exported successfully to CSV file.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false
            });
        });

        // Initialize filter status
        updateFilterStatus();
        }
    });
    </script>
</body>
</html>



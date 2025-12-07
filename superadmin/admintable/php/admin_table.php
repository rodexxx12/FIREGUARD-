<?php
session_start();
require_once '../../db/db.php';

// Check if user is logged in (add your authentication logic here)
// if (!isset($_SESSION['admin_id'])) {
//     header('Location: ../../login/');
//     exit();
// }

$pageTitle = "Admin Management";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Fire Detection System</title>
    
    <!-- Include header with all necessary libraries -->
    <?php include '../../components/header.php'; ?>
    
    <style>
        /* Remove extra spacing */
        .page-title {
            margin-bottom: 10px !important;
            padding: 10px 0 !important;
        }
        
        .x_panel {
            margin-top: 10px !important;
        }
        
        .x_content {
            padding: 10px 15px !important;
        }
        
        .x_content .row {
            margin-bottom: 10px !important;
        }
        
        .dataTables_wrapper {
            margin-top: 10px !important;
        }
        
        .dataTables_length,
        .dataTables_filter {
            margin-bottom: 10px !important;
        }
        
        /* Auto-refresh styles */
        .refreshing {
            opacity: 0.7;
            position: relative;
        }
        
        .refreshing::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .refreshing::before {
            content: '🔄 Refreshing...';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1001;
        }
        
        #refreshBtn i.fa-spin {
            animation: fa-spin 1s infinite linear;
        }
        
        .badge-info {
            background-color: #17a2b8 !important;
        }
        
        /* Status change animation */
        .status-changing {
            animation: pulse 0.5s ease-in-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Real-time search indicators */
        .search-indicator {
            transition: all 0.3s ease;
        }
        
        .search-indicator.searching {
            color: #007bff;
            animation: pulse 1s infinite;
        }
        
        .search-indicator.has-results {
            color: #28a745;
        }
        
        .search-indicator.no-results {
            color: #dc3545;
        }
        
        .input-group-text {
            background-color: #f8f9fa;
            border-color: #ced4da;
        }
        
        /* Real-time filter feedback */
        .filter-active {
            border-color: #007bff !important;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
        }
        
        .search-loading {
            position: relative;
        }
        
        .search-loading::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 20px;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 123, 255, 0.1));
            animation: shimmer 1s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Real-time validation styles */
        .form-control.validating {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        
        .form-control.is-valid {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        
        .form-group {
            position: relative;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        
        /* Password toggle button styles */
        #togglePassword {
            border-left: 0;
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }
        
        #togglePassword:hover {
            background-color: #e9ecef;
            border-color: #ced4da;
        }
        
        #togglePassword:focus {
            box-shadow: none;
            border-color: #ced4da;
        }
        
        
        /* Password generation button styles */
        #generatePassword {
            border-right: 0;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        #generatePassword:hover {
            background-color: #e3f2fd;
            border-color: #17a2b8;
        }
        
        #generatePassword:focus {
            box-shadow: none;
            border-color: #17a2b8;
        }
        
        
        /* Enhanced validation feedback */
        .form-control.validating {
            background-image: linear-gradient(45deg, transparent 30%, rgba(0, 123, 255, 0.1) 50%, transparent 70%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }
        
        .form-control.is-valid {
            background-image: none;
            animation: none;
        }
        
        .form-control.is-invalid {
            background-image: none;
            animation: none;
        }
        
        /* Validation feedback text styling */
        .invalid-feedback {
            font-size: 12px;
            margin-top: 5px;
            display: block;
            color: #dc3545;
        }
        
        .form-control.is-valid + .invalid-feedback {
            color: #28a745;
        }
        
        .form-control.validating + .invalid-feedback {
            color: #007bff;
        }
        
        /* Phone number formatting hint */
        .contact-number-hint {
            font-size: 11px;
            color: #6c757d;
            margin-top: 2px;
            font-style: italic;
        }
        
        /* Enhanced modal styling */
        .modal-body .form-group {
            margin-bottom: 1.5rem;
        }
        
        .modal-body .form-label {
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        /* Loading state for submit button */
        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Success state animation */
        .form-control.is-valid {
            animation: successPulse 0.5s ease-in-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
    </style>
</head>
<?php include '../../components/header.php'; ?>
<body class="nav-md">
    <div class="container body">
      <div class="main_container">
            <?php include('../../components/sidebar.php'); ?>
            </div>
          </div>
        </div>
        <?php include('../../components/navigation.php')?>
        <div class="right_col" role="main">
          <div class="">
            <div class="page-title">
              <div class="title_left">
                <h3>Admin Records <small>List of Admins</small></h3>
              </div>
              <div class="title_right">
                <div class="col-md-5 col-sm-5 col-xs-12 form-group pull-right top_search">
                  <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search Admins...">
                    <span class="input-group-btn">
                      <button class="btn btn-default" type="button">Go!</button>
                    </span>
                  </div>
                </div>
              </div>
            </div>
            <div class="clearfix"></div>
            <div class="row">
              <div class="col-md-12 col-sm-12 col-xs-12">
                <div class="x_panel">
                  <div class="x_title">
                    <h2>Admin Records</h2>
                    <ul class="nav navbar-right panel_toolbox">
                      <li><a class="collapse-link"><i class="fa fa-chevron-up"></i></a></li>
                      <li class="dropdown">
                        <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button" aria-expanded="false"><i class="fa fa-wrench"></i></a>
                        <ul class="dropdown-menu" role="menu">
                          <li><a href="#">Settings 1</a></li>
                          <li><a href="#">Settings 2</a></li>
                        </ul>
                      </li>
                      <li><a class="close-link"><i class="fa fa-close"></i></a></li>
                    </ul>
                    <div class="clearfix"></div>
                  </div>
                  <div class="x_content">
                    <div class="row" style="margin-bottom: 10px;">
                      <div class="col-md-6">
                        <button type="button" class="btn btn-success" id="addAdminBtn">
                          <i class="fa fa-user-plus"></i> Add New Admin
                        </button>
                        <button type="button" class="btn btn-danger" id="clearFiltersBtn" style="margin-left: 5px;">
                          <i class="fa fa-times"></i> Clear Filters
                        </button>
                      </div>
                      <div class="col-md-6">
                        <div id="filterStatus" class="text-right">
                          <span class="badge badge-primary">All (<span id="totalCount">0</span>)</span>
                          <span class="badge badge-success">Active (<span id="activeCount">0</span>)</span>
                          <span class="badge badge-warning">Inactive (<span id="inactiveCount">0</span>)</span>
                        </div>
                      </div>
                    </div>
                    <table id="adminTable" class="table table-striped table-bordered">
                      <thead>
                        <tr>
                          <th>ID</th>
                          <th>Full Name</th>
                          <th>Email</th>
                          <th>Username</th>
                          <th>Contact Number</th>
                          <th>Role</th>
                          <th>Status</th>
                          <th>Created As</th>
                          <th>Updated At</th>
                          <th>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <!-- Data will be loaded via AJAX -->
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

    <!-- Add/Edit Admin Modal -->
    <div class="modal fade" id="adminModal" tabindex="-1" role="dialog" aria-labelledby="adminModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title" id="adminModalLabel">
                        Add New Admin
                    </h4>
                </div>
                <form id="adminForm">
                    <div class="modal-body">
                        <input type="hidden" id="adminId" name="admin_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 form-group">
                                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-group">
                                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-info" id="generatePassword" title="Generate Password">
                                                Generate
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" id="togglePassword" title="Show/Hide Password">
                                                Show/Hide
                                            </button>
                                        </div>
                                    </div>
                                    <div class="invalid-feedback"></div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 form-group">
                                    <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="fullName" name="full_name" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 form-group">
                                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 form-group">
                                    <label for="contactNumber" class="form-label">Contact Number <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="contactNumber" name="contact_number" placeholder="09 1234 5678" required>
                                    <div class="invalid-feedback"></div>
                                    <div class="contact-number-hint">Format: 09 1234 5678 (Philippine mobile number - 11 digits starting with 09)</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                    <div class="invalid-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Save Admin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <!-- JavaScript -->
    <script>
        $(document).ready(function() {
            let adminTable;

            // Initialize DataTable - Gentelella style
            adminTable = $('#adminTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'admin_api.php',
                    type: 'POST',
                    data: function(d) {
                        d.action = 'get_admins';
                    }
                },
                columns: [
                    { data: 'admin_id', name: 'admin_id', visible: false },
                    { data: 'full_name', name: 'full_name' },
                    { data: 'email', name: 'email' },
                    { data: 'username', name: 'username' },
                    { data: 'contact_number', name: 'contact_number' },
                    { 
                        data: 'role', 
                        name: 'role',
                        defaultContent: 'Admin',
                        render: function(data, type, row) {
                            return data ? data.charAt(0).toUpperCase() + data.slice(1) : 'Admin';
                        }
                    },
                    { 
                        data: 'status', 
                        name: 'status',
                        render: function(data, type, row) {
                            const badgeClass = data === 'Active' ? 'badge-success' : 'badge-warning';
                            return `<span class="badge ${badgeClass}">${data}</span>`;
                        }
                    },
                    { 
                        data: 'created_at', 
                        name: 'created_at',
                        render: function(data, type, row) {
                            return data ? new Date(data).toLocaleDateString() : '-';
                        }
                    },
                    { 
                        data: 'updated_at', 
                        name: 'updated_at',
                        render: function(data, type, row) {
                            return data ? new Date(data).toLocaleDateString() : '-';
                        }
                    },
                    { 
                        data: null, 
                        orderable: false, 
                        searchable: false,
                        width: '100px',
                        render: function(data, type, row) {
                            const editBtn = `<button type="button" class="btn btn-sm btn-primary" onclick="editAdmin(${row.admin_id})" title="Edit" style="margin-right: 5px;">
                                <i class="fa fa-edit"></i>
                               </button>`;
                            const statusButton = row.status === 'Active' 
                                ? `<button type="button" class="btn btn-sm btn-outline-warning" onclick="toggleAdminStatus(${row.admin_id}, '${row.full_name}', 'Inactive')" title="Deactivate">
                                    <i class="fa fa-user-times"></i>
                                   </button>`
                                : `<button type="button" class="btn btn-sm btn-outline-success" onclick="toggleAdminStatus(${row.admin_id}, '${row.full_name}', 'Active')" title="Activate">
                                    <i class="fa fa-user-check"></i>
                                   </button>`;
                            
                            return editBtn + statusButton;
                        }
                    }
                ],
                order: [[0, 'desc']],
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                responsive: true,
                language: {
                    processing: "Loading admins...",
                    emptyTable: "No admin records found",
                    zeroRecords: "No matching admin records found"
                }
            });

            // Add admin button
            $('#addAdminBtn').on('click', function() {
                resetForm();
                $('#adminModalLabel').html('Add New Admin');
                $('#adminModal').modal('show');
            });
            
            // Close modal on cancel
            $('#adminModal').on('hidden.bs.modal', function() {
                resetForm();
            });

            // Form submission with enhanced validation
            $('#adminForm').on('submit', function(e) {
                e.preventDefault();
                
                // Validate form before submission
                if (!validateForm()) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: 'Please fix all validation errors before submitting the form.'
                    });
                    return;
                }
                
                const formData = new FormData(this);
                const isEdit = $('#adminId').val() !== '';
                
                // For edit mode, exclude password and status fields since they're hidden
                if (isEdit) {
                    formData.delete('password');
                    formData.delete('status');
                }
                
                formData.append('action', isEdit ? 'update_admin' : 'add_admin');

                // Show loading state
                const $submitBtn = $(this).find('button[type="submit"]');
                const originalText = $submitBtn.html();
                $submitBtn.prop('disabled', true).html('Saving...');

                $.ajax({
                    url: 'admin_api.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            $('#adminModal').modal('hide');
                            resetForm();
                            
                            // Use refresh function instead of direct draw
                            refreshTableData();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: 'An error occurred while processing your request.'
                        });
                    },
                    complete: function() {
                        // Reset button state
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                });
            });


            // Update filter counts and results counter
            function updateFilterCounts() {
                $.ajax({
                    url: 'admin_api.php',
                    type: 'POST',
                    data: {
                        action: 'get_counts'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#totalCount').text(response.data.total);
                            $('#activeCount').text(response.data.active);
                            $('#inactiveCount').text(response.data.inactive);
                            
                            // Update showing count with animation
                            const showingCount = adminTable.page.info().recordsDisplay;
                            const $showingCount = $('#showingCount');
                            const $resultsCounter = $('#resultsCounter');
                            
                            $showingCount.text(showingCount);
                            
                            // Update badge color based on results
                            $resultsCounter.removeClass('badge-secondary badge-success badge-warning');
                            if (showingCount === 0) {
                                $resultsCounter.addClass('badge-warning');
                            } else if (showingCount < response.data.total) {
                                $resultsCounter.addClass('badge-success');
                            } else {
                                $resultsCounter.addClass('badge-secondary');
                            }
                        }
                    }
                });
            }

            // Real-time validation with duplicate checking
            
            // Username validation with real-time duplicate checking
            let usernameTimeout;
            $('#username').on('input', function() {
                const $this = $(this);
                const value = $this.val().trim();
                const $feedback = $this.siblings('.invalid-feedback');
                
                // Clear previous timeout
                clearTimeout(usernameTimeout);
                
                if (value.length === 0) {
                    $this.removeClass('is-valid is-invalid validating');
                    $feedback.text('');
                    return;
                }
                
                // Basic format validation
                if (value.length < 3) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Username must be at least 3 characters long');
                    return;
                }
                
                // Check for valid characters (alphanumeric and underscore only)
                if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Username can only contain letters, numbers, and underscores');
                    return;
                }
                
                // Show checking state
                $this.removeClass('is-valid is-invalid').addClass('validating');
                $feedback.text('Checking availability...');
                
                // Debounced duplicate check
                usernameTimeout = setTimeout(function() {
                    checkDuplicate('username', value, function(response) {
                        if (response.success) {
                            if (response.exists) {
                                $this.removeClass('is-valid validating').addClass('is-invalid');
                                $feedback.text('Username is already taken');
                            } else {
                                $this.removeClass('is-invalid validating').addClass('is-valid');
                                $feedback.text('Username is available');
                            }
                        } else {
                            $this.removeClass('is-valid validating').addClass('is-invalid');
                            $feedback.text('Error checking username availability');
                        }
                    });
                }, 500); // 500ms delay
            });
            
            // Email validation with enhanced format checking and duplicate validation
            let emailTimeout;
            $('#email').on('input', function() {
                const $this = $(this);
                const value = $this.val().trim();
                const $feedback = $this.siblings('.invalid-feedback');
                
                // Clear previous timeout
                clearTimeout(emailTimeout);
                
                if (value.length === 0) {
                    $this.removeClass('is-valid is-invalid validating');
                    $feedback.text('');
                    return;
                }
                
                // Enhanced email format validation
                const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
                
                if (!emailRegex.test(value)) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Please enter a valid email address');
                    return;
                }
                
                // Check for common email domains
                const commonDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'aol.com'];
                const domain = value.split('@')[1].toLowerCase();
                
                if (value.length > 254) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Email address is too long');
                    return;
                }
                
                // Show checking state
                $this.removeClass('is-valid is-invalid').addClass('validating');
                $feedback.text('Checking email availability...');
                
                // Debounced duplicate check
                emailTimeout = setTimeout(function() {
                    checkDuplicate('email', value, function(response) {
                        if (response.success) {
                            if (response.exists) {
                                $this.removeClass('is-valid validating').addClass('is-invalid');
                                $feedback.text('Email address is already registered');
                            } else {
                                $this.removeClass('is-invalid validating').addClass('is-valid');
                                $feedback.text('Email address is available');
                            }
                        } else {
                            $this.removeClass('is-valid validating').addClass('is-invalid');
                            $feedback.text('Error checking email availability');
                        }
                    });
                }, 500); // 500ms delay
            });
            
            // Contact number validation for Philippine mobile numbers (09 + 11 digits)
            let contactTimeout;
            $('#contactNumber').on('input', function() {
                const $this = $(this);
                let value = $this.val().trim();
                const $feedback = $this.siblings('.invalid-feedback');
                
                // Clear previous timeout
                clearTimeout(contactTimeout);
                
                if (value.length === 0) {
                    $this.removeClass('is-valid is-invalid validating');
                    $feedback.text('');
                    return;
                }
                
                // Remove all non-digit characters for validation
                const digitsOnly = value.replace(/\D/g, '');
                
                // Auto-format Philippine mobile number as user types
                let formattedValue = value;
                
                if (digitsOnly.length <= 2) {
                    formattedValue = digitsOnly;
                } else if (digitsOnly.length <= 5) {
                    formattedValue = digitsOnly.replace(/(\d{2})(\d+)/, '$1 $2');
                } else if (digitsOnly.length <= 8) {
                    formattedValue = digitsOnly.replace(/(\d{2})(\d{3})(\d+)/, '$1 $2 $3');
                } else if (digitsOnly.length <= 11) {
                    formattedValue = digitsOnly.replace(/(\d{2})(\d{3})(\d{3})(\d+)/, '$1 $2 $3 $4');
                }
                
                // Update the input value if formatting changed
                if (formattedValue !== value) {
                    $this.val(formattedValue);
                    value = formattedValue;
                }
                
                // Philippine mobile number validation - must start with 09 and be exactly 11 digits
                if (digitsOnly.length !== 11) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Phone number must be exactly 11 digits');
                    return;
                }
                
                if (!digitsOnly.startsWith('09')) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Phone number must start with 09');
                    return;
                }
                
                // Check for common invalid patterns
                if (digitsOnly.match(/^(\d)\1{10}$/)) {
                    $this.removeClass('is-valid validating').addClass('is-invalid');
                    $feedback.text('Phone number cannot be all the same digit');
                    return;
                }
                
                // Show checking state
                $this.removeClass('is-valid is-invalid').addClass('validating');
                $feedback.text('Checking phone number availability...');
                
                // Debounced duplicate check
                contactTimeout = setTimeout(function() {
                    checkDuplicate('contact_number', value, function(response) {
                        if (response.success) {
                            if (response.exists) {
                                $this.removeClass('is-valid validating').addClass('is-invalid');
                                $feedback.text('Phone number is already registered');
                            } else {
                                $this.removeClass('is-invalid validating').addClass('is-valid');
                                $feedback.text('Phone number is available');
                            }
                        } else {
                            $this.removeClass('is-valid validating').addClass('is-invalid');
                            $feedback.text('Error checking phone number availability');
                        }
                    });
                }, 500); // 500ms delay
            });
            
            // Password toggle functionality
            $('#togglePassword').on('click', function() {
                const $passwordField = $('#password');
                const $toggleBtn = $(this);
                
                if ($passwordField.attr('type') === 'password') {
                    $passwordField.attr('type', 'text');
                    $toggleBtn.text('Hide');
                    $toggleBtn.addClass('password-visible');
                    $toggleBtn.attr('title', 'Hide Password');
                } else {
                    $passwordField.attr('type', 'password');
                    $toggleBtn.text('Show/Hide');
                    $toggleBtn.removeClass('password-visible');
                    $toggleBtn.attr('title', 'Show Password');
                }
            });
            
            // Password generation functionality
            $('#generatePassword').on('click', function() {
                const $passwordField = $('#password');
                const $generateBtn = $(this);
                
                // Add visual feedback
                $generateBtn.text('Generating...');
                
                // Generate password after short delay for visual feedback
                setTimeout(function() {
                    const generatedPassword = generateStrongPassword();
                    $passwordField.val(generatedPassword);
                    
                    // Trigger validation to update strength meter
                    $passwordField.trigger('input');
                    
                    // Show success feedback
                    $generateBtn.addClass('btn-success').removeClass('btn-outline-info');
                    $generateBtn.text('Generated!');
                    
                    setTimeout(function() {
                        $generateBtn.removeClass('btn-success').addClass('btn-outline-info');
                        $generateBtn.text('Generate');
                    }, 1000);
                }, 500);
            });
            
            // Password generation function
            function generateStrongPassword() {
                const length = 12;
                const charset = {
                    lowercase: 'abcdefghijklmnopqrstuvwxyz',
                    uppercase: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    numbers: '0123456789',
                    symbols: '!@#$%^&*()_+-=[]{}|;:,.<>?'
                };
                
                let password = '';
                
                // Ensure at least one character from each category
                password += charset.lowercase[Math.floor(Math.random() * charset.lowercase.length)];
                password += charset.uppercase[Math.floor(Math.random() * charset.uppercase.length)];
                password += charset.numbers[Math.floor(Math.random() * charset.numbers.length)];
                password += charset.symbols[Math.floor(Math.random() * charset.symbols.length)];
                
                // Fill the rest with random characters
                const allChars = charset.lowercase + charset.uppercase + charset.numbers + charset.symbols;
                for (let i = 4; i < length; i++) {
                    password += allChars[Math.floor(Math.random() * allChars.length)];
                }
                
                // Shuffle the password
                return password.split('').sort(() => Math.random() - 0.5).join('');
            }
            
            // Password strength validation
            $('#password').on('input', function() {
                const $this = $(this);
                const value = $this.val();
                const $strength = $('#passwordStrength');
                
                if (value.length === 0) {
                    $this.removeClass('is-valid').addClass('is-invalid');
                    $strength.text('Password is required');
                    return;
                }
                
                let strength = 0;
                let strengthText = '';
                let strengthClass = '';
                
                // Length check
                if (value.length >= 8) strength++;
                else strengthText = 'Password must be at least 8 characters';
                
                // Uppercase check
                if (/[A-Z]/.test(value)) strength++;
                
                // Lowercase check
                if (/[a-z]/.test(value)) strength++;
                
                // Number check
                if (/[0-9]/.test(value)) strength++;
                
                // Special character check
                if (/[^A-Za-z0-9]/.test(value)) strength++;
                
                if (strength < 3) {
                    $this.removeClass('is-valid').addClass('is-invalid');
                    strengthClass = 'strength-weak';
                    strengthText = strengthText || 'Weak password';
                } else if (strength < 5) {
                    $this.removeClass('is-invalid').addClass('is-valid');
                    strengthClass = 'strength-medium';
                    strengthText = 'Medium strength password';
                } else {
                    $this.removeClass('is-invalid').addClass('is-valid');
                    strengthClass = 'strength-strong';
                    strengthText = 'Strong password';
                }
                
                $strength.text(strengthText).removeClass('strength-weak strength-medium strength-strong').addClass(strengthClass);
            });
            
            // Full name validation with enhanced checking
            $('#fullName').on('input', function() {
                const $this = $(this);
                const value = $this.val().trim();
                const $feedback = $this.siblings('.invalid-feedback');
                
                if (value.length === 0) {
                    $this.removeClass('is-valid is-invalid');
                    $feedback.text('');
                    return;
                }
                
                // Check minimum length
                if (value.length < 2) {
                    $this.removeClass('is-valid').addClass('is-invalid');
                    $feedback.text('Full name must be at least 2 characters long');
                    return;
                }
                
                // Check for valid characters (letters, spaces, hyphens, apostrophes)
                if (!/^[a-zA-Z\s\-']+$/.test(value)) {
                    $this.removeClass('is-valid').addClass('is-invalid');
                    $feedback.text('Full name can only contain letters, spaces, hyphens, and apostrophes');
                    return;
                }
                
                // Check for multiple words (at least first and last name)
                const words = value.split(/\s+/).filter(word => word.length > 0);
                if (words.length < 2) {
                    $this.removeClass('is-valid').addClass('is-invalid');
                    $feedback.text('Please enter both first and last name');
                    return;
                }
                
                // Check for reasonable length
                if (value.length > 100) {
                    $this.removeClass('is-valid').addClass('is-invalid');
                    $feedback.text('Full name is too long');
                    return;
                }
                
                // Valid name
                $this.removeClass('is-invalid').addClass('is-valid');
                $feedback.text('Full name looks good');
            });

            // Check duplicate function
            function checkDuplicate(field, value, callback) {
                $.ajax({
                    url: 'admin_api.php',
                    type: 'POST',
                    data: {
                        action: 'check_duplicate',
                        field: field,
                        value: value,
                        admin_id: $('#adminId').val() // For edit mode
                    },
                    dataType: 'json',
                    success: function(response) {
                        callback(response);
                    },
                    error: function() {
                        callback({
                            success: false,
                            message: 'Error checking availability'
                        });
                    }
                });
            }
            
            // Form validation before submission
            function validateForm() {
                let isValid = true;
                const requiredFields = ['username', 'password', 'fullName', 'email', 'contactNumber'];
                
                requiredFields.forEach(function(field) {
                    const $field = $('#' + field);
                    
                    if (!$field.hasClass('is-valid')) {
                        isValid = false;
                        $field.addClass('is-invalid');
                    }
                });
                
                // Check if any field is still validating
                if ($('.validating').length > 0) {
                    isValid = false;
                    Swal.fire({
                        icon: 'warning',
                        title: 'Please wait',
                        text: 'Some fields are still being validated. Please wait a moment.'
                    });
                }
                
                return isValid;
            }
            
            // Reset form
            function resetForm() {
                $('#adminForm')[0].reset();
                $('#adminId').val('');
                $('.invalid-feedback').text('');
                $('.form-control, .form-select').removeClass('is-invalid is-valid validating');
                $('.password-strength').text('').removeClass('strength-weak strength-medium strength-strong');
                
                // Clear all timeouts
                clearTimeout(usernameTimeout);
                clearTimeout(emailTimeout);
                clearTimeout(contactTimeout);
                
                // Show password and status fields for add mode
                $('#password').closest('.form-group').show();
                $('#status').closest('.form-group').show();
                
                // Reset password toggle button
                $('#password').attr('type', 'password');
                $('#togglePassword').removeClass('password-visible').attr('title', 'Show Password').text('Show/Hide');
                
                // Reset generate password button
                $('#generatePassword').removeClass('btn-success').addClass('btn-outline-info').text('Generate');
            }

            // Refresh table data
            function refreshTableData() {
                // Show subtle loading indicator
                $('#adminTable tbody').addClass('refreshing');
                
                adminTable.ajax.reload(function() {
                    $('#adminTable tbody').removeClass('refreshing');
                    updateFilterCounts();
                }, false); // false = keep current page and filters
            }
            
            // Top search bar functionality
            $('.top_search .form-control').on('keyup', function(e) {
                if (e.keyCode === 13) {
                    adminTable.search($(this).val()).draw();
                    updateFilterCounts();
                }
            });
            
            $('.top_search button').on('click', function() {
                adminTable.search($('.top_search .form-control').val()).draw();
                updateFilterCounts();
            });
            
            // Clear filters button
            $('#clearFiltersBtn').on('click', function() {
                $('.top_search .form-control').val('');
                adminTable.search('').draw();
                updateFilterCounts();
            });
            
            // Initial count update
            updateFilterCounts();
            
            // Initial count update
            updateFilterCounts();
        });


        // Edit admin function
        function editAdmin(adminId) {
            $.ajax({
                url: 'admin_api.php',
                type: 'POST',
                data: {
                    action: 'get_admin',
                    admin_id: adminId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        const admin = response.data;
                        $('#adminId').val(admin.admin_id);
                        $('#username').val(admin.username);
                        $('#fullName').val(admin.full_name);
                        $('#email').val(admin.email);
                        $('#contactNumber').val(admin.contact_number);
                        $('#status').val(admin.status);
                        
                        // Hide password field for edit mode
                        $('#password').closest('.form-group').hide();
                        $('#status').closest('.form-group').hide();
                        
                        $('#adminModalLabel').html('Edit Admin');
                        $('#adminModal').modal('show');
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message || 'Admin not found.'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'An error occurred while fetching admin data.'
                    });
                }
            });
        }
        
        // Toggle admin status function
        function toggleAdminStatus(adminId, adminName, newStatus) {
            const action = newStatus === 'Active' ? 'activate' : 'deactivate';
            const icon = newStatus === 'Active' ? 'success' : 'warning';
            
            Swal.fire({
                title: `Confirm ${action.charAt(0).toUpperCase() + action.slice(1)}`,
                text: `Are you sure you want to ${action} ${adminName}?`,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: newStatus === 'Active' ? '#28a745' : '#ffc107',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${action}!`,
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
            $.ajax({
                url: 'admin_api.php',
                type: 'POST',
                data: {
                            action: 'toggle_status',
                            admin_id: adminId,
                            status: newStatus
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                                
                                // Add visual feedback
                                const $row = $(`button[onclick*="${adminId}"]`).closest('tr');
                                $row.addClass('status-changing');
                                
                                // Immediate refresh
                                refreshTableData();
                                
                                // Remove animation class after animation completes
                                setTimeout(function() {
                                    $row.removeClass('status-changing');
                                }, 500);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: response.message
                        });
                    }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'An error occurred while updating the admin status.'
                            });
                }
            });
        }
            });
        }
    </script>
    
    <?php include('../../components/footer.php'); ?>
</body>
</html>

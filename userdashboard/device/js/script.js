$(document).ready(function() {
    let statusChart;
    let devicesTable;
    
    // Prevent any existing status update errors
    window.checkForStatusUpdates = function() {
        console.log('Status update checking disabled for device page');
        return Promise.resolve();
    };
    
    // Clear any existing intervals
    if (window.statusUpdateInterval) {
        clearInterval(window.statusUpdateInterval);
        window.statusUpdateInterval = null;
    }
    
    // Initialize the application
    function initApp() {
        initializeDataTable();
        loadDevices();
        loadStatistics();
        
        // Auto-refresh every 30 seconds
        setInterval(function() {
            loadDevices();
            loadStatistics();
        }, 30000);
    }
    
    // Initialize DataTables
    function initializeDataTable() {
        devicesTable = $('#devicesTable').DataTable({
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "All"]],
            order: [[6, 'desc']], // Sort by Last Updated column
            columnDefs: [
                { orderable: false, targets: [7] }, // Actions column not sortable
                { className: "text-center", targets: [0, 4, 5, 7] }, // Center align specific columns
                { className: "text-nowrap", targets: [2, 3] } // No wrap for device numbers
            ],
            dom: 'lfrtip', // Only length, filter, table, info, pagination
            language: {
                search: "Search devices:",
                lengthMenu: "Show _MENU_ devices per page",
                info: "Showing _START_ to _END_ of _TOTAL_ devices",
                infoEmpty: "No devices found",
                infoFiltered: "(filtered from _MAX_ total devices)",
                zeroRecords: "No matching devices found",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            }
        });
        
        console.log('DataTable initialized:', devicesTable);
        
        // Initialize custom filters after DataTable is ready
        setTimeout(function() {
            initializeCustomFilters();
            console.log('Custom filters initialized');
        }, 100);
    }
    
    // Initialize custom filter functionality
    function initializeCustomFilters() {
        // Status filter - search for status text within HTML badges
        $('#statusFilter').on('change', function() {
            const status = $(this).val();
            console.log('Status filter changed to:', status);
            if (status === '') {
                devicesTable.column(4).search('').draw();
            } else {
                // Search for the status text within the HTML badge
                devicesTable.column(4).search(status, false, false).draw();
            }
        });
        
        // Active filter - search for Active/Inactive text within HTML badges
        $('#activeFilter').on('change', function() {
            const active = $(this).val();
            console.log('Active filter changed to:', active);
            if (active === '') {
                devicesTable.column(5).search('').draw();
            } else {
                // Search for the active status text within the HTML badge
                devicesTable.column(5).search(active, false, false).draw();
            }
        });
        
        // Device type filter (searches in device name)
        $('#deviceTypeFilter').on('change', function() {
            const deviceType = $(this).val();
            if (deviceType === '') {
                devicesTable.column(1).search('').draw();
            } else {
                devicesTable.column(1).search(deviceType, false, false).draw();
            }
        });
        
        // Date range filter - improved date filtering
        $('#dateRangeFilter').on('change', function() {
            const dateRange = $(this).val();
            if (dateRange === '') {
                devicesTable.column(6).search('').draw();
            } else {
                const today = new Date();
                let searchPattern = '';
                
                switch(dateRange) {
                    case 'today':
                        // Search for today's date in various formats
                        const todayStr = today.toLocaleDateString();
                        const todayParts = todayStr.split('/');
                        searchPattern = `${todayParts[0]}/${todayParts[1]}/${todayParts[2]}`;
                        break;
                    case 'week':
                        // Search for dates within the last week
                        const weekAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
                        const weekStr = weekAgo.toLocaleDateString();
                        const weekParts = weekStr.split('/');
                        searchPattern = `${weekParts[0]}/${weekParts[1]}/${weekParts[2]}`;
                        break;
                    case 'month':
                        // Search for dates within the last month
                        const monthAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
                        const monthStr = monthAgo.toLocaleDateString();
                        const monthParts = monthStr.split('/');
                        searchPattern = `${monthParts[0]}/${monthParts[1]}/${monthParts[2]}`;
                        break;
                }
                
                if (searchPattern) {
                    devicesTable.column(6).search(searchPattern, false, false).draw();
                }
            }
        });
        
        // Clear all filters button
        $('#clearFilters').on('click', function() {
            $('#statusFilter, #activeFilter, #deviceTypeFilter, #dateRangeFilter').val('');
            devicesTable.search('').columns().search('').draw();
        });
    }
    
    // Login form submission
    $('#loginForm').submit(function(e) {
        e.preventDefault();
        const username = $('#username').val();
        const password = $('#password').val();
        
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: {
                action: 'login',
                username: username,
                password: password
            },
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred during login',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
    
    // Check if user is logged in and initialize the app
    // This will be handled by the PHP backend - if the page loads, user is logged in
    initApp();
    
    // Load devices and statistics on page load
    function loadDevices() {
        $.ajax({
            url: window.location.href + '?action=get_devices',
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    // Clear existing data
                    if (devicesTable) {
                        devicesTable.clear();
                    }
                    
                    if (response.devices.length === 0) {
                        // Add empty row if no devices
                        if (devicesTable) {
                            devicesTable.draw();
                        }
                        return;
                    }
                    
                    // Add devices to DataTable
                    response.devices.forEach(function(device) {
                        let statusClass = `status-${device.status}`;
                        let activeClass = `active-${device.is_active ? 'true' : 'false'}`;
                        let activeIcon = device.is_active ? 'fa-check-circle' : 'fa-times-circle';
                        let updatedAt = new Date(device.updated_at).toLocaleString();
                        
                        let statusBadge = `<span class="badge ${device.status === 'online' ? 'bg-success' : 'bg-danger'}">
                            <i class="fas fa-circle me-1"></i>${device.status.charAt(0).toUpperCase() + device.status.slice(1)}
                        </span>`;
                        
                        let activeBadge = `<span class="badge ${device.is_active ? 'bg-success' : 'bg-secondary'}">
                            <i class="fas ${activeIcon} me-1"></i>${device.is_active ? 'Active' : 'Inactive'}
                        </span>`;
                        
                        let actionsHtml = `
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-info edit-device" data-device-id="${device.device_id}" title="Edit Device">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-danger delete-device" data-device-id="${device.device_id}" title="Delete Device">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        `;
                        
                        if (devicesTable) {
                            devicesTable.row.add([
                                device.device_id,
                                device.device_name,
                                device.device_number,
                                device.serial_number,
                                statusBadge,
                                activeBadge,
                                updatedAt,
                                actionsHtml
                            ]);
                        }
                    });
                    
                    // Draw the table
                    if (devicesTable) {
                        devicesTable.draw();
                    }
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while loading devices',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    }
    
    // Load statistics
    function loadStatistics() {
        $.ajax({
            url: window.location.href + '?action=get_statistics',
            type: 'GET',
            success: function(response) {
                if (response.status === 'success') {
                    const stats = response.statistics;
                    
                    // Update cards
                    $('#totalDevices').text(stats.total_devices);
                    $('#onlineDevices').text(stats.status_data.online);
                    $('#offlineDevices').text(stats.status_data.offline);
                    
                    // Check for device status alerts
                    checkDeviceStatusAlerts(stats);
                }
            },
            error: function() {
                console.error('Error loading statistics');
            }
        });
    }
    
    // Function to check and display device status alerts
    function checkDeviceStatusAlerts(stats) {
        const alertsContainer = $('#deviceAlerts');
        alertsContainer.empty();
        
        let hasAlerts = false;
        let alertHtml = '';
        
        // Check for offline devices
        if (stats.status_data.offline > 0) {
            hasAlerts = true;
            alertHtml += `
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle me-3 fs-4"></i>
                        <div>
                            <strong>Warning:</strong> You have <strong>${stats.status_data.offline}</strong> offline device${stats.status_data.offline > 1 ? 's' : ''}.
                            <br><small class="text-muted">Offline devices may not be functioning properly and could affect your fire detection system's effectiveness.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
        }
        
        // Check for inactive devices - Emergency Alert
        if (stats.total_devices > 0 && stats.active_devices !== undefined) {
            const inactiveCount = stats.total_devices - stats.active_devices;
            if (inactiveCount > 0) {
                hasAlerts = true;
                alertHtml += `
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-radiation me-3 fs-4"></i>
                            <div>
                                <strong>EMERGENCY:</strong> You have <strong>${inactiveCount}</strong> inactive device${inactiveCount > 1 ? 's' : ''}!
                                <br><small class="text-muted">Inactive devices are NOT monitoring for fire hazards. Your fire detection system is compromised. Please activate these devices immediately to ensure complete protection.</small>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
            }
        }
        
        // Show or hide alerts container
        if (hasAlerts) {
            alertsContainer.html(alertHtml).show();
        } else {
            alertsContainer.hide();
        }
    }
    
    // Utility: safely show/hide Bootstrap modal across versions (v5/v4)
    function showAddDeviceModal() {
        const addModalEl = document.getElementById('addDeviceModal');
        if (!addModalEl) return;
        try {
            if (typeof $(addModalEl).modal === 'function') {
                $(addModalEl).modal('show');
                return;
            }
            if (window.bootstrap && bootstrap.Modal) {
                var instanceShow = new bootstrap.Modal(addModalEl);
                instanceShow.show();
            }
        } catch (e) {
            try { if (typeof $(addModalEl).modal === 'function') $(addModalEl).modal('show'); } catch (_) {}
        }
    }

    function hideAddDeviceModal() {
        const addModalEl = document.getElementById('addDeviceModal');
        if (!addModalEl) return;
        try {
            if (typeof $(addModalEl).modal === 'function') {
                $(addModalEl).modal('hide');
                return;
            }
            if (window.bootstrap && bootstrap.Modal) {
                var instanceHide = new bootstrap.Modal(addModalEl);
                instanceHide.hide();
            }
        } catch (e) {
            try { if (typeof $(addModalEl).modal === 'function') $(addModalEl).modal('hide'); } catch (_) {}
        }
    }

    // Add device button click (Bootstrap modal)
    $('#addDeviceBtn').click(function() {
        const addModalEl = document.getElementById('addDeviceModal');
        if (addModalEl) {
            // reset form
            $('#addDeviceForm')[0]?.reset?.();
            $('#device_name_validation').html('');
            $('#device_number_validation').html('');
            $('#serial_number_validation').html('');
            showAddDeviceModal();
        }
    });
    
    
    // Ensure cancel and close buttons always hide the modal
    $(document).on('click', '#addDeviceModal .btn-close, #addDeviceModal [data-bs-dismiss="modal"]', function() {
        hideAddDeviceModal();
    });
    
    // Refresh device list
    $('#refreshDeviceList').click(function() {
        loadDevices();
        loadStatistics();
    });
    
    // Bootstrap Add Device form handlers
    $(document).on('input', '#device_number, #serial_number', function() {
        validateDeviceInputs();
    });
    $(document).on('input', '#device_name', function() {
        const deviceName = $(this).val();
        if (deviceName && deviceName.trim().length < 2) {
            $('#device_name_validation').html('<span class="text-danger">Device name must be at least 2 characters long</span>');
        } else {
            $('#device_name_validation').html('');
        }
    });
    
    $(document).on('click', '#addDeviceSubmit', function() {
        const form = document.getElementById('addDeviceForm');
        if (!form) return;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const deviceNumber = $('#device_number').val();
        const serialNumber = $('#serial_number').val();
        if (!deviceNumber.trim() || !serialNumber.trim()) {
            return;
        }
        const payload = {
            action: 'add_device',
            device_name: $('#device_name').val(),
            device_number: deviceNumber,
            serial_number: serialNumber
        };
        // Disable button to prevent double submit
        const $btn = $(this);
        $btn.prop('disabled', true).text('Adding...');
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: payload,
            success: function(response) {
                hideAddDeviceModal();
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message || 'Device added successfully',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        loadDevices();
                        loadStatistics();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message || 'Failed to add device',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                hideAddDeviceModal();
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while adding the device',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            },
            complete: function() {
                $btn.prop('disabled', false).text('Add Device');
            }
        });
    });
    
    // Function to validate device inputs
    function validateDeviceInputs() {
        const deviceNumber = $('#device_number').val();
        const serialNumber = $('#serial_number').val();
        const deviceName = $('#device_name').val();
        
        // Clear previous validation messages
        $('#device_number_validation').html('');
        $('#serial_number_validation').html('');
        
        // Validate device name (basic client-side validation)
        if (deviceName && deviceName.trim().length < 2) {
            $('#device_name').addClass('is-invalid');
            return;
        } else {
            $('#device_name').removeClass('is-invalid');
        }
        
        // Only check with server if both fields have values
        if (deviceNumber && serialNumber) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'validate_device',
                    device_number: deviceNumber,
                    serial_number: serialNumber
                },
                success: function(response) {
                    if (response.status === 'success') {
                        $('#device_number_validation').html('<span class="validation-success">Valid device number and serial number combination</span>');
                        $('#serial_number_validation').html('<span class="validation-success">Device combination approved</span>');
                    } else {
                        $('#device_number_validation').html('<span class="validation-error">Device number and serial number combination not found in approved devices</span>');
                        $('#serial_number_validation').html('');
                    }
                }
            });
        }
    }
    
    // Edit device (Bootstrap modal instead of SweetAlert)
    function showEditDeviceModal() {
        const el = document.getElementById('editDeviceModal');
        if (!el) return;
        try {
            if (typeof $(el).modal === 'function') {
                $(el).modal('show');
                return;
            }
            if (window.bootstrap && bootstrap.Modal) {
                var inst = new bootstrap.Modal(el);
                inst.show();
            }
        } catch (e) {
            try { if (typeof $(el).modal === 'function') $(el).modal('show'); } catch (_) {}
        }
    }

    function hideEditDeviceModal() {
        const el = document.getElementById('editDeviceModal');
        if (!el) return;
        try {
            if (typeof $(el).modal === 'function') {
                $(el).modal('hide');
                return;
            }
            if (window.bootstrap && bootstrap.Modal) {
                var inst = bootstrap.Modal.getInstance(el) || new bootstrap.Modal(el);
                inst.hide();
            }
        } catch (e) {
            try { if (typeof $(el).modal === 'function') $(el).modal('hide'); } catch (_) {}
        }
    }

    $(document).on('click', '.edit-device', function() {
        const deviceId = $(this).data('device-id');
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: { action: 'get_device_details', device_id: deviceId },
            success: function(response) {
                if (response.status === 'success') {
                    const d = response.device;
                    $('#edit_device_id').val(deviceId);
                    $('#edit_device_name').val(d.device_name || '');
                    $('#edit_device_number').val(d.device_number || '');
                    $('#edit_serial_number').val(d.serial_number || '');
                    $('#edit_device_name_validation').html('');
                    $('#edit_device_number_validation').html('');
                    $('#edit_serial_number_validation').html('');
                    showEditDeviceModal();
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while loading device details',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // Save edit via AJAX
    $(document).on('click', '#saveEditDevice', function() {
        const form = document.getElementById('editDeviceForm');
        if (!form) return;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        const payload = {
            action: 'update_device',
            device_id: $('#edit_device_id').val(),
            device_name: $('#edit_device_name').val(),
            device_number: $('#edit_device_number').val(),
            serial_number: $('#edit_serial_number').val()
        };
        const $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        $.ajax({
            url: window.location.href,
            type: 'POST',
            data: payload,
            success: function(response) {
                hideEditDeviceModal();
                if (response.status === 'success') {
                    Swal.fire({
                        title: 'Success!',
                        text: response.message,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        loadDevices();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: response.message,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                hideEditDeviceModal();
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred while updating the device',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            },
            complete: function() {
                $btn.prop('disabled', false).text('Save Changes');
            }
        });
    });

    // Re-bind validations in edit modal inputs
    $(document).on('input', '#edit_device_number, #edit_serial_number', function() {
        validateEditDeviceInputs();
    });
    $(document).on('input', '#edit_device_name', function() {
        const deviceName = $(this).val();
        if (deviceName && deviceName.trim().length < 2) {
            $('#edit_device_name_validation').html('<span class="text-danger">Device name must be at least 2 characters long</span>');
        } else {
            $('#edit_device_name_validation').html('');
        }
    });
    
    
    // Delete device
    $(document).on('click', '.delete-device', function() {
        const deviceId = $(this).data('device-id');
        
        Swal.fire({
            title: 'Are you sure?',
            text: 'This action cannot be undone!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: window.location.href,
                    type: 'POST',
                    data: {
                        action: 'delete_device',
                        device_id: deviceId
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                title: 'Deleted!',
                                text: response.message,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                loadDevices();
                                loadStatistics();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error!',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'An error occurred while deleting the device',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });
    });
    

    // Add this function at the end of the file or after validateDeviceInputs
    function validateEditDeviceInputs() {
        const deviceNumber = $('#edit_device_number').val();
        const serialNumber = $('#edit_serial_number').val();
        const deviceName = $('#edit_device_name').val();
        
        // Clear previous validation messages
        $('#edit_device_number_validation').html('');
        $('#edit_serial_number_validation').html('');
        
        // Validate device name (basic client-side validation)
        if (deviceName && deviceName.trim().length < 2) {
            $('#edit_device_name').addClass('is-invalid');
            return;
        } else {
            $('#edit_device_name').removeClass('is-invalid');
        }
        
        // Only check with server if both fields have values
        if (deviceNumber && serialNumber) {
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    action: 'validate_device',
                    device_number: deviceNumber,
                    serial_number: serialNumber
                },
                success: function(response) {
                    if (response.status === 'success') {
                        $('#edit_device_number_validation').html('<span class="validation-success"><i class="fas fa-check-circle"></i> Valid device number and serial number combination</span>');
                        $('#edit_serial_number_validation').html('<span class="validation-success"><i class="fas fa-check-circle"></i> Device combination approved</span>');
                    } else {
                        $('#edit_device_number_validation').html('<span class="validation-error"><i class="fas fa-times-circle"></i> Device number and serial number combination not found in approved devices</span>');
                        $('#edit_serial_number_validation').html('');
                    }
                }
            });
        }
    }
    
    // Prevent any status update checking errors
    if (typeof checkForStatusUpdates === 'function') {
        // Override the function to prevent errors
        window.checkForStatusUpdates = function() {
            console.log('Status update checking disabled for device page');
        };
    }
    
    // Clear any existing intervals that might be causing issues
    if (window.statusUpdateInterval) {
        clearInterval(window.statusUpdateInterval);
        window.statusUpdateInterval = null;
    }
});
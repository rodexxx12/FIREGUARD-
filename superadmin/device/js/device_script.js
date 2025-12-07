document.addEventListener('DOMContentLoaded', function() {

    
    // Validation patterns for new structured format
    const deviceNumberPattern = /^DV1-PHI-\d{6}$/;
    const serialNumberPattern = /^SEN-\d{4}-\d{6}$/;

    // Function to validate device number
    function validateDeviceNumber(value) {
        const feedback = document.getElementById('device_number_feedback');
        const input = document.getElementById('device_number');
        
        if (!value) {
            input.classList.add('is-invalid');
            feedback.textContent = 'Device number is required';
            feedback.style.display = 'block';
            return false;
        }
        
        if (!deviceNumberPattern.test(value)) {
            input.classList.add('is-invalid');
            feedback.textContent = 'Device number must be in format: DV1-PHI-[UNIQUE_ID] (e.g., DV1-PHI-000345)';
            feedback.style.display = 'block';
            return false;
        }
        
        return true;
    }

    // Function to validate serial number
    function validateSerialNumber(value) {
        const feedback = document.getElementById('serial_number_feedback');
        const input = document.getElementById('serial_number');
        
        if (!value) {
            input.classList.add('is-invalid');
            feedback.textContent = 'Serial number is required';
            feedback.style.display = 'block';
            return false;
        }
        
        if (!serialNumberPattern.test(value)) {
            input.classList.add('is-invalid');
            feedback.textContent = 'Serial number must be in format: SEN-[YYWW]-[SERIAL] (e.g., SEN-2519-005871)';
            feedback.style.display = 'block';
            return false;
        }
        
        return true;
    }

    // Function to reset add form
    function resetAddForm() {
        const form = document.getElementById('addDeviceForm');
        form.reset();
        document.getElementById('device_number').classList.remove('is-invalid', 'is-valid');
        document.getElementById('serial_number').classList.remove('is-invalid', 'is-valid');
        document.getElementById('device_number_feedback').style.display = 'none';
        document.getElementById('serial_number_feedback').style.display = 'none';
        
        // Reset device info container
        const deviceInfoContainer = document.getElementById('deviceInfoContainer');
        deviceInfoContainer.innerHTML = `
            <div class="device-preview-empty">
                <i class="fa fa-info-circle"></i>
                <p>Device information will be displayed here</p>
                <small>Enter device number and serial number to see preview</small>
            </div>
        `;
    }

    // Function to update device information preview
    function updateDeviceInfoPreview() {
        const deviceNumber = document.getElementById('device_number').value.trim();
        const serialNumber = document.getElementById('serial_number').value.trim();
        const status = document.getElementById('status').value;
        const deviceInfoContainer = document.getElementById('deviceInfoContainer');
        
        if (deviceNumber && serialNumber && validateDeviceNumber(deviceNumber) && validateSerialNumber(serialNumber)) {
            // Show device information preview
            const statusClass = status === 'approved' ? 'approved' : status === 'pending' ? 'pending' : 'deactivated';
            const statusIcon = status === 'approved' ? 'fa-check-circle' : status === 'pending' ? 'fa-clock' : 'fa-ban';
            
            deviceInfoContainer.innerHTML = `
                <div class="device-preview-content">
                    <div class="device-preview-header">
                        <h6 class="device-preview-title">
                            <i class="fa fa-microchip"></i>
                            Device Information
                        </h6>
                        <span class="device-preview-badge ${statusClass}">
                            <i class="fa ${statusIcon}"></i>
                            ${status.charAt(0).toUpperCase() + status.slice(1)}
                        </span>
                    </div>
                    <div class="device-preview-grid">
                        <div class="device-preview-item">
                            <p class="device-preview-label">
                                <i class="fa fa-hashtag"></i> Device Number
                            </p>
                            <p class="device-preview-value code">${deviceNumber}</p>
                        </div>
                        <div class="device-preview-item">
                            <p class="device-preview-label">
                                <i class="fa fa-barcode"></i> Serial Number
                            </p>
                            <p class="device-preview-value code">${serialNumber}</p>
                        </div>
                        <div class="device-preview-item full-width">
                            <p class="device-preview-label">
                                <i class="fa fa-cog"></i> Device Type
                            </p>
                            <p class="device-preview-value">Fire Detection Device</p>
                        </div>
                    </div>
                    <div class="device-preview-footer">
                        <p class="device-preview-footer-text">
                            <i class="fa fa-info-circle"></i>
                            Click "Add Device" to save to database
                        </p>
                    </div>
                </div>
            `;
        } else {
            // Reset to default state
            deviceInfoContainer.innerHTML = `
                <div class="device-preview-empty">
                    <i class="fa fa-info-circle"></i>
                    <p>Device information will be displayed here</p>
                    <small>Enter device number and serial number to see preview</small>
                </div>
            `;
        }
    }



    // Function to download device CSV
    function downloadDeviceCSV(deviceNumber, serialNumber) {
        window.open(`../download_handler.php?action=download_csv&device_number=${encodeURIComponent(deviceNumber)}&serial_number=${encodeURIComponent(serialNumber)}`, '_blank');
    }

    // Function to download device JSON
    function downloadDeviceJSON(deviceNumber, serialNumber) {
        window.open(`../download_handler.php?action=download_json&device_number=${encodeURIComponent(deviceNumber)}&serial_number=${encodeURIComponent(serialNumber)}`, '_blank');
    }

    // Function to download device information in various formats
    function downloadDeviceInfo(deviceNumber, serialNumber, status) {
        Swal.fire({
            title: 'Download Device Information',
            html: `
                <div class="text-center">
                    <p class="mb-3">Choose download format for:</p>
                    <p><strong>Device:</strong> ${deviceNumber}</p>
                    <p><strong>Serial:</strong> ${serialNumber}</p>
                    <p><strong>Status:</strong> ${status}</p>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Download CSV',
            cancelButtonText: 'Cancel',
            showDenyButton: true,
            denyButtonText: 'Download JSON',
            showCloseButton: true,
            customClass: {
                popup: 'swal-wide'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                downloadDeviceCSV(deviceNumber, serialNumber);
            } else if (result.isDenied) {
                downloadDeviceJSON(deviceNumber, serialNumber);
            }
        });
    }

    // Function to download all devices from database
    function downloadAllDevices() {
        Swal.fire({
            title: 'Download All Devices',
            text: 'Choose format to download all devices from database',
            showCancelButton: true,
            confirmButtonText: 'Download CSV',
            cancelButtonText: 'Cancel',
            showDenyButton: true,
            denyButtonText: 'Download JSON'
        }).then((result) => {
            if (result.isConfirmed) {
                window.open('../download_handler.php?action=download_bulk', '_blank');
            } else if (result.isDenied) {
                window.open('../download_handler.php?action=download_bulk_json', '_blank');
            }
        });
    }



    // Generate device number - get next available from database
    function generateDeviceNumber() {
        const formData = new FormData();
        formData.append('action', 'get_next_numbers');
        
        fetch('../add_device.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const deviceInput = document.getElementById('device_number');
                deviceInput.value = data.next_device_number;
                deviceInput.classList.remove('is-invalid');
                deviceInput.classList.add('is-valid');
                document.getElementById('device_number_feedback').style.display = 'none';
                updateDeviceInfoPreview();
            } else {
                console.error('Error getting next device number:', data.message);
                // Fallback to client-side generation
                const deviceNumber = 'DV1-PHI-000001';
                const deviceInput = document.getElementById('device_number');
                deviceInput.value = deviceNumber;
                deviceInput.classList.remove('is-invalid');
                deviceInput.classList.add('is-valid');
                document.getElementById('device_number_feedback').style.display = 'none';
                updateDeviceInfoPreview();
            }
        })
        .catch(error => {
            console.error('Error generating device number:', error);
            // Fallback to client-side generation
            const deviceNumber = 'DV1-PHI-000001';
            const deviceInput = document.getElementById('device_number');
            deviceInput.value = deviceNumber;
            deviceInput.classList.remove('is-invalid');
            deviceInput.classList.add('is-valid');
            document.getElementById('device_number_feedback').style.display = 'none';
            updateDeviceInfoPreview();
        });
    }

    // Generate serial number - get next available from database
    function generateSerialNumber() {
        const formData = new FormData();
        formData.append('action', 'get_next_numbers');
        
        fetch('../add_device.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const serialInput = document.getElementById('serial_number');
                serialInput.value = data.next_serial_number;
                serialInput.classList.remove('is-invalid');
                serialInput.classList.add('is-valid');
                document.getElementById('serial_number_feedback').style.display = 'none';
                updateDeviceInfoPreview();
            } else {
                console.error('Error getting next serial number:', data.message);
                // Fallback to client-side generation
                const now = new Date();
                const year = now.getFullYear().toString().slice(-2);
                const week = Math.ceil((now.getDate() + new Date(now.getFullYear(), 0, 1).getDay()) / 7).toString().padStart(2, '0');
                const serialNumber = `SEN-${year}${week}-000001`;
                const serialInput = document.getElementById('serial_number');
                serialInput.value = serialNumber;
                serialInput.classList.remove('is-invalid');
                serialInput.classList.add('is-valid');
                document.getElementById('serial_number_feedback').style.display = 'none';
                updateDeviceInfoPreview();
            }
        })
        .catch(error => {
            console.error('Error generating serial number:', error);
            // Fallback to client-side generation
            const now = new Date();
            const year = now.getFullYear().toString().slice(-2);
            const week = Math.ceil((now.getDate() + new Date(now.getFullYear(), 0, 1).getDay()) / 7).toString().padStart(2, '0');
            const serialNumber = `SEN-${year}${week}-000001`;
            const serialInput = document.getElementById('serial_number');
            serialInput.value = serialNumber;
            serialInput.classList.remove('is-invalid');
            serialInput.classList.add('is-valid');
            document.getElementById('serial_number_feedback').style.display = 'none';
            updateDeviceInfoPreview();
        });
    }

    // Generate both device number and serial number - get from database
    function generateBoth() {
        const formData = new FormData();
        formData.append('action', 'get_next_numbers');
        
        fetch('../add_device.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Set device number
                const deviceInput = document.getElementById('device_number');
                deviceInput.value = data.next_device_number;
                deviceInput.classList.remove('is-invalid');
                deviceInput.classList.add('is-valid');
                document.getElementById('device_number_feedback').style.display = 'none';
                
                // Set serial number
                const serialInput = document.getElementById('serial_number');
                serialInput.value = data.next_serial_number;
                serialInput.classList.remove('is-invalid');
                serialInput.classList.add('is-valid');
                document.getElementById('serial_number_feedback').style.display = 'none';
                
                updateDeviceInfoPreview();
            } else {
                console.error('Error getting next numbers:', data.message);
                // Fallback to individual generation
                generateDeviceNumber();
                generateSerialNumber();
            }
        })
        .catch(error => {
            console.error('Error generating both numbers:', error);
            // Fallback to individual generation
            generateDeviceNumber();
            generateSerialNumber();
        });
    }

    // Event listeners for generation buttons
    const generateDeviceBtn = document.getElementById('generateDeviceBtn');
    if (generateDeviceBtn) {
        generateDeviceBtn.addEventListener('click', generateDeviceNumber);
    }

    const generateSerialBtn = document.getElementById('generateSerialBtn');
    if (generateSerialBtn) {
        generateSerialBtn.addEventListener('click', generateSerialNumber);
    }

    const generateBothBtn = document.getElementById('generateBothBtn');
    if (generateBothBtn) {
        generateBothBtn.addEventListener('click', generateBoth);
    }



    // Reset form when modal is closed
    const addDeviceModal = document.getElementById('addDeviceModal');
    if (addDeviceModal) {
        addDeviceModal.addEventListener('hidden.bs.modal', function () {
            resetAddForm();
        });
    }

    // Real-time validation for device number (Add form)
    const deviceNumberInput = document.getElementById('device_number');
    if (deviceNumberInput) {
        let deviceNumberTimeout;
        deviceNumberInput.addEventListener('input', function() {
            clearTimeout(deviceNumberTimeout);
            let deviceNumber = this.value.trim().toUpperCase();
            
            // Auto-format device number to ensure proper format
            if (deviceNumber.length > 0) {
                // Remove any existing hyphens and split into parts
                let parts = deviceNumber.replace(/-/g, '').match(/.{1,6}/g) || [];
                if (parts.length >= 3) {
                    // Ensure first part is DV1 and second part is PHI
                    if (parts[0] !== 'DV1') {
                        parts[0] = 'DV1';
                    }
                    if (parts[1] !== 'PHI') {
                        parts[1] = 'PHI';
                    }
                    deviceNumber = parts[0] + '-' + parts[1] + '-' + parts[2];
                } else if (parts.length === 2) {
                    // Ensure first part is DV1 and second part is PHI
                    if (parts[0] !== 'DV1') {
                        parts[0] = 'DV1';
                    }
                    if (parts[1] !== 'PHI') {
                        parts[1] = 'PHI';
                    }
                    deviceNumber = parts[0] + '-' + parts[1] + '-';
                } else if (parts.length === 1) {
                    // Ensure first part is DV1
                    if (parts[0] !== 'DV1') {
                        parts[0] = 'DV1';
                    }
                    deviceNumber = parts[0] + '-';
                }
            }
            
            this.value = deviceNumber;
            
            // Clear previous validation state
            this.classList.remove('is-invalid');
            this.classList.remove('is-valid');
            document.getElementById('device_number_feedback').style.display = 'none';
            
            if (!deviceNumber) {
                this.classList.add('is-invalid');
                document.getElementById('device_number_feedback').textContent = 'Device number is required';
                document.getElementById('device_number_feedback').style.display = 'block';
                updateDeviceInfoPreview();
                return;
            }
            
            // Validate format
            if (!validateDeviceNumber(deviceNumber)) {
                updateDeviceInfoPreview();
                return;
            }
            
            // Client-side validation only
            deviceNumberTimeout = setTimeout(() => {
                // For now, just mark as valid if format is correct
                // In a real application, you might want to check against a database
                deviceNumberInput.classList.add('is-valid');
                updateDeviceInfoPreview();
            }, 500);
        });
    }

    // Real-time validation for serial number (Add form)
    const serialNumberInput = document.getElementById('serial_number');
    if (serialNumberInput) {
        let serialNumberTimeout;
        serialNumberInput.addEventListener('input', function() {
            clearTimeout(serialNumberTimeout);
            let serialNumber = this.value.trim().toUpperCase();
            
            // Auto-format serial number to ensure proper format
            if (serialNumber.length > 0) {
                // Remove any existing hyphens and format as [PRODUCT_CODE]-[YYWW]-[SERIAL]
                let parts = serialNumber.replace(/-/g, '').match(/.{1,6}/g) || [];
                if (parts.length >= 3) {
                    // Ensure second part is 4 digits (YYWW format)
                    if (parts[1].length === 4 && /^\d{4}$/.test(parts[1])) {
                        serialNumber = parts[0] + '-' + parts[1] + '-' + parts[2];
                    } else {
                        // If second part is not 4 digits, format it properly
                        let yyww = parts[1].padEnd(4, '0').substring(0, 4);
                        serialNumber = parts[0] + '-' + yyww + '-' + parts[2];
                    }
                } else if (parts.length === 2) {
                    // Ensure second part is 4 digits
                    let yyww = parts[1].padEnd(4, '0').substring(0, 4);
                    serialNumber = parts[0] + '-' + yyww + '-';
                } else if (parts.length === 1) {
                    serialNumber = parts[0] + '-';
                }
            }
            
            this.value = serialNumber;
            
            // Clear previous validation state
            this.classList.remove('is-invalid');
            this.classList.remove('is-valid');
            document.getElementById('serial_number_feedback').style.display = 'none';
            
            if (!serialNumber) {
                this.classList.add('is-invalid');
                document.getElementById('serial_number_feedback').textContent = 'Serial number is required';
                document.getElementById('serial_number_feedback').style.display = 'block';
                updateDeviceInfoPreview();
                return;
            }
            
            // Validate format
            if (!validateSerialNumber(serialNumber)) {
                updateDeviceInfoPreview();
                return;
            }
            
            // Client-side validation only
            serialNumberTimeout = setTimeout(() => {
                // For now, just mark as valid if format is correct
                // In a real application, you might want to check against a database
                serialNumberInput.classList.add('is-valid');
                updateDeviceInfoPreview();
            }, 500);
        });
    }

    // Update device info preview when status changes
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        statusSelect.addEventListener('change', updateDeviceInfoPreview);
    }

    // Handle form submission - add device to database
    const addDeviceForm = document.getElementById('addDeviceForm');
    if (addDeviceForm) {
        addDeviceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const deviceNumber = document.getElementById('device_number').value.trim();
            const serialNumber = document.getElementById('serial_number').value.trim();
            const status = document.getElementById('status').value;
            
            // Validate form
            if (!validateDeviceNumber(deviceNumber) || !validateSerialNumber(serialNumber)) {
                return;
            }
            
            // Disable submit button
            const submitBtn = document.getElementById('addDeviceBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Adding...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('device_number', deviceNumber);
            formData.append('serial_number', serialNumber);
            formData.append('status', status);
            
            // Add device to database
            fetch('../add_device.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message with download options
                    Swal.fire({
                        icon: 'success',
                        title: 'Device Added Successfully!',
                        html: `
                            <div class="text-center">
                                <p><strong>Device ID:</strong> ${data.device_id}</p>
                                <p><strong>Device Number:</strong> ${data.device_number}</p>
                                <p><strong>Serial Number:</strong> ${data.serial_number}</p>
                                <p><strong>Status:</strong> ${data.status}</p>
                            </div>
                        `,
                        confirmButtonText: 'Download Device Info',
                        showCancelButton: true,
                        cancelButtonText: 'Close'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            downloadDeviceInfo(deviceNumber, serialNumber, status);
                        }
                        // Close modal automatically
                        const modal = bootstrap.Modal.getInstance(document.getElementById('addDeviceModal'));
                        modal.hide();
                    });
                } else {
                    // Show error message
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                console.error('Error adding device:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error adding device. Please try again.'
                });
            })
            .finally(() => {
                // Re-enable submit button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }

    // Initialize charts and statistics
    function initializeCharts() {
        // Check if Chart.js is available
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return;
        }

        // Initialize Monthly Device Additions Chart
        const monthlyChartCtx = document.getElementById('monthlyChart');
        if (monthlyChartCtx && window.monthlyLabels && window.monthlyData) {
            new Chart(monthlyChartCtx, {
                type: 'line',
                data: {
                    labels: window.monthlyLabels,
                    datasets: [{
                        label: 'Device Additions',
                        data: window.monthlyData,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Monthly Device Additions'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Initialize Status Changes Chart
        const statusChartCtx = document.getElementById('statusChart');
        if (statusChartCtx && window.statusChangeData && window.monthlyLabels) {
            const datasets = [];
            const colors = {
                'approved': { border: 'rgb(75, 192, 192)', background: 'rgba(75, 192, 192, 0.2)' },
                'pending': { border: 'rgb(255, 205, 86)', background: 'rgba(255, 205, 86, 0.2)' },
                'deactivated': { border: 'rgb(255, 99, 132)', background: 'rgba(255, 99, 132, 0.2)' }
            };

            Object.keys(window.statusChangeData).forEach(status => {
                if (window.statusChangeData[status] && colors[status]) {
                    datasets.push({
                        label: status.charAt(0).toUpperCase() + status.slice(1),
                        data: window.statusChangeData[status],
                        borderColor: colors[status].border,
                        backgroundColor: colors[status].background,
                        tension: 0.1,
                        fill: false
                    });
                }
            });

            new Chart(statusChartCtx, {
                type: 'line',
                data: {
                    labels: window.monthlyLabels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: true,
                            text: 'Device Status Changes Over Time'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }
    }

    // Initialize charts when page loads
    initializeCharts();
    
    // ===== Initialize DataTables =====
    let deviceTable;
    const searchInput = document.getElementById('device-search');
    const statusSelectFilter = document.getElementById('device-status');
    
    // Initialize DataTables
    if ($.fn.DataTable && $('#datatable').length) {
        deviceTable = $('#datatable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            responsive: true,
            pageLength: 10,
            order: [[0, 'desc']],
            columnDefs: [
                { orderable: false, targets: 4 } // Disable sorting on Actions column
            ],
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search devices...",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            paging: true,
            pagingType: "full_numbers",
            searching: true,
            info: true
        });
        
        // Custom search input integration
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                deviceTable.search(this.value).draw();
            });
        }
        
        // Custom status filter integration
        if (statusSelectFilter) {
            statusSelectFilter.addEventListener('change', function() {
                const status = this.value;
                if (status === '') {
                    deviceTable.column(3).search('').draw();
                } else {
                    deviceTable.column(3).search('^' + status + '$', true, false).draw();
                }
            });
        }
    }

    // Add event listeners for device status actions
    document.addEventListener('click', function(e) {
        // Deactivate device
        if (e.target.closest('.deactivate-btn')) {
            const button = e.target.closest('.deactivate-btn');
            const deviceId = button.dataset.deviceId;
            const deviceNumber = button.dataset.deviceNumber;
            
            Swal.fire({
                title: 'Deactivate Device',
                text: `Are you sure you want to deactivate device ${deviceNumber}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, deactivate it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    deactivateDevice(deviceId);
                }
            });
        }
        
        // Set device to pending
        if (e.target.closest('.set-pending-btn')) {
            const button = e.target.closest('.set-pending-btn');
            const deviceId = button.dataset.deviceId;
            const deviceNumber = button.dataset.deviceNumber;
            
            Swal.fire({
                title: 'Set Device to Pending',
                text: `Are you sure you want to set device ${deviceNumber} to pending status?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ffc107',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, set to pending!'
            }).then((result) => {
                if (result.isConfirmed) {
                    setDevicePending(deviceId);
                }
            });
        }
        
        // Approve device
        if (e.target.closest('.approve-btn')) {
            const button = e.target.closest('.approve-btn');
            const deviceId = button.dataset.deviceId;
            const deviceNumber = button.dataset.deviceNumber;
            
            Swal.fire({
                title: 'Approve Device',
                text: `Are you sure you want to approve device ${deviceNumber}?`,
                icon: 'success',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, approve it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    approveDevice(deviceId);
                }
            });
        }
    });
    
    // Function to deactivate device
    function deactivateDevice(deviceId) {
        const formData = new FormData();
        formData.append('action', 'deactivate');
        formData.append('id', deviceId);
        
        fetch('../functions/ajax_handler.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while deactivating the device.',
                icon: 'error'
            });
        });
    }
    
    // Function to set device to pending
    function setDevicePending(deviceId) {
        const formData = new FormData();
        formData.append('action', 'set_pending');
        formData.append('id', deviceId);
        
        fetch('../functions/ajax_handler.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while setting the device to pending.',
                icon: 'error'
            });
        });
    }
    
    // Function to approve device
    function approveDevice(deviceId) {
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('id', deviceId);
        
        fetch('../functions/ajax_handler.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    title: 'Success!',
                    text: data.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            } else {
                Swal.fire({
                    title: 'Error!',
                    text: data.message,
                    icon: 'error'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                title: 'Error!',
                text: 'An error occurred while approving the device.',
                icon: 'error'
            });
        });
    }

    // Refresh statistics function
    function refreshStatistics() {
        fetch('../functions/ajax_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=get_statistics'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update statistics cards
                updateStatisticsCards(data.statistics);
                // Update charts
                updateCharts(data.statistics);
            }
        })
        .catch(error => {
            console.error('Error refreshing statistics:', error);
        });
    }

    // Update statistics cards
    function updateStatisticsCards(statistics) {
        // Update total devices
        const totalElement = document.querySelector('.stat-card.total h5');
        if (totalElement && statistics.totalDevices !== undefined) {
            totalElement.textContent = statistics.totalDevices;
        }

        // Update status cards
        if (statistics.statusStats) {
            statistics.statusStats.forEach(stat => {
                const statusElement = document.querySelector(`.stat-card.${stat.status} h5`);
                if (statusElement) {
                    statusElement.textContent = stat.count;
                }
            });
        }
    }

    // Update charts with new data
    function updateCharts(statistics) {
        if (statistics.monthlyChartData) {
            window.monthlyLabels = statistics.monthlyChartData.labels;
            window.monthlyData = statistics.monthlyChartData.data;
        }
        if (statistics.statusChartData) {
            window.statusChangeData = statistics.statusChartData;
        }
        
        // Reinitialize charts
        initializeCharts();
    }

    // Add refresh button functionality
    const refreshBtn = document.querySelector('.refresh-statistics');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function(e) {
            e.preventDefault();
            this.disabled = true;
            this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Refreshing...';
            
            refreshStatistics();
            
            // Reload DataTable if it exists
            if (deviceTable) {
                deviceTable.ajax.reload();
            }
            
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fa fa-refresh"></i> Refresh';
            }, 2000);
        });
    }

}); 
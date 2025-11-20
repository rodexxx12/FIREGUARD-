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
            <div class="text-muted">
                <i class="fas fa-info-circle fa-3x mb-2"></i>
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
            deviceInfoContainer.innerHTML = `
                <div class="text-start">
                    <h6 class="mb-3">Device Information Preview</h6>
                    <div class="mb-2">
                        <strong>Device Number:</strong><br>
                        <code>${deviceNumber}</code>
                    </div>
                    <div class="mb-2">
                        <strong>Serial Number:</strong><br>
                        <code>${serialNumber}</code>
                    </div>
                    <div class="mb-2">
                        <strong>Device Type:</strong><br>
                        <span>Fire Detection Device</span>
                    </div>
                    <div class="mb-2">
                        <strong>Status:</strong><br>
                        <span class="badge bg-${status === 'approved' ? 'success' : status === 'pending' ? 'warning' : 'secondary'}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Click "Add Device" to save to database and get download options.</small>
                    </div>
                </div>
            `;
        } else {
            // Reset to default state
            deviceInfoContainer.innerHTML = `
                <div class="text-muted">
                    <i class="fas fa-info-circle fa-3x mb-2"></i>
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
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
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
    
    // ===== Real-time search & filter with debounce =====
    const searchInput = document.getElementById('device-search');
    const statusSelectFilter = document.getElementById('device-status');
    const tableBody = document.getElementById('device-table-body');
    const paginationContainer = document.getElementById('device-pagination');
    const filterForm = document.getElementById('device-filter-form');

    function renderTableRows(devices) {
        if (!tableBody) return;
        if (!devices || devices.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No fire detection devices found.</td></tr>';
            return;
        }
        const rows = devices.map(device => {
            const status = device.status;
            const actionButtons = (function(){
                if (status === 'approved') {
                    return `
                        <button type="button" class="btn btn-warning btn-sm set-pending-btn" data-device-id="${device.admin_device_id}" data-device-number="${device.device_number}" title="Set to Pending">
                            <i class="fas fa-clock"></i>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm deactivate-btn" data-device-id="${device.admin_device_id}" data-device-number="${device.device_number}" title="Deactivate">
                            <i class="fas fa-ban"></i>
                        </button>
                    `;
                } else if (status === 'pending') {
                    return `
                        <button type="button" class="btn btn-success btn-sm approve-btn" data-device-id="${device.admin_device_id}" data-device-number="${device.device_number}" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-danger btn-sm deactivate-btn" data-device-id="${device.admin_device_id}" data-device-number="${device.device_number}" title="Deactivate">
                            <i class="fas fa-ban"></i>
                        </button>
                    `;
                } else {
                    return `
                        <button type="button" class="btn btn-success btn-sm approve-btn" data-device-id="${device.admin_device_id}" data-device-number="${device.device_number}" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button type="button" class="btn btn-warning btn-sm set-pending-btn" data-device-id="${device.admin_device_id}" data-device-number="${device.device_number}" title="Set to Pending">
                            <i class="fas fa-clock"></i>
                        </button>
                    `;
                }
            })();

            return `
                <tr>
                    <td>${escapeHtml(device.device_number)}</td>
                    <td>${escapeHtml(device.serial_number)}</td>
                    <td>Fire Detection Device</td>
                    <td>
                        <span class="status-badge status-${status}">
                            <i class="fas fa-circle"></i>
                            ${status.charAt(0).toUpperCase() + status.slice(1)}
                        </span>
                    </td>
                    <td class="action-btns">
                        ${actionButtons}
                        <form method="POST" style="display:inline;" class="delete-form">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="${device.admin_device_id}">
                            <button type="submit" class="btn btn-danger btn-sm" title="Delete">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            `;
        }).join('');
        tableBody.innerHTML = rows;
    }

    function renderPagination(meta) {
        if (!paginationContainer) return;
        const current = meta.page;
        const totalPages = meta.total_pages;
        const info = `<div class="text-center small text-muted">Showing 10 per page • Page ${current} of ${totalPages}</div>`;

        function pageLink(page, label, disabled, active) {
            return `<li class="page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}"><a class="page-link" href="#" data-page="${page}">${label}</a></li>`;
        }

        let items = '';
        items += pageLink(current - 1, 'Previous', current <= 1, false);
        for (let i = 1; i <= totalPages; i++) {
            items += pageLink(i, i, false, i === current);
        }
        items += pageLink(current + 1, 'Next', current >= totalPages, false);

        paginationContainer.innerHTML = `
            <nav aria-label="Devices pagination" class="mt-3" data-current-page="${current}">
                <ul class="pagination justify-content-center">${items}</ul>
                ${info}
            </nav>
        `;
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    let debounceTimer;
    function triggerSearch(page = 1) {
        const term = searchInput ? searchInput.value.trim() : '';
        const statusVal = statusSelectFilter ? statusSelectFilter.value : '';

        const formData = new FormData();
        formData.append('action', 'search_devices_realtime');
        formData.append('search_term', term);
        formData.append('status', statusVal);
        formData.append('page', page.toString());
        formData.append('per_page', '10');

        fetch('../functions/ajax_handler.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                renderTableRows(data.devices);
                renderPagination({ page: data.page, total_pages: data.total_pages });
            }
        })
        .catch(err => console.error('Realtime search error:', err));
    }

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => triggerSearch(1), 300);
        });
    }

    if (statusSelectFilter) {
        statusSelectFilter.addEventListener('change', function() {
            triggerSearch(1);
        });
    }

    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            triggerSearch(1);
        });
    }

    if (paginationContainer) {
        paginationContainer.addEventListener('click', function(e) {
            const link = e.target.closest('a.page-link');
            if (link && link.dataset.page) {
                e.preventDefault();
                const page = parseInt(link.dataset.page, 10);
                if (!isNaN(page)) {
                    triggerSearch(page);
                }
            }
        });
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
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            
            refreshStatistics();
            
            setTimeout(() => {
                this.disabled = false;
                this.innerHTML = '<i class="fas fa-sync-alt"></i> Refresh';
            }, 2000);
        });
    }

}); 
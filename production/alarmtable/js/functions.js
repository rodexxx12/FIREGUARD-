// Enhanced JavaScript functionality for Building Alarm Management System

$(document).ready(function() {
    // Initialize DataTable with enhanced features
    var table = $('#buildingsTable').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [8] }, // Actions column
            { className: "text-center", targets: [3, 4, 7, 8] },
            { 
                render: function(data, type, row) {
                    if (type === 'display') {
                        return formatBuildingInfo(data);
                    }
                    return data;
                },
                targets: [0] // Building Info column
            }
        ],
        language: {
            search: "Search buildings:",
            lengthMenu: "Show _MENU_ buildings per page",
            info: "Showing _START_ to _END_ of _TOTAL_ buildings",
            infoEmpty: "No buildings found",
            infoFiltered: "(filtered from _MAX_ total buildings)",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        },
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        initComplete: function() {
            // Add custom styling after table initialization
            $('.dataTables_filter input').addClass('form-control');
            $('.dataTables_length select').addClass('form-control');
            
            // Add refresh button
            addRefreshButton();
            
            // Add export buttons
            addExportButtons();
        }
    });

    // Auto-refresh functionality
    setInterval(function() {
        refreshTableData();
    }, 30000); // Refresh every 30 seconds

    // Add real-time status updates
    initializeWebSocket();
});

function formatBuildingInfo(data) {
    // This function can be used to format building information
    return data;
}

function addRefreshButton() {
    var refreshBtn = '<button type="button" class="btn btn-outline-primary btn-sm ms-2" onclick="refreshTableData()">' +
                     '<i class="fas fa-sync-alt"></i> Refresh</button>';
    $('.dataTables_filter').append(refreshBtn);
}

function addExportButtons() {
    var exportDiv = '<div class="export-buttons mt-2">' +
                    '<button type="button" class="btn btn-success btn-sm me-2" onclick="exportToCSV()">' +
                    '<i class="fas fa-file-csv"></i> Export CSV</button>' +
                    '<button type="button" class="btn btn-info btn-sm" onclick="exportToPDF()">' +
                    '<i class="fas fa-file-pdf"></i> Export PDF</button>' +
                    '</div>';
    $('.dataTables_wrapper').append(exportDiv);
}

function refreshTableData() {
    // Show loading state
    $('.table-container').addClass('loading');
    
    // Reload the page to get fresh data
    setTimeout(function() {
        location.reload();
    }, 1000);
}

function exportToCSV() {
    var table = $('#buildingsTable').DataTable();
    var data = table.data().toArray();
    
    // Convert data to CSV format
    var csvContent = "data:text/csv;charset=utf-8,";
    
    // Add headers
    csvContent += "Building Name,Building Type,Owner,Location,Device Status,Alarm Status,Sensor Readings,Safety Features,ML Analysis\n";
    
    // Add data rows
    data.forEach(function(row) {
        var csvRow = row.join(",");
        csvContent += csvRow + "\n";
    });
    
    // Download CSV
    var encodedUri = encodeURI(csvContent);
    var link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "buildings_alarm_data.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function exportToPDF() {
    Swal.fire({
        title: 'Export to PDF',
        text: 'PDF export functionality will be implemented soon.',
        icon: 'info',
        confirmButtonText: 'OK'
    });
}

function viewBuilding(buildingId) {
    // Show loading
    Swal.fire({
        title: 'Loading...',
        text: 'Fetching building details',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch building details via AJAX
    $.ajax({
        url: 'php/api.php',
        method: 'POST',
        data: {
            action: 'get_building_details',
            building_id: buildingId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var building = response.data;
                var content = `
                    <div class="building-details">
                        <h4>${building.building_name}</h4>
                        <p><strong>Type:</strong> ${building.building_type}</p>
                        <p><strong>Address:</strong> ${building.address}</p>
                        <p><strong>Owner:</strong> ${building.owner_name}</p>
                        <p><strong>Contact:</strong> ${building.contact_number}</p>
                        <p><strong>Barangay:</strong> ${building.barangay_name}</p>
                        <p><strong>Floors:</strong> ${building.total_floors}</p>
                        <p><strong>Construction Year:</strong> ${building.construction_year || 'N/A'}</p>
                        <p><strong>Building Area:</strong> ${building.building_area || 'N/A'} sqm</p>
                    </div>
                `;
                
                Swal.fire({
                    title: 'Building Details',
                    html: content,
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error',
                text: 'Failed to fetch building details',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
}

function editBuilding(buildingId) {
    Swal.fire({
        title: 'Edit Building',
        text: 'Edit functionality will be implemented soon.',
        icon: 'question',
        confirmButtonText: 'OK'
    });
}

function deleteBuilding(buildingId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Simulate deletion (replace with actual API call)
            setTimeout(function() {
                Swal.fire(
                    'Deleted!',
                    'Building has been deleted.',
                    'success'
                );
            }, 2000);
        }
    });
}

function acknowledgeAlarm(buildingId) {
    Swal.fire({
        title: 'Acknowledge Alarm',
        text: 'Are you sure you want to acknowledge this alarm?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, acknowledge',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Show loading
            Swal.fire({
                title: 'Processing...',
                text: 'Updating alarm status',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Update alarm status via AJAX
            $.ajax({
                url: 'php/api.php',
                method: 'POST',
                data: {
                    action: 'update_alarm_status',
                    building_id: buildingId,
                    status: 'Acknowledged'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            title: 'Success',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: 'OK'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to update alarm status',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }
    });
}

function viewAlarmHistory(buildingId) {
    // Show loading
    Swal.fire({
        title: 'Loading...',
        text: 'Fetching alarm history',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // Fetch alarm history via AJAX
    $.ajax({
        url: 'php/api.php',
        method: 'POST',
        data: {
            action: 'get_alarm_history',
            building_id: buildingId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                var history = response.data;
                var content = '<div class="alarm-history">';
                
                if (history.length > 0) {
                    content += '<table class="table table-sm">';
                    content += '<thead><tr><th>Timestamp</th><th>Status</th><th>Smoke</th><th>Temp</th><th>Heat</th></tr></thead>';
                    content += '<tbody>';
                    
                    history.forEach(function(record) {
                        content += `<tr>
                            <td>${new Date(record.timestamp).toLocaleString()}</td>
                            <td><span class="status-badge status-${record.status === 'Fire Detected' ? 'danger' : 'normal'}">${record.status}</span></td>
                            <td>${record.smoke} ppm</td>
                            <td>${record.temp}°C</td>
                            <td>${record.heat}°C</td>
                        </tr>`;
                    });
                    
                    content += '</tbody></table>';
                } else {
                    content += '<p>No alarm history found for this building.</p>';
                }
                
                content += '</div>';
                
                Swal.fire({
                    title: 'Alarm History',
                    html: content,
                    width: '80%',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({
                    title: 'Error',
                    text: response.message,
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            }
        },
        error: function() {
            Swal.fire({
                title: 'Error',
                text: 'Failed to fetch alarm history',
                icon: 'error',
                confirmButtonText: 'OK'
            });
        }
    });
}

function initializeWebSocket() {
    // WebSocket initialization for real-time updates
    // This would connect to a WebSocket server for live updates
    console.log('WebSocket initialization would go here');
}

// Utility functions
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatNumber(number) {
    return new Intl.NumberFormat().format(number);
}

// Keyboard shortcuts
$(document).keydown(function(e) {
    // Ctrl + R to refresh
    if (e.ctrlKey && e.keyCode === 82) {
        e.preventDefault();
        refreshTableData();
    }
    
    // Ctrl + E to export CSV
    if (e.ctrlKey && e.keyCode === 69) {
        e.preventDefault();
        exportToCSV();
    }
});

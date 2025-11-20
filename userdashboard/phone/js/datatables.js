/**
 * DataTables Implementation for Phone Numbers Management
 * Real-time filtering, searching, and data management
 */

class PhoneDataTables {
    constructor() {
        this.table = null;
        this.filters = {
            status: 'all',
            primary: 'all',
            label: '',
            dateRange: 'all'
        };
        this.init();
    }

    init() {
        this.initializeTable();
        this.setupFilters();
        this.setupRealTimeUpdates();
        this.setupExportButtons();
    }

    initializeTable() {
        // Initialize DataTable with comprehensive configuration
        this.table = $('#phoneNumbersTable').DataTable({
            // Server-side processing for better performance
            processing: true,
            serverSide: false, // Using client-side for now, can be changed to server-side
            responsive: true,
            autoWidth: false,
            
            // Data configuration
            data: this.getTableData(),
            columns: [
                {
                    title: 'Phone Number',
                    data: 'phone_number',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            const formattedNumber = '+63' + data.substring(1);
                            let html = `<div class="d-flex align-items-center">
                                <i class="bi bi-phone text-primary me-3"></i>
                                <div>
                                    <span class="phone-number-display fw-semibold text-dark">${formattedNumber}</span>`;
                            
                            if (row.is_primary) {
                                html += `<div class="mt-1">
                                    <span class="status-indicator primary">
                                        <i class="bi bi-star-fill me-1"></i>Primary
                                    </span>
                                </div>`;
                            }
                            
                            html += '</div></div>';
                            return html;
                        }
                        return data;
                    },
                    className: 'phone-number-column'
                },
                {
                    title: 'Label',
                    data: 'label',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            const label = data || 'No label';
                            return `<div class="d-flex align-items-center">
                                <span class="phone-label-text text-dark">${label}</span>
                                <button class="btn btn-link btn-sm p-0 ms-2 edit-label-btn" 
                                        data-phone-id="${row.phone_id}"
                                        data-current-label="${data || ''}"
                                        style="color: #6c757d; text-decoration: none;">
                                    <i class="bi bi-pencil-square"></i>
                                </button>
                                <input type="text" class="form-control form-control-sm label-input border-0 bg-light" 
                                       data-phone-id="${row.phone_id}"
                                       value="${data || ''}"
                                       style="display: none; max-width: 150px;">
                            </div>`;
                        }
                        return data || '';
                    },
                    className: 'label-column'
                },
                {
                    title: 'Status',
                    data: 'verified',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            if (data) {
                                return `<span class="status-indicator verified">
                                    <i class="bi bi-check-circle-fill me-1"></i>Verified
                                </span>`;
                            } else {
                                return `<span class="status-indicator unverified">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Unverified
                                </span>`;
                            }
                        }
                        return data ? 'verified' : 'unverified';
                    },
                    className: 'status-column'
                },
                {
                    title: 'Created',
                    data: 'created_at',
                    render: function(data, type, row) {
                        if (type === 'display') {
                            const date = new Date(data);
                            return `<div class="text-muted small">
                                <i class="bi bi-calendar3 me-1"></i>
                                ${date.toLocaleDateString()}
                            </div>
                            <div class="text-muted small">
                                <i class="bi bi-clock me-1"></i>
                                ${date.toLocaleTimeString()}
                            </div>`;
                        }
                        return data;
                    },
                    className: 'date-column'
                },
                {
                    title: 'Actions',
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        let html = '<div class="table-actions">';
                        
                        // Make Primary button
                        if (!row.is_primary && row.verified) {
                            html += `<form method="POST" class="d-inline">
                                <input type="hidden" name="phone_id" value="${row.phone_id}">
                                <button type="submit" name="set_primary" class="btn btn-sm btn-success">
                                    <i class="bi bi-star-fill me-1"></i>Primary
                                </button>
                            </form>`;
                        }
                        
                        // Verify button
                        if (!row.verified) {
                            html += `<button type="button" class="btn btn-sm btn-warning verify-btn" 
                                    data-phone-id="${row.phone_id}">
                                <i class="bi bi-shield-check me-1"></i>Verify
                            </button>`;
                            
                            html += `<button type="button" class="btn btn-sm btn-info resend-btn" 
                                    data-phone-id="${row.phone_id}">
                                <i class="bi bi-arrow-repeat me-1"></i>Resend
                            </button>`;
                        }
                        
                        // Delete button
                        html += `<button type="button" class="btn btn-sm btn-danger delete-btn" 
                                data-phone-id="${row.phone_id}"
                                data-phone-number="+63${row.phone_number.substring(1)}">
                            <i class="bi bi-trash-fill me-1"></i>Delete
                        </button>`;
                        
                        html += '</div>';
                        return html;
                    },
                    className: 'actions-column'
                }
            ],
            
            // Ordering configuration
            order: [[3, 'desc']], // Sort by created date descending
            
            // Language configuration
            language: {
                processing: '<div class="loading-spinner"></div> Processing...',
                lengthMenu: 'Show _MENU_ entries',
                zeroRecords: '<div class="dataTables_empty"><i class="bi bi-phone"></i>No phone numbers found</div>',
                info: 'Showing _START_ to _END_ of _TOTAL_ entries',
                infoEmpty: 'Showing 0 to 0 of 0 entries',
                infoFiltered: '(filtered from _MAX_ total entries)',
                search: 'Search:',
                paginate: {
                    first: '<i class="bi bi-chevron-double-left"></i>',
                    previous: '<i class="bi bi-chevron-left"></i>',
                    next: '<i class="bi bi-chevron-right"></i>',
                    last: '<i class="bi bi-chevron-double-right"></i>'
                }
            },
            
            // DOM configuration
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"B>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            
            // Page length options
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
            pageLength: 25,
            
            // Callbacks
            drawCallback: function() {
                // Re-initialize event handlers after table redraw
                PhoneDataTables.instance.setupEventHandlers();
            },
            
            initComplete: function() {
                // Add custom styling and functionality
                PhoneDataTables.instance.customizeTable();
            }
        });
        
        // Store instance for callbacks
        PhoneDataTables.instance = this;
    }

    getTableData() {
        // Extract data from existing table rows
        const data = [];
        $('.phone-item').each(function() {
            const $row = $(this);
            const phoneId = $row.find('[data-phone-id]').first().data('phone-id');
            const phoneNumber = $row.find('.phone-number-display').text().replace('+63', '0');
            const label = $row.find('.phone-label-text').text().replace('No label', '');
            const isVerified = $row.find('.badge').text().includes('Verified');
            const isPrimary = $row.find('.badge').text().includes('Primary');
            const createdDate = new Date().toISOString(); // You might want to get this from a data attribute
            
            data.push({
                phone_id: phoneId,
                phone_number: phoneNumber,
                label: label,
                verified: isVerified,
                is_primary: isPrimary,
                created_at: createdDate
            });
        });
        
        return data;
    }

    setupFilters() {
        // Status filter
        $('#statusFilter').on('change', (e) => {
            this.filters.status = e.target.value;
            this.applyFilters();
        });

        // Primary filter
        $('#primaryFilter').on('change', (e) => {
            this.filters.primary = e.target.value;
            this.applyFilters();
        });

        // Label filter
        $('#labelFilter').on('input', (e) => {
            this.filters.label = e.target.value;
            this.applyFilters();
        });

        // Date range filter
        $('#dateRangeFilter').on('change', (e) => {
            this.filters.dateRange = e.target.value;
            this.applyFilters();
        });

        // Clear filters
        $('#clearFilters').on('click', () => {
            this.clearFilters();
        });

        // Apply filters button
        $('#applyFilters').on('click', () => {
            this.applyFilters();
        });
    }

    applyFilters() {
        let searchValue = '';
        
        // Build search string based on filters
        if (this.filters.status !== 'all') {
            searchValue += this.filters.status + ' ';
        }
        
        if (this.filters.primary !== 'all') {
            searchValue += (this.filters.primary === 'yes' ? 'primary' : 'not primary') + ' ';
        }
        
        if (this.filters.label) {
            searchValue += this.filters.label + ' ';
        }
        
        // Apply search
        this.table.search(searchValue.trim()).draw();
        
        // Update filter indicators
        this.updateFilterIndicators();
    }

    clearFilters() {
        this.filters = {
            status: 'all',
            primary: 'all',
            label: '',
            dateRange: 'all'
        };
        
        // Reset filter inputs
        $('#statusFilter').val('all');
        $('#primaryFilter').val('all');
        $('#labelFilter').val('');
        $('#dateRangeFilter').val('all');
        
        // Clear table search
        this.table.search('').draw();
        
        // Update indicators
        this.updateFilterIndicators();
    }

    updateFilterIndicators() {
        const activeFilters = Object.values(this.filters).filter(value => 
            value !== 'all' && value !== ''
        ).length;
        
        if (activeFilters > 0) {
            $('#filterIndicator').text(`${activeFilters} filter(s) active`).show();
        } else {
            $('#filterIndicator').hide();
        }
    }

    setupRealTimeUpdates() {
        // Simulate real-time updates (in a real app, this would be WebSocket or polling)
        setInterval(() => {
            this.refreshData();
        }, 30000); // Refresh every 30 seconds
        
        // Listen for custom events
        $(document).on('phoneUpdated', () => {
            this.refreshData();
        });
    }

    refreshData() {
        // Show loading indicator
        this.table.processing(true);
        
        // Simulate API call delay
        setTimeout(() => {
            // In a real implementation, you would fetch new data from the server
            this.table.processing(false);
            
            // Show update notification
            this.showUpdateNotification();
        }, 1000);
    }

    showUpdateNotification() {
        const notification = $(`
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="bi bi-info-circle me-2"></i>
                Data refreshed at ${new Date().toLocaleTimeString()}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `);
        
        $('.datatables-filters').after(notification);
        
        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            notification.alert('close');
        }, 3000);
    }

    setupExportButtons() {
        // Add export buttons
        new $.fn.dataTable.Buttons(this.table, {
            buttons: [
                {
                    extend: 'copy',
                    text: '<i class="bi bi-clipboard"></i> Copy',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'csv',
                    text: '<i class="bi bi-file-earmark-spreadsheet"></i> CSV',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'excel',
                    text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                    className: 'btn btn-outline-secondary btn-sm'
                },
                {
                    extend: 'print',
                    text: '<i class="bi bi-printer"></i> Print',
                    className: 'btn btn-outline-secondary btn-sm'
                }
            ]
        });
        
        this.table.buttons().container().appendTo('.datatables-filters .filter-actions');
    }

    setupEventHandlers() {
        // Re-bind event handlers after table redraw
        this.bindPhoneActions();
        this.bindLabelEditing();
    }

    bindPhoneActions() {
        // Verify button
        $('.verify-btn').off('click').on('click', function() {
            const phoneId = $(this).data('phone-id');
            $('#modalPhoneId').val(phoneId);
            $('#verificationModal').show();
            $('#verification_code').focus();
            startCountdown();
        });

        // Resend button
        $('.resend-btn').off('click').on('click', function() {
            const phoneId = $(this).data('phone-id');
            resendVerificationCode(phoneId);
        });

        // Delete button
        $('.delete-btn').off('click').on('click', function() {
            const phoneId = $(this).data('phone-id');
            const phoneNumber = $(this).data('phone-number');
            
            Swal.fire({
                title: 'Delete Phone Number?',
                text: `Are you sure you want to delete ${phoneNumber}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    deletePhoneNumber(phoneId);
                }
            });
        });
    }

    bindLabelEditing() {
        // Edit label button
        $('.edit-label-btn').off('click').on('click', function() {
            const phoneId = $(this).data('phone-id');
            const currentLabel = $(this).data('current-label') || '';
            
            $(this).siblings('.phone-label-text').hide();
            $(this).hide();
            
            const input = $(`.label-input[data-phone-id="${phoneId}"]`);
            input.show().focus().val(currentLabel);
        });

        // Label input blur
        $('.label-input').off('blur').on('blur', function() {
            const phoneId = $(this).data('phone-id');
            const newLabel = $(this).val().trim();
            
            $(this).hide();
            $(this).siblings('.phone-label-text').show();
            $(this).siblings('.edit-label-btn').show();
            
            if (newLabel !== $(this).siblings('.phone-label-text').text().replace('No label', '').trim()) {
                updatePhoneLabel(phoneId, newLabel);
            }
        });

        // Label input enter key
        $('.label-input').off('keypress').on('keypress', function(e) {
            if (e.which === 13) {
                $(this).blur();
            }
        });
    }

    customizeTable() {
        // Add custom classes and styling
        this.table.table().addClass('table-hover');
        
        // Add search highlighting
        this.table.on('search.dt', function() {
            const searchValue = this.search();
            if (searchValue) {
                $('.dataTables_wrapper tbody td').each(function() {
                    const $cell = $(this);
                    const text = $cell.text();
                    const highlightedText = text.replace(
                        new RegExp(searchValue, 'gi'),
                        `<span class="highlight">${searchValue}</span>`
                    );
                    if (highlightedText !== text) {
                        $cell.html(highlightedText);
                    }
                });
            }
        });
    }

    // Public methods for external use
    refresh() {
        this.refreshData();
    }

    addRow(phoneData) {
        this.table.row.add(phoneData).draw();
    }

    updateRow(phoneId, phoneData) {
        const rowIndex = this.table.rows().eq(0).filter(function(i) {
            return this.table.cell(i, 0).data().phone_id === phoneId;
        });
        
        if (rowIndex.length > 0) {
            this.table.row(rowIndex[0]).data(phoneData).draw();
        }
    }

    removeRow(phoneId) {
        const rowIndex = this.table.rows().eq(0).filter(function(i) {
            return this.table.cell(i, 0).data().phone_id === phoneId;
        });
        
        if (rowIndex.length > 0) {
            this.table.row(rowIndex[0]).remove().draw();
        }
    }
}

// Initialize DataTables when document is ready
$(document).ready(function() {
    // Only initialize if the table exists
    if ($('#phoneNumbersTable').length) {
        window.phoneDataTable = new PhoneDataTables();
    }
});

// Export for global access
window.PhoneDataTables = PhoneDataTables;
